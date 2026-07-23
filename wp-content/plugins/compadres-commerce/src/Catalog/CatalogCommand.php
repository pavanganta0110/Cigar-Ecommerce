<?php

declare(strict_types=1);

namespace Compadres\Commerce\Catalog;

use InvalidArgumentException;

final class CatalogCommand {

	/**
	 * Validate a Compadres product CSV before using WooCommerce's importer.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : CSV file to validate.
	 *
	 * [--error-report=<file>]
	 * : Error report destination. Defaults to <file>.errors.csv.
	 *
	 * ## EXAMPLES
	 *
	 *     wp compadres catalog validate products.csv
	 *
	 * @param list<string>          $args Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function validate( array $args, array $assoc_args ): void {
		$path = $args[0] ?? '';
		if ( '' === $path ) {
			\WP_CLI::error( 'A CSV file path is required.' );
			return;
		}

		try {
			$result = ( new CsvCatalogReader() )->validateFile( $path );
		} catch ( InvalidArgumentException $exception ) {
			\WP_CLI::error( $exception->getMessage() );
			return;
		}

		$errors = $result->errors();
		if ( $errors ) {
			$report_path = $assoc_args['error-report'] ?? $path . '.errors.csv';
			// WP-CLI writes to the explicit operator-supplied local path; WP_Filesystem credential flows do not apply.
			$written = file_put_contents( $report_path, ImportErrorReport::toCsv( $errors ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			if ( false === $written ) {
				\WP_CLI::error( 'Unable to write the CSV error report.' );
			}
			\WP_CLI::error( sprintf( '%d invalid row(s). Error report: %s', count( $errors ), $report_path ) );
		}

		\WP_CLI::success( sprintf( '%d product row(s) are valid for WooCommerce import.', count( $result->validRows() ) ) );
	}
}
