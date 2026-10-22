<?php
/**
 * Privacy policy text and personal data export and erasure.
 *
 * @package German_ID_Age_Verification
 */

defined( 'ABSPATH' ) || exit;

/**
 * Integrates with the WordPress privacy tools.
 */
final class GIDAV_Privacy {

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'policy_text' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * Suggests text for the site's privacy policy.
	 */
	public static function policy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content  = '<p class="privacy-policy-tutorial">' . esc_html__( 'Suggested text for shops that use German ID card age verification:', 'german-id-age-verification' ) . '</p>';
		$content .= '<p><strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'german-id-age-verification' ) . ' </strong>';
		$content .= esc_html__( 'When you order age-restricted products, we ask for the numbers from the machine-readable zone of your German ID card (Personalausweis). We use them only during checkout to check that the numbers are valid, that the card has not expired, and that you have reached the minimum age. The numbers and your date of birth are not stored. We save on your order that your age was verified, the method, and the date. If you have a customer account, we also save the date of the check so you do not have to enter the numbers again. You can ask us to remove this at any time.', 'german-id-age-verification' );
		$content .= '</p>';
		wp_add_privacy_policy_content( __( 'German ID Age Verification', 'german-id-age-verification' ), wp_kses_post( $content ) );
	}

	/**
	 * Registers the exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['german-id-age-verification'] = array(
			'exporter_friendly_name' => __( 'German ID age verification', 'german-id-age-verification' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Registers the eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {
		$erasers['german-id-age-verification'] = array(
			'eraser_friendly_name' => __( 'German ID age verification', 'german-id-age-verification' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Exports the saved verification of a user.
	 *
	 * @param string $email Email address.
	 * @return array
	 */
	public static function export( $email ) {
		$data  = array();
		$user  = get_user_by( 'email', $email );
		$saved = $user ? get_user_meta( $user->ID, GIDAV_Checkout::USER_META, true ) : null;
		if ( is_array( $saved ) && ! empty( $saved['date'] ) ) {
			$data[] = array(
				'group_id'    => 'gidav-age-verification',
				'group_label' => __( 'Age verification', 'german-id-age-verification' ),
				'item_id'     => 'gidav-' . $user->ID,
				'data'        => array(
					array(
						'name'  => __( 'Verified on', 'german-id-age-verification' ),
						'value' => $saved['date'],
					),
					array(
						'name'  => __( 'Minimum age', 'german-id-age-verification' ),
						'value' => (string) (int) $saved['min_age'],
					),
				),
			);
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erases the saved verification of a user.
	 *
	 * @param string $email Email address.
	 * @return array
	 */
	public static function erase( $email ) {
		$user    = get_user_by( 'email', $email );
		$removed = $user ? delete_user_meta( $user->ID, GIDAV_Checkout::USER_META ) : false;
		return array(
			'items_removed'  => (bool) $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
