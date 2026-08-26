<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

interface AttestationRequirement {

	public function requiresAttestation(): bool;
}
