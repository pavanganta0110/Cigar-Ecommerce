<?php

declare(strict_types=1);

namespace Compadres\Commerce\Restrictions;

use Compadres\Commerce\Audit\AuditServiceFactory;
use Exception;
use Throwable;
use WC_Order;
use WP_Error;

final class CheckoutRestrictionIntegration {

	private const MESSAGE = 'We cannot ship the current cart to this destination. Please update the shipping address or remove the restricted item.';

	public function registerHooks(): void {
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'validateOrderReview' ) );
		add_action( 'woocommerce_check_cart_items', array( $this, 'validateCart' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validateCheckout' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'validateBeforeOrder' ), 4 );
		add_action( 'woocommerce_before_pay_action', array( $this, 'validateOrderPayment' ) );
	}

	public function validateOrderReview( string $post_data ): void {
		parse_str( $post_data, $data );
		$this->clearNotice();
		$message = $this->blockedMessage( $data );
		if ( null !== $message ) {
			$this->addNotice( $message );
		}
	}

	public function validateCart(): void {
		$woocommerce = WC();
		if ( null === $woocommerce->customer ) {
			return;
		}
		$customer = $woocommerce->customer;
		$data     = array(
			'billing_country'  => $customer->get_billing_country(),
			'billing_state'    => $customer->get_billing_state(),
			'billing_city'     => $customer->get_billing_city(),
			'billing_postcode' => $customer->get_billing_postcode(),
		);
		if ( '' !== $customer->get_shipping_country() ) {
			$data += array(
				'ship_to_different_address' => '1',
				'shipping_country'          => $customer->get_shipping_country(),
				'shipping_state'            => $customer->get_shipping_state(),
				'shipping_city'             => $customer->get_shipping_city(),
				'shipping_postcode'         => $customer->get_shipping_postcode(),
			);
		}
		if ( $this->hasDestination( $data ) ) {
			$this->clearNotice();
			$message = $this->blockedMessage( $data );
			if ( null !== $message ) {
				$this->addNotice( $message );
			}
		}
	}

	/** @param array<string, mixed> $data */
	public function validateCheckout( array $data, WP_Error $errors ): void {
		try {
			$decision = $this->decisionForCheckout( $data );
			if ( $decision->isBlocked() || ! $this->isUnitedStates( ( new RestrictionContextFactory() )->fromCheckout( $data ) ) ) {
				$message = $this->message( $decision );
				$this->rememberNotice( $message );
				$errors->add( 'compadres_restriction', $message );
				$this->auditBlock( 'restriction.checkout_blocked', $decision, 'checkout_validation' );
			}
		} catch ( Throwable ) {
			$errors->add( 'compadres_restriction_unavailable', 'Shipping restrictions could not be verified. Please try again.' );
		}
	}

	public function validateBeforeOrder( WC_Order $order ): void {
		$this->assertOrderAllowed( $order, 'restriction.pre_order_blocked', 'pre_order' );
	}

	public function validateOrderPayment( WC_Order $order ): void {
		$this->assertOrderAllowed( $order, 'restriction.order_payment_blocked', 'order_payment' );
	}

	/** @param array<string, mixed> $data */
	private function blockedMessage( array $data ): ?string {
		try {
			$context  = ( new RestrictionContextFactory() )->fromCheckout( $data );
			$decision = $this->decision( $context );
			return ! $this->isUnitedStates( $context ) || $decision->isBlocked() ? $this->message( $decision ) : null;
		} catch ( Throwable ) {
			return 'Shipping restrictions could not be verified. Please try again.';
		}
	}

	/** @param array<string, mixed> $data */
	private function decisionForCheckout( array $data ): RestrictionDecision {
		return $this->decision( ( new RestrictionContextFactory() )->fromCheckout( $data ) );
	}

	private function decision( RestrictionContext $context ): RestrictionDecision {
		$runtime = new WordPressRestrictionRuntime();
		return ( new RuleEvaluator() )->evaluate( $runtime->repository()->activeRules( $runtime->now() ), $context, $runtime->now() );
	}

	private function assertOrderAllowed( WC_Order $order, string $event, string $phase ): void {
		try {
			$context  = ( new RestrictionContextFactory() )->fromOrder( $order );
			$decision = $this->decision( $context );
		} catch ( Throwable ) {
			throw new Exception( 'Shipping restrictions could not be verified. Please try again.' );
		}
		if ( $this->isUnitedStates( $context ) && ! $decision->isBlocked() ) {
			return;
		}
		$this->auditBlock( $event, $decision, $phase );
		throw new Exception( esc_html( $this->message( $decision ) ) );
	}

	private function isUnitedStates( RestrictionContext $context ): bool {
		return 'US' === $context->country();
	}

	private function message( RestrictionDecision $decision ): string {
		return '' !== $decision->customerMessage() ? $decision->customerMessage() : self::MESSAGE;
	}

	private function addNotice( string $message ): void {
		$this->rememberNotice( $message );
		if ( ! wc_has_notice( $message, 'error' ) ) {
			wc_add_notice( $message, 'error', array( 'compadres_restriction' => true ) );
		}
	}

	private function clearNotice(): void {
		$notices          = wc_get_notices();
		$errors           = $notices['error'] ?? array();
		$session          = WC()->session;
		$remembered       = (string) $session->get( 'compadres_restriction_notice_message', '' );
		$notices['error'] = array_values(
			array_filter(
				$errors,
				static fn ( array $notice ): bool => empty( $notice['data']['compadres_restriction'] ) && ( '' === $remembered || (string) ( $notice['notice'] ?? '' ) !== $remembered )
			)
		);
		wc_set_notices( $notices );
		$session->__unset( 'compadres_restriction_notice_message' );
	}

	private function rememberNotice( string $message ): void {
		WC()->session->set( 'compadres_restriction_notice_message', $message );
	}

	/** @param array<string, mixed> $data */
	private function hasDestination( array $data ): bool {
		return '' !== (string) ( $data['shipping_country'] ?? $data['billing_country'] ?? '' )
			&& '' !== (string) ( $data['shipping_state'] ?? $data['billing_state'] ?? '' );
	}

	private function auditBlock( string $event, RestrictionDecision $decision, string $phase ): void {
		$rule_ids = array_slice( $decision->ruleIds(), 0, 20 );
		AuditServiceFactory::create()->success(
			$event,
			get_current_user_id(),
			'restriction_rules',
			implode( ',', $rule_ids ),
			array(
				'phase'    => $phase,
				'rule_ids' => $rule_ids,
			)
		);
	}
}
