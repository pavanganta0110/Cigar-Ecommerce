<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

interface KeyValueStore {

	public function get( string $key, mixed $fallback = null ): mixed;

	public function set( string $key, mixed $value ): void;
}
