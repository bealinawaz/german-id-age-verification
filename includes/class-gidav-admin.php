<?php
/**
 * Settings screen, order display, and the 1.x data cleanup.
 *
 * @package German_ID_Age_Verification
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin pages, notices, and plugin links.
 */
final class GIDAV_Admin {

	const PAGE          = 'gidav-settings';
	const CAPABILITY    = 'manage_woocommerce';
	const CLEANUP       = 'gidav_delete_legacy_meta';
	const AUTHOR_URL    = 'https://bealinawaz.com/';
	const CONTACT_URL   = 'https://bealinawaz.com/contact-me/';
	const DONATE_URL    = 'https://www.buymeacoffee.com/bealinawaz';
	const RESET_FIELD   = 'gidav_reset_verification';
	const PROFILE_NONCE = 'gidav_profile';

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_notices', array( __CLASS__, 'legacy_notice' ) );
		add_action( 'admin_post_' . self::CLEANUP, array( __CLASS__, 'handle_cleanup' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'order_display' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'profile_section' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'profile_section' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'profile_save' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'profile_save' ) );
		add_filter( 'option_page_capability_' . GIDAV_Settings::GROUP, array( __CLASS__, 'capability' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( GIDAV_FILE ), array( __CLASS__, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
	}

	/**
	 * Lets shop managers save the settings.
	 *
	 * @return string
	 */
	public static function capability() {
		return self::CAPABILITY;
	}

	/**
	 * Adds the settings page under WooCommerce.
	 */
	public static function add_page() {
		add_submenu_page(
			'woocommerce',
			__( 'German ID Age Verification', 'german-id-age-verification' ),
			__( 'Age Verification', 'german-id-age-verification' ),
			self::CAPABILITY,
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Registers the settings option.
	 */
	public static function register_setting() {
		register_setting(
			GIDAV_Settings::GROUP,
			GIDAV_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'GIDAV_Settings', 'sanitize' ),
			)
		);
	}

	/**
	 * Loads the admin styles on the settings page.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		wp_enqueue_style( 'gidav-admin', plugin_dir_url( GIDAV_FILE ) . 'assets/admin.css', array(), GIDAV_VERSION );
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'german-id-age-verification' ) . '</a>' );
		return $links;
	}

	/**
	 * Adds custom development and donation links under the plugin description.
	 *
	 * @param array  $links Plugin meta links.
	 * @param string $file  Plugin file.
	 * @return array
	 */
	public static function row_meta( $links, $file ) {
		if ( plugin_basename( GIDAV_FILE ) !== $file ) {
			return $links;
		}
		$links[] = '<a href="' . esc_url( self::CONTACT_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Custom development', 'german-id-age-verification' ) . '</a>';
		$links[] = '<a href="' . esc_url( self::DONATE_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Buy me a coffee', 'german-id-age-verification' ) . '</a>';
		return $links;
	}

	/**
	 * Asks the admin to delete ID card numbers that version 1.x saved on orders.
	 */
	public static function legacy_notice() {
		if ( 'yes' !== get_option( GIDAV_Settings::LEGACY_FLAG ) || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p><strong><?php esc_html_e( 'German ID Age Verification: old ID card numbers found on orders', 'german-id-age-verification' ); ?></strong></p>
			<p><?php esc_html_e( 'Version 1 of this plugin saved ID card numbers on orders (as the order fields ida, idb, idc, and idd). Version 2 never stores them. We recommend deleting the old numbers to protect your customers\' data. This cannot be undone, so export them first if you are legally required to keep them.', 'german-id-age-verification' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::CLEANUP ); ?>" />
				<?php wp_nonce_field( self::CLEANUP ); ?>
				<p>
					<button type="submit" name="gidav_choice" value="delete" class="button button-primary"><?php esc_html_e( 'Delete old ID card numbers', 'german-id-age-verification' ); ?></button>
					<button type="submit" name="gidav_choice" value="keep" class="button"><?php esc_html_e( 'Keep them and hide this notice', 'german-id-age-verification' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Deletes the 1.x order meta, or hides the notice when the admin chooses to keep it.
	 */
	public static function handle_cleanup() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'german-id-age-verification' ), 403 );
		}
		check_admin_referer( self::CLEANUP );
		$choice = isset( $_POST['gidav_choice'] ) ? sanitize_key( wp_unslash( $_POST['gidav_choice'] ) ) : '';
		if ( 'delete' === $choice ) {
			$deleted = GIDAV_Settings::delete_legacy_order_meta();
			wp_safe_redirect( add_query_arg( 'gidav_deleted', $deleted, admin_url( 'admin.php?page=' . self::PAGE ) ) );
			exit;
		}
		if ( 'keep' === $choice ) {
			delete_option( GIDAV_Settings::LEGACY_FLAG );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}

	/**
	 * Shows the verification result on the order screen.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function order_display( $order ) {
		if ( ! $order instanceof WC_Order || 'yes' !== $order->get_meta( GIDAV_Checkout::META_STATUS ) ) {
			return;
		}
		$method = 'german_id_saved' === $order->get_meta( GIDAV_Checkout::META_METHOD )
			? __( 'German ID card (saved verification)', 'german-id-age-verification' )
			: __( 'German ID card', 'german-id-age-verification' );
		$date   = (string) $order->get_meta( GIDAV_Checkout::META_DATE );
		$time   = $date ? strtotime( $date . ' UTC' ) : false;
		?>
		<div class="gidav-order">
			<p>
				<strong><?php esc_html_e( 'Age verified:', 'german-id-age-verification' ); ?></strong>
				<?php
				printf(
					/* translators: 1: verification method, 2: minimum age. */
					esc_html__( 'Yes, %1$s, minimum age %2$d', 'german-id-age-verification' ),
					esc_html( $method ),
					(int) $order->get_meta( GIDAV_Checkout::META_AGE )
				);
				?>
				<?php if ( $time ) : ?>
					<br /><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ) ); ?>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Shows the saved verification on the user profile, with an option to remove it.
	 *
	 * @param WP_User $user User being edited.
	 */
	public static function profile_section( $user ) {
		if ( ! current_user_can( self::CAPABILITY ) && get_current_user_id() !== $user->ID ) {
			return;
		}
		$saved = get_user_meta( $user->ID, GIDAV_Checkout::USER_META, true );
		if ( ! is_array( $saved ) || empty( $saved['date'] ) ) {
			return;
		}
		?>
		<h2><?php esc_html_e( 'Age verification', 'german-id-age-verification' ); ?></h2>
		<table class="form-table" role="presentation"><tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'German ID card', 'german-id-age-verification' ); ?></th>
				<td>
					<p>
						<?php
						printf(
							/* translators: 1: date, 2: minimum age. */
							esc_html__( 'Verified on %1$s for a minimum age of %2$d. No ID numbers are stored.', 'german-id-age-verification' ),
							esc_html( date_i18n( get_option( 'date_format' ), strtotime( $saved['date'] ) ) ),
							(int) $saved['min_age']
						);
						?>
					</p>
					<?php wp_nonce_field( self::PROFILE_NONCE, self::PROFILE_NONCE . '_nonce' ); ?>
					<label><input type="checkbox" name="<?php echo esc_attr( self::RESET_FIELD ); ?>" value="1" /> <?php esc_html_e( 'Remove the saved verification (the ID card is checked again at the next order)', 'german-id-age-verification' ); ?></label>
				</td>
			</tr>
		</tbody></table>
		<?php
	}

