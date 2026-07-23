<?php

/**
 * Compadres Commerce intentionally retains audit records on uninstall.
 *
 * Operational audit history is not disposable plugin configuration. Database
 * removal requires a separately reviewed retention and data-destruction
 * procedure; ordinary deactivation and uninstall therefore leave it intact.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
