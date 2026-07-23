<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

use Closure;
use DateTimeImmutable;

final class AgeVerificationService {

	private Closure $now;

	public function __construct(
		private AgeVerificationProvider $provider,
		private VerificationStore $store,
		callable $now
	) {
		$this->now = Closure::fromCallable( $now );
	}

	public function verify( VerificationRequest $request ): VerificationResult {
		$current = $this->store->current();
		/** @var DateTimeImmutable $now */
		$now = ( $this->now )();
		if ( null !== $current && $current->provider() === $this->provider->name() && $current->allowsCheckoutAt( $now ) ) {
			return $current;
		}
		$result = $this->provider->verify( $request );
		$this->store->save( $result );
		return $result;
	}
}