	/**
	 * Removes the saved verification when requested on the profile screen.
	 *
	 * @param int $user_id User ID.
	 */
	public static function profile_save( $user_id ) {
		if ( ! isset( $_POST[ self::PROFILE_NONCE . '_nonce' ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::PROFILE_NONCE . '_nonce' ] ) ), self::PROFILE_NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_user', $user_id ) || empty( $_POST[ self::RESET_FIELD ] ) ) {
			return;
		}
		delete_user_meta( $user_id, GIDAV_Checkout::USER_META );
	}

	/**
	 * Settings field name.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private static function name( $key ) {
		return GIDAV_Settings::OPTION . '[' . $key . ']';
	}

	/**
	 * Renders product categories as a nested checkbox list.
	 *
	 * @param array $selected Selected term IDs.
	 * @param int   $parent   Parent term ID.
	 */
	private static function category_checkboxes( $selected, $parent = 0 ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'parent'     => $parent,
			)
		);
		if ( is_wp_error( $terms ) || ! $terms ) {
			return;
		}
		echo '<ul class="gidav-cats">';
		foreach ( $terms as $term ) {
			echo '<li><label><input type="checkbox" name="' . esc_attr( self::name( 'categories' ) ) . '[]" value="' . esc_attr( (string) $term->term_id ) . '" ' . checked( in_array( (int) $term->term_id, $selected, true ), true, false ) . ' /> ' . esc_html( $term->name ) . '</label>';
			self::category_checkboxes( $selected, (int) $term->term_id );
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Whether the checkout page uses the Checkout block, which this plugin does not support.
	 *
	 * @return bool
	 */
	private static function uses_checkout_block() {
		$page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'checkout' ) : 0;
		return $page_id > 0 && has_block( 'woocommerce/checkout', $page_id );
	}

	/**
	 * Renders the settings page.
	 */
	public static function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$s = GIDAV_Settings::get();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only result of the nonce-checked cleanup action.
		$deleted = isset( $_GET['gidav_deleted'] ) ? absint( $_GET['gidav_deleted'] ) : null;
		?>
		<div class="wrap gidav-settings">
			<h1><?php esc_html_e( 'German ID Age Verification', 'german-id-age-verification' ); ?></h1>

			<?php if ( null !== $deleted ) : ?>
				<div class="notice notice-success inline">
					<?php /* translators: %d: number of deleted entries. */ ?>
					<p><?php echo esc_html( sprintf( _n( 'Deleted %d old ID card entry from orders.', 'Deleted %d old ID card entries from orders.', $deleted, 'german-id-age-verification' ), $deleted ) ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( self::uses_checkout_block() ) : ?>
				<div class="notice notice-error inline">
					<p><?php esc_html_e( 'Your checkout page uses the Checkout block, which cannot show the ID card fields. Orders with restricted products are refused there with an error. Replace the Checkout block with the [woocommerce_checkout] shortcode (or the "Classic Checkout" block) so customers can verify their age.', 'german-id-age-verification' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="notice notice-info inline">
				<p><?php esc_html_e( 'This check confirms that the ID card numbers are well-formed, the card has not expired, and the date of birth on it meets the minimum age. It does not prove that the customer owns the card, and it is not a certified age verification system (AVS) under the German JuSchG or JMStV. You are responsible for meeting the legal requirements for the products you sell.', 'german-id-age-verification' ); ?></p>
			</div>

			<form action="options.php" method="post">
				<?php settings_fields( GIDAV_Settings::GROUP ); ?>
				<table class="form-table" role="presentation"><tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Restricted categories', 'german-id-age-verification' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Restricted categories', 'german-id-age-verification' ); ?></legend>
								<div class="gidav-cats-box">
									<?php self::category_checkboxes( $s['categories'] ); ?>
								</div>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Customers must verify their age when the cart contains a product from one of these categories or their subcategories. Nothing is checked until you choose at least one category.', 'german-id-age-verification' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gidav-min-age"><?php esc_html_e( 'Minimum age', 'german-id-age-verification' ); ?></label></th>
						<td><input id="gidav-min-age" class="small-text" type="number" min="1" max="99" name="<?php echo esc_attr( self::name( 'min_age' ) ); ?>" value="<?php echo esc_attr( (string) $s['min_age'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="gidav-heading"><?php esc_html_e( 'Heading', 'german-id-age-verification' ); ?></label></th>
						<td><input id="gidav-heading" class="regular-text" type="text" name="<?php echo esc_attr( self::name( 'heading' ) ); ?>" value="<?php echo esc_attr( $s['heading'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="gidav-help"><?php esc_html_e( 'Help text', 'german-id-age-verification' ); ?></label></th>
						<td><textarea id="gidav-help" class="large-text" rows="3" name="<?php echo esc_attr( self::name( 'help' ) ); ?>"><?php echo esc_textarea( $s['help'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Remember customers', 'german-id-age-verification' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::name( 'remember' ) ); ?>" value="1" <?php checked( $s['remember'] ); ?> /> <?php esc_html_e( 'Skip the check for logged-in customers who passed it before', 'german-id-age-verification' ); ?></label>
							<p class="description"><?php esc_html_e( 'Only the date of the check and the minimum age are saved on the customer account, never the ID numbers or the date of birth. Raising the minimum age asks everyone again.', 'german-id-age-verification' ); ?></p>
						</td>
					</tr>
				</tbody></table>
				<?php submit_button(); ?>
			</form>
			<?php self::render_support(); ?>
		</div>
		<?php
	}

	/**
	 * Renders the author box with support, custom work, and donation links.
	 */
	private static function render_support() {
		?>
		<div class="gidav-support">
			<h2><?php esc_html_e( 'Need help or a custom version?', 'german-id-age-verification' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: link to the author's website. */
					esc_html__( 'German ID Age Verification is free and built by Ali Nawaz, a WordPress and WooCommerce developer at %s. Need an integration with a certified age verification service, Checkout block support, or other custom WooCommerce checkout rules?', 'german-id-age-verification' ),
					'<a href="' . esc_url( self::AUTHOR_URL ) . '" target="_blank" rel="noopener">bealinawaz.com</a>'
				);
				?>
			</p>
			<p class="gidav-support__actions">
				<a class="button button-primary" href="<?php echo esc_url( self::CONTACT_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Hire me for custom work', 'german-id-age-verification' ); ?></a>
				<a class="button" href="<?php echo esc_url( self::AUTHOR_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Visit bealinawaz.com', 'german-id-age-verification' ); ?></a>
				<a class="button" href="<?php echo esc_url( self::DONATE_URL ); ?>" target="_blank" rel="noopener">&#9749; <?php esc_html_e( 'Buy me a coffee', 'german-id-age-verification' ); ?></a>
			</p>
		</div>
		<?php
	}
}
