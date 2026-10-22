<?php
/**
 * Plugin Name:          German ID Age Verification for WooCommerce – Personalausweis Check
 * Plugin URI:           https://github.com/bealinawaz/german-id-age-verification
 * Description:          Asks for the numbers from a German ID card (Personalausweis) at checkout when the cart contains age-restricted products, and checks the check digits, expiry date, and minimum age. The numbers are never stored.
 * Version:              2.0.0
 * Requires at least:    6.2
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               Ali Nawaz
 * Author URI:           https://bealinawaz.com
 * License:              GPLv2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          german-id-age-verification
 * WC requires at least: 7.1
 * WC tested up to:      11.2
 *
 * @package German_ID_Age_Verification
 */

defined( 'ABSPATH' ) || exit;

define( 'GIDAV_VERSION', '2.0.0' );
define( 'GIDAV_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-gidav-settings.php';
require_once __DIR__ . '/includes/class-gidav-validator.php';
require_once __DIR__ . '/includes/class-gidav-checkout.php';
require_once __DIR__ . '/includes/class-gidav-privacy.php';

// Orders are written through the WooCommerce CRUD API, so both order storage types work.
// The fields use classic checkout hooks, so the Checkout block is declared incompatible
// (and block checkouts with restricted products are refused, see GIDAV_Checkout::block_store_api_checkout()).
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', GIDAV_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', GIDAV_FILE, false );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		add_action( 'init', array( 'GIDAV_Settings', 'maybe_upgrade' ), 5 );
		GIDAV_Checkout::init();
		GIDAV_Privacy::init();

		if ( is_admin() ) {
			require_once __DIR__ . '/includes/class-gidav-admin.php';
			GIDAV_Admin::init();
		}
	}
);
