<?php

declare(strict_types=1);

namespace Compadres\Commerce\Integrations;

final class IntegrationStatus {

	private string $integration;
	private string $state;
	private string $message;
	private bool $productionApproved;

	private function __construct(
		string $integration,
		string $state,
		string $message,
		bool $productionApproved
	) {
		$this->integration        = $integration;
		$this->state              = $state;
		$this->message            = $message;
		$this->productionApproved = $productionApproved;
	}

	public static function sandbox( string $integration, string $message ): self {
		return new self( $integration, 'sandbox', $message, false );
	}

	public static function connected(
		string $integration,
		string $message,
		bool $productionApproved
	): self {
		return new self( $integration, 'connected', $message, $productionApproved );
	}

	public static function disabled( string $integration, string $message ): self {
		return new self( $integration, 'disabled', $message, false );
	}

	public function integration(): string {
		return $this->integration;
	}

	public function state(): string {
		return $this->state;
	}

	public function message(): string {
		return $this->message;
	}

	public function isProductionReady(): bool {
		return 'connected' === $this->state && $this->productionApproved;
	}
}
