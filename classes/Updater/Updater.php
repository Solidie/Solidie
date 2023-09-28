<?php
namespace Solidie\Updater;

use stdClass;

/**
 * This is a unified class for custom WordPress themes and plugins update. 
 */
class Updater {
	private $main_file_path;
	private $license_page_parent = null;
	private $extension_type;
	private $content_identifier_name;
	private $content_name;
	private $content_basename;
	private $url_slug;
	private $update_hook_prefix;
	private $is_plugin;
	private $content_version;
	private $content_slug;
	private $updater_url;
	private $menu_title;
	private $license_option_key;
	private $activate_action;
	private $activate_nonce;
	public  $license;

	/**
	 * Updater constructor
	 *
	 * @param object $config
	 */
	function __construct( object $configs ) {
		return;

		$this->content_identifier_name = $configs->content_name;
		$this->main_file_path      = $configs->file;
		$this->license_page_parent = $configs->root_menu_slug;
		$this->update_hook_prefix  = ( $configs->continuous_update ?? false ) ? '' : 'pre_set_';
		$this->extension_type      = strpos( str_replace( '\\', '/', $this->main_file_path ) , 'wp-content/plugins' ) ? 'plugin' : 'theme';
		$this->is_plugin           = $this->extension_type == 'plugin';

		// Get content meta data and set as property
		$content_info           = $this->is_plugin ? get_plugin_data( $this->main_file_path ) : wp_get_theme();
		$this->content_basename = $this->is_plugin ? plugin_basename( $this->main_file_path ) : get_stylesheet(); // To Do: Test theme update flow
		$this->content_name     = $this->is_plugin ? $content_info['Name'] : $content_info->get('Name');
		$this->content_version  = $this->is_plugin ? $content_info['Version'] : $content_info->get('Version');
		$this->menu_title   = $this->content_name . ' License';

		// Determine URL to this updater directory
		$content_root_url             = $this->is_plugin ? plugin_dir_url( $this->main_file_path ) : get_template_directory_uri() . '/';
		$sep                      = $this->is_plugin ? '/wp-content/plugins/' : '/wp-content/themes/';
		$updater_full_path        = str_replace( '\\', '/', __DIR__ );
		$updater_rel_path         = substr( $updater_full_path, strpos( $updater_full_path, $sep ) + strlen( $sep ) ) . '/';
		$this->content_slug           = substr( $updater_rel_path, 0, strpos( $updater_rel_path, '/' ) );
		$this->url_slug           = 'solidie-license-' . $this->content_slug;
		$this->license_option_key = 'solidie_license_setting_' . $this->content_slug;
		$this->updater_url        = $content_root_url . substr( $updater_rel_path, strlen( $this->content_slug . '/' ) ) ;
		$this->activate_action    = 'solidie_activate_license_key_' . $this->content_slug;
		$this->activate_nonce     = 'solidie_activate_license_key_nonce_' . $this->content_slug;


		// Get saved license
		$this->license = $this->get_license();

		// Register license page hooks if parent page slug defined, it means the content is not free and requires license activation to get updates. 
		if ( ! ( $configs->is_free ?? false ) ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'license_page_asset_enqueue' ) );
			add_action( 'admin_menu', array( $this, 'add_license_page' ), 20 );
			add_action( 'admin_notices', array( $this, 'show_inactive_license_notice' ) );
			add_action( 'wp_ajax_' . $this->activate_action, array( $this, 'license_key_submit' ) );
		}

		// Register plugin and theme api request hooks
		if($this->extension_type == 'plugin') {
			add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
			add_filter( $this->update_hook_prefix . 'site_transient_update_plugins', array( $this, 'check_for_update' ) );
		} else if ( $this->extension_type == 'theme' ) {
			add_filter( $this->update_hook_prefix . 'site_transient_update_themes', array( $this, 'check_for_update' ) );
		}
	}

	/**
	 * Enqueue scripts for license key submission page
	 *
	 * @return void
	 */
	public function license_page_asset_enqueue() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== $this->url_slug ) {
			return;
		}
	
		wp_enqueue_style( $this->url_slug . '-css', $this->updater_url . 'license-form.css', $this->content_version );
		wp_enqueue_script( $this->url_slug . '-js', $this->updater_url . 'license-form.js', array( 'jquery' ), $this->content_version, true );
		wp_localize_script( $this->url_slug . '-js', 'solidie_activate_license_data',
			array( 
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'action'   => $this->activate_action,
				'nonce'    => wp_create_nonce( $this->activate_nonce ),
				'content_name' => $this->content_identifier_name
			)
		);
	}

	/**
	 * Add license key submission as a sub menu under defined parent.
	 *
	 * @return void
	 */
	public function add_license_page() {
		add_submenu_page( $this->license_page_parent, $this->menu_title, __( 'License' ), 'manage_options', $this->url_slug, array( $this, 'license_form' ) );
	}

	/**
	 * License key submission page html contents
	 *
	 * @return void
	 */
	public function license_form() {
		// Refresh license state before page load
		$this->APICall();

		// Load the form now
		include __DIR__ . '/license-form.php';
	}

	/**
	 * Return prepared request
	 *
	 * @param string|null $action
	 * @return object|null
	 */
	private function APICall( $action = null, $license_key = null ) {
		if ( ! $license_key ) {
			$license_info = $this->get_license();
			$license_key  = $license_info ? ( $license_info['license_key'] ?? '' ) : '';
		}

	
		// Parse license key
		$fragments = explode( ' ', ( @base64_decode( $license_key ) ?? '' ) );
		if ( count( $fragments ) !== 2 ) {
			return;
		}

		// Fragments explained
		$api_endpoint     = trailingslashit( $fragments[0] ) . 'solidie/api/'; // e.g https://solidie.com/
		$real_license_key = $fragments[1]; // e.g afzz4nU2tUGDvdt5wuEwqpm3d8FSNfH8NrE8pbxNr+A=
		
		$payload =  array(
			'license_key'  => $real_license_key,
			'endpoint'     => get_home_url(),
			'content_name' => $this->content_identifier_name,
			'action'       => $action ?? ( $this->license_page_parent ? 'update-check' : 'update-check-free' ),
		);

		$request = wp_remote_post( $api_endpoint, array( 'body' => $payload ) );
		$response = ( ! is_wp_error( $request ) && is_array( $request ) ) ? @json_decode( $request['body'] ?? null ) : null;

		// Set fall back
		$response          = is_object( $response ) ? $response : new stdClass();
		$response->success = $response->success ?? false;
		$response->data    = $response->data ?? new stdClass();
		
		// Deactivate key if any request send falsy
		if ( $response && isset( $response->data->activated ) && $response->data->activated === false ) {
			update_option(
				$this->license_option_key, 
				array( 
					'activated'   => false, 
					'license_key' => $license_key,
					'message'     => __( 'The license key is expired or revoked!' ) 
				) 
			);
		}

		return $response;
	}

	/**
	 * Activate license key on submit
	 *
	 * @return void
	 */
	public function license_key_submit() {

		if ( ! isset( $_POST['nonce'], $_POST['content_name'], $_POST[ 'license_key' ] ) || $_POST['content_name'] !== $this->content_identifier_name || empty( $_POST[ 'license_key' ] ) ) {
			wp_send_json_error( array( 'message' => 'Invalid data!' ) );
			exit;
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], $this->activate_nonce ) ) {
			wp_send_json_error( array( 'message' => 'Session expired! Reload the page and try again please.' ) );
			exit;
		}
			
		$license_key = wp_unslash( $_POST[ 'license_key' ] );
		$response    = $this->APICall( 'activate-license', $license_key );

		if ( $response->success ) {
			$license_info = array(
				'license_key' => $license_key,
				'activated'   => $response->data->activated ? true : false,
				'licensee'    => $response->data->licensee ?? null,
				'expires_on'  => $response->data->expires_on ?? null,
				'plan_name'   => $response->data->plan_name ?? null,
				'message'     => $response->data->message ?? null,
			);

			update_option($this->license_option_key, $license_info);
			wp_send_json_success( array( 'message' => $license_info['message'] ) );
		} else {
			wp_send_json_error( array( 'message' => $response->data->message ?? __( 'Request error! The license key is not correct or could not connect to the validator server.' ) ) );
		}

		exit;
	}

	/**
	 * @param $res
	 * @param $action
	 * @param $args
	 *
	 * @return bool|\stdClass
	 *
	 * Get the plugin info from server
	 */
	function plugin_info( $res, $action, $args ) {

		// do nothing if this is not about getting plugin information and not about this content.
		if ( $action !== 'plugin_information' || ( $this->content_slug !== $args->slug && $this->content_basename !== $args->slug ) ) {
			return false;
		}

		$remote    = $this->APICall();
		$res       = new \stdClass();
		$res->slug = $this->content_slug;
		$res->name = $this->content_name;
		$res->version = $this->content_version;
		
		if ( $remote->success ) {
			$res->version      = $remote->data->version;
			$res->last_updated = date_format( date_create( '@' . $remote->data->release_timestamp ), "Y-m-d H:i:s" );
			$res->sections     = array(
				'changelog' => nl2br( $remote->data->changelog ),
			);
		}
		
		return $res;
	}

	/**
	 * @param $transient
	 *
	 * @return mixed
	 */
	public function check_for_update( $transient ) {
		$update_info = null;
		$request_body = $this->APICall();
		if ( $request_body->success && version_compare( $this->content_version, $request_body->data->version, '<' ) ) {
			$update_info = array(
				'new_version'   => $request_body->data->version,
				'package'       => $request_body->data->download_url,
				'tested'        => $request_body->host_version[0] ?? null,
				'compatibility' => $request_body->host_version,
				'slug'          => $this->content_basename,
				'url'           => $request_body->data->content_url,
			);
		}

		// Now update this content data in the transient
		$transient->response[ $this->content_basename ] = $update_info ? ( $this->extension_type=='plugin' ? (object)$update_info : $update_info ) : null;

		return $transient;
	}

	/**
	 * Show license notice at the top if no key assigned or assigned on not valid anymore
	 *
	 * @return void
	 */
	public function show_inactive_license_notice() {
		if ( $this->license['activated'] ) {
			return;
		}

		$class = 'notice notice-error';
		$message = sprintf(
			__('There is an error with your %s License. Automatic update has been turned off, %s Please check license %s.', $this->url_slug), 
			$this->content_name, 
			" <a href='" . admin_url( 'admin.php?page=' . $this->url_slug ) . "'>", 
			'</a>'
		);

		printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
	}

	/**
	 * Get saved license
	 *
	 * @return void
	 */
	private function get_license() {
		// Get from option. Not submitted yet if it is empty.
		$license_option = get_option( $this->license_option_key, null );
		if ( empty( $license_option ) ) {
			return null;
		}

		// Unsrialize the license info
		$license = maybe_unserialize( $license_option );
		$license = is_array( $license ) ? $license : array();

		$keys = array(
			'activated',
			'license_key',
			'licensee',
			'expires_on',
			'plan_name',
			'message'
		);

		foreach ( $keys as $key ) {
			$license[ $key ] = ! empty( $license[ $key ] ) ? $license[ $key ] : null;
		}

		return $license;
	}
}
