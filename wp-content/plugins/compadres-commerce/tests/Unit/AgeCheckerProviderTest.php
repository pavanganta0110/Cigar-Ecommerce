<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\AgeVerification\AgeCheckerProvider;
use Compadres\Commerce\AgeVerification\AgeCheckerTransport;
use Compadres\Commerce\AgeVerification\VerificationRequest;
use Compadres\Commerce\AgeVerification\VerificationStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AgeCheckerProviderTest extends TestCase {

	public function testProviderFailureNormalizesToUnavailableWithoutSensitiveFailureDetails(): void {
		$transport = new class() implements AgeCheckerTransport {
			/** @param array<string, string> $request
			 *  @return array<string, string>
			 */
			public function verify( array $request ): array {
				throw new \RuntimeException( 'Authorization: Bearer private-token' );
			}
		};
		$provider  = new AgeCheckerProvider(
			$transport,
			'',
			static fn (): DateTimeImmutable => new DateTimeImmutable( '2026-07-19 12:00:00+00:00' )
		);

		$result = $provider->verify( VerificationRequest::fromCheckout( array() ) )->toArray();

		self::assertSame( VerificationStatus::UNAVAILABLE, $result['status'] );
		self::assertSame( 'provider_unavailable', $result['reason_code'] );
		self::assertNotContains( 'private-token', $result );
	}

	public function testProviderNormalizesResponseAndBuildsConfiguredHostedWorkflowUrl(): void {
		$transport = new class() implements AgeCheckerTransport {
			/** @var array<string, string> */
			public array $request = array();

			/** @param array<string, string> $request
			 *  @return array<string, string>
			 */
			public function verify( array $request ): array {
				$this->request = $request;
				return array(
					'reference'   => 'ac-manual-123',
					'status'      => 'manual_review',
					'reason_code' => 'document_required',
					'verified_at' => '2026-07-19T12:00:00+00:00',
					'expires_at'  => '',
				);
			}
		};
		$provider  = new AgeCheckerProvider(
			$transport,
			'https://sandbox.example.test/verify/{reference}?return_url={return_url}',
			static fn (): DateTimeImmutable => new DateTimeImmutable( '2026-07-19 12:00:00+00:00' )
		);
		$request   = VerificationRequest::fromCheckout(
			array(
				'billing_first_name'      => 'Ada',
				'compadres_date_of_birth' => '1990-01-02',
			),
			true
		);

		$result = $provider->verify( $request );

		self::assertSame( VerificationStatus::MANUAL_REVIEW, $result->toArray()['status'] );
		self::assertSame( 'ac-manual-123', $result->toArray()['reference'] );
		self::assertSame( 'Ada', $transport->request['first_name'] );
		self::assertSame(
			'https://sandbox.example.test/verify/ac-manual-123?return_url=https%3A%2F%2Fstore.example.test%2Fcheckout%2F',
			$provider->hostedVerificationUrl( 'ac-manual-123', 'https://store.example.test/checkout/' )
		);
	}

	public function testMalformedPassedResponseFailsClosed(): void {
		$transport = new class() implements AgeCheckerTransport {
			public function verify( array $request ): array {
				return array( 'status' => VerificationStatus::PASSED );
			}
		};
		$provider  = new AgeCheckerProvider( $transport, '', static fn (): DateTimeImmutable => new DateTimeImmutable( '2026-07-19 12:00:00+00:00' ) );

		self::assertSame( VerificationStatus::UNAVAILABLE, $provider->verify( VerificationRequest::fromCheckout( array() ) )->status() );
	}
}
