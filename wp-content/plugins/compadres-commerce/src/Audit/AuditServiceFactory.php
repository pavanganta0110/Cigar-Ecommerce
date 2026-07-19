<?php

declare(strict_types=1);

namespace Compadres\Commerce\Audit;

use Compadres\Commerce\Infrastructure\Environment;
use Compadres\Commerce\Infrastructure\Redactor;
use DateTimeImmutable;

final class AuditServiceFactory {

	public static function create(): AuditService {
		global $wpdb;
		$environment_name = getenv( 'APP_ENV' );
		return new AuditService(
			new WordPressAuditStore( $wpdb ),
			new Redactor(),
			Environment::fromString( false === $environment_name ? 'production' : (string) $environment_name ),
			static fn (): DateTimeImmutable => new DateTimeImmutable( 'now', wp_timezone() ),
			static function (): string {
				$request_id = isset( $_SERVER['HTTP_X_REQUEST_ID'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REQUEST_ID'] ) ) : '';
				return '' !== $request_id ? $request_id : wp_generate_uuid4();
			},
			static function ( string $message ): void {
				error_log( 'Compadres audit write failed: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Redacted operational failure signal; must not interrupt checkout.
			}
		);
	}

	/** @return array<string, string> */
	public static function requestContext(): array {
		return array(
			'method' => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '',
			'route'  => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
		);
	}
}
