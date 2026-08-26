<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

use Compadres\Commerce\Infrastructure\Environment;

/** Immutable, environment-backed FedEx API configuration. */
final class FedExConfiguration {

	private const PRODUCTION_ORIGIN = 'https://apis.fedex.com';
	private const SANDBOX_ORIGIN    = 'https://apis-sandbox.fedex.com';

	public function __construct(
		private Environment $environment,
		private bool $production_approved,
		private string $api_base_url,
		private string $client_id,
		private string $client_secret,
		private string $account_number,
		private string $origin_country,
		private string $origin_state,
		private string $origin_postal_code
	) {
		$this->api_base_url       = rtrim( trim( $this->api_base_url ), '/' );
		$this->client_id          = trim( $this->client_id );
		$this->client_secret      = trim( $this->client_secret );
		$this->account_number     = trim( $this->account_number );
		$this->origin_country     = strtoupper( trim( $this->origin_country ) );
		$this->origin_state       = strtoupper( trim( $this->origin_state ) );
		$this->origin_postal_code = strtoupper( trim( $this->origin_postal_code ) );
	}

	public static function fromEnvironment( Environment $environment ): self {
		return new self(
			$environment,
			strtolower( (string) getenv( 'COMPADRES_SHIPPING_PRODUCTION_APPROVED' ) ) === 'true',
			(string) getenv( 'COMPADRES_FEDEX_API_BASE_URL' ),
			(string) getenv( 'COMPADRES_FEDEX_CLIENT_ID' ),
			(string) getenv( 'COMPADRES_FEDEX_CLIENT_SECRET' ),
			(string) getenv( 'COMPADRES_FEDEX_ACCOUNT_NUMBER' ),
			(string) getenv( 'COMPADRES_FEDEX_ORIGIN_COUNTRY' ),
			(string) getenv( 'COMPADRES_FEDEX_ORIGIN_STATE' ),
			(string) getenv( 'COMPADRES_FEDEX_ORIGIN_POSTAL_CODE' )
		);
	}

	public function isConfigured(): bool {
		return ( ! $this->environment->isProduction() || $this->production_approved )
			&& $this->validOrigin()
			&& $this->validCredentials()
			&& $this->validShipper();
	}

	public function apiBaseUrl(): string {
		return $this->api_base_url;
	}

	public function clientId(): string {
		return $this->client_id;
	}

	public function clientSecret(): string {
		return $this->client_secret;
	}

	public function accountNumber(): string {
		return $this->account_number;
	}

	/** @return array{countryCode: string, stateOrProvinceCode: string, postalCode: string} */
	public function shipperAddress(): array {
		return array(
			'countryCode'         => $this->origin_country,
			'stateOrProvinceCode' => $this->origin_state,
			'postalCode'          => $this->origin_postal_code,
		);
	}

	private function validOrigin(): bool {
		if ( $this->environment->isProduction() ) {
			return self::PRODUCTION_ORIGIN === $this->api_base_url;
		}
		return self::SANDBOX_ORIGIN === $this->api_base_url;
	}

	private function validCredentials(): bool {
		return 1 === preg_match( '/^[A-Za-z0-9._-]{1,128}$/', $this->client_id )
			&& '' !== $this->client_secret
			&& strlen( $this->client_secret ) <= 256
			&& 1 === preg_match( '/^[0-9]{9}$/', $this->account_number );
	}

	private function validShipper(): bool {
		return 1 === preg_match( '/^[A-Z]{2}$/', $this->origin_country )
			&& 1 === preg_match( '/^[A-Z0-9]{1,3}$/', $this->origin_state )
			&& 1 === preg_match( '/^[A-Z0-9 -]{3,10}$/', $this->origin_postal_code );
	}
}
