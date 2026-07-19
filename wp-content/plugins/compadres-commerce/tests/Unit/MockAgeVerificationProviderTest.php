<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\AgeVerification\MockAgeVerificationProvider;
use Compadres\Commerce\AgeVerification\VerificationRequest;
use Compadres\Commerce\AgeVerification\VerificationStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MockAgeVerificationProviderTest extends TestCase {

	public function testDevelopmentFixtureCanExercisePendingHostedFlowAndRefreshToPass(): void {
		$provider = new MockAgeVerificationProvider(
			false,
			static fn (): DateTimeImmutable => new DateTimeImmutable( '2026-07-19 12:00:00+00:00' ),
			VerificationStatus::PENDING,
			VerificationStatus::PASSED,
			'https://verify.example.test/{reference}?return_url={return_url}'
		);

		$pending = $provider->verify( VerificationRequest::fromCheckout( array() ) );
		$passed  = $provider->refresh( $pending->reference() );

		self::assertSame( VerificationStatus::PENDING, $pending->status() );
		self::assertSame( VerificationStatus::PASSED, $passed->status() );
		self::assertSame(
			'https://verify.example.test/mock-local?return_url=https%3A%2F%2Fstore.example.test%2Fcheckout%2F',
			$provider->hostedVerificationUrl( 'mock-local', 'https://store.example.test/checkout/' )
		);
	}
}
