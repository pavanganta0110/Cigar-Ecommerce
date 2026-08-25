<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

interface FedExTransport {

	/**
	 * @param array<string, string> $headers
	 * @return array{status: int, body: string}
	 */
	public function post( string $url, array $headers, string $body ): array;
}
