<?php

declare(strict_types=1);

namespace Compadres\Commerce\Checkout;

/**
 * Session-scoped duplicate-order guard keyed by cart fingerprint.
 *
 * A lock is held for a bounded TTL rather than released explicitly on
 * failure: checkout validation failures happen inside independent
 * WordPress hook callbacks this store cannot wrap in a try/finally, so a
 * short lease that self-expires is the only mechanism that reliably lets a
 * customer retry after a recoverable failure without ever allowing two
 * orders to be created from the same submitted cart within the lease.
 */
final class IdempotencyStore {

	private const SESSION_KEY      = 'compadres_checkout_idempotency';
	private const LOCK_TTL_SECONDS = 60;
	private const MAX_RECORDS      = 20;

	/** @var callable(): int */
	private $now;

	public function __construct( private SessionStore $session, ?callable $now = null ) {
		$this->now = $now ?? static fn (): int => time();
	}

	public function acquire( CartFingerprint $fingerprint ): IdempotencyDecision {
		$now     = ( $this->now )();
		$records = $this->records();
		$key     = $fingerprint->value();
		$record  = $records[ $key ] ?? null;

		if ( is_array( $record ) ) {
			if ( isset( $record['order_id'] ) && is_int( $record['order_id'] ) ) {
				return IdempotencyDecision::duplicateOrder( $record['order_id'] );
			}
			if ( isset( $record['locked_at'] ) && is_int( $record['locked_at'] ) && $record['locked_at'] + self::LOCK_TTL_SECONDS > $now ) {
				return IdempotencyDecision::locked();
			}
		}

		$records[ $key ] = array( 'locked_at' => $now );
		$this->save( $this->prune( $records, $now ) );
		return IdempotencyDecision::proceed();
	}

	public function recordOrder( CartFingerprint $fingerprint, int $order_id ): void {
		$now                              = ( $this->now )();
		$records                          = $this->records();
		$records[ $fingerprint->value() ] = array(
			'locked_at' => $now,
			'order_id'  => $order_id,
		);
		$this->save( $this->prune( $records, $now ) );
	}

	/** @return array<string, mixed> */
	private function records(): array {
		$records = $this->session->get( self::SESSION_KEY, array() );
		return is_array( $records ) ? $records : array();
	}

	/** @param array<string, mixed> $records */
	private function save( array $records ): void {
		$this->session->set( self::SESSION_KEY, $records );
	}

	/**
	 * @param array<string, mixed> $records
	 * @return array<string, mixed>
	 */
	private function prune( array $records, int $now ): array {
		$records = array_filter(
			$records,
			static function ( mixed $record ) use ( $now ): bool {
				if ( ! is_array( $record ) ) {
					return false;
				}
				if ( isset( $record['order_id'] ) && is_int( $record['order_id'] ) ) {
					return true;
				}
				return isset( $record['locked_at'] ) && is_int( $record['locked_at'] ) && $record['locked_at'] + self::LOCK_TTL_SECONDS > $now;
			}
		);
		return count( $records ) > self::MAX_RECORDS ? array_slice( $records, -self::MAX_RECORDS, null, true ) : $records;
	}
}
