<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\AgeVerification\AgeVerificationProvider;
use Compadres\Commerce\AgeVerification\CheckoutGuard;
use Compadres\Commerce\AgeVerification\VerificationRequest;
use Compadres\Commerce\AgeVerification\VerificationResult;
use Compadres\Commerce\AgeVerification\VerificationStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CheckoutGuardTest extends TestCase {

	public function testCheckoutIsAllowedOnlyForAnUnexpiredPassedResult(): void {
		$provider = new class() implements AgeVerificationProvider {
			public function name(): string {
				return 'agechecker';
			}

			public function verify( VerificationRequest $request ): VerificationResult {
				throw new \RuntimeException( 'Not used.' );
			}

			public function hostedVerificationUrl( string $reference, string $return_url ): ?string {
				return 'https://hosted.example.test/' . rawurlencode( $reference );
			}
		};
		$guard    = new CheckoutGuard(
			$provider,
			static fn (): DateTimeImmutable => new DateTimeImmutable( '2026-07-19 12:00:00+00:00' )
		);
		$passed   = new VerificationResult( 'agechecker', 'pass-1', VerificationStatus::PASSED, 'match', new DateTimeImmutable( '2026-07-19 11:00:00+00:00' ), new DateTimeImmutable( '2026-07-20 11:00:00+00:00' ) );

		self::assertTrue( $guard->decision( $passed, 'https://store.example.test/checkout/' )->allowed() );
		foreach ( array( VerificationStatus::FAILED, VerificationStatus::PENDING, VerificationStatus::MANUAL_REVIEW, VerificationStatus::EXPIRED, VerificationStatus::UNAVAILABLE ) as $status ) {
			$result   = new VerificationResult( 'agechecker', 'ref-' . $status, $status, 'not_passed', new DateTimeImmutable( '2026-07-19 11:00:00+00:00' ) );
			$decision = $guard->decision( $result, 'https://store.example.test/checkout/' );
			self::assertFalse( $decision->allowed(), $status );
			self::assertNotSame( '', $decision->message(), $status );
		}
		self::assertSame(
			'https://hosted.example.test/ref-manual_review',
			$guard->decision(
				new VerificationResult( 'agechecker', 'ref-manual_review', VerificationStatus::MANUAL_REVIEW, 'document_required', new DateTimeImmutable( '2026-07-19 11:00:00+00:00' ) ),
				'https://store.example.test/checkout/'
			)->hostedUrl()
		);
	}
}
