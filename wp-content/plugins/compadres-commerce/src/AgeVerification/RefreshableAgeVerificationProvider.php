<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

interface RefreshableAgeVerificationProvider {

	public function refresh( string $reference ): VerificationResult;
}
