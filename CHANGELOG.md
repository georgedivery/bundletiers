# Changelog

All notable changes to BundleTiers are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the versions follow
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- The settings are split into five sections: General, Appearance, Behavior, Texts and Status.
- A Status section with the versions of BundleTiers, WooCommerce, WordPress and PHP, the active theme (block or classic), how the cart and checkout pages are built (blocks or shortcode), order storage (HPOS), how prices and tax are set, and how many products use bundles. Nothing is sent anywhere.
- First-run steps ("Start in three steps") that stay until they are hidden, and a notice that says how many products use bundles or invites you to switch them on.
- Style presets (Cards, List, Minimal) and a live preview of the cards that follows the colors, border, corners, text color, texts and layout as you change them.
- A text color setting. Leave it empty to keep the color of your theme.
- A savings color setting for the "you save" pill (empty keeps the soft green), and an "On narrow screens" setting: one tier per row (the default) or keep the columns, for themes that lay the cards out themselves.
- Short hints under the settings fields, and a tier editor that fits a narrow screen (each tier becomes a block).
- In narrow places (a side column, a small phone) the tiers show one per row.
- The `bundletiers_base_price` filter (the price a bundle discount starts from) and the `bundletiers_tax_factor` filter.
- The chosen card is sent with the add to cart form, so the right quantity is used even when the page script could not run (for example in a quick view).
- Screen readers hear the tier, its price and the saving; animations stop for people who ask for less motion, and the selected tier stays visible in Windows high-contrast mode.

### Changed
- Cards and List have a new look: a round indicator, a "you save" pill and a tinted selected card. The plain look is now called Minimal.
- A fixed pack price is divided by the quantity of its tier, so every item costs the same share of it: 3 items for 65.05 make 4 items cost 86.73, not 65.05 as before.
- A tier can be for at most 1000 items.
- The text on the badge is black or white, whichever reads better on the accent color, and the soft grey text is darker.
- The default texts ("You save", "per item", "Bundle") and the "Most popular" badge follow the language of the site, also when they were saved in English.
- The address of the stylesheet and scripts changes when the files change, so browsers do not keep old copies.
- WordPress 6.5 or later is required (it was 6.4), and the plugin no longer loads its translations by hand because WordPress does it.
- Source strings use US spelling (color).

### Fixed
- Fixed amounts (amount off each item, fixed pack price) now mean the same on the product page and in the cart when taxes are on. Before, the product page used the price shown in the shop and the cart used the price as entered, so with prices entered without tax and shown with tax (or the other way round) the cart could charge a different amount than the card promised. The cart now takes the discount in the price shown in the shop (set in WooCommerce → Settings → Tax) and gives WooCommerce the stored basis back.
- The visible bundle line on an order is stored under a fixed key (`bundletiers_pack`). The "cart label" setting only changes the name shown in the admin, emails and order pages, so changing or clearing it no longer changes what is stored on orders. An empty label falls back to "Bundle", in the cart as well.
- A very large quantity (millions) no longer takes the cart down: prices are worked out per line, not per item.
- Cards printed by a `[product_page]` shortcode or a page builder had no style and no script; the assets are now loaded wherever the selector is printed. The hidden quantity field no longer replaces the field of another product (a side cart, a related list).
- The selector script starts when it loads late (a delayed or deferred script) and picks up selectors printed later (a quick view, an AJAX load); one selector with bad data no longer stops the others.
- A price that another plugin sets on a cart line (dynamic pricing, add-ons) is used instead of being overwritten.
- The Position and Style settings only accept the choices the fields offer; anything else falls back to the default.
- Tier data that is not text no longer raises PHP warnings.
- Uninstall now handles every site of a multisite network and forgets the hidden first-run steps.
- The cards no longer touch the add to cart button when they are shown below it.

## [0.1.0] - 2026-10-07

First version, a package for local and demo sites (not published in the WordPress plugin directory).

### Added
- Quantity-tier bundle discounts for simple and variable WooCommerce products.
- Three discount types: percent, amount off each item, fixed pack price.
- Tiers are chosen from the total quantity in the cart, including different
  variations of the same product.
- Discounts use the current price (sale and other price changes included).
- Exact to the cent: the cart, order and invoice always agree.
- Global settings tab and a per-product tab with custom tiers.
- Bundle selector on the product page with prices, savings, badges, variation
  and stock support. The template can be overridden in the theme.
- Bundle shown in the cart, checkout, mini-cart and on order lines.
- Optional rule that keeps coupons off bundle items.
- On the product page of a bundle product the quantity field is replaced by a hidden input (set to the default tier), through the template, not with CSS. The cart page and other products keep their normal field. Template: `bundletiers/quantity-hidden.php`.
- HPOS and Cart/Checkout Blocks compatibility declared.
- English source strings with a Bulgarian (bg_BG) translation.

### Fixed
- A fatal error on simple products (`get_max_purchase_quantity()`).
