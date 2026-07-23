<?php

declare(strict_types=1);

namespace Compadres\Commerce\Restrictions;

use DateTimeImmutable;

final class RuleEvaluator {

	/** @param list<RestrictionRule> $rules */
	public function evaluate( array $rules, RestrictionContext $context, DateTimeImmutable $at ): RestrictionDecision {
		usort(
			$rules,
			static fn ( RestrictionRule $left, RestrictionRule $right ): int => array( $right->priority(), $left->id() ) <=> array( $left->priority(), $right->id() )
		);
		$matched = array();
		$message = '';
		foreach ( $rules as $rule ) {
			if ( $rule->isActiveAt( $at ) && $rule->matches( $context ) ) {
				$matched[] = $rule->id();
				$message   = '' === $message ? $rule->blockedMessage() : $message;
			}
		}
		return new RestrictionDecision( $matched, $message );
	}
}
