<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tax;

use Compadres\Commerce\Catalog\ProductMetadata;
use DateTimeImmutable;
use Exception;
use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Item_Tax;
use WC_Product;
use WP_Error;

/** Applies and snapshots the approved manual destination-state sales-tax rule. */
final class ManualSalesTaxIntegration {
	public function registerHooks(): void {
		add_filter( 'woocommerce_product_get_tax_status', array( $this, 'taxableStatus' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_tax_status', array( $this, 'taxableStatus' ), 10, 2 );
		add_filter( 'woocommerce_product_get_tax_class', array( $this, 'taxClass' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_tax_class', array( $this, 'taxClass' ), 10, 2 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validateDestination' ), 12, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'snapshotRule' ), 7, 2 );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'snapshotAmount' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'validateOrderBeforePayment' ), 20, 3 );
		add_action( 'woocommerce_before_pay_action', array( $this, 'validateOrderPayment' ), 12 );
	}

	public function taxableStatus( string $status, WC_Product $product ): string {
		return $this->usesManualCigarTax( $product ) ? 'taxable' : $status;
	}

	public function taxClass( string $tax_class, WC_Product $product ): string {
		return $this->usesManualCigarTax( $product ) ? ManualSalesTaxInstaller::TAX_CLASS : $tax_class;
	}

	/** @param array<string, mixed> $data */
	public function validateDestination( array $data, WP_Error $errors ): void {
		if ( ! $this->cartRequiresManualTax() ) {
			return;
		}
		$country = strtoupper( trim( (string) ( $data['shipping_country'] ?? $data['billing_country'] ?? '' ) ) );
		$state   = strtoupper( trim( (string) ( $data['shipping_state'] ?? $data['billing_state'] ?? '' ) ) );
		if ( 'US' !== $country || null === $this->ruleForState( $state ) || ! ManualSalesTaxInstaller::isInstallationValid() ) {
			$errors->add(
				'compadres_tax_rule_unavailable',
				esc_html__( 'Sales tax cannot be calculated for this destination. Please contact Compadres Cigars.', 'compadres-commerce' )
			);
		}
	}

	/** @param array<string, mixed> $data */
	public function snapshotRule( WC_Order $order, array $data ): void {
		if ( ! $this->orderRequiresManualTax( $order ) ) {
			return;
		}
		$country = strtoupper( trim( (string) ( $data['shipping_country'] ?? $data['billing_country'] ?? $order->get_shipping_country() ) ) );
		$state   = strtoupper( trim( (string) ( $data['shipping_state'] ?? $data['billing_state'] ?? $order->get_shipping_state() ) ) );
		$rule    = $this->ruleForState( $state );
		if ( 'US' !== $country || null === $rule || ! ManualSalesTaxInstaller::isInstallationValid() ) {
			throw new \RuntimeException( 'Sales tax rule unavailable for checkout destination.' );
		}

		$order->update_meta_data( '_compadres_sales_tax_rule_state', $rule->state() );
		$order->update_meta_data( '_compadres_sales_tax_country', 'US' );
		$order->update_meta_data( '_compadres_sales_tax_rate_percent', $rule->ratePercent() );
		$order->update_meta_data( '_compadres_sales_tax_calculation_basis', $rule->calculationBasis() );
		$order->update_meta_data( '_compadres_sales_tax_source_column', $rule->sourceColumn() );
		$order->update_meta_data( '_compadres_sales_tax_source_document', $rule->sourceDocument() );
		$order->update_meta_data( '_compadres_sales_tax_source_sha256', $rule->sourceSha256() );
		$order->update_meta_data( '_compadres_sales_tax_rule_version', $rule->ruleVersion() );
		$order->update_meta_data( '_compadres_sales_tax_effective_from', $rule->effectiveFrom() );
		$order->update_meta_data( '_compadres_sales_tax_shipping_taxable', $rule->shippingTaxable() ? 'yes' : 'no' );
		$order->update_meta_data( '_compadres_sales_tax_is_average_reference', 'yes' );
	}

	public function snapshotAmount( WC_Order $order ): void {
		if ( ! $this->orderRequiresManualTax( $order ) ) {
			return;
		}
		$rule   = $this->ruleForState( strtoupper( (string) $order->get_meta( '_compadres_sales_tax_rule_state' ) ) );
		$amount = null !== $rule ? $this->validatedManualTaxAmount( $order, $rule ) : null;
		if ( null === $amount ) {
			throw new Exception( esc_html__( 'Sales tax validation is incomplete. Please contact Compadres Cigars.', 'compadres-commerce' ) );
		}
		$order->update_meta_data( '_compadres_sales_tax_amount', $amount );
		$order->save_meta_data();
	}

	/** @param array<string, mixed> $data */
	public function validateOrderBeforePayment( int $order_id, array $data, WC_Order $order ): void {
		unset( $order_id, $data );
		$this->assertCompleteSnapshot( $order );
	}

	public function validateOrderPayment( WC_Order $order ): void {
		$this->assertCompleteSnapshot( $order );
	}

	private function assertCompleteSnapshot( WC_Order $order ): void {
		if ( ! $this->orderRequiresManualTax( $order ) ) {
			return;
		}
		$state = strtoupper( trim( (string) $order->get_shipping_state() ) );
		if ( '' === $state ) {
			$state = strtoupper( trim( (string) $order->get_billing_state() ) );
		}
		$country = strtoupper( trim( (string) $order->get_shipping_country() ) );
		if ( '' === $country ) {
			$country = strtoupper( trim( (string) $order->get_billing_country() ) );
		}
		$rule              = $this->ruleForState( $state );
		$manual_tax_amount = null !== $rule ? $this->validatedManualTaxAmount( $order, $rule ) : null;
		$valid             = null !== $rule
			&& 'US' === $country
			&& ManualSalesTaxInstaller::isInstallationValid()
			&& 'US' === (string) $order->get_meta( '_compadres_sales_tax_country' )
			&& $rule->state() === (string) $order->get_meta( '_compadres_sales_tax_rule_state' )
			&& $rule->ratePercent() === (string) $order->get_meta( '_compadres_sales_tax_rate_percent' )
			&& $rule->calculationBasis() === (string) $order->get_meta( '_compadres_sales_tax_calculation_basis' )
			&& $rule->sourceColumn() === (string) $order->get_meta( '_compadres_sales_tax_source_column' )
			&& $rule->sourceDocument() === (string) $order->get_meta( '_compadres_sales_tax_source_document' )
			&& $rule->sourceSha256() === (string) $order->get_meta( '_compadres_sales_tax_source_sha256' )
			&& $rule->ruleVersion() === (string) $order->get_meta( '_compadres_sales_tax_rule_version' )
			&& $rule->effectiveFrom() === (string) $order->get_meta( '_compadres_sales_tax_effective_from' )
			&& 'no' === (string) $order->get_meta( '_compadres_sales_tax_shipping_taxable' )
			&& 'yes' === (string) $order->get_meta( '_compadres_sales_tax_is_average_reference' )
			&& null !== $manual_tax_amount
			&& $manual_tax_amount === (string) $order->get_meta( '_compadres_sales_tax_amount' );
		if ( ! $valid ) {
			throw new Exception( esc_html__( 'Sales tax validation is incomplete. Please contact Compadres Cigars.', 'compadres-commerce' ) );
		}
	}

	private function validatedManualTaxAmount( WC_Order $order, ManualSalesTaxRule $rule ): ?string {
		if ( ! class_exists( 'WC_Tax' ) ) {
			return null;
		}
		$rates = \WC_Tax::find_rates(
			array(
				'country'   => 'US',
				'state'     => $rule->state(),
				'postcode'  => '',
				'city'      => '',
				'tax_class' => ManualSalesTaxInstaller::TAX_CLASS,
			)
		);
		if ( 1 !== count( $rates ) ) {
			return null;
		}
		$expected_rate_id = (int) array_key_first( $rates );
		$manual_tax       = 0.0;
		$manual_tax_items = 0;
		foreach ( $order->get_items( 'tax' ) as $tax_item ) {
			if ( ! $tax_item instanceof WC_Order_Item_Tax ) {
				return null;
			}
			$tax_total      = (float) $tax_item->get_tax_total();
			$shipping_tax   = (float) $tax_item->get_shipping_tax_total();
			$tax_rate       = \WC_Tax::_get_tax_rate( $tax_item->get_rate_id() );
			$tax_rate_class = is_array( $tax_rate ) ? (string) ( $tax_rate['tax_rate_class'] ?? '' ) : '';
			if ( ManualSalesTaxInstaller::TAX_CLASS !== $tax_rate_class ) {
				if ( 0.0 !== $tax_total || 0.0 !== $shipping_tax ) {
					return null;
				}
				continue;
			}
			if ( $expected_rate_id !== $tax_item->get_rate_id() || ( ! $rule->shippingTaxable() && 0.0 !== $shipping_tax ) ) {
				return null;
			}
			++$manual_tax_items;
			$manual_tax += $tax_total + $shipping_tax;
		}
		$formatted_amount = wc_format_decimal( $manual_tax, 2 );
		if ( wc_format_decimal( $order->get_total_tax(), 2 ) !== $formatted_amount ) {
			return null;
		}
		if ( (float) $rule->ratePercent() > 0.0 && 1 !== $manual_tax_items ) {
			return null;
		}
		if ( 0.0 === (float) $rule->ratePercent() && ( $manual_tax_items > 1 || 0.0 !== $manual_tax ) ) {
			return null;
		}
		return $formatted_amount;
	}

	private function usesManualCigarTax( WC_Product $product ): bool {
		$classification = trim( (string) $product->get_meta( ProductMetadata::PREFIX . 'sales_tax_classification' ) );
		if ( '' === $classification && $product->is_type( 'variation' ) ) {
			$parent         = wc_get_product( $product->get_parent_id() );
			$classification = false !== $parent
				? trim( (string) $parent->get_meta( ProductMetadata::PREFIX . 'sales_tax_classification' ) )
				: '';
		}
		return 'cigar' === strtolower( $classification );
	}

	private function cartRequiresManualTax(): bool {
		if ( ! function_exists( 'WC' ) || null === WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'] ?? null;
			if ( $product instanceof WC_Product && $this->usesManualCigarTax( $product ) ) {
				return true;
			}
		}
		return false;
	}

	private function orderRequiresManualTax( WC_Order $order ): bool {
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if (
				( $product instanceof WC_Product && $this->usesManualCigarTax( $product ) )
				|| ManualSalesTaxInstaller::TAX_CLASS === $item->get_tax_class()
			) {
				return true;
			}
		}
		return false;
	}

	private function ruleForState( string $state ): ?ManualSalesTaxRule {
		return ( new ManualSalesTaxRuleSet() )->forState( $state, new DateTimeImmutable( current_time( 'Y-m-d' ) ) );
	}
}
