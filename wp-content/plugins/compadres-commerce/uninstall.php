<?php
/**
 * Compadres Commerce uninstall handler.
 *
 * Commercial, compliance, order, audit, and provider records are deliberately
 * retained. Destructive erasure requires a separately reviewed maintenance tool.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'compadres_commerce_schema_version' );
