<?php

declare(strict_types=1);

namespace Compadres\Commerce\Infrastructure;

final class Environment {

	private const ALLOWED = array( 'local', 'development', 'staging', 'production' );

	private string $value;

	private function __construct( string $value ) {
		$this->value = in_array( $value, self::ALLOWED, true ) ? $value : 'production';
	}

	public static function fromString( string $value ): self {
		return new self( strtolower( trim( $value ) ) );
	}

	public function value(): string {
		return $this->value;
	}

	public function allowsDevelopmentProviders(): bool {
		return in_array( $this->value, array( 'local', 'development' ), true );
	}

	public function isProduction(): bool {
		return 'production' === $this->value;
	}
}
