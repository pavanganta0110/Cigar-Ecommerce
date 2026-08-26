<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tax;

/** Immutable checkout sales-tax rule and source provenance. */
final class ManualSalesTaxRule {
	public function __construct(
		private string $state,
		private string $rate_percent,
		private string $effective_from
	) {
	}

	public function state(): string {
		return $this->state;
	}

	public function ratePercent(): string {
		return $this->rate_percent;
	}

	public function effectiveFrom(): string {
		return $this->effective_from;
	}

	public function calculationBasis(): string {
		return 'avg_combined_reference';
	}

	public function sourceColumn(): string {
		return 'Avg Combined Reference %';
	}

	public function sourceDocument(): string {
		return 'Compadres_Cigars_50_State_Tobacco_Tax_Matrix_2026.xlsx - 50-State Tax Matrix.pdf';
	}

	public function sourceSha256(): string {
		return '802f4b18906fe7e6a25c179885ad7fb2b7a536951ab1a9d17b98bdfa249e36b3';
	}

	public function ruleVersion(): string {
		return ManualSalesTaxInstaller::VERSION;
	}

	public function shippingTaxable(): bool {
		return false;
	}

	public function isAverageReference(): bool {
		return true;
	}
}
