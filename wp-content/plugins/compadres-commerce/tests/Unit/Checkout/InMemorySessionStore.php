<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit\Checkout;

use Compadres\Commerce\Checkout\SessionStore;

final class InMemorySessionStore implements SessionStore {

	/** @var array<string, mixed> */
	private array $values = array();

	public function get( string $key, mixed $fallback = null ): mixed {
		return $this->values[ $key ] ?? $fallback;
	}

	public function set( string $key, mixed $value ): void {
		$this->values[ $key ] = $value;
	}
}
