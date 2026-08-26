<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

use Compadres\Commerce\Shipping\FedExTransport;

final class FakeFedExTransport implements FedExTransport {

	/** @var list<array{url: string, headers: array<string, string>, body: string}> */
	private array $requests = array();

	/** @var list<array{status: int, body: string}> */
	private array $responses;

	/** @param list<array{status: int, body: string}> $responses */
	public function __construct( array $responses ) {
		$this->responses = $responses;
	}

	/**
	 * @param array<string, string> $headers
	 * @return array{status: int, body: string}
	 */
	public function post( string $url, array $headers, string $body ): array {
		$this->requests[] = array(
			'url'     => $url,
			'headers' => $headers,
			'body'    => $body,
		);
		if ( array() === $this->responses ) {
			return array(
				'status' => 503,
				'body'   => '{}',
			);
		}
		return array_shift( $this->responses );
	}

	/** @return list<array{url: string, headers: array<string, string>, body: string}> */
	public function requests(): array {
		return $this->requests;
	}
}
