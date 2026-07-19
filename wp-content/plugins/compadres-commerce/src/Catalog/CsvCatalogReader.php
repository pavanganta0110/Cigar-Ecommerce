<?php

declare(strict_types=1);

namespace Compadres\Commerce\Catalog;

use InvalidArgumentException;
use SplFileObject;

final class CsvCatalogReader {

	private const REQUIRED_COLUMNS = array( 'product_name', 'sku', 'brand', 'product_type' );

	public function validateFile( string $path ): ImportValidationResult {
		if ( ! is_readable( $path ) ) {
			throw new InvalidArgumentException( 'CSV file is not readable.' );
		}

		$file   = new SplFileObject( $path, 'r' );
		$header = $file->fgetcsv();
		if ( false === $header || array( null ) === $header ) {
			throw new InvalidArgumentException( 'CSV file is empty.' );
		}

		$columns = array_map( array( $this, 'normalizeHeader' ), $header );
		$missing = array_values( array_diff( self::REQUIRED_COLUMNS, $columns ) );
		if ( $missing ) {
			throw new InvalidArgumentException( 'Missing required CSV columns: ' . implode( ', ', $missing ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$rows = array();
		while ( ! $file->eof() ) {
			$values = $file->fgetcsv();
			if ( false === $values || array( null ) === $values ) {
				continue;
			}
			$values = array_pad( $values, count( $columns ), '' );
			/** @var array<string, string> $row */
			$row    = array_combine( $columns, array_map( static fn ( mixed $value ): string => trim( (string) $value ), array_slice( $values, 0, count( $columns ) ) ) );
			$rows[] = $row;
		}

		return ImportValidator::validateRows( $rows, 2 );
	}

	private function normalizeHeader( mixed $header ): string {
		return strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header ) ?? (string) $header ) );
	}
}
