<?php
/**
 * Removes the plugin settings and the saved verification flags on customer accounts.
 * The "age verified" records on orders are kept, because they are part of the order history.
 *
 * @package German_ID_Age_Verification
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'gidav_settings' );
delete_option( 'gidav_version' );
delete_option( 'gidav_legacy_order_meta' );
delete_metadata( 'user', 0, 'gidav_age_verified', '', true );
