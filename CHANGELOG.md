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
