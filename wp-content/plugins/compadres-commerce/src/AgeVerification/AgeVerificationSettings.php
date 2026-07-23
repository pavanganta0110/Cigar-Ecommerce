<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

final class AgeVerificationSettings {

	/** @return array<string, mixed> */
	public static function defaults(): array {
		return array(
			'enabled'                => true,
			'provider'               => '',
			'requires_date_of_birth' => false,
			'hosted_url_template'    => '',
			'production_approved'    => false,
			'mock_status'            => VerificationStatus::PASSED,
			'mock_refresh_status'    => VerificationStatus::PASSED,
		);
	}

	/** @param array<string, mixed> $values
	 *  @return array<string, mixed>
	 */
	public static function sanitize( array $values ): array {
		$provider = isset( $values['provider'] ) && in_array( $values['provider'], array( '', 'agechecker', 'mock' ), true ) ? (string) $values['provider'] : '';
		$template = isset( $values['hosted_url_template'] ) ? trim( (string) $values['hosted_url_template'] ) : '';
		if ( '' !== $template && ! self::approvedHostedTemplate( $template ) ) {
			$template = '';
		}
		return array(
			'enabled'                => ! empty( $values['enabled'] ),
			'provider'               => $provider,
			'requires_date_of_birth' => ! empty( $values['requires_date_of_birth'] ),
			'hosted_url_template'    => $template,
			'production_approved'    => ! empty( $values['production_approved'] ),
			'mock_status'            => isset( $values['mock_status'] ) && in_array( $values['mock_status'], VerificationStatus::all(), true ) ? (string) $values['mock_status'] : VerificationStatus::PASSED,
			'mock_refresh_status'    => isset( $values['mock_refresh_status'] ) && in_array( $values['mock_refresh_status'], VerificationStatus::all(), true ) ? (string) $values['mock_refresh_status'] : VerificationStatus::PASSED,
		);
	}

	private static function approvedHostedTemplate( string $template ): bool {
		if ( ! str_starts_with( $template, 'https://' ) || ! str_contains( $template, '{reference}' ) || ! str_contains( $template, '{return_url}' ) ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure policy is unit tested without loading WordPress.
		$host = strtolower( (string) parse_url( $template, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return false;
		}
		$environment = strtolower( (string) getenv( 'APP_ENV' ) );
		if ( in_array( $environment, array( 'local', 'development' ), true ) && str_ends_with( $host, '.example.test' ) ) {
			return true;
		}
		$allowed = array_filter( array_map( 'trim', explode( ',', strtolower( (string) getenv( 'COMPADRES_AGECHECKER_ALLOWED_HOSTS' ) ) ) ) );
		return in_array( $host, $allowed, true );
	}
}
