<?php

declare(strict_types=1);

namespace Compadres\Commerce\Contracts;

interface TaxProvider {

	/**
	 * Quote tax for a transaction.
	 *
	 * @param array<string, mixed> $transaction Transaction details.
	 * @return array<string, mixed>
	 */
	public function quote( array $transaction ): array;
	public function commit( string $reference ): bool;
	public function void( string $reference ): bool;
	/** @param array<string, mixed> $adjustment */
	public function refund( string $reference, array $adjustment ): string;
	public function connectionTest(): bool;
}
