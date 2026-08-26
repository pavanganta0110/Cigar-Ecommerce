<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit\Tax;

use Compadres\Commerce\Tax\ManualSalesTaxInstaller;
use Compadres\Commerce\Tax\ManualSalesTaxRuleSet;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ManualSalesTaxRuleSetTest extends TestCase {
	public function testContainsOneEffectiveDatedRuleForEveryState(): void {
		$rules = ( new ManualSalesTaxRuleSet() )->all();

		self::assertCount( 50, $rules );
		self::assertCount( 50, array_unique( array_map( static fn ( $rule ): string => $rule->state(), $rules ) ) );
		self::assertSame( array(), array_values( array_filter( $rules, static fn ( $rule ): bool => ! preg_match( '/^\d+\.\d{3}$/', $rule->ratePercent() ) ) ) );
	}

	public function testUsesTheUploadedAvgCombinedReferenceValuesExactly(): void {
		$rules = new ManualSalesTaxRuleSet();
		$date  = new DateTimeImmutable( '2026-08-19' );

		self::assertSame( '9.460', $rules->forState( 'AL', $date )?->ratePercent() );
		self::assertSame( '8.440', $rules->forState( 'mo', $date )?->ratePercent() );
		self::assertSame( '6.600', $rules->forState( 'NJ', $date )?->ratePercent() );
		self::assertSame( '10.110', $rules->forState( 'LA', $date )?->ratePercent() );
		self::assertSame( '0.000', $rules->forState( 'DE', $date )?->ratePercent() );
		self::assertSame( '0.000', $rules->forState( 'MT', $date )?->ratePercent() );
		self::assertSame( '0.000', $rules->forState( 'NH', $date )?->ratePercent() );
		self::assertSame( '0.000', $rules->forState( 'OR', $date )?->ratePercent() );
	}

	public function testRulesAreNotAppliedBeforeBusinessApprovalDate(): void {
		$rules = new ManualSalesTaxRuleSet();

		self::assertNull( $rules->forState( 'MO', new DateTimeImmutable( '2026-08-18' ) ) );
		self::assertNotNull( $rules->forState( 'MO', new DateTimeImmutable( '2026-08-19' ) ) );
	}

	public function testUnknownOrUnsupportedDestinationsFailClosed(): void {
		$rules = new ManualSalesTaxRuleSet();
		$date  = new DateTimeImmutable( '2026-08-19' );

		self::assertNull( $rules->forState( '', $date ) );
		self::assertNull( $rules->forState( 'DC', $date ) );
		self::assertNull( $rules->forState( 'XX', $date ) );
	}

	public function testRulePreservesSourceAndAverageRateLimitation(): void {
		$rule = ( new ManualSalesTaxRuleSet() )->forState( 'MO', new DateTimeImmutable( '2026-08-19' ) );

		self::assertNotNull( $rule );
		self::assertSame( 'avg_combined_reference', $rule->calculationBasis() );
		self::assertSame( 'Avg Combined Reference %', $rule->sourceColumn() );
		self::assertSame( 'Compadres_Cigars_50_State_Tobacco_Tax_Matrix_2026.xlsx - 50-State Tax Matrix.pdf', $rule->sourceDocument() );
		self::assertSame( '2026-08-19', $rule->effectiveFrom() );
		self::assertSame( ManualSalesTaxInstaller::VERSION, $rule->ruleVersion() );
		self::assertFalse( $rule->shippingTaxable() );
		self::assertTrue( $rule->isAverageReference() );
	}
}
