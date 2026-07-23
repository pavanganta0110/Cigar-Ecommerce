<?php

declare(strict_types=1);

namespace Compadres\Commerce\Restrictions;

use DateTimeImmutable;
use InvalidArgumentException;

final class RestrictionRule {

	private function __construct(
		private int $id,
		private string $name,
		private string $country,
		private string $blocked_message,
		/** @var list<string> */
		private array $states,
		/** @var list<string> */
		private array $cities,
		/** @var list<string> */
		private array $postal_codes,
		/** @var list<string> */
		private array $postal_prefixes,
		/** @var list<int> */
		private array $product_ids,
		/** @var list<int> */
		private array $category_ids,
		/** @var list<int> */
		private array $brand_ids,
		private int $priority,
		private DateTimeImmutable $effective_at,
		private ?DateTimeImmutable $expires_at
	) {
	}

	/** @param array<string, mixed> $values */
	public static function fromArray( array $values ): self {
		$id           = (int) ( $values['id'] ?? 0 );
		$name         = trim( (string) ( $values['name'] ?? '' ) );
		$country      = strtoupper( trim( (string) ( $values['country'] ?? 'US' ) ) );
		$message      = trim( (string) ( $values['blocked_message'] ?? 'We cannot ship the current cart to this destination. Please update the shipping address or remove the restricted item.' ) );
		$states       = self::strings( $values['state'] ?? array(), static fn ( string $value ): string => strtoupper( trim( $value ) ) );
		$cities       = self::strings( $values['city'] ?? array(), static fn ( string $value ): string => mb_strtolower( trim( $value ) ) );
		$postal_codes = self::strings( $values['postal_code'] ?? array(), self::postal( ... ) );
		$prefixes     = self::strings( $values['postal_prefix'] ?? array(), self::postal( ... ) );
		$product_ids  = self::ids( $values['product_ids'] ?? array() );
		$category_ids = self::ids( $values['category_ids'] ?? array() );
		$brand_ids    = self::ids( $values['brand_ids'] ?? array() );
		$has_scope    = array() !== $states || array() !== $cities || array() !== $postal_codes || array() !== $prefixes || array() !== $product_ids || array() !== $category_ids || array() !== $brand_ids;
		$valid_states = array() === array_filter( $states, static fn ( string $state ): bool => 1 !== preg_match( '/^[A-Z]{2}$/', $state ) );
		if ( $id < 1 || '' === $name || 1 !== preg_match( '/^[A-Z]{2}$/', $country ) || '' === $message || mb_strlen( $message ) > 500 || ! $valid_states || ! $has_scope ) {
			throw new InvalidArgumentException( 'Restriction rule data is invalid.' );
		}
		return new self(
			$id,
			$name,
			$country,
			$message,
			$states,
			$cities,
			$postal_codes,
			$prefixes,
			$product_ids,
			$category_ids,
			$brand_ids,
			(int) ( $values['priority'] ?? 0 ),
			new DateTimeImmutable( (string) ( $values['effective_at'] ?? 'now' ) ),
			isset( $values['expires_at'] ) && '' !== $values['expires_at'] ? new DateTimeImmutable( (string) $values['expires_at'] ) : null
		);
	}

	public function id(): int {
		return $this->id;
	}

	public function name(): string {
		return $this->name;
	}

	public function priority(): int {
		return $this->priority;
	}

	public function blockedMessage(): string {
		return $this->blocked_message;
	}

	public function matches( RestrictionContext $context ): bool {
		return $this->country === $context->country()
			&& self::matchesAnyOrEmpty( $this->states, $context->state() )
			&& self::matchesAnyOrEmpty( $this->cities, $context->city() )
			&& self::matchesAnyOrEmpty( $this->postal_codes, $context->postalCode() )
			&& $this->postalPrefixMatches( $context->postalCode() )
			&& $this->cartScopeMatchesAny( $context );
	}

	public function isActiveAt( DateTimeImmutable $at ): bool {
		return $this->effective_at <= $at && ( null === $this->expires_at || $this->expires_at > $at );
	}

	private static function postal( mixed $value ): string {
		return strtoupper( preg_replace( '/\s+/', '', trim( (string) $value ) ) ?? '' );
	}

	/**
	 * @param callable(string):string $normalize
	 * @return list<string>
	 */
	private static function strings( mixed $values, callable $normalize ): array {
		$values = is_array( $values ) ? $values : array( $values );
		$result = array_values( array_unique( array_filter( array_map( static fn ( mixed $value ): string => $normalize( (string) $value ), $values ) ) ) );
		sort( $result );
		return $result;
	}

	/** @return list<int> */
	private static function ids( mixed $values ): array {
		if ( ! is_array( $values ) ) {
			return array();
		}
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $values ), static fn ( int $id ): bool => $id > 0 ) ) );
		sort( $ids );
		return $ids;
	}

	private function cartScopeMatchesAny( RestrictionContext $context ): bool {
		$has_scope = array() !== $this->product_ids || array() !== $this->category_ids || array() !== $this->brand_ids;
		return ! $has_scope
			|| array() !== array_intersect( $this->product_ids, $context->productIds() )
			|| array() !== array_intersect( $this->category_ids, $context->categoryIds() )
			|| array() !== array_intersect( $this->brand_ids, $context->brandIds() );
	}

	/** @param list<string> $required */
	private static function matchesAnyOrEmpty( array $required, string $actual ): bool {
		return array() === $required || in_array( $actual, $required, true );
	}

	private function postalPrefixMatches( string $postal_code ): bool {
		if ( array() === $this->postal_prefixes ) {
			return true;
		}
		foreach ( $this->postal_prefixes as $prefix ) {
			if ( str_starts_with( $postal_code, $prefix ) ) {
				return true;
			}
		}
		return false;
	}
}
