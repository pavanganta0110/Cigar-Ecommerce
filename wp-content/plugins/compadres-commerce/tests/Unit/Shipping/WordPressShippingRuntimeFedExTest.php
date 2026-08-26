<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Infrastructure\Environment;
use Compadres\Commerce\Shipping\FedExConfiguration;
use Compadres\Commerce\Shipping\FedExShippingProvider;
use Compadres\Commerce\Shipping\NoShippingProvider;
use Compadres\Commerce\Shipping\WordPressShippingRuntime;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

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

	public function test_default_runtimes_share_one_request_scoped_fedex_provider(): void {
		$values   = array(
			'APP_ENV'                                => 'production',
			'COMPADRES_SHIPPING_PROVIDER'            => 'fedex',
			'COMPADRES_SHIPPING_PRODUCTION_APPROVED' => 'true',
			'COMPADRES_FEDEX_API_BASE_URL'           => 'https://apis.fedex.com',
			'COMPADRES_FEDEX_CLIENT_ID'              => 'client-id',
			'COMPADRES_FEDEX_CLIENT_SECRET'          => 'client-secret',
			'COMPADRES_FEDEX_ACCOUNT_NUMBER'         => '123456789',
			'COMPADRES_FEDEX_ORIGIN_COUNTRY'         => 'US',
			'COMPADRES_FEDEX_ORIGIN_STATE'           => 'MO',
			'COMPADRES_FEDEX_ORIGIN_POSTAL_CODE'     => '63101',
		);
		$original = array();
		foreach ( $values as $name => $value ) {
			$original[ $name ] = getenv( $name );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Isolated environment-backed configuration test.
			putenv( $name . '=' . $value );
		}

		try {
			$first  = new WordPressShippingRuntime();
			$second = new WordPressShippingRuntime();
			self::assertSame( $first->provider(), $second->provider() );
		} finally {
			foreach ( $original as $name => $value ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Restores the isolated test environment.
				putenv( false === $value ? $name : $name . '=' . $value );
			}
			$property = new ReflectionProperty( WordPressShippingRuntime::class, 'request_provider' );
			$property->setValue( null, null );
		}
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
