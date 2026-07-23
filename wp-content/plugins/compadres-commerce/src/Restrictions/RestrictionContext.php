<?php

declare(strict_types=1);

namespace Compadres\Commerce\Restrictions;

final class RestrictionContext {

	/**
	 * @param list<int> $product_ids
	 * @param list<int> $category_ids
	 * @param list<int> $brand_ids
	 */
	public function __construct(
		private string $state,
		private string $city,
		private string $postal_code,
		private array $product_ids,
		private array $category_ids,
		private array $brand_ids,
		private string $country = 'US'
	) {
	}

	public function state(): string {
		return strtoupper( trim( $this->state ) );
	}

	public function country(): string {
		return strtoupper( trim( $this->country ) );
	}

	public function city(): string {
		return mb_strtolower( trim( $this->city ) );
	}

	public function postalCode(): string {
		return strtoupper( preg_replace( '/\s+/', '', trim( $this->postal_code ) ) ?? '' );
	}

	/** @return list<int> */
	public function productIds(): array {
		return $this->product_ids;
	}

	/** @return list<int> */
	public function categoryIds(): array {
		return $this->category_ids;
	}

	/** @return list<int> */
	public function brandIds(): array {
		return $this->brand_ids;
	}
}
