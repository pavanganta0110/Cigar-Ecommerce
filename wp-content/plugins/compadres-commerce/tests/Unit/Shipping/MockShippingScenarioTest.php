<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Shipping\MockShippingProvider;
use Compadres\Commerce\Shipping\MockShippingScenario;
use PHPUnit\Framework\TestCase;

final class MockShippingScenarioTest extends TestCase {

	public function test_eligible_scenario_exposes_eligible_service(): void {
		$scenario = MockShippingScenario::fromString( MockShippingScenario::ELIGIBLE );
		$this->assertTrue( $scenario->isProviderAvailable() );
		$this->assertSame( array( MockShippingScenario::SERVICE_ELIGIBLE ), $scenario->eligibleServiceIds() );
		$this->assertTrue( $scenario->supportsAdultSignature( MockShippingScenario::SERVICE_ELIGIBLE ) );
		$provider = new MockShippingProvider( $scenario );
		$this->assertTrue( $provider->isConfigured() );
		$this->assertSame( 'mock-', substr( (string) $provider->serviceReference( MockShippingScenario::SERVICE_ELIGIBLE ), 0, 5 ) );
	}

	public function test_ineligible_scenario_offers_only_unsupported_service(): void {
		$scenario = MockShippingScenario::fromString( MockShippingScenario::INELIGIBLE );
		$this->assertSame( array(), $scenario->eligibleServiceIds() );
		$this->assertFalse( $scenario->supportsAdultSignature( MockShippingScenario::SERVICE_INELIGIBLE ) );
		$provider = new MockShippingProvider( $scenario );
		$this->assertTrue( $provider->isConfigured() );
		$this->assertFalse( $provider->supportsAdultSignature( MockShippingScenario::SERVICE_INELIGIBLE ) );
	}

	public function test_unavailable_scenario_is_not_configured(): void {
		$scenario = MockShippingScenario::fromString( MockShippingScenario::UNAVAILABLE );
		$this->assertFalse( $scenario->isProviderAvailable() );
		$provider = new MockShippingProvider( $scenario );
		$this->assertFalse( $provider->isConfigured() );
	}

	public function test_none_scenario_has_no_eligible_service(): void {
		$scenario = MockShippingScenario::fromString( MockShippingScenario::NONE );
		$this->assertTrue( $scenario->isProviderAvailable() );
		$this->assertSame( array(), $scenario->eligibleServiceIds() );
	}

	public function test_unknown_scenario_fails_closed_as_unavailable(): void {
		$scenario = MockShippingScenario::fromString( 'bogus' );
		$this->assertSame( MockShippingScenario::UNAVAILABLE, $scenario->value() );
	}

	public function test_rates_are_deterministic(): void {
		$scenario = MockShippingScenario::fromString( MockShippingScenario::ELIGIBLE );
		$rates    = $scenario->rates();
		$this->assertCount( 1, $rates );
		$this->assertSame( MockShippingScenario::SERVICE_ELIGIBLE, $rates[0]['id'] );
		$this->assertTrue( (bool) $rates[0]['supports_as'] );
	}
}
