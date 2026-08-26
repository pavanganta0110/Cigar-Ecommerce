<?php

declare(strict_types=1);

namespace Compadres\Commerce\Checkout;

use Compadres\Commerce\Audit\AuditServiceFactory;
use Exception;
use RuntimeException;
use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WooCommerce;

/**
 * Blocks duplicate order creation from a double-submitted or concurrently
 * retried checkout request.
 *
 * Registered at the earliest priority on order creation so a locked or
 * already-completed attempt never reaches the restriction (4), age (5),
 * shipping (6), or tax (7) checkout hooks a second time for the same cart,
 * destination, and shipping selection.
 */
final class CheckoutOrchestrator {

	public function __construct( private ?IdempotencyStore $idempotency = null ) {}

	public function registerHooks(): void {
		add_action( 'woocommerce_checkout_create_order', array( $this, 'guardOrderCreation' ), 1, 2 );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'recordOrder' ), 1 );
	}

	/** @param array<string, mixed> $data */
	public function guardOrderCreation( WC_Order $_order, array $data ): void {
		unset( $_order );
		$decision = $this->store()->acquire( $this->fingerprintFromCheckoutData( $data ) );
		if ( $decision->shouldProceed() ) {
			return;
		}
		$this->auditBlock( $decision );
		if ( null !== $decision->existingOrderId() ) {
			throw new Exception( esc_html__( 'This order has already been placed. Check your account for confirmation before submitting again.', 'compadres-commerce' ) );
		}
		throw new Exception( esc_html__( 'Your order is already being processed. Please wait a moment before trying again.', 'compadres-commerce' ) );
	}

	public function recordOrder( WC_Order $order ): void {
		$this->store()->recordOrder( $this->fingerprintFromOrder( $order ), $order->get_id() );
	}

	/** @param array<string, mixed> $data */
	private function fingerprintFromCheckoutData( array $data ): CartFingerprint {
		$woocommerce = WC();
		if ( null === $woocommerce->cart ) {
			throw new RuntimeException( 'Cart data is unavailable.' );
		}
		$items = array();
		foreach ( $woocommerce->cart->get_cart() as $item ) {
			$items[] = array(
				'product_id'   => $item['product_id'] ?? 0,
				'variation_id' => $item['variation_id'] ?? 0,
				'quantity'     => $item['quantity'] ?? 0,
			);
		}
		return CartFingerprint::fromCart(
			$items,
			(string) ( $data['shipping_country'] ?? $data['billing_country'] ?? '' ),
			(string) ( $data['shipping_state'] ?? $data['billing_state'] ?? '' ),
			(string) ( $data['shipping_postcode'] ?? $data['billing_postcode'] ?? '' ),
			$this->chosenShippingMethod( $woocommerce )
		);
	}

	private function fingerprintFromOrder( WC_Order $order ): CartFingerprint {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$items[] = array(
				'product_id'   => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'quantity'     => $item->get_quantity(),
			);
		}
		$shipping_method = '';
		foreach ( $order->get_items( 'shipping' ) as $shipping_item ) {
			if ( $shipping_item instanceof WC_Order_Item_Shipping ) {
				$shipping_method = (string) $shipping_item->get_method_id();
				break;
			}
		}
		return CartFingerprint::fromCart(
			$items,
			(string) $order->get_shipping_country(),
			(string) $order->get_shipping_state(),
			(string) $order->get_shipping_postcode(),
			$shipping_method
		);
	}

	private function chosenShippingMethod( WooCommerce $woocommerce ): string {
		$methods = $woocommerce->session->get( 'chosen_shipping_methods', array() );
		return is_array( $methods ) && array() !== $methods ? (string) reset( $methods ) : '';
	}

	private function store(): IdempotencyStore {
		$this->idempotency ??= new IdempotencyStore( new WooCommerceSessionStore( WC()->session ) );
		return $this->idempotency;
	}

	private function auditBlock( IdempotencyDecision $decision ): void {
		AuditServiceFactory::create()->failure(
			'checkout.duplicate_submission_blocked',
			$decision->reason(),
			get_current_user_id(),
			'checkout',
			null !== $decision->existingOrderId() ? (string) $decision->existingOrderId() : '',
			array( 'reason' => $decision->reason() )
		);
	}
}
