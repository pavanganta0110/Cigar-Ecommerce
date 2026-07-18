<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\Infrastructure\Environment;
use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase {

	public function test_local_environment_allows_development_providers(): void {
		self::assertTrue( Environment::fromString( 'local' )->allowsDevelopmentProviders() );
	}

	public function test_production_environment_rejects_development_providers(): void {
		self::assertFalse( Environment::fromString( 'production' )->allowsDevelopmentProviders() );
	}

	public function test_unknown_environment_fails_safe_as_production(): void {
		self::assertSame( 'production', Environment::fromString( 'unexpected' )->value() );
	}
}
