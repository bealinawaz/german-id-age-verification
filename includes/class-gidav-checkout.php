<?php
/**
 * Checkout fields, validation, and order records.
 *
 * @package German_ID_Age_Verification
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shows the German ID card fields when the cart contains age-restricted products.
 */
final class GIDAV_Checkout {

	const USER_META   = 'gidav_age_verified';
	const META_STATUS = '_gidav_age_verified';
	const META_METHOD = '_gidav_method';
	const META_DATE   = '_gidav_verified_at';
	const META_AGE    = '_gidav_min_age';

	/**
	 * Verification result for the order being placed: "", "id_card", or "saved".
	 *
	 * @var string
	 */
	private static $result = '';

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_after_order_notes', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_to_order' ) );
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'add_order_note' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'block_store_api_checkout' ) );
	}

	/**
	 * Checkout field names, keyed by validator key.
	 *
	 * @return array
	 */
	public static function fields() {
		return array(
			'doc'    => 'gidav_doc',
			'birth'  => 'gidav_birth',
			'expiry' => 'gidav_expiry',
			'check'  => 'gidav_check',
		);
	}

	/**
	 * Field labels, keyed by validator key.
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			'doc'    => __( '1. Document number', 'german-id-age-verification' ),
			'birth'  => __( '2. Date of birth and check digit', 'german-id-age-verification' ),
			'expiry' => __( '3. Expiry date and check digit', 'german-id-age-verification' ),
			'check'  => __( '4. Final check digit', 'german-id-age-verification' ),
		);
	}

	/**
	 * Restricted category IDs, including their subcategories.
	 *
	 * @return int[]
	 */
	public static function restricted_categories() {
		$ids = GIDAV_Settings::get()['categories'];
		foreach ( $ids as $id ) {
			$children = get_term_children( $id, 'product_cat' );
			if ( is_array( $children ) ) {
				$ids = array_merge( $ids, $children );
			}
		}
		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * Whether the cart contains a product from a restricted category.
	 *
	 * @param WC_Cart|null $cart Cart.
	 * @return bool
	 */
	public static function cart_requires_verification( $cart = null ) {
		$cart = $cart ? $cart : ( function_exists( 'WC' ) ? WC()->cart : null );
		if ( ! $cart ) {
			return false;
		}
		$categories = self::restricted_categories();
		$requires   = false;
		if ( $categories ) {
			foreach ( $cart->get_cart() as $item ) {
				if ( ! empty( $item['product_id'] ) && has_term( $categories, 'product_cat', (int) $item['product_id'] ) ) {
					$requires = true;
					break;
				}
			}
		}

		/**
		 * Filters whether the cart needs German ID age verification.
		 *
		 * @param bool    $requires Whether verification is required.
		 * @param WC_Cart $cart     Cart.
		 */
		return (bool) apply_filters( 'gidav_cart_requires_verification', $requires, $cart );
	}

	/**
	 * Saved verification for the logged-in customer, if remembering is enabled and it covers the current minimum age.
	 *
	 * @return array|null Array with "date" (Y-m-d) and "min_age", or null.
	 */
	public static function saved_verification() {
		$settings = GIDAV_Settings::get();
		if ( ! $settings['remember'] || ! is_user_logged_in() ) {
			return null;
		}
		$saved = get_user_meta( get_current_user_id(), self::USER_META, true );
		if ( ! is_array( $saved ) || empty( $saved['date'] ) || empty( $saved['min_age'] ) || (int) $saved['min_age'] < $settings['min_age'] ) {
			return null;
		}
		return $saved;
	}

	/**
	 * Renders the verification section.
	 */
	public static function render() {
		if ( ! self::cart_requires_verification() ) {
			return;
		}
		$settings = GIDAV_Settings::get();
		$saved    = self::saved_verification();
		?>
		<div id="gidav-verification" class="gidav">
			<h3 class="gidav__heading"><?php echo esc_html( $settings['heading'] ); ?></h3>
			<?php if ( $saved ) : ?>
				<p class="gidav__saved">
					<?php
					printf(
						/* translators: %s: date of the earlier verification. */
						esc_html__( 'Your age was verified on %s. No further check is needed.', 'german-id-age-verification' ),
						esc_html( date_i18n( get_option( 'date_format' ), strtotime( $saved['date'] ) ) )
					);
					?>
				</p>
			<?php else : ?>
				<?php if ( '' !== $settings['help'] ) : ?>
					<p id="gidav-help" class="gidav__help"><?php echo esc_html( $settings['help'] ); ?></p>
				<?php endif; ?>
				<details class="gidav__where">
					<summary><?php esc_html_e( 'Where do I find these numbers?', 'german-id-age-verification' ); ?></summary>
					<img class="gidav__diagram" src="<?php echo esc_url( plugin_dir_url( GIDAV_FILE ) . 'assets/id-card-mrz.svg' ); ?>" width="560" height="300" alt="<?php esc_attr_e( 'Diagram of the machine-readable zone of a German ID card with the four number groups marked 1 to 4.', 'german-id-age-verification' ); ?>" />
					<ul>
						<li><?php esc_html_e( 'ID cards issued since November 2010: look at the three lines of letters and numbers at the bottom of the back.', 'german-id-age-verification' ); ?></li>
						<li><?php esc_html_e( '1: the 10 characters after "IDD<<" in the first line.', 'german-id-age-verification' ); ?></li>
						<li><?php esc_html_e( '2: the first 7 digits of the second line (date of birth and its check digit).', 'german-id-age-verification' ); ?></li>
						<li><?php esc_html_e( '3: the 7 digits after the first "<" in the second line (expiry date and its check digit). You can leave out the "D".', 'german-id-age-verification' ); ?></li>
						<li><?php esc_html_e( '4: the last digit of the second line.', 'german-id-age-verification' ); ?></li>
						<li><?php esc_html_e( 'Older ID cards issued until October 2010: use the bottom line on the front. 1 is the first 10 digits, 2 and 3 are the two groups of 7 digits, and 4 is the last digit.', 'german-id-age-verification' ); ?></li>
					</ul>
				</details>
				<div class="gidav__fields">
					<?php
					$specs = array(
						'doc'    => array( __( '10 letters and digits', 'german-id-age-verification' ), 11, 'text' ),
						'birth'  => array( __( '7 digits', 'german-id-age-verification' ), 7, 'numeric' ),
						'expiry' => array( __( '7 digits', 'german-id-age-verification' ), 8, 'text' ),
						'check'  => array( __( '1 digit', 'german-id-age-verification' ), 1, 'numeric' ),
					);
					$labels = self::labels();
					foreach ( self::fields() as $key => $name ) {
						woocommerce_form_field(
							$name,
							array(
								'type'              => 'text',
								'label'             => $labels[ $key ],
								'description'       => $specs[ $key ][0],
								'required'          => true,
								'class'             => array( 'gidav__field', 'gidav__field--' . $key ),
								'input_class'       => array( 'gidav__input' ),
								'custom_attributes' => array(
									'maxlength'      => $specs[ $key ][1],
									'inputmode'      => $specs[ $key ][2],
									'autocomplete'   => 'off',
									'autocapitalize' => 'characters',
									'spellcheck'     => 'false',
									'data-gidav'     => $key,
								),
							),
							''
						);
					}
					?>
				</div>
				<p class="gidav__status" role="status" aria-live="polite"></p>
				<p class="gidav__privacy"><?php esc_html_e( 'The numbers are only used to check your age for this order and are not stored.', 'german-id-age-verification' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Loads the live check script and styles on the checkout page when needed.
	 */
	public static function enqueue() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() || ! self::cart_requires_verification() ) {
			return;
		}
		$url = plugin_dir_url( GIDAV_FILE ) . 'assets/';
		wp_enqueue_style( 'gidav-checkout', $url . 'checkout.css', array(), GIDAV_VERSION );
		if ( self::saved_verification() ) {
			return;
		}
		wp_enqueue_script( 'gidav-checkout', $url . 'checkout.js', array(), GIDAV_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

		$messages = array();
		foreach ( array_keys( self::fields() ) as $key ) {
			foreach ( array( 'format', 'checksum', 'date', 'expired', 'underage' ) as $code ) {
				$messages[ $key ][ $code ] = self::message( $key, $code );
			}
		}
		wp_localize_script(
			'gidav-checkout',
			'gidavCheckout',
			array(
				'minAge'   => GIDAV_Settings::get()['min_age'],
				'today'    => wp_date( 'Y-m-d' ),
				'messages' => $messages,
				'valid'    => __( 'The ID card numbers look correct.', 'german-id-age-verification' ),
			)
		);
	}

	/**
	 * Error message for a field and error code.
	 *
	 * @param string $key  Validator key.
	 * @param string $code Error code.
	 * @return string Plain text.
	 */
	public static function message( $key, $code ) {
		$labels = self::labels();
		$label  = $labels[ $key ];
		switch ( $code ) {
			case 'empty':
				/* translators: %s: field label. */
				return sprintf( __( '%s is a required field.', 'german-id-age-verification' ), $label );
			case 'format':
				$formats = array(
					'doc'    => __( 'The document number must be 10 letters and digits, as printed on your ID card.', 'german-id-age-verification' ),
					'birth'  => __( 'The date of birth must be 7 digits.', 'german-id-age-verification' ),
					'expiry' => __( 'The expiry date must be 7 digits.', 'german-id-age-verification' ),
					'check'  => __( 'The final check digit must be a single digit.', 'german-id-age-verification' ),
				);
				return $formats[ $key ];
			case 'checksum':
				if ( 'check' === $key ) {
					return __( 'The numbers do not match the final check digit. Please check all four fields for typos.', 'german-id-age-verification' );
				}
				/* translators: %s: field label. */
				return sprintf( __( '%s does not match its check digit. Please check it for typos.', 'german-id-age-verification' ), $label );
			case 'date':
				/* translators: %s: field label. */
				return sprintf( __( '%s does not contain a valid date.', 'german-id-age-verification' ), $label );
			case 'expired':
				return __( 'This ID card has expired. Please use a valid ID card.', 'german-id-age-verification' );
			case 'underage':
				/* translators: %d: minimum age. */
				return sprintf( __( 'You must be at least %d years old to buy some of the products in your cart.', 'german-id-age-verification' ), GIDAV_Settings::get()['min_age'] );
		}
		return __( 'Please check the ID card numbers.', 'german-id-age-verification' );
	}

	/**
	 * Validates the ID card numbers when the order is placed. The numbers are not stored.
	 *
	 * @param array    $data   Posted checkout data.
	 * @param WP_Error $errors Validation errors.
	 */
	public static function validate( $data, $errors ) {
		self::$result = '';
		if ( ! self::cart_requires_verification() ) {
			return;
		}
		if ( self::saved_verification() ) {
			self::$result = 'saved';
			return;
		}

		$input = array();
		foreach ( self::fields() as $key => $name ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by WooCommerce in WC_Checkout::process_checkout().
			$input[ $key ] = isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : '';
		}

		$result = GIDAV_Validator::validate( $input, GIDAV_Settings::get()['min_age'] );
		$fields = self::fields();
		foreach ( $result['errors'] as $key => $code ) {
			$errors->add( 'gidav_' . $key, esc_html( self::message( $key, $code ) ), array( 'id' => $fields[ $key ] ) );
		}
		if ( $result['valid'] ) {
			self::$result = 'id_card';
		}
	}

	/**
	 * Records the verification on the order (never the ID numbers) and remembers it for the customer.
	 *
	 * @param WC_Order $order Order being created.
	 */
	public static function save_to_order( $order ) {
		if ( '' === self::$result ) {
			return;
		}
		$settings = GIDAV_Settings::get();
		$order->update_meta_data( self::META_STATUS, 'yes' );
		$order->update_meta_data( self::META_METHOD, 'saved' === self::$result ? 'german_id_saved' : 'german_id' );
		$order->update_meta_data( self::META_DATE, gmdate( 'Y-m-d H:i:s' ) );
		$order->update_meta_data( self::META_AGE, $settings['min_age'] );

		$customer_id = (int) $order->get_customer_id();
		if ( 'id_card' === self::$result && $settings['remember'] && $customer_id > 0 ) {
			update_user_meta(
				$customer_id,
				self::USER_META,
				array(
					'date'    => wp_date( 'Y-m-d' ),
					'min_age' => $settings['min_age'],
				)
			);
		}
	}

	/**
	 * Adds an order note describing the check.
	 *
	 * @param WC_Order $order Created order.
	 */
	public static function add_order_note( $order ) {
		if ( '' === self::$result || ! $order instanceof WC_Order ) {
			return;
		}
		$note = 'saved' === self::$result
			? __( 'Age verification: the customer has a saved German ID card verification. No ID numbers were stored.', 'german-id-age-verification' )
			: __( 'Age verification: German ID card check digits, expiry date, and minimum age passed. The ID numbers were not stored.', 'german-id-age-verification' );
		$order->add_order_note( $note );
		self::$result = '';
	}

	/**
	 * Refuses Checkout block orders with restricted products, because the block cannot show the ID card fields.
	 * Customers with a saved verification can still check out.
	 *
	 * @param WC_Order $order Order being placed through the Store API.
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When verification is required.
	 */
	public static function block_store_api_checkout( $order ) {
		if ( ! class_exists( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException::class ) ) {
			return;
		}
		if ( ! self::cart_requires_verification() ) {
			return;
		}
		if ( self::saved_verification() ) {
			$order->update_meta_data( self::META_STATUS, 'yes' );
			$order->update_meta_data( self::META_METHOD, 'german_id_saved' );
			$order->update_meta_data( self::META_DATE, gmdate( 'Y-m-d H:i:s' ) );
			$order->update_meta_data( self::META_AGE, GIDAV_Settings::get()['min_age'] );
			$order->save();
			return;
		}
		throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
			'gidav_age_verification_required',
			esc_html__( 'Some products in your cart require age verification with a German ID card, which is not available on this checkout. Please contact the shop.', 'german-id-age-verification' ),
			400
		);
	}
}
