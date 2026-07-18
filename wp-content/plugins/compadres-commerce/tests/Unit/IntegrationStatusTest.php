<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\Integrations\IntegrationStatus;
use PHPUnit\Framework\TestCase;

final class IntegrationStatusTest extends TestCase {

	public function test_configured_sandbox_is_not_production_approved(): void {
		$status = IntegrationStatus::sandbox( 'age_verification', 'Mock provider connected' );

		self::assertSame( 'sandbox', $status->state() );
		self::assertFalse( $status->isProductionReady() );
	}

	public function test_production_ready_requires_separate_approval(): void {
		$status = IntegrationStatus::connected( 'payment', 'Gateway connected', false );

		self::assertFalse( $status->isProductionReady() );
		self::assertSame( 'connected', $status->state() );
	}
}
