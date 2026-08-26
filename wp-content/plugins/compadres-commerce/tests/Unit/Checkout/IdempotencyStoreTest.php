<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit\Checkout;

use Compadres\Commerce\Checkout\CartFingerprint;
use Compadres\Commerce\Checkout\IdempotencyStore;
use PHPUnit\Framework\TestCase;

final class IdempotencyStoreTest extends TestCase {

	public function test_a_fresh_fingerprint_may_proceed(): void {
		$store = new IdempotencyStore( new InMemorySessionStore() );

		$decision = $store->acquire( $this->fingerprint() );

		self::assertTrue( $decision->shouldProceed() );
	}

	public function test_a_concurrent_resubmission_of_the_same_cart_is_locked(): void {
		$store       = new IdempotencyStore( new InMemorySessionStore() );
		$fingerprint = $this->fingerprint();

		$store->acquire( $fingerprint );
		$decision = $store->acquire( $fingerprint );

		self::assertFalse( $decision->shouldProceed() );
		self::assertSame( 'locked', $decision->reason() );
		self::assertNull( $decision->existingOrderId() );
	}

	public function test_a_different_cart_is_not_blocked_by_an_unrelated_lock(): void {
		$store = new IdempotencyStore( new InMemorySessionStore() );
		$store->acquire( $this->fingerprint( 1 ) );

		$decision = $store->acquire( $this->fingerprint( 2 ) );

		self::assertTrue( $decision->shouldProceed() );
	}

	public function test_resubmitting_after_a_completed_order_reports_the_duplicate(): void {
		$store       = new IdempotencyStore( new InMemorySessionStore() );
		$fingerprint = $this->fingerprint();

		$store->acquire( $fingerprint );
		$store->recordOrder( $fingerprint, 501 );
		$decision = $store->acquire( $fingerprint );

		self::assertFalse( $decision->shouldProceed() );
		self::assertSame( 'duplicate_order', $decision->reason() );
		self::assertSame( 501, $decision->existingOrderId() );
	}

	public function test_a_lock_expires_after_its_ttl_so_a_recoverable_failure_can_be_retried(): void {
		$time        = 1000;
		$store       = new IdempotencyStore(
			new InMemorySessionStore(),
			static function () use ( &$time ): int {
				return $time;
			}
		);
		$fingerprint = $this->fingerprint();

		$store->acquire( $fingerprint );
		$time    += 61;
		$decision = $store->acquire( $fingerprint );

		self::assertTrue( $decision->shouldProceed() );
	}

	public function test_a_completed_order_is_remembered_past_the_lock_ttl(): void {
		$time        = 1000;
		$store       = new IdempotencyStore(
			new InMemorySessionStore(),
			static function () use ( &$time ): int {
				return $time;
			}
		);
		$fingerprint = $this->fingerprint();

		$store->acquire( $fingerprint );
		$store->recordOrder( $fingerprint, 501 );
		$time    += 61;
		$decision = $store->acquire( $fingerprint );

		self::assertFalse( $decision->shouldProceed() );
		self::assertSame( 501, $decision->existingOrderId() );
	}

	private function fingerprint( int $product_id = 10 ): CartFingerprint {
		return CartFingerprint::fromCart(
			array(
				array(
					'product_id'   => $product_id,
					'variation_id' => 0,
					'quantity'     => 1,
				),
			),
			'US',
			'MO',
			'64111',
			'fedex_ground'
		);
	}
}
