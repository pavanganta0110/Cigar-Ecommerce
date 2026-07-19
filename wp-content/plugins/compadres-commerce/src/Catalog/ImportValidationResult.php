<?php

declare(strict_types=1);

namespace Compadres\Commerce\Catalog;

final class ImportValidationResult {
	/**
	 * @param list<array<string, string>>                $valid_rows Valid rows.
	 * @param list<array{row:int,messages:list<string>}> $errors Invalid-row reports.
	 */
	public function __construct( private array $valid_rows, private array $errors ) {}
	/** @return list<array<string, string>> */
	public function validRows(): array {
		return $this->valid_rows; }
	/** @return list<array{row:int,messages:list<string>}> */
	public function errors(): array {
		return $this->errors; }
}
