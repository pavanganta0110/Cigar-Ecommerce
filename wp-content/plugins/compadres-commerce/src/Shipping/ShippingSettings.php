<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

/**
 * Settings for the development mock shipping provider.
 *
 * The only persisted setting is the deterministic mock scenario used by local
 * and explicitly enabled staging tests. Production never enables the mock.
 */
final class ShippingSettings {

	public const OPTION_SCENARIO = 'compadres_shipping_mock_scenario';

	/** @return array{scenario: string} */
	public static function defaults(): array {
		return array( 'scenario' => MockShippingScenario::UNAVAILABLE );
	}

	/**
	 * @param array<string, mixed> $values
	 * @return array{scenario: string}
	 */
	public static function sanitize( array $values ): array {
		$scenario = isset( $values['scenario'] ) && in_array( (string) $values['scenario'], MockShippingScenario::VALID, true )
			? (string) $values['scenario']
			: MockShippingScenario::UNAVAILABLE;
		return array( 'scenario' => $scenario );
	}

	/** @return array{scenario: string} */
	public static function read(): array {
		$value = get_option( self::OPTION_SCENARIO, array() );
		return self::sanitize( is_array( $value ) ? $value : array() );
	}

	public static function scenario(): MockShippingScenario {
		$settings = self::read();
		return MockShippingScenario::fromString( $settings['scenario'] );
	}

	public static function updateScenario( string $scenario ): void {
		update_option( self::OPTION_SCENARIO, self::sanitize( array( 'scenario' => $scenario ) ) );
	}
}
