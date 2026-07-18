<?php

declare(strict_types=1);

namespace Compadres\Commerce\Contracts;

interface AgeVerificationProvider {

	/** @param array<string, scalar|null> $customer */
	public function start( array $customer ): string;
	/** @param array<string, scalar|null> $answers */
	public function submit( string $reference, array $answers ): string;
	public function result( string $reference ): string;
	public function connectionTest(): bool;
}
