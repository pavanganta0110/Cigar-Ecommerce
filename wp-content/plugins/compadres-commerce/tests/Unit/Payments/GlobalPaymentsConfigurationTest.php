<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Payments;

use Compadres\Commerce\Infrastructure\Environment;
use Compadres\Commerce\Payments\GlobalPaymentsConfiguration;
use PHPUnit\Framework\TestCase;

final class GlobalPaymentsConfigurationTest extends TestCase {

	public function test_complete_production_configuration_is_enabled(): void {
		$configuration = new GlobalPaymentsConfiguration(
			Environment::fromString( 'production' ),
			true,
			'sandbox-app-id',
			'sandbox-app-key',
			'live-app-id',
			'live-app-key',
			'https://compadrescigars.example/contact'
		);

		self::assertTrue( $configuration->isConfigured() );
		self::assertTrue( $configuration->hasLiveCredentials() );
	}

	public function test_missing_approval_fails_closed_in_production(): void {
		$configuration = new GlobalPaymentsConfiguration(
			Environment::fromString( 'production' ),
			false,
			'sandbox-app-id',
			'sandbox-app-key',
			'live-app-id',
			'live-app-key',
			'https://compadrescigars.example/contact'
		);

		self::assertFalse( $configuration->isConfigured() );
	}

	public function test_missing_live_credentials_fails_closed_in_production(): void {
		$configuration = new GlobalPaymentsConfiguration(
			Environment::fromString( 'production' ),
			true,
			'sandbox-app-id',
			'sandbox-app-key',
			'',
			'',
			'https://compadrescigars.example/contact'
		);

		self::assertFalse( $configuration->isConfigured() );
	}

	public function test_missing_merchant_contact_url_fails_closed(): void {
		$configuration = new GlobalPaymentsConfiguration(
			Environment::fromString( 'local' ),
			false,
			'sandbox-app-id',
			'sandbox-app-key',
			'',
			'',
			''
		);

		self::assertFalse( $configuration->isConfigured() );
	}

	public function test_sandbox_configuration_does_not_require_production_approval(): void {
		$configuration = new GlobalPaymentsConfiguration(
			Environment::fromString( 'local' ),
			false,
			'sandbox-app-id',
			'sandbox-app-key',
			'',
			'',
			'https://compadrescigars.example/contact'
		);

		self::assertTrue( $configuration->isConfigured() );
		self::assertFalse( $configuration->hasLiveCredentials() );
	}

	public function test_production_still_requires_sandbox_style_credential_format(): void {
		$configuration = new GlobalPaymentsConfiguration(
			Environment::fromString( 'production' ),
			true,
			'sandbox-app-id',
			'sandbox-app-key',
			'invalid id with spaces',
			'live-app-key',
			'https://compadrescigars.example/contact'
		);

		self::assertFalse( $configuration->hasLiveCredentials() );
		self::assertFalse( $configuration->isConfigured() );
	}
}
