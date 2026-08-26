<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Payments;

use Compadres\Commerce\Infrastructure\Environment;

/**
 * Immutable, environment-backed configuration for the Global Payments
 * Unified Payments gateway (globalpayments_gpapi), the modern tokenized
 * product line of the official "GlobalPayments Gateway Provider for
 * WooCommerce" plugin. Sandbox and live App Id/App Key pairs are kept and
 * validated separately since the plugin stores both at once and switches
 * between them with its own Live Mode toggle.
 */
final class GlobalPaymentsConfiguration {

	public function __construct(
		private Environment $environment,
		private bool $production_approved,
		private string $sandbox_app_id,
		private string $sandbox_app_key,
		private string $live_app_id,
		private string $live_app_key,
		private string $merchant_contact_url
	) {
		$this->sandbox_app_id       = trim( $this->sandbox_app_id );
		$this->sandbox_app_key      = trim( $this->sandbox_app_key );
		$this->live_app_id          = trim( $this->live_app_id );
		$this->live_app_key         = trim( $this->live_app_key );
		$this->merchant_contact_url = trim( $this->merchant_contact_url );
	}

	public static function fromEnvironment( Environment $environment ): self {
		return new self(
			$environment,
			strtolower( (string) getenv( 'COMPADRES_PAYMENT_PRODUCTION_APPROVED' ) ) === 'true',
			(string) getenv( 'COMPADRES_GLOBAL_PAYMENTS_SANDBOX_APP_ID' ),
			(string) getenv( 'COMPADRES_GLOBAL_PAYMENTS_SANDBOX_APP_KEY' ),
			(string) getenv( 'COMPADRES_GLOBAL_PAYMENTS_APP_ID' ),
			(string) getenv( 'COMPADRES_GLOBAL_PAYMENTS_APP_KEY' ),
			(string) getenv( 'COMPADRES_GLOBAL_PAYMENTS_MERCHANT_CONTACT_URL' )
		);
	}

	public function isConfigured(): bool {
		if ( ! $this->hasMerchantContactUrl() ) {
			return false;
		}
		if ( $this->environment->isProduction() ) {
			return $this->production_approved && $this->hasLiveCredentials();
		}
		return $this->hasSandboxCredentials();
	}

	public function environment(): Environment {
		return $this->environment;
	}

	public function productionApproved(): bool {
		return $this->production_approved;
	}

	public function hasSandboxCredentials(): bool {
		return self::validId( $this->sandbox_app_id ) && self::validKey( $this->sandbox_app_key );
	}

	public function hasLiveCredentials(): bool {
		return self::validId( $this->live_app_id ) && self::validKey( $this->live_app_key );
	}

	public function hasMerchantContactUrl(): bool {
		return '' !== $this->merchant_contact_url && str_starts_with( $this->merchant_contact_url, 'https://' );
	}

	public function sandboxAppId(): string {
		return $this->sandbox_app_id;
	}

	public function sandboxAppKey(): string {
		return $this->sandbox_app_key;
	}

	public function liveAppId(): string {
		return $this->live_app_id;
	}

	public function liveAppKey(): string {
		return $this->live_app_key;
	}

	public function merchantContactUrl(): string {
		return $this->merchant_contact_url;
	}

	private static function validId( string $value ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9._-]{1,128}$/', $value );
	}

	private static function validKey( string $value ): bool {
		return '' !== $value && strlen( $value ) <= 256;
	}
}
