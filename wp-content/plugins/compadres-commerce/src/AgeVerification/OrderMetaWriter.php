<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

interface OrderMetaWriter {

	public function set( string $key, mixed $value ): void;
}
