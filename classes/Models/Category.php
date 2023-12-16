<?php

namespace Solidie\Models;

use Solidie\Helpers\_Array;

class Category {
	/**
	 * Create or update category
	 *
	 * @param array $category Single category data associative array
	 * @return int
	 */
	public static function createUpdateCategory( array $category ) {
		$_category = array(
			'category_name' => $category['category_name'],
			'parent_id'     => $category['parent_id'] ?? null,
			'content_type'  => $category['content_type'],
		);

		global $wpdb;
		$cat_id = $category['category_id'] ?? null;

		if ( ! empty( $category['category_id'] ) ) {
			$wpdb->update(
				DB::categories(),
				$_category,
				array( 'category_id' => $category['category_id'] )
			);
		} else {
			$wpdb->insert(
				DB::categories(),
				$_category
			);
			$cat_id = $wpdb->insert_id;
		}

		return $cat_id;
	}

	/**
	 * Get all categories regardless of contents
	 *
	 * @return array
	 */
	public static function getCategories() {
		global $wpdb;
		$cats = $wpdb->get_results(
			"SELECT * FROM " . DB::categories(),
			ARRAY_A
		);

		$cats = _Array::getArray( $cats );
		$cats = _Array::groupRows( $cats, 'content_type' );
		foreach ( $cats as $index => $cat ) {
			$cats[ $index ] = _Array::buildNestedArray( $cat, null, 'parent_id', 'category_id' );
		}

		return (object) $cats;
	}

	/**
	 * Delete category
	 *
	 * @param int $category_id
	 * @return void
	 */
	public static function deleteCategory( $category_id ) {
		global $wpdb;

		// Update content categories to null where it is used
		$wpdb->update(
			DB::contents(),
			array( 'category_id' => null ),
			array( 'category_id' => $category_id )
		);

		// Delete category itself
		$wpdb->delete(
			DB::categories(),
			array( 'category_id' => $category_id )
		);
	}

	/**
	 * Get children IDs of a category
	 *
	 * @param int $category_id
	 * @return array
	 */
	public static function getChildren( $category_id, $linear = true ) {
		
		$category_id = (int) $category_id;

		global $wpdb;
		$cats = $wpdb->get_results(
			"SELECT * FROM " . DB::categories(),
			ARRAY_A
		);

		$cats   = _Array::castRecursive( $cats );
		$table  = _Array::buildNestedArray( $cats, $category_id, 'parent_id', 'category_id' );

		return $linear ? _Array::convertToSingleTable( $table, 'children' ) : $table;
	}
}
