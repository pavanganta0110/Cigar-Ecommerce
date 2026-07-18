# Architecture

Compadres uses one WordPress/WooCommerce installation. Brands are structured product taxonomy terms, not separate sites. All products share WooCommerce inventory, cart, checkout, customers, orders, and reports.

Business logic lives in the namespaced `compadres-commerce` plugin. Presentation lives in the `compadres` theme. Checkout-critical age, restriction, shipping, tax, and payment services are provider interfaces with explicit environment/readiness state and fail-closed production behavior.

WooCommerce HPOS remains the order source of truth. Versioned custom tables hold restriction rules, minimal age audit records, immutable order snapshots, provider idempotency/webhook records, refunds, audit entries, and export jobs where searchable structured data is required.
