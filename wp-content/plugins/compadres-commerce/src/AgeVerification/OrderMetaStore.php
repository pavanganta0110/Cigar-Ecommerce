<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

interface OrderMetaStore extends OrderMetaWriter {

	public function get( string $key, mixed $fallback = null ): mixed;
}
