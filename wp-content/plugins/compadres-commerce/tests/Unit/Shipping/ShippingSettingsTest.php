<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Shipping\MockShippingScenario;
use Compadres\Commerce\Shipping\ShippingSettings;
use PHPUnit\Framework\TestCase;

final class ShippingSettingsTest extends TestCase {

	public function test_defaults_fail_closed_as_unavailable(): void {
		$defaults = ShippingSettings::defaults();
		$this->assertSame( MockShippingScenario::UNAVAILABLE, $defaults['scenario'] );
	}

	public function test_sanitize_accepts_valid_scenarios(): void {
		foreach ( MockShippingScenario::VALID as $scenario ) {
			$sanitized = ShippingSettings::sanitize( array( 'scenario' => $scenario ) );
			$this->assertSame( $scenario, $sanitized['scenario'] );
		}
	}

	public function test_sanitize_rejects_invalid_scenario(): void {
		$sanitized = ShippingSettings::sanitize( array( 'scenario' => 'carrier-approved-real' ) );
		$this->assertSame( MockShippingScenario::UNAVAILABLE, $sanitized['scenario'] );
	}

	public function test_sanitize_drops_unknown_keys(): void {
		$sanitized = ShippingSettings::sanitize(
			array(
				'scenario'         => MockShippingScenario::NONE,
				'approved_carrier' => 'ups',
			)
		);
		$this->assertArrayNotHasKey( 'approved_carrier', $sanitized );
		$this->assertSame( MockShippingScenario::NONE, $sanitized['scenario'] );
	}
}
