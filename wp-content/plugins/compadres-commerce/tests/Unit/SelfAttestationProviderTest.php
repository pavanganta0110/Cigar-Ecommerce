<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\AgeVerification\SelfAttestationProvider;
use Compadres\Commerce\AgeVerification\VerificationRequest;
use Compadres\Commerce\AgeVerification\VerificationStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SelfAttestationProviderTest extends TestCase {

	public function test_it_requires_attestation(): void {
		$provider = new SelfAttestationProvider(
			static fn (): DateTimeImmutable => new DateTimeImmutable( '2026-07-19 12:00:00+00:00' ),
			static fn (): string => 'test-ref'
		);

		self::assertTrue( $provider->requiresAttestation() );
	}

	public function test_reaching_verify_always_passes_because_the_checkbox_was_already_validated(): void {
		$now      = new DateTimeImmutable( '2026-07-19 12:00:00+00:00' );
		$provider = new SelfAttestationProvider(
			static fn (): DateTimeImmutable => $now,
			static fn (): string => 'test-ref'
		);

		$result = $provider->verify( VerificationRequest::fromCheckout( array( 'compadres_age_attestation' => '1' ), false, true ) );

		self::assertSame( VerificationStatus::PASSED, $result->status() );
		self::assertSame( 'self_attestation', $result->provider() );
		self::assertSame( 'self-attested-test-ref', $result->reference() );
		self::assertTrue( $result->allowsCheckoutAt( $now ) );
		self::assertFalse( $result->allowsCheckoutAt( $now->modify( '+2 days' ) ) );
	}

	public function test_it_has_no_hosted_verification_url(): void {
		$provider = new SelfAttestationProvider(
			static fn (): DateTimeImmutable => new DateTimeImmutable( '2026-07-19 12:00:00+00:00' ),
			static fn (): string => 'test-ref'
		);

		self::assertNull( $provider->hostedVerificationUrl( 'self-attested-test-ref', 'https://store.example.test/checkout/' ) );
	}
}
