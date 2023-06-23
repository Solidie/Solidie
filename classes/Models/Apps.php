<?php

namespace Solidie\Store\Models;

use Solidie\Store\Main;

class Apps extends Main{
	/**
	 * Licensing variation key for items
	 */
	const LICENSING_VARIATION = 'licensing-variation';

	/**
	 * Free product identifer meta key
	 */
	const FREE_META_KEY = 'solidie_item_is_free';
	
	/**
	 * Get item list that is accessible by a user. 
	 *
	 * @param int $user_id
	 * @return array
	 */
	public static function getAppListForUser( $user_id ) {
		return array();
	}

	/**
	 * Get associated product id by item id
	 *
	 * @param integer $item_id
	 * @return int
	 */
	public static function getProductID( int $item_id ) {
		global $wpdb;

		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT product_id FROM " . self::table( 'items' ) . " WHERE item_id=%d",
				$item_id
			)
		);

		return $id ? $id : 0;
	}

	/**
	 * Create or update item
	 *
	 * @param array $item_data
	 * @param int $store_id
	 * @return int
	 */
	public static function updateApp( int $store_id, array $item_data ) {
		$item = array();

		$item['item_id']    = ! empty( $item_data['item_id'] ) ? $item_data['item_id'] : 0;
		$item['product_id'] = ! empty( $item_data['item_id'] ) ? self::getProductID( $item['item_id'] ) : 0;
		$item['item_name']  = ! empty( $item_data['item_name'] ) ? $item_data['item_name'] : 'Untitled App';

		// Sync core product first
		$product_id = self::syncProduct( $item, $store_id );
		if ( empty( $product_id ) ) {
			return false;
		}

		// Update product id as it might've been created newly
		$item['product_id'] = $product_id;
		
		// To Do: Sync variations and other stuffs
	}

	/**
	 * Sync product core
	 *
	 * @param array $item
	 * @param integer $store_id
	 * @return int
	 */
	private static function syncProduct( array $item, int $store_id ) {

		// Update existing product info id exists
		// To Do: Check if the propduct is in the store actually
		if ( ! empty( $item['item_id'] ) ) {
			wp_update_post(
				array(
					'ID'         => $item['product_id'],
					'post_title' => $item['item_name']
				)
			);
			
			return $item['product_id'];
		} 
		
		// Create new product
		$product = new \WC_Product_Simple();
		$product->set_name( $item['item_name'] );
		// $product->set_slug( 'medium-size-wizard-hat-in-new-york' );
		$product->set_regular_price( 500.00 ); // in current shop currency
		$product->set_short_description( '<p>Here it is... A WIZARD HAT!</p><p>Only here and now.</p>' );
		$product->set_description( 'long description here...' );
		// $product->set_image_id( 90 );
		// $product->set_category_ids( array( 19 ) );
		// $product->set_tag_ids( array( 19 ) );
		$product->save();

		// Return the new ID
		$product_id = $product->get_id();

		// Create Solidie entry
		global $wpdb;
		$wpdb->insert( 
			self::table( 'items' ), 
			array(
				'product_id' => $product_id,
				'store_id'   => $store_id
			)
		);

		wp_update_post(
			array(
				'ID' => $product_id,
				'post_status' => 'pending'
			)
		);

		return $product_id;
	}

	private static function syncVariations() {

	}

	/**
	 * Get release log
	 *
	 * @param integer $item_id
	 * @return array
	 */
	public static function getReleases( int $item_id) {
		return array();
	}

	/**
	 * Get linked item id by WooCommerce product id
	 *
	 * @param integer $product_id
	 * @return object|null
	 */
	public static function getAppByProductId( int $product_id ) {
		global $wpdb;
		$item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::table( 'items' ) . " WHERE product_id=%d",
				$product_id
			)
		);

		return ( $item && is_object( $item ) ) ? $item : null;
	}
	
	/**
	 * Get item by item id
	 *
	 * @param integer $item_id
	 * @return object|null
	 */
	public static function getAppByID( int $item_id ) {
		global $wpdb;
		$item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT item.*, product.post_title AS item_title FROM " . self::table( 'items' ) . " item 
				INNER JOIN {$wpdb->posts} product ON item.product_id=product.ID 
				WHERE item.item_id=%d",
				$item_id
			)
		);

		return ( $item && is_object( $item ) ) ? $item : null;
	}

	/**
	 * Get item id associated with woocommerce product post name.
	 *
	 * @param string $post_name
	 * @return int|null
	 */
	public static function getAppIdByProductPostName( string $post_name ) {
		global $wpdb;

		$item_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT item.item_id FROM ".self::table('items')." item INNER JOIN {$wpdb->posts} product ON item.product_id=product.ID WHERE product.post_type='product' AND product.post_name=%s LIMIT 1",
				$post_name
			)
		);

		return $item_id ? $item_id : null;
	}

	/**
	 * Check if a product is solidie item
	 *
	 * @param string|int $product_id_or_name
	 * @return boolean
	 */
	public static function isProductApp( $product_id_or_name ) {
		$product_id = is_numeric( $product_id_or_name ) ? $product_id_or_name : self::getAppIdByProductPostName( $product_id_or_name );
		$item = self::getAppByProductId( $product_id );
		return $item !== null;
	}

	/**
	 * Get permalink as per content type
	 *
	 * @param int $id
	 * @return string
	 */
	public static function getPermalink( $id ) {
		$item         = self::getAppByProductId( $id );
		$post_name    = get_post_field( 'post_name', $id );
		$base_slug    = AdminSetting::get( 'contents.' . $item->item_type . '.slug' );
		return get_home_url() . '/' . trim( $base_slug, '/' ) . '/' . $post_name . '/';
	}

	/**
	 * Check if an associated item is free or not
	 *
	 * @param int|string $item_id_or_name
	 * @return boolean
	 */
	public static function isAppFree( $item_id_or_name ) {
		$item_id = is_numeric( $item_id_or_name ) ? $item_id_or_name : self::getAppIdByProductPostName( $item_id_or_name );
		$product_id = self::getProductID( $item_id );
		return get_post_meta( $product_id, self::FREE_META_KEY, true ) == true;
	}

	/**
	 * Get purchae by order id and variation id
	 *
	 * @param integer $order_id
	 * @param integer $variation_id
	 * @return object|null
	 */
	public static function getPurchaseByOrderVariation( int $order_id, int $variation_id ) {
		global $wpdb;
		$purchase = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::table( 'sales' ) . " WHERE order_id=%d AND variation_id=%d",
				$order_id,
				$variation_id
			)
		);

		return ( $purchase && is_object( $purchase ) ) ? $purchase : null;
	}

	/**
	 * Link items to customer after order complete
	 *
	 * @param integer $order_id
	 * @return void
	 */
	public static function processPurchase( int $order_id ) {
		// This method is for only initial order. Skip if it is renewal one. 
		if ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
			return;
		}

		global $wpdb;
		$order               = wc_get_order( $order_id );
		$order_complete_date = $order->get_date_completed();
		$items               = self::getAppsFromOrder( $order_id );
		$commission_rate     = self::getSiteCommissionRate();

		foreach ( $items as $item ) {
			// Skip if already added
			if ( self::getPurchaseByOrderVariation( $order_id, $item['variation_id'] ) ) {
				continue;
			}

			// Calculate commission
			$sale_price   = (int)$item['sale_price'];
			$commission   = ( $commission_rate / 100 ) * $sale_price;

			// Variation validity
			$expires_on  = $item['licensing']['validity_days'] ? ( new \DateTime( $order_complete_date ) )->modify('+'.$item['licensing']['validity_days'].' days')->format('Y-m-d') : null;
			
			// Insert the item in the sales table
			$wpdb->insert(
				self::table( 'sales' ),
				array(
					'item_id'            => $item['item_id'],
					'customer_id'		 => wc_get_order( $order_id )->get_customer_id(),
					'order_id'           => $order_id,
					'variation_id'       => $item['variation_id'],
					'sale_price'         => $item['sale_price'],
					'commission'         => $commission,
					'commission_rate'    => $commission_rate,
					'license_key_limit'  => $item['licensing']['license_key_limit'],
					'license_expires_on' => $expires_on,
				)
			);

			// Generate license keys
			Licensing::generateLicenseKeys( $wpdb->insert_id, $item['licensing']['license_key_limit'] );
		}
	}

	/**
	 * When customer renews a subscription
	 *
	 * @param object $subscription
	 * @return void
	 */
	public static function processSubscriptionRenewal( $subscription ) {
		global $wpdb;
		
		$items  = self::filterAppsFromOrderItems( $subscription->get_items() );

		foreach ( $items as $item ) {
			// Don't update if validity is null which means lifetime license
			if ( ! $item['licensing']['validity_days'] ) {
				continue;
			}

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE ".self::table( 'sales' )." SET license_expires_on=DATE_ADD(license_expires_on, INTERVAL %d DAY) WHERE item_id=%d AND license_expires_on IS NOT NULL",
					$item['licensing']['validity_days'],
					$item['item_id']
				)
			);
		}
	}

	/**
	 * Update next payment date
	 *
	 * @param object $subscription
	 * @return void
	 */
	public static function supportCustomPeriodForSubscription( $subscription ) {
		/* $parent_order = $subscription->get_parent_id();
		$subscription->set_date( 'next_payment', '2025-05-04' );
		$subscription->save(); */
	}

	/**
	 * Return only purchased item info from a mixed cart
	 *
	 * @param integer $order_id
	 * @return array
	 */
	public static function getAppsFromOrder( int $order_id ) {
		$order = wc_get_order( $order_id ); 
		return self::filterAppsFromOrderItems( $order->get_items() );
	}

	/**
	 * Filter items from order items
	 *
	 * @param array $items
	 * @return array
	 */
	public static function filterAppsFromOrderItems( array $items ) {
		$items  = array();
		foreach ( $items as $item ) {
			$product_type = $item->get_product()->get_type();
			$product_id   = $item->get_product_id();
			$variation_id = $item->get_variation_id();
			$variation    = new \WC_Product_Variation( $variation_id );
			$item          = self::getAppByProductId( $product_id );
			$var_info     = self::getVariationInfo( $variation );

			// Skip non-item products or unsupported variation.
			if ( ! $item || ! in_array( $product_type, array( 'subscription_variation', 'variation' ) )  || ! $var_info ) {
				continue;
			}

			// To Do: Get sale price in USD currency
			$items[] = array(
				'item'			 => $item,
				'product_id'     => $product_id,
				'item_id'        => $item->item_id,
				'variation_id'   => $variation_id,
				'licensing'      => $var_info,
				'sale_price'     => $variation->get_sale_price(),
			);
		}

		return $items;
	}

	/**
	 * Return variation label
	 *
	 * @param \WC_Product_Variation $variation
	 * @return array|null
	 */
	public static function getVariationInfo( \WC_Product_Variation $variation ) {
		$lincensing = self::getVariationBluePrint();
		$attributes = $variation->get_attributes();

		if ( isset( $attributes[ self::LICENSING_VARIATION ], $lincensing[ $attributes[ self::LICENSING_VARIATION ] ] ) ) {
			$data = $lincensing[ $attributes[ self::LICENSING_VARIATION ] ];
			$data['plan_key'] = $attributes[ self::LICENSING_VARIATION ];
			return $data;
		}

		return null;
	}
}