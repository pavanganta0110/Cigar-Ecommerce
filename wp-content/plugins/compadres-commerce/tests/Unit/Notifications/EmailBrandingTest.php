<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit\Notifications;

use Compadres\Commerce\Notifications\EmailBranding;
use PHPUnit\Framework\TestCase;

final class EmailBrandingTest extends TestCase {

	public function test_colors_are_bounded_hex_values_from_the_theme_palette(): void {
		$branding = new EmailBranding();

		foreach ( array( $branding->baseColor(), $branding->backgroundColor(), $branding->bodyBackgroundColor(), $branding->textColor() ) as $color ) {
			self::assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/', $color );
		}
	}

	public function test_from_name_identifies_the_business(): void {
		self::assertSame( 'Compadres Cigars', ( new EmailBranding() )->fromName() );
	}

	public function test_footer_text_uses_the_site_title_placeholder_and_states_the_signature_requirement(): void {
		$footer = ( new EmailBranding() )->footerText();

		self::assertStringContainsString( '{site_title}', $footer );
		self::assertStringContainsString( 'Adult signature required', $footer );
	}
}
