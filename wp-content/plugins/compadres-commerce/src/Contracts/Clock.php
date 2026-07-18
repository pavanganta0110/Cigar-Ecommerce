<?php

declare(strict_types=1);

namespace Compadres\Commerce\Contracts;

interface Clock {

	public function now(): \DateTimeImmutable;
}
