<?php

declare(strict_types=1);

namespace Compadres\Commerce\Restrictions;

use DateTimeImmutable;

interface RestrictionRepository {

	/** @return list<RestrictionRule> */
	public function activeRules( DateTimeImmutable $at ): array;
}
