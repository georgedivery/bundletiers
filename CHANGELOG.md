# Changelog

All notable changes to BundleTiers are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
- The visible bundle line on an order is stored under a fixed key (`bundletiers_pack`). The "cart label" setting only changes the name shown in the admin, emails and order pages, so changing or clearing it no longer changes what is stored on orders (an empty label falls back to "Bundle").
- The Position and Style settings only accept the choices the fields offer; anything else falls back to the default.
- Fixed amounts (amount off each item, fixed pack price) now mean the same on the product page and in the cart when taxes are on. Before, the product page used the price shown in the shop and the cart used the price as entered, so with prices entered without tax and shown with tax (or the other way round) the cart could charge a different amount than the card promised. The cart now takes the discount in the price shown in the shop (set in WooCommerce → Settings → Tax) and gives WooCommerce the stored basis back.
