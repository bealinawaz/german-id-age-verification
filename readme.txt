=== German ID Age Verification for WooCommerce – Personalausweis Check ===
Contributors: imalinawaz
Donate link: https://www.buymeacoffee.com/bealinawaz
Tags: age verification, altersverifikation, personalausweis, age check, fsk 18
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Age verification for WooCommerce with the German ID card (Personalausweis): checks the ID numbers, expiry date, and minimum age at checkout.

== Description ==

German ID Age Verification adds a **Personalausweis check to the WooCommerce checkout** for age-restricted products. When the cart contains a product from a restricted category, such as FSK 18 films, games, spirits, or tobacco, the customer enters the numbers from the machine-readable zone of their German ID card. The plugin checks them before the order can be placed (Altersprüfung / Altersverifikation mit dem Personalausweis).

The check is privacy-first. The ID numbers are used only during checkout and are **never stored**, not on the order, not on the customer account, and not in logs. The order only records that the age was verified, the method, and the date.

**What is checked**

* **Check digits:** every group of numbers and the final check digit are validated with the ICAO 9303 algorithm used on German ID cards, which catches typos and invented numbers.
* **Expiry date:** expired ID cards are rejected.
* **Minimum age:** the date of birth on the card must meet your minimum age (18 by default).
* **Both card formats:** ID cards issued since November 2010 (numbers on the back) and older cards issued until October 2010 (numbers on the front).

**Features**

* **Only when needed:** the fields appear only when the cart contains a product from a category you choose, including its subcategories.
* **Instant feedback:** customers see mistakes as they type, with error messages linked to each field. Nothing is sent anywhere until the order is placed, and the server repeats every check.
* **Help for customers:** a diagram and plain-text instructions show where the four number groups are on the card.
* **Returning customers:** logged-in customers who passed the check can skip it next time. Only the date of the check is saved, never the numbers or the date of birth.
* **Order record:** the order screen and an order note show "Age verified: yes, German ID card" with the date.
* **Privacy tools:** suggested privacy policy text, plus personal data export and erasure for the saved verification.
* **HPOS ready:** works with WooCommerce High-Performance Order Storage.

**Important legal notice**

A checksum-based ID number check confirms that the numbers are well-formed, that the card has not expired, and that the date of birth printed on it meets your age limit. It does **not** prove that the customer owns the card or is the person ordering. It is **not** a certified age verification system (Altersverifikationssystem, AVS) as required for some products under the German Youth Protection Act (JuSchG) and the Interstate Treaty on the Protection of Minors in the Media (JMStV), and it does not replace an age check on delivery (for example a "Persönliche Übergabe" or "Alterssichtprüfung" shipping service). You, the merchant, are responsible for complying with the law for the products you sell. If in doubt, ask a lawyer.

**Classic checkout only**

The fields use the classic checkout. If your checkout page uses the Checkout block, orders with restricted products are refused there with an error message instead of being placed without a check. Use the `[woocommerce_checkout]` shortcode or the Classic Checkout block on your checkout page.

**Custom development**

Need an integration with a certified age verification service, Checkout block support, or other custom checkout rules for your German shop? The plugin is built and maintained by Ali Nawaz, a WordPress and WooCommerce developer. [Get in touch for custom work](https://bealinawaz.com/contact-me/?utm_source=wordpress.org&utm_medium=plugin-readme&utm_campaign=german-id-age-verification).

== Installation ==

1. Install and activate WooCommerce and this plugin.
2. Go to **WooCommerce → Age Verification**.
3. Tick the product categories that need an age check, such as "FSK18", and check the minimum age.
4. Make sure your checkout page uses the classic checkout (`[woocommerce_checkout]`).
5. Add the suggested text from **Settings → Privacy** to your privacy policy.

== Frequently Asked Questions ==

= Is this a legally sufficient age verification (Altersverifikation) in Germany? =

Not for every product. It checks that the ID numbers are valid, the card is current, and the birth date meets your age limit. It cannot confirm that the customer is the card holder, so it is not a certified AVS under JuSchG or JMStV. Many shops combine it with an age-checked delivery service. You are responsible for your own compliance.

= Where are the numbers on the German ID card (Personalausweis)? =

On cards issued since November 2010, they are in the three lines of letters and numbers at the bottom of the back. On older cards, they are in the bottom line on the front. The checkout shows a diagram and step-by-step help.

= Are the ID numbers stored? =

No. They are checked when the order is placed and then discarded. The order only stores "age verified", the method, the minimum age, and the date. For logged-in customers who passed the check, the date of the check is saved on their account so they do not have to enter the numbers again (you can turn this off).

= Can I use it for 16+ products? =

Yes. Set the minimum age to 16. If you raise the minimum age later, customers with a saved verification are asked again.

= Does it work with the WooCommerce Checkout block? =

No. The Checkout block cannot show these fields, so orders with restricted products are refused there with a clear error. Use the classic checkout.

= Does it work with ID cards from other countries or passports? =

No, only German ID cards (Personalausweis). Customers without one need another way to prove their age, so mention this in your shop.

= What happened to the FSK18 category the old version created? =

Version 2 no longer creates categories. If an "FSK18" category exists, it is selected automatically on update. You can choose any categories in the settings.

== Privacy ==

The plugin does not send data to external services. ID numbers are processed only in memory during checkout. Stored data: on orders, "age verified", method, minimum age, and date; on customer accounts (optional), the date of the check and the minimum age.

== Changelog ==

= 2.0.0 =
* Complete rewrite, renamed from Germany Age Verify to German ID Age Verification for WooCommerce.
* Privacy: ID numbers are never stored. Stored ID numbers from version 1 are deleted from customer accounts on update, and a one-click admin action removes those saved on orders.
* New: correct ICAO 9303 check digits for ID cards issued since November 2010 and before, plus expiry date and minimum age checks. Version 1 did not check the age at all.
* New: settings page under WooCommerce for restricted categories, minimum age, texts, and remembering verified customers.
* New: instant client-side feedback with accessible, field-linked error messages and a diagram of the card.
* New: order records, order notes, privacy policy text, and personal data export and erasure.
* New: Checkout block orders with restricted products are refused instead of skipping the check.
* New: High-Performance Order Storage (HPOS) compatibility.
* Removed: the automatic "FSK18" category creation (an existing FSK18 category is preselected), and the insecure ajax.php file that loaded WordPress directly.
* Fixed: fatal errors on PHP 8.
* Customers verified with version 1 need to verify again once, because version 1 never checked the age.

= 1.0 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
Complete rewrite with real age checks. Removes stored ID numbers for privacy; see the notice in the admin to remove old numbers from orders. Classic checkout only.
