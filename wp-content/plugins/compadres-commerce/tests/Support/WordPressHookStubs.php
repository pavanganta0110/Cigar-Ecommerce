<?php

declare(strict_types=1);

if ( ! function_exists( 'add_action' ) ) {
	/** @param callable|array{object|string,string} $callback */
	function add_action( string $hook, callable|array $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$GLOBALS['compadres_test_hooks'][] = array( 'action', $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/** @param callable|array{object|string,string} $callback */
	function add_filter( string $hook, callable|array $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$GLOBALS['compadres_test_hooks'][] = array( 'filter', $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	/** @param callable|array{object|string,string} $callback */
	function add_shortcode( string $tag, callable|array $callback ): void {
		$GLOBALS['compadres_test_hooks'][] = array( 'shortcode', $tag, $callback, 10, 1 );
	}
}
