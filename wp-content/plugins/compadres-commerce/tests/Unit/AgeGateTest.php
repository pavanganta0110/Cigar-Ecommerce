<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\Compliance\AgeGateSettings;
use Compadres\Commerce\Compliance\AgeGateToken;
use PHPUnit\Framework\TestCase;

final class AgeGateTest extends TestCase {

	public function testSettingsSanitizeWordingLifetimeAndSameSite(): void {
		$settings = AgeGateSettings::sanitize(
			array(
				'enabled'               => '1',
				'title'                 => '  <b>Adults only</b> ',
				'explanatory_text'      => '<script>bad()</script>Visitors must be 21.',
				'confirmation_label'    => 'I am 21 or older',
				'exit_label'            => 'Exit',
				'exit_url'              => 'https://www.google.com/',
				'cookie_lifetime_hours' => '99999',
				'same_site'             => 'Invalid',
			)
		);

		self::assertTrue( $settings['enabled'] );
		self::assertSame( 'Adults only', $settings['title'] );
		self::assertStringNotContainsString( '<script>', $settings['explanatory_text'] );
		self::assertSame( 8760, $settings['cookie_lifetime_hours'] );
		self::assertSame( 'Lax', $settings['same_site'] );
	}

	public function testTokenIsSignedAndExpires(): void {
		$token = new AgeGateToken( 'development-test-secret' );
		$value = $token->issue( 1_000, 3_600 );

		self::assertTrue( $token->isValid( $value, 4_599 ) );
		self::assertFalse( $token->isValid( $value, 4_601 ) );
		self::assertFalse( $token->isValid( $value . 'tampered', 1_001 ) );
		self::assertFalse( $token->isValid( 'malformed', 1_001 ) );
	}

	public function testAgeGateCanBeDisabled(): void {
		$settings = AgeGateSettings::sanitize( array( 'enabled' => '0' ) );

		self::assertFalse( $settings['enabled'] );
	}
}
