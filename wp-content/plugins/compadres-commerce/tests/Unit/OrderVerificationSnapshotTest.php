<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\AgeVerification\OrderMetaWriter;
use Compadres\Commerce\AgeVerification\OrderVerificationSnapshot;
use Compadres\Commerce\AgeVerification\VerificationResult;
use Compadres\Commerce\AgeVerification\VerificationStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OrderVerificationSnapshotTest extends TestCase {

	public function testOrderSnapshotContainsOnlyApprovedProtectedMetadata(): void {
		$order  = new class() implements OrderMetaWriter {
			/** @var array<string, mixed> */
			public array $metadata = array();

			public function set( string $key, mixed $value ): void {
				$this->metadata[ $key ] = $value;
			}
		};
		$result = new VerificationResult(
			'agechecker',
			'ac-123',
			VerificationStatus::PASSED,
			'manual_approved',
			new DateTimeImmutable( '2026-07-19 12:00:00+00:00' ),
			new DateTimeImmutable( '2026-08-19 12:00:00+00:00' ),
			'approved',
			42,
			new DateTimeImmutable( '2026-07-19 12:00:00+00:00' )
		);

		( new OrderVerificationSnapshot() )->write( $order, $result );

		self::assertSame(
			array(
				'_compadres_age_provider',
				'_compadres_age_reference',
				'_compadres_age_status',
				'_compadres_age_verified_at',
				'_compadres_age_expires_at',
				'_compadres_age_reason_code',
				'_compadres_age_manual_action',
				'_compadres_age_reviewer_id',
				'_compadres_age_manual_decided_at',
			),
			array_keys( $order->metadata )
		);
		self::assertArrayNotHasKey( '_compadres_date_of_birth', $order->metadata );
	}
}
