<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

interface VerificationStore {

	public function current(): ?VerificationResult;

	public function save( VerificationResult $result ): void;
}
