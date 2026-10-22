<?php
/**
 * Plugin settings and upgrade routine.
 *
 * @package German_ID_Age_Verification
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads, validates, and migrates the plugin settings.
 */
final class GIDAV_Settings {

	const OPTION         = 'gidav_settings';
	const GROUP          = 'gidav';
	const VERSION_OPTION = 'gidav_version';
	const LEGACY_FLAG    = 'gidav_legacy_order_meta';
	const LEGACY_KEYS    = array( 'ida', 'idb', 'idc', 'idd' );

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'categories' => array(),
			'min_age'    => 18,
			'heading'    => __( 'Age verification', 'german-id-age-verification' ),
			'help'       => __( 'Your order contains products that we may only sell to adults. Please enter the numbers from the machine-readable zone of your German ID card (Personalausweis). They are checked and then discarded, never stored.', 'german-id-age-verification' ),
			'remember'   => true,
		);
	}

	/**
	 * Current settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$saved    = get_option( self::OPTION, array() );
		$settings = array_replace( self::defaults(), is_array( $saved ) ? $saved : array() );

		$settings['categories'] = array_values( array_filter( array_map( 'absint', (array) $settings['categories'] ) ) );
		$settings['min_age']    = max( 1, min( 99, (int) $settings['min_age'] ) );
		if ( '' === trim( (string) $settings['heading'] ) ) {
			$settings['heading'] = self::defaults()['heading'];
		}
		return $settings;
	}

	/**
	 * Sanitizes the settings form.
	 *
	 * @param mixed $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input      = is_array( $input ) ? $input : array();
		$categories = array();
		if ( isset( $input['categories'] ) && is_array( $input['categories'] ) ) {
			foreach ( $input['categories'] as $term_id ) {
				$term_id = absint( $term_id );
				if ( $term_id && term_exists( $term_id, 'product_cat' ) ) {
					$categories[] = $term_id;
				}
			}
		}

		$min_age = isset( $input['min_age'] ) ? absint( $input['min_age'] ) : 18;

		return array(
			'categories' => array_values( array_unique( $categories ) ),
			'min_age'    => max( 1, min( 99, $min_age ? $min_age : 18 ) ),
			'heading'    => isset( $input['heading'] ) ? sanitize_text_field( $input['heading'] ) : '',
			'help'       => isset( $input['help'] ) ? sanitize_textarea_field( $input['help'] ) : '',
			'remember'   => ! empty( $input['remember'] ),
		);
	}

	/**
	 * Runs once after an update from 1.x.
	 *
	 * - Preselects an existing "FSK18" product category (1.x created it automatically).
	 * - Deletes the ID card numbers 1.x stored in user meta.
	 * - Flags old order meta so an admin can delete it with one click (see GIDAV_Admin).
	 */
	public static function maybe_upgrade() {
		$installed = (string) get_option( self::VERSION_OPTION, '0' );
		if ( version_compare( $installed, GIDAV_VERSION, '>=' ) ) {
			return;
		}

		if ( false === get_option( self::OPTION, false ) ) {
			$settings = self::defaults();
			$term     = get_term_by( 'slug', 'fsk18', 'product_cat' );
			if ( ! $term ) {
				$term = get_term_by( 'name', 'FSK18', 'product_cat' );
			}
			if ( $term && ! is_wp_error( $term ) ) {
				$settings['categories'] = array( (int) $term->term_id );
			}
			update_option( self::OPTION, $settings );
		}

		if ( '0' === $installed ) {
			self::delete_legacy_user_meta();
			if ( self::has_legacy_order_meta() ) {
				update_option( self::LEGACY_FLAG, 'yes', false );
			}
		}

		update_option( self::VERSION_OPTION, GIDAV_VERSION );
	}

	/**
	 * Deletes the raw ID card numbers that 1.x saved for customers.
	 *
	 * Only users that also have the 1.x "verify_age" flag are touched, so meta with the same
	 * generic names from other plugins is left alone.
	 */
	private static function delete_legacy_user_meta() {
		$user_ids = get_users(
			array(
				'meta_key'     => 'verify_age', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-time upgrade.
				'meta_compare' => 'EXISTS',
				'fields'       => 'ID',
				'number'       => -1,
			)
		);
		foreach ( $user_ids as $user_id ) {
			foreach ( array_merge( self::LEGACY_KEYS, array( 'verify_age' ) ) as $key ) {
				delete_user_meta( (int) $user_id, $key );
			}
		}
	}

	/**
	 * Whether any order still has 1.x ID card meta.
	 *
	 * @return bool
	 */
	public static function has_legacy_order_meta() {
		global $wpdb;
		$keys         = self::LEGACY_KEYS;
		$placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-time upgrade check; placeholders are built from a fixed list.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.meta_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type IN ('shop_order', 'shop_order_placehold') AND pm.meta_key IN ($placeholders) LIMIT 1",
				$keys
			)
		);
		if ( ! $found && self::hpos_meta_table_exists() ) {
			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key IN ($placeholders) LIMIT 1",
					$keys
				)
			);
		}
		// phpcs:enable

		return (bool) $found;
	}

	/**
	 * Deletes 1.x ID card meta from all orders, in both order storage tables.
	 *
	 * @return int Rows deleted.
	 */
	public static function delete_legacy_order_meta() {
		global $wpdb;
		$keys         = self::LEGACY_KEYS;
		$placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-time cleanup; placeholders are built from a fixed list.
		$deleted = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE pm FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type IN ('shop_order', 'shop_order_placehold') AND pm.meta_key IN ($placeholders)",
				$keys
			)
		);
		if ( self::hpos_meta_table_exists() ) {
			$deleted += (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key IN ($placeholders)",
					$keys
				)
			);
		}
		// phpcs:enable

		if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'orders' );
			wp_cache_flush_group( 'post_meta' );
		}
		delete_option( self::LEGACY_FLAG );
		return $deleted;
	}

	/**
	 * Whether the HPOS order meta table exists.
	 *
	 * @return bool
	 */
	private static function hpos_meta_table_exists() {
		global $wpdb;
		$table = $wpdb->prefix . 'wc_orders_meta';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Schema check during a one-time cleanup.
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}
}
