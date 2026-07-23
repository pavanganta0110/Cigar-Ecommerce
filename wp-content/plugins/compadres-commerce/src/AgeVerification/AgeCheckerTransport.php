<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

interface AgeCheckerTransport {

	/**
	 * Sends transient checkout data to AgeChecker. Implementations must not log or persist this payload.
	 *
	 * @param  array<string, string> $request
	 * @return array<string, string>
	 */
	public function verify( array $request ): array;
}
