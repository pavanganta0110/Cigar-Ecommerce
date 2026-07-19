<?php

declare(strict_types=1);

namespace Compadres\Commerce\Audit;

use Closure;
use Compadres\Commerce\Infrastructure\Environment;
use Compadres\Commerce\Infrastructure\Redactor;
use DateTimeImmutable;
use Throwable;

final class AuditService {

	private Closure $now;
	private Closure $correlationId;
	private Closure $failureReporter;

	public function __construct(
		private AuditStore $store,
		private Redactor $redactor,
		private Environment $environment,
		callable $now,
		callable $correlation_id,
		?callable $failure_reporter = null
	) {
		$this->now             = Closure::fromCallable( $now );
		$this->correlationId   = Closure::fromCallable( $correlation_id );
		$this->failureReporter = Closure::fromCallable( $failure_reporter ?? static function (): void {} );
	}

	/**
	 * @param array<string, mixed> $previous_value
	 * @param array<string, mixed> $new_value
	 * @param array<string, mixed> $request_context
	 */
	public function entityChange( string $event_type, string $entity_type, string $entity_id, array $previous_value, array $new_value, int $user_id, array $request_context = array() ): int|false {
		return $this->write( $event_type, $user_id, $entity_type, $entity_id, $previous_value, $new_value, 'success', '', $request_context );
	}

	/** @param array<string, mixed> $context */
	public function success( string $event_type, int $user_id, string $entity_type = '', string $entity_id = '', array $context = array() ): int|false {
		return $this->write( $event_type, $user_id, $entity_type, $entity_id, array(), array(), 'success', '', $context );
	}

	/** @param array<string, mixed> $context */
	public function failure( string $event_type, string $failure_reason, int $user_id, string $entity_type = '', string $entity_id = '', array $context = array() ): int|false {
		return $this->write( $event_type, $user_id, $entity_type, $entity_id, array(), array(), 'failure', $failure_reason, $context );
	}

	/**
	 * @param array<string, mixed> $previous_value
	 * @param array<string, mixed> $new_value
	 * @param array<string, mixed> $request_context
	 */
	private function write( string $event_type, int $user_id, string $entity_type, string $entity_id, array $previous_value, array $new_value, string $result, string $failure_reason, array $request_context ): int|false {
		try {
			/** @var DateTimeImmutable $now */
			$now      = ( $this->now )();
			$audit_id = $this->store->insert(
				array(
					'event_type'      => $event_type,
					'user_id'         => $user_id,
					'entity_type'     => $entity_type,
					'entity_id'       => $entity_id,
					'previous_value'  => $this->redactor->redact( $previous_value ),
					'new_value'       => $this->redactor->redact( $new_value ),
					'result'          => $result,
					'failure_reason'  => $this->redactor->redactMessage( $failure_reason ),
					'correlation_id'  => (string) ( $this->correlationId )(),
					'environment'     => $this->environment->value(),
					'request_context' => $this->redactor->redact( $request_context ),
					'created_at'      => $now->format( 'Y-m-d H:i:s' ),
				)
			);
			if ( false === $audit_id ) {
				( $this->failureReporter )( 'Audit storage returned failure.' );
			}
			return $audit_id;
		} catch ( Throwable $exception ) {
			( $this->failureReporter )( $this->redactor->redactMessage( $exception->getMessage() ) );
			return false;
		}
	}
}
