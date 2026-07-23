<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

use Compadres\Commerce\Audit\AuditServiceFactory;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use WC_Order;
use WP_Error;

final class CheckoutIntegration {

	public function __construct( private ?WordPressAgeVerificationRuntime $runtime = null ) {
		$this->runtime ??= new WordPressAgeVerificationRuntime();
	}

	public function registerHooks(): void {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'checkoutFields' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validateCheckout' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'beforeOrderCreation' ), 5, 2 );
		add_action( 'wp_ajax_compadres_age_verification_refresh', array( $this, 'refreshStatus' ) );
		add_action( 'wp_ajax_nopriv_compadres_age_verification_refresh', array( $this, 'refreshStatus' ) );
		add_action( 'template_redirect', array( $this, 'maybeRefreshStatus' ) );
	}

	/** @param array<string, mixed> $fields
	 *  @return array<string, mixed>
	 */
	public function checkoutFields( array $fields ): array {
		$config = $this->runtime->configuration();
		if ( ! $config->enabled() || ! $config->requiresDateOfBirth() ) {
			return $fields;
		}
		$fields['billing']['compadres_date_of_birth'] = array(
			'type'         => 'date',
			'label'        => __( 'Date of birth', 'compadres-commerce' ),
			'required'     => true,
			'autocomplete' => 'bday',
			'priority'     => 35,
			'description'  => __( 'Used transiently by the configured age-verification provider and not stored by Compadres.', 'compadres-commerce' ),
		);
		return $fields;
	}

	/** @param array<string, mixed> $data */
	public function validateCheckout( array $data, WP_Error $errors ): void {
		if ( $this->runtime->developmentBypassEnabled() ) {
			return;
		}
		$provider = $this->runtime->provider();
		$store    = $this->runtime->store();
		$now      = static fn (): DateTimeImmutable => new DateTimeImmutable( 'now', wp_timezone() );
		AuditServiceFactory::create()->success( 'age_verification.started', get_current_user_id(), 'checkout', '', AuditServiceFactory::requestContext() );
		try {
			$workflow = new CheckoutWorkflow(
				new AgeVerificationService( $provider, $store, $now ),
				new CheckoutGuard( $provider, $now ),
				$provider instanceof DateOfBirthRequirement && $provider->requiresDateOfBirth()
			);
			$decision = $workflow->verify( $data, wc_get_checkout_url() );
			$result   = $store->current();
			if ( null !== $result ) {
				$this->auditResult( $result );
			}
			if ( ! $decision->allowed() ) {
				$message = esc_html( $decision->message() );
				if ( null !== $decision->hostedUrl() ) {
					$message .= ' ' . sprintf(
						'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a> %3$s',
						esc_url( $decision->hostedUrl() ),
						esc_html__( 'Continue secure verification with AgeChecker', 'compadres-commerce' ),
						esc_html__( 'Any requested identity document is handled by AgeChecker; Compadres does not receive or store the file.', 'compadres-commerce' )
					);
					$message .= ' <a href="' . esc_url( wp_nonce_url( add_query_arg( 'compadres_refresh_age', '1', wc_get_checkout_url() ), 'compadres_age_verification_refresh' ) ) . '">' . esc_html__( 'Refresh verification status', 'compadres-commerce' ) . '</a>';
				}
				$errors->add( 'compadres_age_verification', wp_kses_post( $message ) );
			}
		} catch ( InvalidArgumentException $exception ) {
			$errors->add( 'compadres_age_verification_dob', esc_html( $exception->getMessage() ) );
		} catch ( RuntimeException ) {
			$errors->add( 'compadres_age_verification_unavailable', esc_html__( 'Age verification is unavailable. Checkout cannot continue.', 'compadres-commerce' ) );
		}
	}

	/** @param array<string, mixed> $_data */
	public function beforeOrderCreation( WC_Order $order, array $_data ): void {
		unset( $_data );
		$order->delete_meta_data( 'compadres_date_of_birth' );
		$order->delete_meta_data( '_compadres_date_of_birth' );
		if ( $this->runtime->developmentBypassEnabled() ) {
			return;
		}
		$result = $this->runtime->store()->current();
		if ( null === $result ) {
			throw new RuntimeException( 'Age verification must pass before order creation.' );
		}
		$provider = $this->runtime->provider();
		$decision = ( new CheckoutGuard( $provider, static fn (): DateTimeImmutable => new DateTimeImmutable( 'now', wp_timezone() ) ) )->decision( $result, wc_get_checkout_url() );
		if ( ! $decision->allowed() ) {
			throw new RuntimeException( 'Age verification must pass before order creation.' );
		}
		( new OrderVerificationSnapshot() )->write( new WooCommerceOrderMetaWriter( $order ), $result );
	}

	public function refreshStatus(): void {
		check_ajax_referer( 'compadres_age_verification_refresh', 'nonce' );
		$store   = $this->runtime->store();
		$current = $store->current();
		if ( null === $current ) {
			wp_send_json_error( array( 'message' => __( 'No age verification is available to refresh.', 'compadres-commerce' ) ), 409 );
		}
		$provider = $this->runtime->provider();
		if ( $provider instanceof RefreshableAgeVerificationProvider ) {
			$current = $provider->refresh( $current->reference() );
			$store->save( $current );
			$this->auditResult( $current );
		}
		wp_send_json_success(
			array(
				'status'  => $current->status(),
				'message' => __( 'Verification status refreshed. Return to checkout to continue.', 'compadres-commerce' ),
			)
		);
	}

	public function maybeRefreshStatus(): void {
		if ( ! isset( $_GET['compadres_refresh_age'] ) ) {
			return;
		}
		check_admin_referer( 'compadres_age_verification_refresh' );
		$store    = $this->runtime->store();
		$current  = $store->current();
		$provider = $this->runtime->provider();
		if ( null !== $current && $provider instanceof RefreshableAgeVerificationProvider ) {
			$current = $provider->refresh( $current->reference() );
			$store->save( $current );
			$this->auditResult( $current );
		}
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	private function auditResult( VerificationResult $result ): void {
		$data  = array_intersect_key( $result->toArray(), array_flip( array( 'provider', 'reference', 'status', 'reason_code', 'verified_at', 'expires_at' ) ) );
		$audit = AuditServiceFactory::create();
		$audit->entityChange( 'age_verification.status_updated', 'age_verification', $result->reference(), array(), $data, get_current_user_id(), AuditServiceFactory::requestContext() );
		$events = array(
			VerificationStatus::PASSED        => 'age_verification.passed',
			VerificationStatus::FAILED        => 'age_verification.failed',
			VerificationStatus::MANUAL_REVIEW => 'age_verification.manual_review_required',
			VerificationStatus::EXPIRED       => 'age_verification.expired',
			VerificationStatus::UNAVAILABLE   => 'age_verification.provider_unavailable',
		);
		if ( isset( $events[ $result->status() ] ) ) {
			$audit->entityChange( $events[ $result->status() ], 'age_verification', $result->reference(), array(), $data, get_current_user_id(), AuditServiceFactory::requestContext() );
		}
	}
}
