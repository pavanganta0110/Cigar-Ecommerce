<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\AgeVerification\ProviderConfiguration;
use Compadres\Commerce\AgeVerification\ProviderFactory;
use Compadres\Commerce\AgeVerification\SelfAttestationProvider;
use Compadres\Commerce\AgeVerification\UnavailableProvider;
use Compadres\Commerce\Infrastructure\Environment;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProviderFactoryTest extends TestCase {

	public function testUnconfiguredProviderFailsClosed(): void {
		$config   = ProviderConfiguration::fromArray( array( 'enabled' => true ) );
		$provider = ProviderFactory::create(
			$config,
			Environment::fromString( 'staging' ),
			null,
			static fn (): DateTimeImmutable => new DateTimeImmutable( '2026-07-19 12:00:00+00:00' )
		);

		self::assertInstanceOf( UnavailableProvider::class, $provider );
		self::assertSame( 'not_configured', $config->integrationStatus() );
	}

	public function testMockProviderIsUnavailableInProduction(): void {
		$config   = ProviderConfiguration::fromArray(
			array(
				'enabled'  => true,
				'provider' => 'mock',
			)
		);
		$provider = ProviderFactory::create(
			$config,
			Environment::fromString( 'production' ),
			null,
			static fn (): DateTimeImmutable => new DateTimeImmutable( '2026-07-19 12:00:00+00:00' )
		);

		self::assertInstanceOf( UnavailableProvider::class, $provider );
		self::assertSame( 'mock_blocked_in_production', $provider->verify( \Compadres\Commerce\AgeVerification\VerificationRequest::fromCheckout( array(), false ) )->toArray()['reason_code'] );
	}

	public function testSelfAttestationProviderWorksLocallyWithoutProductionApproval(): void {
		$config   = ProviderConfiguration::fromArray(
			array(
				'enabled'  => true,
				'provider' => 'self_attestation',
			)
		);
		$provider = ProviderFactory::create(
			$config,
			Environment::fromString( 'local' ),
			null,
			static fn (): DateTimeImmutable => new DateTimeImmutable( '2026-07-19 12:00:00+00:00' )
		);

		self::assertInstanceOf( SelfAttestationProvider::class, $provider );
	}

	public function testSelfAttestationProviderIsUnavailableInProductionWithoutApproval(): void {
		$config   = ProviderConfiguration::fromArray(
			array(
				'enabled'  => true,
				'provider' => 'self_attestation',
			)
		);
		$provider = ProviderFactory::create(
			$config,
			Environment::fromString( 'production' ),
			null,
			static fn (): DateTimeImmutable => new DateTimeImmutable( '2026-07-19 12:00:00+00:00' )
		);

		self::assertInstanceOf( UnavailableProvider::class, $provider );
	}

	public function testSelfAttestationProviderIsAvailableInProductionWithApproval(): void {
		$config   = ProviderConfiguration::fromArray(
			array(
				'enabled'             => true,
				'provider'            => 'self_attestation',
				'production_approved' => true,
			)
		);
		$provider = ProviderFactory::create(
			$config,
			Environment::fromString( 'production' ),
			null,
			static fn (): DateTimeImmutable => new DateTimeImmutable( '2026-07-19 12:00:00+00:00' )
		);

		self::assertInstanceOf( SelfAttestationProvider::class, $provider );
	}
}
