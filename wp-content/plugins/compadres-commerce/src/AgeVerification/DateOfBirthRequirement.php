<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

interface DateOfBirthRequirement {

	public function requiresDateOfBirth(): bool;
}
