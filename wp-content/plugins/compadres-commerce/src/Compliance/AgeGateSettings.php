<?php

declare(strict_types=1);

namespace Compadres\Commerce\Compliance;

final class AgeGateSettings {

	/** @return array{enabled:bool,title:string,explanatory_text:string,confirmation_label:string,exit_label:string,exit_url:string,cookie_lifetime_hours:int,same_site:string} */
	public static function defaults(): array {
		return array(
			'enabled'               => true,
			'title'                 => 'Are you 21 or older?',
			'explanatory_text'      => 'You must be at least 21 years old to enter this website. Site entry confirmation does not perform identity verification.',
			'confirmation_label'    => 'I am 21 or older',
			'exit_label'            => 'Exit website',
			'exit_url'              => 'https://www.google.com/',
			'cookie_lifetime_hours' => 720,
			'same_site'             => 'Lax',
		);
	}

	/**
	 * @param  array<string, mixed> $input Raw settings.
	 * @return array{enabled:bool,title:string,explanatory_text:string,confirmation_label:string,exit_label:string,exit_url:string,cookie_lifetime_hours:int,same_site:string}
	 */
	public static function sanitize( array $input ): array {
		$defaults  = self::defaults();
		$same_site = (string) ( $input['same_site'] ?? $defaults['same_site'] );
		if ( ! in_array( $same_site, array( 'Lax', 'Strict', 'None' ), true ) ) {
			$same_site = 'Lax';
		}
		$exit_url = trim( (string) ( $input['exit_url'] ?? $defaults['exit_url'] ) );
		if ( false === filter_var( $exit_url, FILTER_VALIDATE_URL ) ) {
			$exit_url = $defaults['exit_url'];
		}
		return array(
			'enabled'               => in_array( $input['enabled'] ?? false, array( true, 1, '1', 'on', 'yes' ), true ),
			'title'                 => self::text( $input['title'] ?? $defaults['title'] ),
			'explanatory_text'      => self::text( $input['explanatory_text'] ?? $defaults['explanatory_text'] ),
			'confirmation_label'    => self::text( $input['confirmation_label'] ?? $defaults['confirmation_label'] ),
			'exit_label'            => self::text( $input['exit_label'] ?? $defaults['exit_label'] ),
			'exit_url'              => $exit_url,
			'cookie_lifetime_hours' => min( 8760, max( 1, (int) ( $input['cookie_lifetime_hours'] ?? $defaults['cookie_lifetime_hours'] ) ) ),
			'same_site'             => $same_site,
		);
	}

	private static function text( mixed $value ): string {
		return trim( strip_tags( (string) $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Domain sanitizer remains usable without WordPress loaded.
	}
}
