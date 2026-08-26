<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

/** @param array<string, mixed> $arguments */
function wp_remote_post( string $url, array $arguments ): array {
	$GLOBALS['compadres_fedex_transport_arguments'] = $arguments;
	return array(
		'response' => array( 'code' => 200 ),
		'body'     => '{}',
	);
}

/** @param mixed $response */
function is_wp_error( $response ): bool {
	unset( $response );
	return false;
}

/** @param array<string, mixed> $response */
function wp_remote_retrieve_response_code( array $response ): int {
	return (int) $response['response']['code'];
}

/** @param array<string, mixed> $response */
function wp_remote_retrieve_body( array $response ): string {
	return (string) $response['body'];
}
