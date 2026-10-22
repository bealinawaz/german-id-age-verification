<?php
/**
 * German ID card (Personalausweis) machine-readable zone checks.
 *
 * @package German_ID_Age_Verification
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates the four groups of numbers from the machine-readable zone (MRZ) of a German ID card.
 *
 * Supported layouts:
 * - ID cards issued since November 2010 (back of the card, three lines):
 *   document number (9 letters or digits) + check digit, birth date YYMMDD + check digit,
 *   expiry date YYMMDD + check digit (followed by "D"), and the final composite check digit.
 * - ID cards issued until October 2010 (front of the card, two lines):
 *   serial number (9 digits) + check digit (followed by "D"), birth date + check digit,
 *   expiry date + check digit, and the composite check digit.
 *
 * Check digits follow ICAO Doc 9303: weights 7, 3, 1 repeating, 0–9 count as their value,
 * A–Z as 10–35, and the filler "<" as 0. The check digit is the sum modulo 10.
 */
final class GIDAV_Validator {

	/**
	 * ICAO 9303 check digit.
	 *
	 * @param string $value Characters 0-9, A-Z, or <.
	 * @return int
	 */
	public static function check_digit( $value ) {
		$weights = array( 7, 3, 1 );
		$sum     = 0;
		$length  = strlen( $value );
		for ( $i = 0; $i < $length; $i++ ) {
			$char = $value[ $i ];
			if ( ctype_digit( $char ) ) {
				$number = (int) $char;
			} elseif ( ctype_upper( $char ) ) {
				$number = ord( $char ) - 55;
			} else {
				$number = 0;
			}
			$sum += $number * $weights[ $i % 3 ];
		}
		return $sum % 10;
	}

	/**
	 * Normalizes user input: uppercase, without spaces.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function normalize( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		return strtoupper( (string) preg_replace( '/\s+/', '', $value ) );
	}

	/**
	 * Converts YYMMDD to a date, or null when invalid.
	 *
	 * @param string $yymmdd Date digits.
	 * @param string $kind   "birth" (century chosen so the date is not in the future) or "expiry" (20YY).
	 * @param int    $now    Current timestamp.
	 * @return DateTimeImmutable|null
	 */
	public static function parse_date( $yymmdd, $kind, $now ) {
		if ( ! preg_match( '/^(\d{2})(\d{2})(\d{2})$/', $yymmdd, $m ) ) {
			return null;
		}
		$year  = 2000 + (int) $m[1];
		$month = (int) $m[2];
		$day   = (int) $m[3];
		if ( 'birth' === $kind && $year > (int) wp_date( 'Y', $now ) ) {
			$year -= 100;
		}
		if ( ! checkdate( $month, $day, $year ) ) {
			return null;
		}
		return new DateTimeImmutable( sprintf( '%04d-%02d-%02d', $year, $month, $day ), wp_timezone() );
	}

	/**
	 * Validates the four groups.
	 *
	 * Returns an array with "valid" (bool), "errors" (field key => error code), and, when the
	 * numbers are well-formed, "age" and "expired". Nothing here is stored.
	 *
	 * @param array $input   Keys doc, birth, expiry, check.
	 * @param int   $min_age Minimum age.
	 * @param int   $now     Current timestamp (for tests).
	 * @return array
	 */
	public static function validate( $input, $min_age, $now = 0 ) {
		$now    = $now ? $now : time();
		$errors = array();

		$doc    = self::normalize( isset( $input['doc'] ) ? $input['doc'] : '' );
		$birth  = self::normalize( isset( $input['birth'] ) ? $input['birth'] : '' );
		$expiry = self::normalize( isset( $input['expiry'] ) ? $input['expiry'] : '' );
		$check  = self::normalize( isset( $input['check'] ) ? $input['check'] : '' );

		// Older cards print a "D" after the serial number, newer cards after the expiry date.
		if ( 11 === strlen( $doc ) && 'D' === substr( $doc, -1 ) ) {
			$doc = substr( $doc, 0, 10 );
		}
		if ( 8 === strlen( $expiry ) && 'D' === substr( $expiry, -1 ) ) {
			$expiry = substr( $expiry, 0, 7 );
		}

		$formats = array(
			'doc'    => array( $doc, '/^[A-Z0-9]{9}\d$/' ),
			'birth'  => array( $birth, '/^\d{7}$/' ),
			'expiry' => array( $expiry, '/^\d{7}$/' ),
			'check'  => array( $check, '/^\d$/' ),
		);
		foreach ( $formats as $key => $format ) {
			if ( '' === $format[0] ) {
				$errors[ $key ] = 'empty';
			} elseif ( ! preg_match( $format[1], $format[0] ) ) {
				$errors[ $key ] = 'format';
			}
		}

		foreach ( array(
			'doc'    => $doc,
			'birth'  => $birth,
			'expiry' => $expiry,
		) as $key => $value ) {
			if ( ! isset( $errors[ $key ] ) && (string) self::check_digit( substr( $value, 0, -1 ) ) !== substr( $value, -1 ) ) {
				$errors[ $key ] = 'checksum';
			}
		}

		if ( $errors ) {
			return array(
				'valid'  => false,
				'errors' => $errors,
			);
		}

		if ( (string) self::check_digit( $doc . $birth . $expiry ) !== $check ) {
			return array(
				'valid'  => false,
				'errors' => array( 'check' => 'checksum' ),
			);
		}

		$birth_date  = self::parse_date( substr( $birth, 0, 6 ), 'birth', $now );
		$expiry_date = self::parse_date( substr( $expiry, 0, 6 ), 'expiry', $now );
		if ( ! $birth_date ) {
			$errors['birth'] = 'date';
		}
		if ( ! $expiry_date ) {
			$errors['expiry'] = 'date';
		}
		if ( $errors ) {
			return array(
				'valid'  => false,
				'errors' => $errors,
			);
		}

		$today   = new DateTimeImmutable( wp_date( 'Y-m-d', $now ), wp_timezone() );
		$age     = (int) $birth_date->diff( $today )->y;
		$expired = $expiry_date < $today;

		if ( $expired ) {
			$errors['expiry'] = 'expired';
		}
		if ( $age < (int) $min_age ) {
			$errors['birth'] = 'underage';
		}

		return array(
			'valid'   => ! $errors,
			'errors'  => $errors,
			'age'     => $age,
			'expired' => $expired,
		);
	}
}
