<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

final class CheckoutDecision {

	public function __construct( private bool $allowed, private string $message = '', private ?string $hostedUrl = null ) {}

	public function allowed(): bool {
		return $this->allowed;
	}

	public function message(): string {
		return $this->message;
	}

	public function hostedUrl(): ?string {
		return $this->hostedUrl;
	}
}
