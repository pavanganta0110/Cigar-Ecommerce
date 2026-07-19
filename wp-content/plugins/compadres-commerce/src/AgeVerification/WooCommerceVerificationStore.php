<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

use DateTimeImmutable;
use Throwable;

final class WooCommerceVerificationStore implements VerificationStore {

	private const KEY = 'compadres_age_verification';

	public function __construct( private KeyValueStore $session ) {}

	public function current(): ?VerificationResult {
		$data = $this->session->get( self::KEY );
		if ( ! is_array( $data ) || ! isset( $data['provider'], $data['reference'], $data['status'], $data['reason_code'], $data['verified_at'], $data['expires_at'] ) ) {
			return null;
		}
		try {
			return new VerificationResult(
				(string) $data['provider'],
				(string) $data['reference'],
				(string) $data['status'],
				(string) $data['reason_code'],
				new DateTimeImmutable( (string) $data['verified_at'] ),
				'' === (string) $data['expires_at'] ? null : new DateTimeImmutable( (string) $data['expires_at'] )
			);
		} catch ( Throwable ) {
			return null;
		}
	}

	public function save( VerificationResult $result ): void {
		$data = $result->toArray();
		$this->session->set(
			self::KEY,
			array_intersect_key(
				$data,
				array_flip( array( 'provider', 'reference', 'status', 'reason_code', 'verified_at', 'expires_at' ) )
			)
		);
	}
}
