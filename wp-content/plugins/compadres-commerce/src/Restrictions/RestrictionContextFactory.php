<?php

declare(strict_types=1);

namespace Compadres\Commerce\Restrictions;

use Compadres\Commerce\Catalog\BrandTaxonomy;
use RuntimeException;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

final class RestrictionContextFactory {

	/** @param array<string, mixed> $data */
	public function fromCheckout( array $data ): RestrictionContext {
		$shipping = ! empty( $data['ship_to_different_address'] );
		$prefix   = $shipping ? 'shipping_' : 'billing_';
		return $this->create(
			(string) ( $data[ $prefix . 'state' ] ?? '' ),
			(string) ( $data[ $prefix . 'city' ] ?? '' ),
			(string) ( $data[ $prefix . 'postcode' ] ?? '' ),
			(string) ( $data[ $prefix . 'country' ] ?? '' ),
			$this->cartProductIds()
		);
	}

	public function fromOrder( WC_Order $order ): RestrictionContext {
		$shipping = '' !== $order->get_shipping_country();
		$items    = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$items[] = (int) $item->get_product_id();
			if ( $item->get_variation_id() > 0 ) {
				$items[] = (int) $item->get_variation_id();
			}
		}
		return $this->create(
			$shipping ? $order->get_shipping_state() : $order->get_billing_state(),
			$shipping ? $order->get_shipping_city() : $order->get_billing_city(),
			$shipping ? $order->get_shipping_postcode() : $order->get_billing_postcode(),
			$shipping ? $order->get_shipping_country() : $order->get_billing_country(),
			$items
		);
	}

	/** @param list<int> $product_ids */
	private function create( string $state, string $city, string $postal_code, string $country, array $product_ids ): RestrictionContext {
		$products   = array();
		$categories = array();
		$brands     = array();
		foreach ( array_unique( $product_ids ) as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product instanceof WC_Product ) {
				throw new RuntimeException( 'Cart product data is unavailable.' );
			}
			$parent_id  = $product->get_parent_id() > 0 ? $product->get_parent_id() : $product->get_id();
			$products[] = $product->get_id();
			$products[] = $parent_id;
			$categories = array_merge( $categories, wp_get_post_terms( $parent_id, 'product_cat', array( 'fields' => 'ids' ) ) );
			$brands     = array_merge( $brands, wp_get_post_terms( $parent_id, BrandTaxonomy::TAXONOMY, array( 'fields' => 'ids' ) ) );
		}
		return new RestrictionContext(
			$state,
			$city,
			$postal_code,
			$this->ids( $products ),
			$this->ids( $categories ),
			$this->ids( $brands ),
			$country
		);
	}

	/** @return list<int> */
	private function cartProductIds(): array {
		$woocommerce = WC();
		if ( null === $woocommerce->cart ) {
			throw new RuntimeException( 'Cart data is unavailable.' );
		}
		$ids = array();
		foreach ( $woocommerce->cart->get_cart() as $item ) {
			$ids[] = (int) ( $item['product_id'] ?? 0 );
			$ids[] = (int) ( $item['variation_id'] ?? 0 );
		}
		return $this->ids( $ids );
	}

	/**
	 * @param array<mixed> $values
	 * @return list<int>
	 */
	private function ids( array $values ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $values ), static fn ( int $id ): bool => $id > 0 ) ) );
		sort( $ids );
		return $ids;
	}
}
