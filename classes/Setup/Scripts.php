<?php
/**
 * Script registrars
 *
 * @package solidie
 */

namespace Solidie\Setup;

use Solidie\Controllers\ProController;
use Solidie\Helpers\_Array;
use Solidie\Helpers\Colors;
use Solidie\Helpers\Utilities;
use Solidie\Main;
use Solidie\Models\AdminSetting;
use Solidie\Models\Contents;

/**
 * Script class
 */
class Scripts {

	/**
	 * Scripts constructor, register script hooks
	 *
	 * @return void
	 */
	public function __construct() {

		// Load scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'adminScripts' ), 11 );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontendScripts' ), 11 );

		// Register script translations
		add_action( 'admin_enqueue_scripts', array( $this, 'scriptTranslation' ), 9 );
		add_action( 'wp_enqueue_scripts', array( $this, 'scriptTranslation' ), 9 );

		// Load script for pro dashboard, especially for the indentory page.
		add_action( 'solidie_fe_dashboard_js_enqueue_before', array( $this, 'loadScriptForProDashboard' ) );

		// Load text domain
		add_action( 'init', array( $this, 'loadTextDomain' ) );

		// JS Variables
		add_action( 'wp_head', array( $this, 'loadVariables' ), 1000 );
		add_action( 'admin_head', array( $this, 'loadVariables' ), 1000 );
	}

	/**
	 * Load environment and color variables
	 *
	 * @return void
	 */
	public function loadVariables() {

		// Load dynamic colors
		$dynamic_colors = Colors::getColors();
		$_colors        = '';
		foreach ( $dynamic_colors as $name => $code ) {
			$_colors .= '--solidie-color-' . esc_attr( $name ) . ':' . esc_attr( $code ) . ';';
		}
		?>
			<style>
				:root{
					<?php echo esc_html( $_colors ); ?>
				}
			</style>
		<?php

		$nonce_action = '_solidie_' . str_replace( '-', '_', gmdate( 'Y-m-d' ) );
		$nonce        = wp_create_nonce( $nonce_action );
		$user         = wp_get_current_user();

		// Check if email subscribed
		$subscribeds = _Array::getArray( get_option( ProController::SUBSCRIBED_MAILS ) );

		// Determine the react react root path
		$parsed    = wp_parse_url( get_home_url() );
		$root_site = 'http' . ( is_ssl() ? 's' : '' ) . '://' . $parsed['host'] . ( ! empty( $parsed['port'] ) ? ':' . $parsed['port'] : '' );
		$home_path = trim( $parsed['path'] ?? '', '/' );
		$page_path = is_singular() ? trim( str_replace( $root_site, '', get_permalink( get_the_ID() ) ), '/' ) : null;

		// Load data
		$data = apply_filters(
			'solidie_frontend_variables',
			array(
				'is_admin'         => is_admin(),
				'action_hooks'     => array(),
				'filter_hooks'     => array(),
				'mountpoints'      => ( object ) array(),
				'home_path'        => $home_path,
				'page_path'        => $page_path,
				'app_name'         => Main::$configs->app_id,
				'nonce'            => $nonce,
				'nonce_action'     => $nonce_action,
				'is_pro_installed' => Utilities::isProInstalled( false ),
				'is_pro_active'    => Utilities::isProInstalled( true ),
				'colors'           => $dynamic_colors,
				'opacities'        => Colors::getOpacities(),
				'contrast'         => Colors::CONTRAST_FACTOR,
				'text_domain'      => Main::$configs->text_domain,
				'date_format'      => get_option( 'date_format' ),
				'time_format'      => get_option( 'time_format' ),
				'readonly_mode'    => apply_filters( 'solidie_readonly_mode', false ), // It's for solidie demo site only. No other use is expected.
				'is_apache'        => is_admin() ? strpos( sanitize_text_field( $_SERVER['SERVER_SOFTWARE'] ?? '' ), 'Apache' ) !== false : null,
				'bloginfo'         => array(
					'name' => get_bloginfo( 'name' ),
				),
				'user'             => array(
					'id'                    => $user ? $user->ID : 0,
					'first_name'            => $user ? $user->first_name : null,
					'last_name'             => $user ? $user->last_name : null,
					'email'                 => $user ? $user->user_email : null,
					'display_name'          => $user ? $user->display_name : null,
					'avatar_url'            => $user ? get_avatar_url( $user->ID ) : null,
					'newsletter_subscribed' => $user && in_array( $user->user_email, $subscribeds ),
				),
				'settings'         => array(
					'contents' => AdminSetting::getContentSettings(),
					'general'  => array(),
				),
				'permalinks'       => array(
					'home_url'          => get_home_url(),
					'ajaxurl'           => admin_url( 'admin-ajax.php' ),
					'inventory_backend' => Utilities::getBackendPermalink( AdminPage::INVENTORY_SLUG ),
					'settings'          => Utilities::getBackendPermalink( AdminPage::SETTINGS_SLUG ),
					'dashboard'         => Utilities::getBackendPermalink( Main::$configs->root_menu_slug ),
					'gallery'           => ( object ) Contents::getGalleryPermalink(),
					'gallery_root'      => Contents::getGalleryPermalink( false ),
					'logout'            => htmlspecialchars_decode( wp_logout_url( get_home_url() ) ),
					'api_host'          => Main::$configs->api_host,
				),
			)
		);

		$pointer = Main::$configs->app_id;

		?>
		<script>
			window.<?php echo $pointer; ?> = <?php echo wp_json_encode( $data ); ?>;
			window.<?php echo $pointer; ?>pro = window.<?php echo $pointer; ?>;
		</script>
		<?php
	}

