<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

interface AgeCheckerStatusTransport {

	/** @return array<string, string> */
	public function status( string $reference ): array;
}
