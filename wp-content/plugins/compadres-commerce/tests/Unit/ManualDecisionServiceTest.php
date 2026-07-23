<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\AgeVerification\ManualDecisionService;
use Compadres\Commerce\AgeVerification\OrderMetaStore;
use Compadres\Commerce\AgeVerification\VerificationStatus;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class ManualDecisionServiceTest extends TestCase {

	public function testManualReviewCanBeApprovedOnceWithReviewerAndTimestamp(): void {
		$order   = $this->manualReviewOrder();
		$service = new ManualDecisionService();

		$service->decide( $order, 'approved', 42, new DateTimeImmutable( '2026-07-19 13:00:00+00:00' ) );

		self::assertSame( VerificationStatus::PASSED, $order->values['_compadres_age_status'] );
		self::assertSame( 'approved', $order->values['_compadres_age_manual_action'] );
		self::assertSame( 42, $order->values['_compadres_age_reviewer_id'] );
		self::assertSame( '2026-07-19T13:00:00+00:00', $order->values['_compadres_age_manual_decided_at'] );
		self::assertSame( '2026-07-20T13:00:00+00:00', $order->values['_compadres_age_expires_at'] );

		$this->expectException( DomainException::class );
		$service->decide( $order, 'rejected', 7, new DateTimeImmutable( '2026-07-19 14:00:00+00:00' ) );
	}

	public function testManualReviewCanBeRejected(): void {
		$order = $this->manualReviewOrder();

		( new ManualDecisionService() )->decide( $order, 'rejected', 7, new DateTimeImmutable( '2026-07-19 13:00:00+00:00' ) );

		self::assertSame( VerificationStatus::FAILED, $order->values['_compadres_age_status'] );
		self::assertSame( 'manual_rejected', $order->values['_compadres_age_reason_code'] );
	}

	private function manualReviewOrder(): OrderMetaStore {
		return new class() implements OrderMetaStore {
			/** @var array<string, mixed> */
			public array $values = array(
				'_compadres_age_provider'          => 'agechecker',
				'_compadres_age_reference'         => 'ac-manual',
				'_compadres_age_status'            => 'manual_review',
				'_compadres_age_verified_at'       => '2026-07-19T12:00:00+00:00',
				'_compadres_age_expires_at'        => '',
				'_compadres_age_reason_code'       => 'document_required',
				'_compadres_age_manual_action'     => '',
				'_compadres_age_reviewer_id'       => 0,
				'_compadres_age_manual_decided_at' => '',
			);

			public function get( string $key, mixed $fallback = null ): mixed {
				return $this->values[ $key ] ?? $fallback;
			}

			public function set( string $key, mixed $value ): void {
				$this->values[ $key ] = $value;
			}
		};
	}
}
