<?php

declare(strict_types=1);

namespace Compadres\Commerce\Catalog;

use InvalidArgumentException;

final class ImportValidator {

	/** @param list<array<string, string>> $rows */
	public static function validateRows( array $rows, int $first_row_number = 1 ): ImportValidationResult {
		$valid  = array();
		$errors = array();
		foreach ( $rows as $index => $row ) {
			$messages = array();
			foreach ( array( 'product_name', 'sku', 'brand', 'product_type' ) as $required ) {
				if ( '' === trim( (string) ( $row[ $required ] ?? '' ) ) ) {
					$messages[] = $required . ' is required';
				}
			}
			if ( ! in_array( $row['product_type'] ?? '', array( 'simple', 'variable', 'variation' ), true ) ) {
				$messages[] = 'product_type is invalid';
			}
			try {
				ProductData::sanitize( $row );
			} catch ( InvalidArgumentException $exception ) {
				$messages[] = $exception->getMessage();
			}
			if ( $messages ) {
				$errors[] = array(
					'row'      => $index + $first_row_number,
					'messages' => $messages,
				);
			} else {
				$valid[] = $row;
			}
		}
		return new ImportValidationResult( $valid, $errors );
	}
}
