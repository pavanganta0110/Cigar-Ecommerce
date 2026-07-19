<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\AgeVerification\KeyValueStore;
use Compadres\Commerce\AgeVerification\VerificationResult;
use Compadres\Commerce\AgeVerification\VerificationStatus;
use Compadres\Commerce\AgeVerification\WooCommerceVerificationStore;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class VerificationStoreTest extends TestCase {

	public function testSessionStorageContainsOnlyApprovedActiveCheckoutFields(): void {
		$session = new class() implements KeyValueStore {
			/** @var array<string, mixed> */
			public array $values = array();

			public function get( string $key, mixed $fallback = null ): mixed {
				return $this->values[ $key ] ?? $fallback;
			}

			public function set( string $key, mixed $value ): void {
				$this->values[ $key ] = $value;
			}
		};
		$store   = new WooCommerceVerificationStore( $session );
		$result  = new VerificationResult(
			'agechecker',
			'ac-123',
			VerificationStatus::PASSED,
			'match',
			new DateTimeImmutable( '2026-07-19 12:00:00+00:00' ),
			new DateTimeImmutable( '2026-08-19 12:00:00+00:00' ),
			'approved',
			42,
			new DateTimeImmutable( '2026-07-19 12:00:00+00:00' )
		);

		$store->save( $result );

		self::assertSame(
			array( 'provider', 'reference', 'status', 'reason_code', 'verified_at', 'expires_at' ),
			array_keys( $session->values['compadres_age_verification'] )
		);
		self::assertSame( 'ac-123', $store->current()?->reference() );
		self::assertArrayNotHasKey( 'date_of_birth', $session->values['compadres_age_verification'] );
		self::assertArrayNotHasKey( 'reviewer_id', $session->values['compadres_age_verification'] );
	}
}
