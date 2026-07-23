<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\AgeVerification\AgeVerificationProvider;
use Compadres\Commerce\AgeVerification\AgeVerificationService;
use Compadres\Commerce\AgeVerification\VerificationRequest;
use Compadres\Commerce\AgeVerification\VerificationResult;
use Compadres\Commerce\AgeVerification\VerificationStatus;
use Compadres\Commerce\AgeVerification\VerificationStore;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AgeVerificationServiceTest extends TestCase {

	public function testCurrentUnexpiredPassIsReusedWithoutDuplicateProviderCall(): void {
		$current  = new VerificationResult(
			'agechecker',
			'ac-existing',
			VerificationStatus::PASSED,
			'match',
			new DateTimeImmutable( '2026-07-18 12:00:00+00:00' ),
			new DateTimeImmutable( '2026-08-18 12:00:00+00:00' )
		);
		$provider = new class() implements AgeVerificationProvider {
			public int $calls = 0;

			public function name(): string {
				return 'agechecker';
			}

			public function verify( VerificationRequest $request ): VerificationResult {
				++$this->calls;
				throw new \RuntimeException( 'Provider should not be called.' );
			}

			public function hostedVerificationUrl( string $reference, string $return_url ): ?string {
				return null;
			}
		};
		$store    = new class( $current ) implements VerificationStore {
			public function __construct( private VerificationResult $result ) {}

			public function current(): ?VerificationResult {
				return $this->result;
			}

			public function save( VerificationResult $result ): void {
				throw new \RuntimeException( 'Current verification should not be replaced.' );
			}
		};
		$service  = new AgeVerificationService(
			$provider,
			$store,
			static fn (): DateTimeImmutable => new DateTimeImmutable( '2026-07-19 12:00:00+00:00' )
		);

		$result = $service->verify( VerificationRequest::fromCheckout( array() ) );

		self::assertSame( $current, $result );
		self::assertSame( 0, $provider->calls );
	}

	public function testAutomaticVerificationPersistsOnlyTheNormalizedMinimalResult(): void {
		$provider = new class() implements AgeVerificationProvider {
			public int $calls = 0;
			/** @var array<string, string> */
			public array $received = array();

			public function name(): string {
				return 'agechecker';
			}

			public function verify( VerificationRequest $request ): VerificationResult {
				++$this->calls;
				$this->received = $request->providerData();
				return new VerificationResult(
					'agechecker',
					'ac-123',
					VerificationStatus::PASSED,
					'match',
					new DateTimeImmutable( '2026-07-19 12:00:00+00:00' ),
					new DateTimeImmutable( '2026-07-20 12:00:00+00:00' )
				);
			}

			public function hostedVerificationUrl( string $reference, string $return_url ): ?string {
				return null;
			}
		};
		$store    = new class() implements VerificationStore {
			/** @var array<string, mixed>|null */
			public ?array $saved = null;

			public function current(): ?VerificationResult {
				return null;
			}

			public function save( VerificationResult $result ): void {
				$this->saved = $result->toArray();
			}
		};
		$service  = new AgeVerificationService(
			$provider,
			$store,
			static fn (): DateTimeImmutable => new DateTimeImmutable( '2026-07-19 12:01:00+00:00' )
		);
		$request  = VerificationRequest::fromCheckout(
			array(
				'billing_first_name'      => 'Ada',
				'billing_last_name'       => 'Lovelace',
				'billing_email'           => 'ada@example.test',
				'billing_phone'           => '555-0100',
				'billing_address_1'       => '1 Main Street',
				'billing_address_2'       => '',
				'billing_city'            => 'St. Louis',
				'billing_state'           => 'MO',
				'billing_postcode'        => '63101',
				'billing_country'         => 'US',
				'compadres_date_of_birth' => '1990-01-02',
			),
			true
		);

		$result = $service->verify( $request );

		self::assertTrue( $result->allowsCheckoutAt( new DateTimeImmutable( '2026-07-19 12:01:00+00:00' ) ) );
		self::assertSame( 1, $provider->calls );
		self::assertSame( 'Ada', $provider->received['first_name'] );
		self::assertSame( '1990-01-02', $provider->received['date_of_birth'] );
		self::assertSame( array( 'provider', 'reference', 'status', 'reason_code', 'verified_at', 'expires_at', 'manual_action', 'reviewer_id', 'manual_decided_at' ), array_keys( $store->saved ?? array() ) );
		self::assertArrayNotHasKey( 'date_of_birth', $store->saved ?? array() );
	}
}
