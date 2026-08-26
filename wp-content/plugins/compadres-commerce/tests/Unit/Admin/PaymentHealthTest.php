<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit\Admin;

use Compadres\Commerce\Admin\PaymentHealth;
use Compadres\Commerce\Infrastructure\Environment;
use Compadres\Commerce\Payments\GlobalPaymentsConfiguration;
use PHPUnit\Framework\TestCase;

final class PaymentHealthTest extends TestCase {

	public function test_disabled_when_merchant_contact_url_missing(): void {
		$status = PaymentHealth::describe(
			new GlobalPaymentsConfiguration( Environment::fromString( 'local' ), false, '', '', '', '', '' )
		);

		self::assertSame( 'disabled', $status->state() );
		self::assertFalse( $status->isProductionReady() );
	}

	public function test_sandbox_when_sandbox_credentials_present_outside_production(): void {
		$status = PaymentHealth::describe(
			new GlobalPaymentsConfiguration(
				Environment::fromString( 'local' ),
				false,
				'sandbox-app-id',
				'sandbox-app-key',
				'',
				'',
				'https://compadrescigars.example/contact'
			)
		);

		self::assertSame( 'sandbox', $status->state() );
		self::assertFalse( $status->isProductionReady() );
	}

	public function test_disabled_in_production_without_approval(): void {
		$status = PaymentHealth::describe(
			new GlobalPaymentsConfiguration(
				Environment::fromString( 'production' ),
				false,
				'sandbox-app-id',
				'sandbox-app-key',
				'live-app-id',
				'live-app-key',
				'https://compadrescigars.example/contact'
			)
		);

		self::assertSame( 'disabled', $status->state() );
	}

	public function test_connected_and_production_ready_when_approved(): void {
		$status = PaymentHealth::describe(
			new GlobalPaymentsConfiguration(
				Environment::fromString( 'production' ),
				true,
				'sandbox-app-id',
				'sandbox-app-key',
				'live-app-id',
				'live-app-key',
				'https://compadrescigars.example/contact'
			)
		);

		self::assertSame( 'connected', $status->state() );
		self::assertTrue( $status->isProductionReady() );
	}
}
