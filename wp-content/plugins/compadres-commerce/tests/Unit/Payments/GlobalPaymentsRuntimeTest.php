<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Payments;

use Compadres\Commerce\Infrastructure\Environment;
use Compadres\Commerce\Payments\GlobalPaymentsConfiguration;
use Compadres\Commerce\Payments\GlobalPaymentsRuntime;
use PHPUnit\Framework\TestCase;

final class GlobalPaymentsRuntimeTest extends TestCase {

	public function test_unsupported_gateway_variants_are_always_hidden(): void {
		$runtime  = new GlobalPaymentsRuntime( $this->configuredSandbox() );
		$gateways = $runtime->filterAvailableGateways(
			array(
				'globalpayments_gpapi'     => 'unified',
				'globalpayments_heartland' => 'heartland',
				'globalpayments_genius'    => 'genius',
				'globalpayments_transit'   => 'transit',
				'globalpayments_googlepay' => 'googlepay',
				'globalpayments_klarna'    => 'klarna',
				'other_gateway'            => 'untouched',
			)
		);

		self::assertSame(
			array(
				'globalpayments_gpapi' => 'unified',
				'other_gateway'        => 'untouched',
			),
			$gateways
		);
	}

	public function test_unified_payments_is_hidden_when_not_configured(): void {
		$runtime  = new GlobalPaymentsRuntime( $this->unconfigured() );
		$gateways = $runtime->filterAvailableGateways(
			array(
				'globalpayments_gpapi' => 'unified',
				'other_gateway'        => 'untouched',
			)
		);

		self::assertSame( array( 'other_gateway' => 'untouched' ), $gateways );
		self::assertFalse( $runtime->isConfigured() );
	}

	public function test_unified_payments_stays_available_when_sandbox_configured(): void {
		$runtime  = new GlobalPaymentsRuntime( $this->configuredSandbox() );
		$gateways = $runtime->filterAvailableGateways( array( 'globalpayments_gpapi' => 'unified' ) );

		self::assertSame( array( 'globalpayments_gpapi' => 'unified' ), $gateways );
		self::assertTrue( $runtime->isConfigured() );
	}

	private function configuredSandbox(): GlobalPaymentsConfiguration {
		return new GlobalPaymentsConfiguration(
			Environment::fromString( 'local' ),
			false,
			'sandbox-app-id',
			'sandbox-app-key',
			'',
			'',
			'https://compadrescigars.example/contact'
		);
	}

	private function unconfigured(): GlobalPaymentsConfiguration {
		return new GlobalPaymentsConfiguration(
			Environment::fromString( 'local' ),
			false,
			'',
			'',
			'',
			'',
			''
		);
	}
}
