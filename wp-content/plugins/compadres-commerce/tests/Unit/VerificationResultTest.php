<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\AgeVerification\VerificationResult;
use Compadres\Commerce\AgeVerification\VerificationStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class VerificationResultTest extends TestCase {

	public function testResultExposesOnlyApprovedMinimalVerificationData(): void {
		$result = new VerificationResult(
			'agechecker',
			'provider-reference-123',
			VerificationStatus::PASSED,
			'match',
			new DateTimeImmutable( '2026-07-19 12:00:00+00:00' ),
			new DateTimeImmutable( '2026-08-19 12:00:00+00:00' )
		);

		self::assertSame(
			array(
				'provider'          => 'agechecker',
				'reference'         => 'provider-reference-123',
				'status'            => 'passed',
				'reason_code'       => 'match',
				'verified_at'       => '2026-07-19T12:00:00+00:00',
				'expires_at'        => '2026-08-19T12:00:00+00:00',
				'manual_action'     => '',
				'reviewer_id'       => 0,
				'manual_decided_at' => '',
			),
			$result->toArray()
		);
		self::assertTrue( $result->allowsCheckoutAt( new DateTimeImmutable( '2026-07-20 12:00:00+00:00' ) ) );
		self::assertFalse( $result->allowsCheckoutAt( new DateTimeImmutable( '2026-08-20 12:00:00+00:00' ) ) );
	}

	public function testAuthorizedManualDecisionRecordsOnlyDecisionAndReviewer(): void {
		$pending  = new VerificationResult(
			'agechecker',
			'manual-123',
			VerificationStatus::MANUAL_REVIEW,
			'document_required',
			new DateTimeImmutable( '2026-07-19 12:00:00+00:00' )
		);
		$approved = $pending->withManualDecision( 'approved', 42, new DateTimeImmutable( '2026-07-19 13:00:00+00:00' ) );

		self::assertSame( VerificationStatus::PASSED, $approved->toArray()['status'] );
		self::assertSame( 'approved', $approved->toArray()['manual_action'] );
		self::assertSame( 42, $approved->toArray()['reviewer_id'] );
		self::assertSame( 'manual_approved', $approved->toArray()['reason_code'] );
		self::assertSame( '2026-07-19T13:00:00+00:00', $approved->toArray()['manual_decided_at'] );
	}
}
