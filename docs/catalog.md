# Structured catalog

Compadres uses WooCommerce products for inventory, pricing, carts, and orders. A dedicated `compadres_brand` product taxonomy and `_compadres_*` product metadata hold cigar-specific data. The implementation does not contain approved production catalog content; bundled examples and fixtures are explicitly fictional.

## Product schema

“REST” below means the field is available through WooCommerce or WordPress REST APIs subject to the normal authenticated edit-product permissions. Internal classifications and integration identifiers are never rendered by the Compadres product-specification tab.

| Field / CSV header | Type and requirement | Validation and controlled values | Variation behavior | Storage | Frontend / REST / import |
|---|---|---|---|---|---|
| Product name (`product_name`) | String; required by the pre-import validator | Trimmed; WooCommerce applies its normal product validation | Each variation row has a required descriptive name in the Compadres validation format | WordPress product title | Visible; core WooCommerce REST; maps to WooCommerce `name` |
| Product type (`product_type`) | Enum; required | `simple`, `variable`, or `variation` | `variation` rows must identify a parent | WooCommerce product type | Determines rendering; core REST; maps to `type` |
| SKU (`sku`) | String; required | WooCommerce enforces SKU uniqueness during import/save | Variations should have their own unique SKU | `_sku` | Product/admin visibility; core REST; maps to `sku` |
| UPC (`upc`) | Digit string; optional | Empty or 8–14 ASCII digits | May override at variation level | `_compadres_upc` | Hidden by the public specification tab; authenticated product REST; mapped and validated |
| Parent (`parent`) | Parent ID or SKU relationship; required operationally for variation rows | Resolved by the WooCommerce importer; parent must exist or be included earlier in the import | Applies only to variations | WooCommerce parent relationship (`post_parent`) | Not independently displayed; core REST relationship; maps to `parent_id` |
| Brand (`brand`) | Taxonomy term name; required by validator | Text is sanitized; existing term is matched and a term is created when absent during an authorized import | Variations inherit the parent’s catalog brand; assign brand on parent rows | `compadres_brand` taxonomy | Brand archive and product catalog; taxonomy REST; maps to `compadres_brand` |
| Product line (`product_line`) | String; optional | HTML removed and surrounding whitespace trimmed | Parent-level cigar data | `_compadres_product_line` | Public specification tab; authenticated product REST; mapped and validated |
| Country of origin (`country_of_origin`) | String; optional | HTML removed and trimmed | Parent-level cigar data | `_compadres_country_of_origin` | Public specification tab and catalog filter; authenticated REST; mapped and validated |
| Wrapper (`wrapper`) | String; optional | HTML removed and trimmed | Parent-level cigar data | `_compadres_wrapper` | Public specification tab and catalog filter; authenticated REST; mapped and validated |
| Binder (`binder`) | String; optional | HTML removed and trimmed | Parent-level cigar data | `_compadres_binder` | Public specification tab; authenticated REST; mapped and validated |
| Filler (`filler`) | String; optional | HTML removed and trimmed | Parent-level cigar data | `_compadres_filler` | Public specification tab; authenticated REST; mapped and validated |
| Strength (`strength`) | Enum; optional | Empty, `mild`, `mild-medium`, `medium`, `medium-full`, or `full` | Parent-level cigar data | `_compadres_strength` | Public specification tab and catalog filter; authenticated REST; mapped and validated |
| Flavor profile (`flavor_profile`) | String; optional | HTML removed and trimmed; it is descriptive catalog copy, not a controlled tasting claim | Parent-level cigar data | `_compadres_flavor_profile` | Public specification tab; authenticated REST; mapped and validated |
| Vitola (`vitola`) | String; optional | HTML removed and trimmed | Parent-level cigar data | `_compadres_vitola` | Public specification tab and catalog filter; authenticated REST; mapped and validated |
| Cigar length (`length`) | Decimal inches; optional | 0/empty or 0–20, normalized to at most two decimals | Parent-level cigar data | `_compadres_length` | Public specification tab; authenticated REST; mapped and validated. This is distinct from shipping dimensions. |
| Ring gauge (`ring_gauge`) | Integer; optional | 0/empty or 0–100 | Parent-level cigar data | `_compadres_ring_gauge` | Public specification tab; authenticated REST; mapped and validated |
| Pack quantity (`pack_quantity`) | Integer; optional | 0–1000 | May override at variation level | `_compadres_pack_quantity` | Public specification tab; authenticated product REST for parent products; mapped and validated |
| Box quantity (`box_quantity`) | Integer; optional | 0–1000 | May override at variation level | `_compadres_box_quantity` | Public specification tab; authenticated product REST for parent products; mapped and validated |
| Weight (`weight`) | WooCommerce decimal in the configured store unit; optional | WooCommerce validation | Variations may override | `_weight` | Used for shipping; core REST; maps to `weight` |
| Shipping length (`shipping_length`) | WooCommerce decimal in the configured dimension unit; optional | WooCommerce validation | Variations may override | `_length` | Used for shipping; core REST; maps to WooCommerce `length` |
| Shipping width (`shipping_width`) | WooCommerce decimal; optional | WooCommerce validation | Variations may override | `_width` | Used for shipping; core REST; maps to `width` |
| Shipping height (`shipping_height`) | WooCommerce decimal; optional | WooCommerce validation | Variations may override | `_height` | Used for shipping; core REST; maps to `height` |
| Sales-tax classification (`sales_tax_classification`) | String identifier; optional until an approved tax design is configured | HTML removed and trimmed; no legal or tax meaning is inferred | Parent-level | `_compadres_sales_tax_classification` | Internal only; authenticated REST; mapped and validated |
| Excise-tax classification (`excise_tax_classification`) | String identifier; optional until an approved tax design is configured | HTML removed and trimmed; no legal or tax meaning is inferred | Parent-level | `_compadres_excise_tax_classification` | Internal only; authenticated REST; mapped and validated |
| Future Odoo product ID (`future_odoo_id`) | String identifier; optional | HTML removed and trimmed | Parent-level unless a future integration explicitly supports variations | `_compadres_future_odoo_id` | Internal only; authenticated REST; mapped and validated |
| Future wholesale product ID (`future_wholesale_id`) | String identifier; optional | HTML removed and trimmed | Parent-level unless a future integration explicitly supports variations | `_compadres_future_wholesale_id` | Internal only; authenticated REST; mapped and validated |

