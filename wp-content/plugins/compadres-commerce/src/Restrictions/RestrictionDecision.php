<?php

declare(strict_types=1);

namespace Compadres\Commerce\Restrictions;

final class RestrictionDecision {

	/** @param list<int> $rule_ids */
	public function __construct( private array $rule_ids, private string $customer_message = '' ) {
	}

	public function isBlocked(): bool {
		return array() !== $this->rule_ids;
	}

	/** @return list<int> */
	public function ruleIds(): array {
		return $this->rule_ids;
	}

	public function customerMessage(): string {
		return $this->customer_message;
	}
}
