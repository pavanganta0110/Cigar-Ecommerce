<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

use JsonException;

/** OAuth and Rates API client for the documented FedEx REST contract. */
final class FedExApiClient {

	private ?string $access_token = null;

	public function __construct(
		private FedExConfiguration $configuration,
		private FedExTransport $transport
	) {
	}

	/** @return list<FedExRateQuote> */
	public function rates( ShippingContext $context ): array {
		if ( ! $this->configuration->isConfigured() || ! $this->validContext( $context ) ) {
			throw new ShippingProviderException( 'FedEx rating is unavailable.' );
		}
		$response = $this->transport->post(
			$this->configuration->apiBaseUrl() . '/rate/v1/rates/quotes',
			array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $this->accessToken(),
				'Content-Type'  => 'application/json',
				'X-locale'      => 'en_US',
			),
			$this->rateRequestBody( $context )
		);
		if ( 200 !== $response['status'] ) {
			throw new ShippingProviderException( 'FedEx rating is unavailable.' );
		}
		return $this->normalizeRates( $this->decode( $response['body'], 'FedEx rating is unavailable.' ) );
	}

	private function accessToken(): string {
		if ( null !== $this->access_token ) {
			return $this->access_token;
		}
		$response = $this->transport->post(
			$this->configuration->apiBaseUrl() . '/oauth/token',
			array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
			http_build_query(
				array(
					'grant_type'    => 'client_credentials',
					'client_id'     => $this->configuration->clientId(),
					'client_secret' => $this->configuration->clientSecret(),
				),
				'',
				'&',
				PHP_QUERY_RFC3986
			)
		);
		if ( 200 !== $response['status'] ) {
			throw new ShippingProviderException( 'FedEx authorization is unavailable.' );
		}
		$decoded = $this->decode( $response['body'], 'FedEx authorization is unavailable.' );
		$token   = isset( $decoded['access_token'] ) && is_string( $decoded['access_token'] )
			? trim( $decoded['access_token'] )
			: '';
		if ( '' === $token || strlen( $token ) > 4096 ) {
			throw new ShippingProviderException( 'FedEx authorization is unavailable.' );
		}
		$this->access_token = $token;
		return $token;
	}

	private function rateRequestBody( ShippingContext $context ): string {
		$body = array(
			'accountNumber'     => array( 'value' => $this->configuration->accountNumber() ),
			'carrierCodes'      => array( 'FDXE', 'FDXG' ),
			'requestedShipment' => array(
				'pickupType'                => 'USE_SCHEDULED_PICKUP',
				'rateRequestType'           => array( 'ACCOUNT' ),
				'shipper'                   => array( 'address' => $this->configuration->shipperAddress() ),
				'recipient'                 => array(
					'address' => array(
						'countryCode'         => strtoupper( $context->country() ),
						'stateOrProvinceCode' => strtoupper( $context->state() ),
						'postalCode'          => strtoupper( $context->postalCode() ),
						'residential'         => true,
					),
				),
				'packagingType'             => 'YOUR_PACKAGING',
				'requestedPackageLineItems' => array(
					array(
						'groupPackageCount'      => 1,
						'weight'                 => array(
							'units' => strtoupper( $context->weightUnit() ),
							'value' => round( $context->weight(), 2 ),
						),
						'packageSpecialServices' => array(
							'specialServiceTypes' => array( 'SIGNATURE_OPTION' ),
							'signatureOptionType' => 'ADULT',
						),
					),
				),
			),
		);
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- JSON_THROW_ON_ERROR is required for fail-closed request construction.
			return json_encode( $body, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new ShippingProviderException( 'FedEx rating is unavailable.' );
		}
	}

	/**
	 * @param array<string, mixed> $response
	 * @return list<FedExRateQuote>
	 */
	private function normalizeRates( array $response ): array {
		$transaction = $this->identifier( $response['transactionId'] ?? '', 64 );
		$details     = $response['output']['rateReplyDetails'] ?? array();
		if ( '' === $transaction || ! is_array( $details ) ) {
			return array();
		}
		$rates = array();
		foreach ( $details as $detail ) {
			if ( ! is_array( $detail ) || 'ADULT' !== ( $detail['signatureOptionType'] ?? '' ) ) {
				continue;
			}
			$service = strtolower( $this->identifier( $detail['serviceType'] ?? '', 64 ) );
			$charge  = $this->accountCharge( $detail['ratedShipmentDetails'] ?? array() );
			if ( '' === $service || null === $charge ) {
				continue;
			}
			$label   = $this->label( $detail['serviceName'] ?? '', $service );
			$rates[] = new FedExRateQuote(
				$service,
				$label,
				$charge['amount'],
				$charge['currency'],
				true,
				$transaction . ':' . $service
			);
		}
		return $rates;
	}

	/**
	 * @param mixed $details
	 * @return array{amount: float, currency: string}|null
	 */
	private function accountCharge( mixed $details ): ?array {
		if ( ! is_array( $details ) ) {
			return null;
		}
		foreach ( $details as $detail ) {
			if ( ! is_array( $detail ) || 'ACCOUNT' !== ( $detail['rateType'] ?? '' ) ) {
				continue;
			}
			$amount   = $detail['totalNetCharge'] ?? null;
			$currency = strtoupper( (string) ( $detail['shipmentRateDetail']['currency'] ?? '' ) );
			if ( ! is_int( $amount ) && ! is_float( $amount ) ) {
				continue;
			}
			$value = (float) $amount;
			if ( $value < 0 || ! is_finite( $value ) || 1 !== preg_match( '/^[A-Z]{3}$/', $currency ) ) {
				continue;
			}
			return array(
				'amount'   => round( $value, 2 ),
				'currency' => $currency,
			);
		}
		return null;
	}

	/** @return array<string, mixed> */
	private function decode( string $body, string $failure_message ): array {
		if ( '' === $body || strlen( $body ) > 1048576 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal fixed messages only; this exception is never rendered directly.
			throw new ShippingProviderException( $failure_message );
		}
		try {
			$decoded = json_decode( $body, true, 64, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal fixed messages only; this exception is never rendered directly.
			throw new ShippingProviderException( $failure_message );
		}
		if ( ! is_array( $decoded ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal fixed messages only; this exception is never rendered directly.
			throw new ShippingProviderException( $failure_message );
		}
		return $decoded;
	}

	private function validContext( ShippingContext $context ): bool {
		return $context->hasDestination()
			&& 1 === preg_match( '/^[A-Z]{2}$/', strtoupper( $context->country() ) )
			&& 1 === preg_match( '/^[A-Z0-9]{1,3}$/', strtoupper( $context->state() ) )
			&& 1 === preg_match( '/^[A-Z0-9 -]{3,10}$/', strtoupper( $context->postalCode() ) )
			&& $context->weight() > 0
			&& $context->weight() <= 150
			&& in_array( strtoupper( $context->weightUnit() ), array( 'LB', 'KG' ), true );
	}

	private function identifier( mixed $value, int $length ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$normalized = preg_replace( '/[^A-Za-z0-9._:-]/', '', $value );
		return substr( is_string( $normalized ) ? $normalized : '', 0, $length );
	}

	private function label( mixed $value, string $fallback ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Carrier text is normalized, bounded, and escaped by WooCommerce when rendered.
		$label = is_string( $value ) ? trim( strip_tags( $value ) ) : '';
		$label = preg_replace( '/\s+/', ' ', $label );
		$label = substr( is_string( $label ) ? $label : '', 0, 80 );
		return '' !== $label ? $label : ucwords( str_replace( '_', ' ', $fallback ) );
	}
}
