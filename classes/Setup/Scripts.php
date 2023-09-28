<?php

namespace Solidie\Setup;

use Solidie\Helpers\Colors;
use Solidie\Main;
use Solidie\Models\AdminSetting;
use Solidie\Models\FrontendDashboard;

// To Do: Load frontend scripts only in catalog and single content page when not in development mode
// To Do: Load frontend dashboard script only in the dashboard
// To Do: Load backend dashboard script only in solidie backend pages
// To Do: Pass sales data to solidie (if the plan is reveneue share) from only JS as it is encoded and hard to reverse engineer. TBD how to get the data in JS first.

class Scripts extends Main {
	public function __construct() {

		add_action( 'admin_enqueue_scripts', array( $this, 'adminScripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontendScripts' ) );

		// Vars
		add_action( 'wp_head', array( $this, 'loadVariables' ), 1000 );
		add_action( 'admin_head', array( $this, 'loadVariables' ), 1000 );
	}

	public function loadVariables() {

		// Load dynamic colors
		$dynamic_colors = Colors::getColors();
		$_colors        = '';
		foreach ( $dynamic_colors as $name => $code ) {
			$_colors .= '--crewhrm-color-' . esc_attr( $name ) . ':' . esc_attr( $code ) . ';';
		}
		echo '<style>:root{' . $_colors . '}</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Load data
		$data = array(
			'ajax_url'     => admin_url( 'admin-ajax.php' ),
			'home_url'     => get_home_url(),
			'home_path'    => rtrim( parse_url( get_home_url() )['path'] ?? '/', '/' ) . '/',
			'content_name' => self::$configs->content_name,
			'nonce'        => wp_create_nonce( Main::$configs->app_name ),
			'colors'       => $dynamic_colors,
			'settings'     => array(
				'contents'  => AdminSetting::get( 'contents' ),
				'dashboard' => AdminSetting::get( 'dashboard' ),
			)
		);
		
		echo '<script>window.Solidie=' . wp_json_encode( $data ) . '</script>';
	}

	public function adminScripts() {
		if ( get_admin_page_parent() == self::$configs->root_menu_slug  ) {
			wp_enqueue_script( 'solidie-admin-script', self::$configs->dist_url . 'admin-dashboard.js', array( 'jquery' ), self::$configs->version, true );
		}
	}

	public function frontendScripts() {
		if ( FrontendDashboard::is_dashboard() ) {
			wp_enqueue_script( 'appstore-frontend-dashboard-script', self::$configs->dist_url . 'frontend-dashboard.js', array( 'jquery' ), self::$configs->version, true );
		} else {
			wp_enqueue_script( 'appstore-frontend-script', self::$configs->dist_url . 'frontend.js', array( 'jquery' ), self::$configs->version, true );
		}
	}
}