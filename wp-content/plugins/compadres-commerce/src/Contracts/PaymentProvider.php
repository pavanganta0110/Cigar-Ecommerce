<?php

declare(strict_types=1);

namespace Compadres\Commerce\Contracts;

interface PaymentProvider {

	/**
	 * Authorize a transaction.
	 *
	 * @param array<string, mixed> $request Transaction request.
	 * @return array<string, mixed>
	 */
	public function authorize( array $request, string $idempotencyKey ): array;
	public function capture( string $transactionId, int $amountMinor ): bool;
	public function void( string $transactionId ): bool;
	public function refund( string $transactionId, int $amountMinor, string $idempotencyKey ): string;
	public function connectionTest(): bool;
}
