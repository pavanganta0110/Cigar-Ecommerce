<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tax;

use Throwable;

/** Installs the approved rules into WooCommerce's native destination tax table. */
final class ManualSalesTaxInstaller {
	public const TAX_CLASS      = 'compadres-cigars';
	public const OPTION_VERSION = 'compadres_manual_sales_tax_version';
	public const VERSION        = '2026-08-19-avg-combined-v2';
	private const RATE_NAME     = 'Compadres Avg Sales Tax';

	public static function maybeInstall(): void {
		if ( ! class_exists( 'WC_Tax' ) ) {
			return;
		}

		if ( self::VERSION === get_option( self::OPTION_VERSION ) && self::isInstallationValid() ) {
			return;
		}

		global $wpdb;
		if ( ! $wpdb instanceof \wpdb ) {
			delete_option( self::OPTION_VERSION );
			return;
		}

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic replacement of WooCommerce tax rows.
			delete_option( self::OPTION_VERSION );
			return;
		}
		try {
			self::registerTaxClass();
			self::replaceOwnedRates();
			if ( ! self::isInstallationValid() ) {
				throw new \RuntimeException( 'Installed manual sales-tax rates failed integrity validation.' );
			}
			update_option( 'woocommerce_calc_taxes', 'yes' );
			update_option( self::OPTION_VERSION, self::VERSION, false );
			if ( false === $wpdb->query( 'COMMIT' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Completes the atomic tax-rate replacement.
				throw new \RuntimeException( 'Unable to commit the manual sales-tax installation.' );
			}
		} catch ( Throwable ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Restores the last valid tax-rate set on any failure.
			delete_option( self::OPTION_VERSION );
		}
	}

	public static function isInstallationValid(): bool {
		if ( ! class_exists( 'WC_Tax' ) ) {
			return false;
		}
		if ( ! \WC_Tax::get_tax_class_by( 'slug', self::TAX_CLASS ) ) {
			return false;
		}
		foreach ( \WC_Tax::get_rates_for_tax_class( '' ) as $rate ) {
			if ( self::RATE_NAME === (string) $rate->tax_rate_name ) {
				return false;
			}
		}

		$expected = array();
		foreach ( ( new ManualSalesTaxRuleSet() )->all() as $rule ) {
			$expected[ $rule->state() ] = array(
				'rate'     => number_format( (float) $rule->ratePercent(), 4, '.', '' ),
				'shipping' => $rule->shippingTaxable() ? '1' : '0',
			);
		}

		$rates = \WC_Tax::get_rates_for_tax_class( self::TAX_CLASS );
		if ( count( $expected ) !== count( $rates ) ) {
			return false;
		}

		foreach ( $rates as $rate ) {
			$state = strtoupper( (string) $rate->tax_rate_state );
			if (
				! isset( $expected[ $state ] )
				|| 'US' !== strtoupper( (string) $rate->tax_rate_country )
				|| self::RATE_NAME !== (string) $rate->tax_rate_name
				|| self::TAX_CLASS !== (string) $rate->tax_rate_class
				|| '1' !== (string) $rate->tax_rate_priority
				|| '0' !== (string) $rate->tax_rate_compound
				|| $expected[ $state ]['shipping'] !== (string) $rate->tax_rate_shipping
				|| number_format( (float) $rate->tax_rate, 4, '.', '' ) !== $expected[ $state ]['rate']
			) {
				return false;
			}
			unset( $expected[ $state ] );
		}
		foreach ( ( new ManualSalesTaxRuleSet() )->all() as $rule ) {
			$resolved = \WC_Tax::find_rates(
				array(
					'country'   => 'US',
					'state'     => $rule->state(),
					'postcode'  => '',
					'city'      => '',
					'tax_class' => self::TAX_CLASS,
				)
			);
			if ( 1 !== count( $resolved ) ) {
				return false;
			}
			$resolved_rate = reset( $resolved );
			$shipping      = is_array( $resolved_rate ) ? strtolower( (string) ( $resolved_rate['shipping'] ?? '' ) ) : '';
			if (
				! is_array( $resolved_rate )
				|| self::RATE_NAME !== (string) ( $resolved_rate['label'] ?? '' )
				|| number_format( (float) ( $resolved_rate['rate'] ?? -1 ), 4, '.', '' ) !== number_format( (float) $rule->ratePercent(), 4, '.', '' )
				|| ( $rule->shippingTaxable() ? 'yes' : 'no' ) !== $shipping
			) {
				return false;
			}
		}

		return array() === $expected;
	}

	private static function replaceOwnedRates(): void {
		foreach ( array( '', self::TAX_CLASS ) as $tax_class ) {
			foreach ( \WC_Tax::get_rates_for_tax_class( $tax_class ) as $rate ) {
				if ( self::RATE_NAME === (string) $rate->tax_rate_name ) {
					\WC_Tax::_delete_tax_rate( (int) $rate->tax_rate_id );
				}
			}
		}

		$order = 0;
		foreach ( ( new ManualSalesTaxRuleSet() )->all() as $rule ) {
			$rate_id = \WC_Tax::_insert_tax_rate(
				array(
					'tax_rate_country'  => 'US',
					'tax_rate_state'    => $rule->state(),
					'tax_rate'          => $rule->ratePercent(),
					'tax_rate_name'     => self::RATE_NAME,
					'tax_rate_priority' => 1,
					'tax_rate_compound' => 0,
					'tax_rate_shipping' => $rule->shippingTaxable() ? 1 : 0,
					'tax_rate_order'    => $order,
					'tax_rate_class'    => self::TAX_CLASS,
				)
			);
			if ( $rate_id <= 0 ) {
				throw new \RuntimeException( 'Unable to install a manual sales-tax rate.' );
			}
			++$order;
		}
	}

	private static function registerTaxClass(): void {
		if ( ! \WC_Tax::get_tax_class_by( 'slug', self::TAX_CLASS ) ) {
			$result = \WC_Tax::create_tax_class( 'Compadres Cigars', self::TAX_CLASS );
			if ( is_wp_error( $result ) ) {
				throw new \RuntimeException( 'Unable to register the Compadres cigar tax class.' );
			}
			if ( self::TAX_CLASS !== (string) ( $result['slug'] ?? '' ) ) {
				throw new \RuntimeException( 'Unable to register the Compadres cigar tax class.' );
			}
		}
	}
}
