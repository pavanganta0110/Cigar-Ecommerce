<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

use WC_Session;

final class WooCommerceSessionAdapter implements KeyValueStore {

	public function __construct( private WC_Session $session ) {}

	public function get( string $key, mixed $fallback = null ): mixed {
		return $this->session->get( $key, $fallback );
	}

	public function set( string $key, mixed $value ): void {
		$this->session->set( $key, $value );
	}
}
