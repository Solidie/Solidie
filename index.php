<?php
/**
 * Plugin Name: Solidie
 * Plugin URI: https://solidie.com
 * Description: Digital content showcase
 * Author: Solidie
 * Version: 1.0.0
 * Author URI: https://solidie.com
 * Requires at least: 5.3
 * Tested up to: 6.4.1
 * Text Domain: solidie
 *
 * @package solidie
 */

// Load autoloader
require_once __DIR__ . '/classes/Main.php';

( new Solidie\Main() )->init(
	(object) array(
		'file'           => __FILE__,
		'mode'           => 'development',
		'root_menu_slug' => 'solidie',
		'db_prefix'      => 'solidie_',
		'current_url'    => ( is_ssl() ? 'https' : 'http' ) . '://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) ) . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ),
	)
);
