<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\Restrictions\RestrictionContext;
use Compadres\Commerce\Restrictions\RestrictionRule;
use Compadres\Commerce\Restrictions\RuleEvaluator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RestrictionRuleEvaluatorTest extends TestCase {

	public function testActiveStateRuleBlocksMatchingDestination(): void {
		$rule    = RestrictionRule::fromArray(
			array(
				'id'           => 17,
				'name'         => 'Fictional destination rule',
				'state'        => 'XY',
				'priority'     => 100,
				'effective_at' => '2026-01-01T00:00:00+00:00',
				'expires_at'   => '2027-01-01T00:00:00+00:00',
			)
		);
		$context = new RestrictionContext( 'xy', 'Sample City', '12345', array( 42 ), array( 7 ), array( 3 ) );

		$decision = ( new RuleEvaluator() )->evaluate( array( $rule ), $context, new DateTimeImmutable( '2026-07-19T12:00:00+00:00' ) );

		self::assertTrue( $decision->isBlocked() );
		self::assertSame( array( 17 ), $decision->ruleIds() );
	}

	public function testEveryConfiguredDestinationAndCartScopeMustMatch(): void {
		$rule      = RestrictionRule::fromArray(
			array(
				'id'           => 18,
				'name'         => 'Fictional city category rule',
				'state'        => 'XY',
				'city'         => 'Sample City',
				'postal_code'  => '12345',
				'category_ids' => array( 7 ),
				'effective_at' => '2026-01-01T00:00:00+00:00',
			)
		);
		$evaluator = new RuleEvaluator();
		$now       = new DateTimeImmutable( '2026-07-19T12:00:00+00:00' );

		self::assertTrue( $evaluator->evaluate( array( $rule ), new RestrictionContext( 'XY', 'sample city', '12345', array( 42 ), array( 7 ), array( 3 ) ), $now )->isBlocked() );
		self::assertFalse( $evaluator->evaluate( array( $rule ), new RestrictionContext( 'XY', 'Other City', '12345', array( 42 ), array( 7 ), array( 3 ) ), $now )->isBlocked() );
		self::assertFalse( $evaluator->evaluate( array( $rule ), new RestrictionContext( 'XY', 'Sample City', '12345', array( 42 ), array( 8 ), array( 3 ) ), $now )->isBlocked() );
	}

	public function testMatchingRulesAreReportedByDescendingPriority(): void {
		$low  = RestrictionRule::fromArray(
			array(
				'id'           => 20,
				'name'         => 'Lower priority',
				'state'        => 'XY',
				'priority'     => 10,
				'effective_at' => '2026-01-01T00:00:00+00:00',
			)
		);
		$high = RestrictionRule::fromArray(
			array(
				'id'           => 21,
				'name'         => 'Higher priority',
				'state'        => 'XY',
				'priority'     => 100,
				'effective_at' => '2026-01-01T00:00:00+00:00',
			)
		);

		$decision = ( new RuleEvaluator() )->evaluate(
			array( $low, $high ),
			new RestrictionContext( 'XY', '', '', array(), array(), array() ),
			new DateTimeImmutable( '2026-07-19T12:00:00+00:00' )
		);

		self::assertSame( array( 21, 20 ), $decision->ruleIds() );
	}

	public function testAnyConfiguredCartScopeCanMatchTheRule(): void {
		$rule    = RestrictionRule::fromArray(
			array(
				'id'           => 22,
				'name'         => 'Fictional mixed cart scope',
				'product_ids'  => array( 42 ),
				'category_ids' => array( 8 ),
				'brand_ids'    => array( 9 ),
				'effective_at' => '2026-01-01T00:00:00+00:00',
			)
		);
		$context = new RestrictionContext( 'XY', 'Sample City', '12345', array( 42 ), array( 7 ), array( 3 ) );

		self::assertTrue( ( new RuleEvaluator() )->evaluate( array( $rule ), $context, new DateTimeImmutable( '2026-07-19T12:00:00+00:00' ) )->isBlocked() );
	}

	public function testMultipleValuesWithinOneDestinationScopeAreAlternatives(): void {
		$rule      = RestrictionRule::fromArray(
			array(
				'id'           => 23,
				'name'         => 'Fictional multi-state rule',
				'state'        => array( 'XY', 'YZ' ),
				'effective_at' => '2026-01-01T00:00:00+00:00',
			)
		);
		$now       = new DateTimeImmutable( '2026-07-19T12:00:00+00:00' );
		$evaluator = new RuleEvaluator();

		self::assertTrue( $evaluator->evaluate( array( $rule ), new RestrictionContext( 'YZ', '', '', array(), array(), array() ), $now )->isBlocked() );
		self::assertFalse( $evaluator->evaluate( array( $rule ), new RestrictionContext( 'AB', '', '', array(), array(), array() ), $now )->isBlocked() );
	}

	public function testRuleCountryMustMatchTheDestinationCountry(): void {
		$rule    = RestrictionRule::fromArray(
			array(
				'id'              => 24,
				'name'            => 'Fictional U.S. rule',
				'country'         => 'US',
				'state'           => 'XY',
				'effective_at'    => '2026-01-01T00:00:00+00:00',
				'blocked_message' => 'This cart cannot be shipped to the selected destination.',
			)
		);
		$context = new RestrictionContext( 'XY', '', '', array(), array(), array(), 'CA' );

		self::assertFalse( ( new RuleEvaluator() )->evaluate( array( $rule ), $context, new DateTimeImmutable( '2026-07-19T12:00:00+00:00' ) )->isBlocked() );
	}

	public function testHighestPriorityMatchingRuleProvidesTheCustomerMessage(): void {
		$low  = RestrictionRule::fromArray(
			array(
				'id'              => 25,
				'name'            => 'Lower priority fictional rule',
				'state'           => 'XY',
				'priority'        => 10,
				'effective_at'    => '2026-01-01T00:00:00+00:00',
				'blocked_message' => 'Lower priority message.',
			)
		);
		$high = RestrictionRule::fromArray(
			array(
				'id'              => 26,
				'name'            => 'Higher priority fictional rule',
				'state'           => 'XY',
				'priority'        => 20,
				'effective_at'    => '2026-01-01T00:00:00+00:00',
				'blocked_message' => 'This cart cannot be shipped to the selected destination.',
			)
		);

		$decision = ( new RuleEvaluator() )->evaluate( array( $low, $high ), new RestrictionContext( 'XY', '', '', array(), array(), array() ), new DateTimeImmutable( '2026-07-19T12:00:00+00:00' ) );

		self::assertSame( 'This cart cannot be shipped to the selected destination.', $decision->customerMessage() );
	}
}
