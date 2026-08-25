<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

/** FedEx HTTP transport using the WordPress HTTP API. */
final class WordPressFedExTransport implements FedExTransport {

	/**
	 * @param array<string, string> $headers
	 * @return array{status: int, body: string}
	 */
	public function post( string $url, array $headers, string $body ): array {
		$response = wp_remote_post(
			$url,
			array(
				'body'               => $body,
				'headers'            => $headers,
				'redirection'        => 0,
				'reject_unsafe_urls' => true,
				'sslverify'          => true,
				'timeout'            => 15,
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new ShippingProviderException( 'FedEx transport is unavailable.' );
		}
		return array(
			'status' => (int) wp_remote_retrieve_response_code( $response ),
			'body'   => (string) wp_remote_retrieve_body( $response ),
		);
	}
}
