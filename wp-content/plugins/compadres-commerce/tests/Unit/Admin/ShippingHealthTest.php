<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit\Admin;

use Compadres\Commerce\Admin\ShippingHealth;
use PHPUnit\Framework\TestCase;

final class ShippingHealthTest extends TestCase {

	public function test_sandbox_when_mock_method_is_allowed(): void {
		$status = ShippingHealth::describe( true );

		self::assertSame( 'sandbox', $status->state() );
		self::assertFalse( $status->isProductionReady() );
	}

	public function test_disabled_when_no_provider_is_available(): void {
		$status = ShippingHealth::describe( false );

		self::assertSame( 'disabled', $status->state() );
		self::assertFalse( $status->isProductionReady() );
	}
}
