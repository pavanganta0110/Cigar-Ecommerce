<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

use DateTimeImmutable;
use InvalidArgumentException;

final class VerificationRequest {

	/** @param array<string, string> $providerData */
	private function __construct( private array $providerData ) {}

	/** @param array<string, mixed> $checkout */
	public static function fromCheckout( array $checkout, bool $date_of_birth_required = false ): self {
		$map  = array(
			'first_name' => 'billing_first_name',
			'last_name'  => 'billing_last_name',
			'email'      => 'billing_email',
			'phone'      => 'billing_phone',
			'address_1'  => 'billing_address_1',
			'address_2'  => 'billing_address_2',
			'city'       => 'billing_city',
			'state'      => 'billing_state',
			'postcode'   => 'billing_postcode',
			'country'    => 'billing_country',
		);
		$data = array();
		foreach ( $map as $provider_key => $checkout_key ) {
			$value                 = isset( $checkout[ $checkout_key ] ) ? (string) $checkout[ $checkout_key ] : '';
			$without_markup        = preg_replace( '/<[^>]*>/', '', $value );
			$data[ $provider_key ] = trim( (string) preg_replace( '/[\x00-\x1F\x7F]/u', '', (string) $without_markup ) );
		}
		if ( $date_of_birth_required ) {
			$date_of_birth = isset( $checkout['compadres_date_of_birth'] ) ? trim( (string) $checkout['compadres_date_of_birth'] ) : '';
			$parsed        = DateTimeImmutable::createFromFormat( '!Y-m-d', $date_of_birth );
			$errors        = DateTimeImmutable::getLastErrors();
			if ( false === $parsed || ( false !== $errors && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) || $parsed->format( 'Y-m-d' ) !== $date_of_birth || $parsed >= new DateTimeImmutable( 'today' ) ) {
				throw new InvalidArgumentException( 'A valid past date of birth is required for age verification.' );
			}
			$data['date_of_birth'] = $date_of_birth;
		}
		return new self( $data );
	}

	/** @return array<string, string> */
	public function providerData(): array {
		return $this->providerData;
	}
}
