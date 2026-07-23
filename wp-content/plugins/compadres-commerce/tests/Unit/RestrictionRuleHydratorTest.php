<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\Restrictions\RestrictionContext;
use Compadres\Commerce\Restrictions\RestrictionRuleHydrator;
use Compadres\Commerce\Restrictions\RuleEvaluator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RestrictionRuleHydratorTest extends TestCase {

	public function testJoinedRowsBecomeOneRuleWithNormalizedTargets(): void {
		$rows = array(
			array(
				'id'           => '31',
				'name'         => 'Fictional hydrated rule',
				'priority'     => '50',
				'effective_at' => '2026-01-01 00:00:00',
				'expires_at'   => null,
				'target_type'  => 'state',
				'target_value' => 'XY',
			),
			array(
				'id'           => '31',
				'name'         => 'Fictional hydrated rule',
				'priority'     => '50',
				'effective_at' => '2026-01-01 00:00:00',
				'expires_at'   => null,
				'target_type'  => 'product_id',
				'target_value' => '42',
			),
		);

		$rules = ( new RestrictionRuleHydrator() )->hydrate( $rows );

		self::assertCount( 1, $rules );
		self::assertTrue(
			( new RuleEvaluator() )->evaluate(
				$rules,
				new RestrictionContext( 'XY', '', '', array( 42 ), array(), array() ),
				new DateTimeImmutable( '2026-07-19T12:00:00+00:00' )
			)->isBlocked()
		);
	}
}
