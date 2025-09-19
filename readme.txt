=== WooCommerce Dynamic Components Pricing ===
Contributors: serhatcicek
Tags: woocommerce, pricing, components, dynamic
Requires at least: 5.8
Tested up to: 6.x
License: MIT

Simple add-on that displays product “set components” and updates the total price live. Shortcode: [display_components]

== Requirements ==
- WooCommerce
- (Optional) ACF fields:
  _default_components (comma-separated product IDs)
  _product_components (comma-separated product IDs)

== Usage ==
1) Activate the plugin.
2) Add [display_components] into single product content/template.
3) Customers adjust quantities, total price updates instantly, and the custom price is passed to the cart.

== Notes ==
- Cart price recalculation included (before_calculate_totals).
- Security/validation and showing chosen components in cart/checkout can be added later.
