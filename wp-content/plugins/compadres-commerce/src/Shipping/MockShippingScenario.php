<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

/**
 * Deterministic development scenarios for the mock shipping provider/method.
 *
 * These scenarios are explicitly fictional and exist only for local and
 * explicitly enabled staging testing. They never perform real carrier
 * transactions and must never be represented as carrier-approved.
 */
final class MockShippingScenario {

	public const ELIGIBLE    = 'eligible';
	public const INELIGIBLE  = 'ineligible';
	public const UNAVAILABLE = 'unavailable';
	public const NONE        = 'none';

	public const SERVICE_ELIGIBLE   = 'compadres_mock_eligible';
	public const SERVICE_INELIGIBLE = 'compadres_mock_ineligible';

	public const VALID = array(
		self::ELIGIBLE,
		self::INELIGIBLE,
		self::UNAVAILABLE,
		self::NONE,
	);

	private const RATES = array(
		self::ELIGIBLE    => array(
			array(
				'id'          => self::SERVICE_ELIGIBLE,
				'label'       => 'Adult Signature Eligible Test Service',
				'supports_as' => true,
			),
		),
		self::INELIGIBLE  => array(
			array(
				'id'          => self::SERVICE_INELIGIBLE,
				'label'       => 'Test Service (no adult signature)',
				'supports_as' => false,
			),
		),
		self::UNAVAILABLE => array(),
		self::NONE        => array(),
	);

	private string $scenario;

	private function __construct( string $scenario ) {
		$this->scenario = $scenario;
	}

	public static function fromString( string $scenario ): self {
		return new self( in_array( $scenario, self::VALID, true ) ? $scenario : self::UNAVAILABLE );
	}

	public function value(): string {
		return $this->scenario;
	}

	/**
	 * The provider is considered reachable for eligibility except when the
	 * scenario simulates provider outage.
	 */
	public function isProviderAvailable(): bool {
		return self::UNAVAILABLE !== $this->scenario;
	}

	/**
	 * @return list<array{id: string, label: string, supports_as: bool}>
	 */
	public function rates(): array {
		return self::RATES[ $this->scenario ];
	}

	/**
	 * @return list<string>
	 */
	public function eligibleServiceIds(): array {
		if ( self::ELIGIBLE !== $this->scenario ) {
			return array();
		}
		return array( self::SERVICE_ELIGIBLE );
	}

	public function supportsAdultSignature( string $service_id ): bool {
		foreach ( $this->rates() as $rate ) {
			if ( $rate['id'] === $service_id ) {
				return (bool) $rate['supports_as'];
			}
		}
		return false;
	}
}
