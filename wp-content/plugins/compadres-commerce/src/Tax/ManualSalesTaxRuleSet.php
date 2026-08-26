<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tax;

use DateTimeImmutable;

/** Business-approved 2026 Avg Combined Reference rates for the 50 U.S. states. */
final class ManualSalesTaxRuleSet {
	private const EFFECTIVE_FROM = '2026-08-19';

	/** @var array<string, string> */
	private const RATES = array(
		'AL' => '9.460',
		'AK' => '1.820',
		'AZ' => '8.520',
		'AR' => '9.460',
		'CA' => '8.990',
		'CO' => '7.890',
		'CT' => '6.350',
		'DE' => '0.000',
		'FL' => '6.980',
		'GA' => '7.490',
		'HI' => '4.500',
		'ID' => '6.030',
		'IL' => '8.960',
		'IN' => '7.000',
		'IA' => '6.940',
		'KS' => '8.690',
		'KY' => '6.000',
		'LA' => '10.110',
		'ME' => '5.500',
		'MD' => '6.000',
		'MA' => '6.250',
		'MI' => '6.000',
		'MN' => '8.140',
		'MS' => '7.060',
		'MO' => '8.440',
		'MT' => '0.000',
		'NE' => '6.980',
		'NV' => '8.240',
		'NH' => '0.000',
		'NJ' => '6.600',
		'NM' => '7.670',
		'NY' => '8.540',
		'NC' => '7.000',
		'ND' => '7.090',
		'OH' => '7.290',
		'OK' => '9.060',
		'OR' => '0.000',
		'PA' => '6.340',
		'RI' => '7.000',
		'SC' => '7.490',
		'SD' => '6.110',
		'TN' => '9.610',
		'TX' => '8.200',
		'UT' => '7.420',
		'VT' => '6.390',
		'VA' => '5.770',
		'WA' => '9.510',
		'WV' => '6.590',
		'WI' => '5.720',
		'WY' => '5.560',
	);

	/** @return list<ManualSalesTaxRule> */
	public function all(): array {
		$rules = array();
		foreach ( self::RATES as $state => $rate ) {
			$rules[] = new ManualSalesTaxRule( $state, $rate, self::EFFECTIVE_FROM );
		}
		return $rules;
	}

	public function forState( string $state, DateTimeImmutable $at ): ?ManualSalesTaxRule {
		$state = strtoupper( trim( $state ) );
		if ( $at < new DateTimeImmutable( self::EFFECTIVE_FROM ) || ! isset( self::RATES[ $state ] ) ) {
			return null;
		}
		return new ManualSalesTaxRule( $state, self::RATES[ $state ], self::EFFECTIVE_FROM );
	}
}
