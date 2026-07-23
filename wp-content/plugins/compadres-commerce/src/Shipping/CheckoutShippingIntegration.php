<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

use Compadres\Commerce\Audit\AuditServiceFactory;
use Closure;
use DateTimeImmutable;
use Exception;
use RuntimeException;
use Throwable;
use WC;

/**
 * Server-side checkout enforcement for Adult Signature Required shipping.
 *
 * This integration is the single enforcement point. It runs after cart,
 * geographic-restriction, and age-verification checks, and before payment
 * processing. It never trusts a browser-submitted eligibility value.
 */
final class CheckoutShippingIntegration {

	private AdultSignaturePolicy $policy;
	private WordPressShippingRuntime $runtime;
	/** @var Closure(): DateTimeImmutable */
	private Closure $clock;

	/**
	 * @param callable(): DateTimeImmutable $clock
	 */
	public function __construct(
		?AdultSignaturePolicy $policy = null,
		?WordPressShippingRuntime $runtime = null,
		?callable $clock = null
	) {
		$this->policy  = $policy ?? new AdultSignaturePolicy();
		$this->runtime = $runtime ?? new WordPressShippingRuntime();
		$this->clock   = Closure::fromCallable( $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable( 'now', wp_timezone() ) );
	}

	public function register(): void {
		// After restriction (10) and age verification (10); before order creation.
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate' ), 11, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'persist' ), 6 );
		add_action( 'woocommerce_before_pay_action', array( $this, 'blockPayIfIneligible' ), 11 );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'displayNotice' ), 10 );
		add_action( 'update_option_' . ShippingSettings::OPTION_SCENARIO, array( $this, 'auditSettingsUpdate' ), 10, 2 );
		add_action( 'add_option_' . ShippingSettings::OPTION_SCENARIO, array( $this, 'auditSettingsAdd' ), 10, 2 );
	}

	/**
	 * Validate shipping eligibility during checkout validation.
	 *
	 * @param array<string, mixed> $data
	 * @param \WP_Error            $errors
	 */
	public function validate( array $data, \WP_Error $errors ): void {
		$result = $this->evaluate( $data );
		$audit  = AuditServiceFactory::create();
		if ( $result->eligible() ) {
			$audit->success(
				'shipping.eligibility_checked',
				get_current_user_id(),
				'shipping',
				'',
				array_merge(
					$result->auditContext(),
					array( 'result' => OrderShippingMeta::ELIGIBILITY_ALLOWED )
				)
			);
			return;
		}
		$audit->failure(
			'shipping.checkout_blocked',
			$result->reason(),
			get_current_user_id(),
			'shipping',
			'',
			array_merge(
				$result->auditContext(),
				array( 'result' => OrderShippingMeta::ELIGIBILITY_BLOCKED )
			)
		);
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce error API escapes.
		$errors->add( 'compadres_shipping_ineligible', $result->customerMessage() );
	}

	/**
	 * Persist minimal shipping-eligibility metadata when the order is created.
	 *
	 * @param \WC_Order $order
	 */
	public function persist( \WC_Order $order ): void {
		$selected = $this->resolveSelectedServiceId();
		$context  = new ShippingContext(
			(string) $order->get_shipping_country(),
			(string) $order->get_shipping_state(),
			(string) $order->get_shipping_postcode(),
			$selected,
			$this->orderProductIds( $order )
		);
		$provider = $this->runtime->provider();
		$result   = $this->policy->evaluate( $context, $provider );

		$order->update_meta_data( OrderShippingMeta::ADULT_SIGNATURE_REQUIRED, $result->requiresAdultSignature() ? 'yes' : 'no' );
		$order->update_meta_data( OrderShippingMeta::PROVIDER, $result->auditContext()['provider'] );
		$order->update_meta_data( OrderShippingMeta::SERVICE, $result->selectedServiceId() );
		try {
			$reference = $result->eligible() ? $this->normalizeReference( $provider->serviceReference( $result->selectedServiceId() ) ) : null;
		} catch ( Throwable ) {
			$reference = null;
		}
		if ( null !== $reference ) {
			$order->update_meta_data( OrderShippingMeta::SERVICE_REFERENCE, $reference );
		}
		$order->update_meta_data(
			OrderShippingMeta::ELIGIBILITY,
			$result->eligible() ? OrderShippingMeta::ELIGIBILITY_ALLOWED : OrderShippingMeta::ELIGIBILITY_BLOCKED
		);
		$order->update_meta_data( OrderShippingMeta::ELIGIBILITY_CHECKED_AT, ( $this->clock )()->format( 'c' ) );

		if ( ! $result->eligible() ) {
			$this->auditBlock( $result, 'pre_order' );
			throw new Exception( esc_html( $result->customerMessage() ) );
		}
	}

	/**
	 * Block pay-for-order when eligible shipping is absent. Fails closed.
	 *
	 * @param \WC_Order $order
	 */
	public function blockPayIfIneligible( \WC_Order $order ): void {
		$stored   = (string) $order->get_meta( OrderShippingMeta::ELIGIBILITY );
		$selected = $this->serviceIdFromStoredOrder( $order );
		if ( OrderShippingMeta::ELIGIBILITY_ALLOWED !== $stored || '' === $selected ) {
			$result = $this->invalidOrderSnapshotResult();
			$this->auditBlock( $result, 'order_payment' );
			throw new Exception( esc_html( $result->customerMessage() ) );
		}

		$context = new ShippingContext(
			(string) $order->get_shipping_country(),
			(string) $order->get_shipping_state(),
			(string) $order->get_shipping_postcode(),
			$selected,
			$this->orderProductIds( $order )
		);
		$result  = $this->policy->evaluate( $context, $this->runtime->provider() );
		if ( ! $result->eligible() ) {
			$this->auditBlock( $result, 'order_payment' );
			throw new Exception( esc_html( $result->customerMessage() ) );
		}
		if ( (string) $order->get_meta( OrderShippingMeta::PROVIDER ) !== $result->auditContext()['provider'] ) {
			$result = $this->invalidOrderSnapshotResult();
			$this->auditBlock( $result, 'order_payment' );
			throw new Exception( esc_html( $result->customerMessage() ) );
		}
	}

	/**
	 * Display the Adult Signature Required checkout notice.
	 */
	public function displayNotice(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static, approved copy.
		wc_print_notice( $this->checkoutNotice(), 'notice' );
	}

	private function checkoutNotice(): string {
		return 'Cigar deliveries use Adult Signature Required service. An adult must be present to sign. Delivery requirements may vary based on the approved shipping service.';
	}

	/**
	 * Evaluate eligibility from the checkout submission data.
	 *
	 * @param array<string, mixed> $data
	 */
	private function evaluate( array $data ): ShippingEligibilityResult {
		$selected = $this->resolveSelectedServiceId( $data );
		$context  = new ShippingContext(
			(string) ( $data['shipping_country'] ?? $data['billing_country'] ?? '' ),
			(string) ( $data['shipping_state'] ?? $data['billing_state'] ?? '' ),
			(string) ( $data['shipping_postcode'] ?? $data['billing_postcode'] ?? '' ),
			$selected,
			$this->cartProductIds()
		);
		return $this->policy->evaluate( $context, $this->runtime->provider() );
	}

	/**
	 * Resolve one selected service from server-calculated package rates.
	 * Multiple packages are unsupported at launch and therefore fail closed.
	 *
	 * @param array<string, mixed>|null $data
	 */
	private function resolveSelectedServiceId( ?array $data = null ): string {
		$woocommerce = WC();
		$methods     = null !== $data ? ( $data['shipping_method'] ?? array() ) : array();
		if ( ! is_array( $methods ) || array() === $methods ) {
			$methods = null !== $woocommerce->session
				? $woocommerce->session->get( 'chosen_shipping_methods', array() )
				: array();
		}
		if ( ! is_array( $methods ) || 1 !== count( $methods ) ) {
			return '';
		}
		$rate_id = reset( $methods );
		if ( ! is_string( $rate_id ) || '' === $rate_id || strlen( $rate_id ) > 128 ) {
			return '';
		}

		$packages = $woocommerce->shipping()->get_packages();
		if ( 1 !== count( $packages ) ) {
			return '';
		}
		$package = reset( $packages );
		$rates   = is_array( $package ) && isset( $package['rates'] ) && is_array( $package['rates'] )
			? $package['rates']
			: array();
		if ( ! array_key_exists( $rate_id, $rates ) ) {
			return '';
		}
		return $this->serviceIdFromRate( $rate_id );
	}

	/** Extract a bounded service identifier from method:instance:service. */
	private function serviceIdFromRate( string $rate_id ): string {
		$parts = explode( ':', $rate_id );
		if (
			3 !== count( $parts )
			|| MockShippingMethod::METHOD_ID !== $parts[0]
			|| 1 !== preg_match( '/^[0-9]+$/', $parts[1] )
			|| 1 !== preg_match( '/^[a-z0-9_]{1,64}$/', $parts[2] )
		) {
			return '';
		}
		return $parts[2];
	}

	private function serviceIdFromStoredOrder( \WC_Order $order ): string {
		if ( 'yes' !== (string) $order->get_meta( OrderShippingMeta::ADULT_SIGNATURE_REQUIRED ) ) {
			return '';
		}
		if ( '' === (string) $order->get_meta( OrderShippingMeta::PROVIDER ) ) {
			return '';
		}
		if ( ! $this->validEligibilityTimestamp( (string) $order->get_meta( OrderShippingMeta::ELIGIBILITY_CHECKED_AT ) ) ) {
			return '';
		}
		$service = (string) $order->get_meta( OrderShippingMeta::SERVICE );
		return 1 === preg_match( '/^[a-z0-9_]{1,64}$/', $service ) ? $service : '';
	}

	private function validEligibilityTimestamp( string $value ): bool {
		if ( '' === $value || strlen( $value ) > 64 ) {
			return false;
		}
		$timestamp = DateTimeImmutable::createFromFormat( DATE_ATOM, $value );
		$errors    = DateTimeImmutable::getLastErrors();
		if ( false === $timestamp ) {
			return false;
		}
		if ( is_array( $errors ) && ( 0 !== $errors['warning_count'] || 0 !== $errors['error_count'] ) ) {
			return false;
		}
		return hash_equals( $timestamp->format( DATE_ATOM ), $value );
	}

	private function invalidOrderSnapshotResult(): ShippingEligibilityResult {
		return ShippingEligibilityResult::blocked(
			true,
			false,
			'',
			false,
			ShippingEligibilityResult::REASON_INVALID_ORDER_SNAPSHOT,
			array( 'provider' => 'order_snapshot' )
		);
	}

	private function normalizeReference( ?string $reference ): ?string {
		if ( null === $reference ) {
			return null;
		}
		$normalized = preg_replace( '/[^A-Za-z0-9._:-]/', '', $reference );
		$normalized = substr( is_string( $normalized ) ? $normalized : '', 0, 128 );
		return '' !== $normalized ? $normalized : null;
	}

	/** @return list<int> */
	private function cartProductIds(): array {
		$woocommerce = WC();
		if ( null === $woocommerce->cart ) {
			throw new RuntimeException( 'Cart data is unavailable.' );
		}
		$ids = array();
		foreach ( $woocommerce->cart->get_cart() as $item ) {
			$ids[] = (int) ( $item['product_id'] ?? 0 );
			$ids[] = (int) ( $item['variation_id'] ?? 0 );
		}
		return array_values( array_unique( array_filter( $ids, static fn ( int $id ): bool => $id > 0 ) ) );
	}

	/** @return list<int> */
	private function orderProductIds( \WC_Order $order ): array {
		$ids = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$ids[] = (int) $item->get_product_id();
			$ids[] = (int) $item->get_variation_id();
		}
		return array_values( array_unique( array_filter( $ids, static fn ( int $id ): bool => $id > 0 ) ) );
	}

	private function auditBlock( ShippingEligibilityResult $result, string $phase ): void {
		AuditServiceFactory::create()->failure(
			'shipping.checkout_blocked',
			$result->reason(),
			get_current_user_id(),
			'shipping',
			'',
			array_merge(
				$result->auditContext(),
				array(
					'phase'  => $phase,
					'result' => OrderShippingMeta::ELIGIBILITY_BLOCKED,
				)
			)
		);
	}

	/**
	 * @param mixed $option_name
	 * @param mixed $value
	 */
	public function auditSettingsAdd( $option_name, $value ): void {
		$this->auditSettingsUpdate( null, $value );
	}

	/**
	 * Audit shipping settings changes (e.g. mock scenario updates used for
	 * local testing). Never includes addresses or carrier payloads.
	 *
	 * @param mixed $old_value
	 * @param mixed $new_value
	 */
	public function auditSettingsUpdate( $old_value, $new_value ): void {
		$settings = is_array( $new_value ) ? ShippingSettings::sanitize( $new_value ) : ShippingSettings::defaults();
		AuditServiceFactory::create()->success(
			'shipping.settings_updated',
			get_current_user_id(),
			'shipping',
			'',
			array( 'scenario' => $settings['scenario'] )
		);
	}
}
