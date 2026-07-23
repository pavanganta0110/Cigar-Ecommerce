<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\Plugin;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/Support/WordPressHookStubs.php';

final class PluginBootTest extends TestCase {

	public function testRepeatedBootDoesNotDuplicateHookRegistration(): void {
		$GLOBALS['compadres_test_hooks'] = array();

		Plugin::boot();
		$first_count = count( $GLOBALS['compadres_test_hooks'] );
		Plugin::boot();

		self::assertGreaterThan( 0, $first_count );
		self::assertCount( $first_count, $GLOBALS['compadres_test_hooks'] );
	}
}
