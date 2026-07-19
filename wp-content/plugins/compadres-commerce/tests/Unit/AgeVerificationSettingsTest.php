<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\AgeVerification\AgeVerificationSettings;
use PHPUnit\Framework\TestCase;

final class AgeVerificationSettingsTest extends TestCase {

	protected function tearDown(): void {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Isolate deployment-policy tests.
		putenv( 'APP_ENV' );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Isolate deployment-policy tests.
		putenv( 'COMPADRES_AGECHECKER_ALLOWED_HOSTS' );
	}

	public function testProductionHostedTemplateRequiresDeploymentAllowlist(): void {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Exercise production policy.
		putenv( 'APP_ENV=production' );
		$template = 'https://verify.example.test/{reference}?return_url={return_url}';

		self::assertSame( '', AgeVerificationSettings::sanitize( array( 'hosted_url_template' => $template ) )['hosted_url_template'] );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Exercise deployment allowlist.
		putenv( 'COMPADRES_AGECHECKER_ALLOWED_HOSTS=verify.example.test' );
		self::assertSame( $template, AgeVerificationSettings::sanitize( array( 'hosted_url_template' => $template ) )['hosted_url_template'] );
	}

	public function testLocalEnvironmentAcceptsOnlyReservedExampleHostWithoutAllowlist(): void {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Exercise local policy.
		putenv( 'APP_ENV=local' );

		self::assertSame(
			'https://verify.example.test/{reference}?return_url={return_url}',
			AgeVerificationSettings::sanitize( array( 'hosted_url_template' => 'https://verify.example.test/{reference}?return_url={return_url}' ) )['hosted_url_template']
		);
		self::assertSame( '', AgeVerificationSettings::sanitize( array( 'hosted_url_template' => 'https://evil.example.com/{reference}?return_url={return_url}' ) )['hosted_url_template'] );
	}
}
