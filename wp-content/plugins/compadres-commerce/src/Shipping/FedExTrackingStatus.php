<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

/** The latest FedEx status for one tracking number, normalized and bounded. */
final class FedExTrackingStatus {

	public const NOT_FOUND = 'NOTFOUND';

	public function __construct(
		private string $code,
		private string $text
	) {
	}

	/**
	 * @param array<string, mixed> $response Decoded FedEx Track API response.
	 * @throws ShippingProviderException When the response has no usable result.
	 */
	public static function fromResponse( array $response ): self {
		$result = $response['output']['completeTrackResults'][0]['trackResults'][0] ?? null;
		if ( ! is_array( $result ) ) {
			throw new ShippingProviderException( 'FedEx tracking is unavailable.' );
		}
		$error = $result['error']['code'] ?? '';
		if ( is_string( $error ) && str_contains( $error, 'NOTFOUND' ) ) {
			return new self( self::NOT_FOUND, 'Not yet in the FedEx system' );
		}
		$detail = $result['latestStatusDetail'] ?? null;
		if ( ! is_array( $detail ) ) {
			throw new ShippingProviderException( 'FedEx tracking is unavailable.' );
		}
		$code = self::clean( $detail['code'] ?? '', 8 );
		$text = self::clean( $detail['statusByLocale'] ?? ( $detail['description'] ?? '' ), 80 );
		if ( '' === $code || '' === $text ) {
			throw new ShippingProviderException( 'FedEx tracking is unavailable.' );
		}
		return new self( strtoupper( $code ), $text );
	}

	public function code(): string {
		return $this->code;
	}

	public function text(): string {
		return $this->text;
	}

	public function delivered(): bool {
		return 'DL' === $this->code;
	}

	/** Delivery or shipment exception that staff should look at. */
	public function needsAttention(): bool {
		return in_array( $this->code, array( 'DE', 'SE' ), true );
	}

	private static function clean( mixed $value, int $length ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Carrier text is normalized, bounded, and escaped when rendered.
		$text = trim( preg_replace( '/\s+/', ' ', strip_tags( $value ) ) ?? '' );
		return substr( $text, 0, $length );
	}
}
