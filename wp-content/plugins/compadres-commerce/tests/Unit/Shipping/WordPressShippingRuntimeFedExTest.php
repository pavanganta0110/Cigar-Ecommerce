<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Infrastructure\Environment;
use Compadres\Commerce\Shipping\FedExConfiguration;
use Compadres\Commerce\Shipping\FedExShippingProvider;
use Compadres\Commerce\Shipping\NoShippingProvider;
use Compadres\Commerce\Shipping\WordPressShippingRuntime;
use PHPUnit\Framework\TestCase;

final class WordPressShippingRuntimeFedExTest extends TestCase {

	public function test_production_selects_configured_fedex_provider(): void {
		$environment = Environment::fromString( 'production' );
		$runtime     = new WordPressShippingRuntime(
			$environment,
			$this->configuration( $environment, true ),
			new FakeFedExTransport( array() ),
			'fedex'
		);

		self::assertInstanceOf( FedExShippingProvider::class, $runtime->provider() );
		self::assertTrue( $runtime->fedExMethodAllowed() );
	}

	public function test_unselected_or_unapproved_fedex_fails_closed(): void {
		$environment = Environment::fromString( 'production' );
		$unselected  = new WordPressShippingRuntime(
			$environment,
			$this->configuration( $environment, true ),
			new FakeFedExTransport( array() ),
			'none'
		);
		$unapproved  = new WordPressShippingRuntime(
			$environment,
			$this->configuration( $environment, false ),
			new FakeFedExTransport( array() ),
			'fedex'
		);

		self::assertInstanceOf( NoShippingProvider::class, $unselected->provider() );
		self::assertInstanceOf( FedExShippingProvider::class, $unapproved->provider() );
		self::assertFalse( $unapproved->provider()->isConfigured() );
		self::assertFalse( $unapproved->fedExMethodAllowed() );
	}

	private function configuration( Environment $environment, bool $approved ): FedExConfiguration {
		return new FedExConfiguration(
			$environment,
			$approved,
			'https://apis.fedex.com',
			'client-id',
			'client-secret',
			'123456789',
			'US',
			'MO',
			'63101'
		);
	}
}