Standard template columns also include `regular_price`, `stock`, `description`, `short_description`, and `categories`. They map to WooCommerce pricing, managed inventory quantity, content, excerpt, and product categories. Price, stock, SKU, weight, and shipping dimensions may be supplied on variation rows.

## Brand schema

Brands are `compadres_brand` taxonomy terms with public archives under `/brands/<slug>/`. Supported term metadata includes short description, logo and hero attachment IDs, accent color, brand story, SEO title, meta description, featured-product IDs, display order, active state, and future Odoo/wholesale IDs. Brand administration requires the product-term management capability. The taxonomy is REST-enabled; sensitive integration credentials are not part of brand records.

## Development fixtures

Load fictional catalog fixtures:

```bash
wp compadres fixtures load
```

Rerunning the command is safe. Brands are matched by fixed slugs, products and variations by `DEV-FICTIONAL-*` SKUs, and records are updated rather than duplicated. Fixture products carry `_compadres_fixture=1`; fixture brands carry `compadres_fixture=1` term metadata.

Remove only fixture-created brands and products:

```bash
wp compadres fixtures remove
```

Removal queries only marked fixture records. It preserves orders, customers, unmarked products, and unmarked brands.

Fixture commands run only when `APP_ENV` is `local` or `development`. Staging additionally requires `COMPADRES_ENABLE_FIXTURES=1`. Production is always rejected. Fixtures are fake merchandising and simulated sales data and must never be loaded into production or treated as approved product content.

## CSV validation

Validate before opening **Products → Import** in WooCommerce:

```bash
wp compadres catalog validate <file>
```

Optionally choose the report path:

```bash
wp compadres catalog validate products.csv --error-report=/tmp/products.errors.csv
```

Behavior:

- Required headers are `product_name`, `sku`, `brand`, and `product_type`.
- UTF-8 BOMs on the first header are accepted.
- Blank lines are ignored.
- Data-row errors report the original source row number, including the header row offset.
- A valid file exits `0` and reports its valid row count.
- Missing/unreadable files, missing headers, or invalid rows exit nonzero.
- When rows are invalid, the default report is `<file>.errors.csv`.
- The machine-readable report contains `row` and `errors` columns; multiple errors use a semicolon-delimited value in the `errors` cell.
- Validation checks required values, product type, UPC, strength, cigar length, ring gauge, pack quantity, and box quantity.
- The validator never imports data. The supported workflow proceeds to WooCommerce import only after exit code `0`; therefore invalid rows are not handed to the importer. Bypassing this preflight is unsupported.

Error reports are generated artifacts and are ignored by Git. Unit tests use operating-system temporary files and clean them after execution where practical.

## WooCommerce import workflow

1. Copy `wp-content/plugins/compadres-commerce/assets/catalog-import-template.csv` outside the repository.
2. Replace every fictional example and development-only classification with approved data.
3. Put a variable parent before its variation rows.
4. Set each variation’s `parent` to the parent SKU (or a WooCommerce-supported parent identifier).
5. Run `wp compadres catalog validate <file>` and resolve every reported row.
6. In WooCommerce **Products → Import**, upload the validated file and review the automatic mappings.
7. Confirm core pricing, inventory, category, parent, weight, and dimension mappings before running the import.
8. Verify imported products, brand assignments, variations, stock, and public specification output in staging.

The plugin contributes mapping options for every `_compadres_*` field and the brand taxonomy. It also provides default mappings for the supplied snake-case headers. Metadata is sanitized again as WooCommerce constructs each product. Brand terms are matched or created after the product is inserted. Future ERP identifiers are inert reference fields; no Odoo or wholesale synchronization is implied.
