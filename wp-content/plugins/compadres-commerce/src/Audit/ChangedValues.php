<?php

declare(strict_types=1);

namespace Compadres\Commerce\Audit;

final class ChangedValues {

	/**
	 * @param array<string, mixed> $previous
	 * @param array<string, mixed> $current
	 * @return array{previous:array<string,mixed>,current:array<string,mixed>,changed:bool}
	 */
	public static function between( array $previous, array $current ): array {
		$old_changes = array();
		$new_changes = array();
		foreach ( array_unique( array_merge( array_keys( $previous ), array_keys( $current ) ) ) as $key ) {
			$old_value = $previous[ $key ] ?? null;
			$new_value = $current[ $key ] ?? null;
			if ( $old_value === $new_value ) {
				continue;
			}
			$old_changes[ $key ] = $old_value;
			$new_changes[ $key ] = $new_value;
		}
		return array(
			'previous' => $old_changes,
			'current'  => $new_changes,
			'changed'  => array() !== $old_changes || array() !== $new_changes,
		);
	}
}
