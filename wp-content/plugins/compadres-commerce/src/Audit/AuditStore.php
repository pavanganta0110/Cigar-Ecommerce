<?php

declare(strict_types=1);

namespace Compadres\Commerce\Audit;

interface AuditStore {

	/** @param array<string, mixed> $record */
	public function insert( array $record ): int|false;
}
