<?php

declare(strict_types=1);

namespace Compadres\Commerce\Infrastructure;

final class Redactor {

	private const SENSITIVE_KEYS = array(
		'api_key',
		'authorization',
		'cookie',
		'card_number',
		'client_secret',
		'cvv',
		'document_image',
		'government_id',
		'government_id_number',
		'license_key',
		'password',
		'signature_key',
		'token',
		'transaction_key',
		'provider_response',
		'raw_payload',
	);

	/**
	 * @param array<string|int, mixed> $context
	 * @return array<string|int, mixed>
	 */
	public function redact( array $context ): array {
		$redacted = array();
		foreach ( $context as $key => $value ) {
			if ( is_string( $key ) && $this->isSensitiveKey( $key ) ) {
				$redacted[ $key ] = '[REDACTED]';
				continue;
			}
			$redacted[ $key ] = is_array( $value ) ? $this->redact( $value ) : $value;
		}
		return $redacted;
	}

	private function isSensitiveKey( string $key ): bool {
		$normalized = strtolower( str_replace( array( '-', ' ' ), '_', $key ) );
		foreach ( self::SENSITIVE_KEYS as $sensitive_key ) {
			if ( $normalized === $sensitive_key || str_contains( $normalized, $sensitive_key ) ) {
				return true;
			}
		}
		return false;
	}

	public function redactMessage( string $message ): string {
		return (string) preg_replace(
			'/(Authorization:\s*Bearer)\s+[^\s]+/i',
			'$1 [REDACTED]',
			$message
		);
	}
}
