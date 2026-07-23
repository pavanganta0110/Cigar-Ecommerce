<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\AgeVerification\AgeVerificationProvider;
use Compadres\Commerce\AgeVerification\AgeVerificationService;
use Compadres\Commerce\AgeVerification\CheckoutGuard;
use Compadres\Commerce\AgeVerification\CheckoutWorkflow;
use Compadres\Commerce\AgeVerification\VerificationRequest;
use Compadres\Commerce\AgeVerification\VerificationResult;
use Compadres\Commerce\AgeVerification\VerificationStatus;
use Compadres\Commerce\AgeVerification\VerificationStore;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CheckoutWorkflowTest extends TestCase {

	public function testForgedBrowserStatusAndEntryCookieCannotBypassServerVerification(): void {
		$provider = new class() implements AgeVerificationProvider {
			public function name(): string {
				return 'agechecker';
			}
			public function hostedVerificationUrl( string $reference, string $return_url ): ?string {
				return null;
			}
			public function verify( VerificationRequest $request ): VerificationResult {
				return new VerificationResult( 'agechecker', 'ac-failed', VerificationStatus::FAILED, 'no_match', new DateTimeImmutable( '2026-07-19 12:00:00+00:00' ) );
			}
		};
		$store    = new class() implements VerificationStore {
			private ?VerificationResult $current = null;
			public function current(): ?VerificationResult {
				return $this->current;
			}
			public function save( VerificationResult $result ): void {
				$this->current = $result;
			}
		};
		$now      = static fn (): DateTimeImmutable => new DateTimeImmutable( '2026-07-19 12:01:00+00:00' );
		$workflow = new CheckoutWorkflow( new AgeVerificationService( $provider, $store, $now ), new CheckoutGuard( $provider, $now ), false );

		$decision = $workflow->verify(
			array(
				'compadres_age_verification_status' => 'passed',
				'compadres_age_confirmed'           => 'valid-entry-gate-cookie',
			),
			'https://store.example.test/checkout/'
		);

		self::assertFalse( $decision->allowed() );
		self::assertSame( VerificationStatus::FAILED, $store->current()?->status() );
	}
}