	/**
	 * Load scripts for admin dashboard
	 *
	 * @return void
	 */
	public function adminScripts() {
		if ( Utilities::isAdminDashboard() ) {
			$this->loadTinyMCE();
			wp_enqueue_script( 'solidie-backend', Main::$configs->dist_url . 'admin-dashboard.js', array( 'jquery' ), Main::$configs->version, true );
		}
	}

	/**
	 * Load resource for TinyMCE editor
	 *
	 * @return void
	 */
	public function loadTinyMCE() {
		wp_enqueue_style( 'solidie-backend-tiny-style', Main::$configs->dist_url . 'libraries/tinymce/css/style.css', array(), Main::$configs->version );
		wp_enqueue_script( 'solidie-backend-tiny', Main::$configs->dist_url . 'libraries/tinymce/js/tinymce/tinymce.min.js', array( 'jquery' ) );
	}

	/**
	 * Load scripts for frontend view
	 *
	 * @return void
	 */
	public function frontendScripts() {
		if ( ! empty( $GLOBALS['solidie_gallery_data'] ) ) {
			wp_enqueue_style( 'solidie-tiny-styles-css', Main::$configs->dist_url . 'libraries/prism/prism.css', array(), Main::$configs->version );
			wp_enqueue_script( 'solidie-tiny-styles-js', Main::$configs->dist_url . 'libraries/prism/prism.js', array(), Main::$configs->version, true );
		}
		wp_enqueue_script( 'solidie-frontend', Main::$configs->dist_url . 'frontend.js', array( 'jquery' ), Main::$configs->version, true );
	}

	/**
	 * Load scripts to render free page in pro dashboard
	 *
	 * @return void
	 */
	public function loadScriptForProDashboard() {
		$this->loadTinyMCE();
		wp_enqueue_script( 'solidie-frontend-patch', Main::$configs->dist_url . 'frontend-dashboard-patch.js', array( 'jquery' ), Main::$configs->version, true );
	}

	/**
	 * Load text domain for translations
	 *
	 * @return void
	 */
	public function loadTextDomain() {
		load_plugin_textdomain( Main::$configs->text_domain, false, Main::$configs->dir . 'languages' );
	}

	/**
	 * Load translations
	 *
	 * @return void
	 */
	public function scriptTranslation() {

		$domain = Main::$configs->text_domain;
		$dir    = Main::$configs->dir . 'languages/';

		wp_enqueue_script( 'solidie-translations', Main::$configs->dist_url . 'libraries/translation-loader.js', array( 'jquery' ), Main::$configs->version, true );
		wp_set_script_translations( 'solidie-translations', $domain, $dir );
	}
}
