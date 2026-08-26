<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit\Admin;

use Compadres\Commerce\Admin\AgeVerificationHealth;
use Compadres\Commerce\AgeVerification\ProviderConfiguration;
use PHPUnit\Framework\TestCase;

final class AgeVerificationHealthTest extends TestCase {

	public function test_disabled_when_not_enabled(): void {
		$status = AgeVerificationHealth::describe( ProviderConfiguration::fromArray( array( 'enabled' => false ) ) );

		self::assertSame( 'disabled', $status->state() );
		self::assertFalse( $status->isProductionReady() );
	}

	public function test_disabled_when_no_provider_selected(): void {
		$status = AgeVerificationHealth::describe( ProviderConfiguration::fromArray( array( 'enabled' => true ) ) );

		self::assertSame( 'disabled', $status->state() );
	}

	public function test_sandbox_for_mock_provider(): void {
		$status = AgeVerificationHealth::describe(
			ProviderConfiguration::fromArray(
				array(
					'enabled'  => true,
					'provider' => 'mock',
				)
			)
		);

		self::assertSame( 'sandbox', $status->state() );
		self::assertFalse( $status->isProductionReady() );
	}

	public function test_connected_but_not_production_ready_without_approval(): void {
		$status = AgeVerificationHealth::describe(
			ProviderConfiguration::fromArray(
				array(
					'enabled'             => true,
					'provider'            => 'agechecker',
					'production_approved' => false,
				)
			)
		);

		self::assertSame( 'connected', $status->state() );
		self::assertFalse( $status->isProductionReady() );
	}

	public function test_production_ready_when_approved(): void {
		$status = AgeVerificationHealth::describe(
			ProviderConfiguration::fromArray(
				array(
					'enabled'             => true,
					'provider'            => 'agechecker',
					'production_approved' => true,
				)
			)
		);

		self::assertSame( 'connected', $status->state() );
		self::assertTrue( $status->isProductionReady() );
	}
}
