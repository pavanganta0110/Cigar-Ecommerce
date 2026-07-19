<?php

declare(strict_types=1);

namespace Compadres\Commerce\Catalog;

use RuntimeException;

final class ImportErrorReport {

	/** @param list<array{row:int,messages:list<string>}> $errors */
	public static function toCsv( array $errors ): string {
		$stream = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory stream, not a WordPress filesystem path.
		if ( false === $stream ) {
			throw new RuntimeException( 'Unable to open error report stream.' );
		}
		fputcsv( $stream, array( 'row', 'errors' ), ',', '"', '', "\n" );
		foreach ( $errors as $error ) {
			fputcsv( $stream, array( (string) $error['row'], implode( '; ', $error['messages'] ) ), ',', '"', '', "\n" );
		}
		rewind( $stream );
		$csv = stream_get_contents( $stream );
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Paired in-memory stream close.
		if ( false === $csv ) {
			throw new RuntimeException( 'Unable to read error report stream.' );
		}
		return $csv;
	}
}
