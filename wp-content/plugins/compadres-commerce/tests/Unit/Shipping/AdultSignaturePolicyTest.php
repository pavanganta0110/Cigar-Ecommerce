<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Shipping\AdultSignaturePolicy;
use Compadres\Commerce\Shipping\MockShippingProvider;
use Compadres\Commerce\Shipping\MockShippingScenario;
use Compadres\Commerce\Shipping\NoShippingProvider;
use Compadres\Commerce\Shipping\ShippingContext;
use Compadres\Commerce\Shipping\ShippingEligibilityResult;
use PHPUnit\Framework\TestCase;

final class AdultSignaturePolicyTest extends TestCase {

	private function context( string $service = '', array $products = array( 1 ) ): ShippingContext {
		return new ShippingContext( 'US', 'MO', '63101', $service, $products );
	}

	public function test_cigar_cart_always_requires_adult_signature(): void {
		$policy = new AdultSignaturePolicy();
		$this->assertTrue( $policy->requiresAdultSignature( $this->context() ) );
		$this->assertFalse( $policy->requiresAdultSignature( new ShippingContext( 'US', 'MO', '63101', '', array() ) ) );
	}

	public function test_eligible_service_allows_checkout(): void {
		$policy   = new AdultSignaturePolicy();
		$provider = new MockShippingProvider( MockShippingScenario::fromString( MockShippingScenario::ELIGIBLE ) );
		$result   = $policy->evaluate( $this->context( MockShippingScenario::SERVICE_ELIGIBLE ), $provider );

		$this->assertTrue( $result->eligible() );
		$this->assertTrue( $result->requiresAdultSignature() );
		$this->assertSame( ShippingEligibilityResult::REASON_OK, $result->reason() );
	}

	public function test_service_without_adult_signature_is_rejected(): void {
		$policy   = new AdultSignaturePolicy();
		$provider = new MockShippingProvider( MockShippingScenario::fromString( MockShippingScenario::INELIGIBLE ) );
		$result   = $policy->evaluate( $this->context( MockShippingScenario::SERVICE_INELIGIBLE ), $provider );

		$this->assertFalse( $result->eligible() );
		$this->assertSame( ShippingEligibilityResult::REASON_SERVICE_UNSUPPORTED, $result->reason() );
		$this->assertStringNotContainsString( 'mock', $result->customerMessage() );
	}

	public function test_provider_unavailable_fails_closed(): void {
		$policy   = new AdultSignaturePolicy();
		$provider = new MockShippingProvider( MockShippingScenario::fromString( MockShippingScenario::UNAVAILABLE ) );
		$result   = $policy->evaluate( $this->context(), $provider );

		$this->assertFalse( $result->eligible() );
		$this->assertFalse( $provider->isConfigured() );
		$this->assertSame( ShippingEligibilityResult::REASON_PROVIDER_UNAVAILABLE, $result->reason() );
	}

	public function test_provider_exception_is_normalized_and_audit_values_are_bounded(): void {
		$result = ( new AdultSignaturePolicy() )->evaluate(
			$this->context( str_repeat( 'attacker-', 30 ) ),
			new ThrowingShippingProvider()
		);

		$this->assertFalse( $result->eligible() );
		$this->assertSame( ShippingEligibilityResult::REASON_PROVIDER_UNAVAILABLE, $result->reason() );
		$this->assertLessThanOrEqual( 64, strlen( $result->auditContext()['provider'] ) );
		$this->assertLessThanOrEqual( 64, strlen( $result->auditContext()['service'] ) );
	}

	public function test_no_eligible_service_fails_closed(): void {
		$policy   = new AdultSignaturePolicy();
		$provider = new MockShippingProvider( MockShippingScenario::fromString( MockShippingScenario::NONE ) );
		$result   = $policy->evaluate( $this->context(), $provider );

		$this->assertFalse( $result->eligible() );
		$this->assertSame( ShippingEligibilityResult::REASON_NO_ELIGIBLE_SERVICE, $result->reason() );
	}

	public function test_missing_service_selection_fails_closed(): void {
		$policy   = new AdultSignaturePolicy();
		$provider = new MockShippingProvider( MockShippingScenario::fromString( MockShippingScenario::ELIGIBLE ) );
		$result   = $policy->evaluate( $this->context( '' ), $provider );

		$this->assertFalse( $result->eligible() );
		$this->assertSame( ShippingEligibilityResult::REASON_NO_SERVICE_SELECTED, $result->reason() );
	}

	public function test_no_provider_fails_closed(): void {
		$policy   = new AdultSignaturePolicy();
		$provider = new NoShippingProvider();
		$result   = $policy->evaluate( $this->context(), $provider );

		$this->assertFalse( $result->eligible() );
		$this->assertFalse( $provider->isConfigured() );
	}

	public function test_customer_message_omits_provider_internals(): void {
		$policy   = new AdultSignaturePolicy();
		$provider = new MockShippingProvider( MockShippingScenario::fromString( MockShippingScenario::INELIGIBLE ) );
		$result   = $policy->evaluate( $this->context( MockShippingScenario::SERVICE_INELIGIBLE ), $provider );

		$message = strtolower( $result->customerMessage() );
		$this->assertStringNotContainsString( 'mock', $message );
		$this->assertStringNotContainsString( 'compadres_mock', $message );
		$this->assertStringNotContainsString( 'exception', $message );
		$this->assertStringNotContainsString( 'provider', $message );
	}

	public function test_audit_context_never_includes_addresses(): void {
		$policy   = new AdultSignaturePolicy();
		$provider = new MockShippingProvider( MockShippingScenario::fromString( MockShippingScenario::ELIGIBLE ) );
		$result   = $policy->evaluate( $this->context( MockShippingScenario::SERVICE_ELIGIBLE ), $provider );

		$context = $result->auditContext();
		$this->assertArrayHasKey( 'provider', $context );
		$this->assertArrayHasKey( 'service', $context );
		$this->assertArrayNotHasKey( 'postcode', $context );
		$this->assertArrayNotHasKey( 'address', $context );
	}
}
