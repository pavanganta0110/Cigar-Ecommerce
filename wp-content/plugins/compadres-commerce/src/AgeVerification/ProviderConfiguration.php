<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

final class ProviderConfiguration {

	private function __construct(
		private bool $enabled,
		private string $provider,
		private bool $requiresDateOfBirth,
		private string $hostedUrlTemplate,
		private bool $productionApproved,
		private string $mockStatus,
		private string $mockRefreshStatus
	) {}

	/** @param array<string, mixed> $values */
	public static function fromArray( array $values ): self {
		return new self(
			(bool) ( $values['enabled'] ?? false ),
			in_array( $values['provider'] ?? '', array( 'agechecker', 'mock' ), true ) ? (string) $values['provider'] : '',
			(bool) ( $values['requires_date_of_birth'] ?? false ),
			isset( $values['hosted_url_template'] ) ? trim( (string) $values['hosted_url_template'] ) : '',
			(bool) ( $values['production_approved'] ?? false ),
			isset( $values['mock_status'] ) && in_array( $values['mock_status'], VerificationStatus::all(), true ) ? (string) $values['mock_status'] : VerificationStatus::PASSED,
			isset( $values['mock_refresh_status'] ) && in_array( $values['mock_refresh_status'], VerificationStatus::all(), true ) ? (string) $values['mock_refresh_status'] : VerificationStatus::PASSED
		);
	}

	public function enabled(): bool {
		return $this->enabled;
	}

	public function provider(): string {
		return $this->provider;
	}

	public function requiresDateOfBirth(): bool {
		return $this->requiresDateOfBirth;
	}

	public function hostedUrlTemplate(): string {
		return $this->hostedUrlTemplate;
	}

	public function productionApproved(): bool {
		return $this->productionApproved;
	}

	public function mockStatus(): string {
		return $this->mockStatus;
	}

	public function mockRefreshStatus(): string {
		return $this->mockRefreshStatus;
	}

	public function integrationStatus(): string {
		if ( ! $this->enabled || '' === $this->provider ) {
			return 'not_configured';
		}
		return 'mock' === $this->provider ? 'mock' : 'configured_not_approved';
	}
}
