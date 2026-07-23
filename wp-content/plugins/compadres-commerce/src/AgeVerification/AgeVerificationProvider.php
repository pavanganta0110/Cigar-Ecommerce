<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

interface AgeVerificationProvider {

	public function name(): string;

	public function verify( VerificationRequest $request ): VerificationResult;

	public function hostedVerificationUrl( string $reference, string $return_url ): ?string;
}
