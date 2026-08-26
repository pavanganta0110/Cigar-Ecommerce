<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Shipping\WordPressFedExTransport;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/WordPressFedExTransportFunctions.php';

final class WordPressFedExTransportTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['compadres_fedex_transport_arguments'] );
	}

	public function test_bounds_response_during_transport(): void {
		$transport = new WordPressFedExTransport();
		$response  = $transport->post( 'https://apis.fedex.com/rate/v1/rates/quotes', array(), '{}' );

		self::assertSame(
			array(
				'status' => 200,
				'body'   => '{}',
			),
			$response
		);
		self::assertIsArray( $GLOBALS['compadres_fedex_transport_arguments'] );
		self::assertSame( 1048577, $GLOBALS['compadres_fedex_transport_arguments']['limit_response_size'] );
	}
}
