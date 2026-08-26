<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Infrastructure\Environment;
use Compadres\Commerce\Shipping\FedExConfiguration;
use PHPUnit\Framework\TestCase;

final class FedExConfigurationTest extends TestCase {

	public function test_complete_production_configuration_is_enabled(): void {
		$configuration = new FedExConfiguration(
			Environment::fromString( 'production' ),
			true,
			'https://apis.fedex.com',
			'client-id',
			'client-secret',
			'123456789',
			'US',
			'MO',
			'63101'
		);

		self::assertTrue( $configuration->isConfigured() );
		self::assertSame( 'https://apis.fedex.com', $configuration->apiBaseUrl() );
		self::assertSame( '123456789', $configuration->accountNumber() );
	}

	public function test_missing_credentials_or_approval_fails_closed(): void {
		$missing_secret = new FedExConfiguration(
			Environment::fromString( 'production' ),
			true,
			'https://apis.fedex.com',
			'client-id',
			'',
			'123456789',
			'US',
			'MO',
			'63101'
		);
		$not_approved   = new FedExConfiguration(
			Environment::fromString( 'production' ),
			false,
			'https://apis.fedex.com',
			'client-id',
			'client-secret',
			'123456789',
			'US',
			'MO',
			'63101'
		);

		self::assertFalse( $missing_secret->isConfigured() );
		self::assertFalse( $not_approved->isConfigured() );
	}

	public function test_production_rejects_sandbox_and_unapproved_origins(): void {
		$sandbox  = new FedExConfiguration(
			Environment::fromString( 'production' ),
			true,
			'https://apis-sandbox.fedex.com',
			'client-id',
			'client-secret',
			'123456789',
			'US',
			'MO',
			'63101'
		);
		$attacker = new FedExConfiguration(
			Environment::fromString( 'staging' ),
			true,
			'https://fedex.example.test',
			'client-id',
			'client-secret',
			'123456789',
			'US',
			'MO',
			'63101'
		);

		self::assertFalse( $sandbox->isConfigured() );
		self::assertFalse( $attacker->isConfigured() );
	}

	public function test_sandbox_configuration_does_not_require_production_approval(): void {
		$configuration = new FedExConfiguration(
			Environment::fromString( 'local' ),
			false,
			'https://apis-sandbox.fedex.com',
			'client-id',
			'client-secret',
			'123456789',
			'US',
			'MO',
			'63101'
		);

		self::assertTrue( $configuration->isConfigured() );
	}
}
