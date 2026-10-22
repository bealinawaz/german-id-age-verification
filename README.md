# German ID Age Verification for WooCommerce

Age verification (Altersverifikation) for WooCommerce shops in Germany. When the cart contains age-restricted products, customers enter the numbers from the machine-readable zone of their German ID card (Personalausweis) at checkout. The plugin validates the ICAO 9303 check digits, the expiry date, and the minimum age, and never stores the ID numbers.

![Tested up to WordPress 7.1](https://img.shields.io/badge/WordPress-7.1-blue)
![WooCommerce 7.1+](https://img.shields.io/badge/WooCommerce-7.1%2B-96588a)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![License GPLv2 or later](https://img.shields.io/badge/license-GPLv2%2B-green)

> **Legal notice:** a checksum-based check confirms that the ID numbers are well-formed, the card is not expired, and the birth date on it meets the age limit. It does not prove that the customer owns the card. It is not a certified age verification system (AVS) under the German JuSchG or JMStV. Merchants are responsible for their own compliance.

## Features

- Fields appear only when the cart contains products from the chosen categories (and their subcategories).
- Both card layouts: issued since November 2010 (back, 3-line MRZ) and until October 2010 (front, 2-line MRZ).
- Server-side validation in `woocommerce_after_checkout_validation`; a small vanilla JS file gives the same feedback while typing, without any network request.
- Accessible: labelled inputs, error messages linked with `aria-describedby`, a live status region, and a diagram with text instructions.
- Privacy-first: no ID numbers in order meta, user meta, sessions, or logs. Orders store `_gidav_age_verified`, `_gidav_method`, `_gidav_min_age`, and `_gidav_verified_at`.
- Optional "remember" for logged-in customers (`gidav_age_verified` user meta with only the date and minimum age), with profile reset, privacy export, and erasure.
- Checkout block orders (Store API) with restricted products are refused with an error, so the check cannot be bypassed.
- HPOS compatible.

## Setup

1. Activate WooCommerce and the plugin.
2. Go to **WooCommerce → Age Verification**, tick the restricted categories, and set the minimum age.
3. Use the classic checkout (`[woocommerce_checkout]`).

Test numbers (a fictional card, valid until 31 December 2030, born 1 January 1990): `L01X00T471`, `9001011`, `3012316`, `6`.

## Upgrading from 1.x

- An existing `FSK18` category is preselected; no categories are created any more.
- Raw ID numbers saved in user meta (`ida`, `idb`, `idc`, `idd`, `verify_age`) are deleted on update.
- ID numbers saved on orders are deleted when an admin clicks the button in the admin notice. This is a separate step because it changes order records, which some shops must keep unchanged.

## Hooks

```php
// Require verification for a specific product too.
add_filter( 'gidav_cart_requires_verification', function ( $requires, $cart ) {
	foreach ( $cart->get_cart() as $item ) {
		if ( 123 === $item['product_id'] ) {
			return true;
		}
	}
	return $requires;
}, 10, 2 );
```

## Project structure

```
german-id-age-verification.php     Plugin header, bootstrap, WooCommerce feature declarations
includes/class-gidav-validator.php ICAO 9303 check digits, date, expiry, and age checks
includes/class-gidav-checkout.php  Checkout fields, validation, order records, Store API guard
includes/class-gidav-settings.php  Settings, 1.x migration, and legacy data cleanup
includes/class-gidav-admin.php     WooCommerce → Age Verification screen, notices, order and profile display
includes/class-gidav-privacy.php   Privacy policy text, exporter, and eraser
assets/checkout.js                 Live client-side check
assets/id-card-mrz.svg             Diagram of the machine-readable zone
uninstall.php                      Removes settings and saved verifications (order records are kept)
```

## Custom development

Need an integration with a certified age verification service, Checkout block support, or custom checkout rules for your German shop? [Hire Ali Nawaz for custom WooCommerce work](https://bealinawaz.com/contact-me/?utm_source=github&utm_medium=readme&utm_campaign=german-id-age-verification).

## License

GPLv2 or later. See [LICENSE](LICENSE).
