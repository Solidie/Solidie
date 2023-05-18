<?php

namespace Solidie\AppStore\Setup;

use Solidie\AppStore\Main;
use Solidie\AppStore\Models\Apps;
use Solidie\AppStore\Models\Hit;
use Solidie\AppStore\Models\Licensing;
use Solidie\AppStore\Models\Release;

class RestAPI extends Main {
	const API_PATH               = '/appstore/api';
	const NONCE_ACTION           = 'app_store_download';
	const DOWNLOAD_LINK_VALIDITY = 720; // in minutes. 12 hours here as WordPress normally checks for updates every 12 hours.

	private static $required_fields = array(
		'app_name',
		'license_key',
		'endpoint',
		'action'
	);

	public function __construct() {
		add_action( 'init', array( $this, 'add_license_api' ) );
	}
	
	public function add_license_api() {
		// Check if it is api request
		$url         = explode( '?', self::$configs->current_url );
		$current_url = trim( $url[0], '/' );
		if ( get_home_url() . self::API_PATH !== $current_url ) {
			return;
		}

		// Set locale
		$locale = $_GET['locale'] ?? $_POST['locale'] ?? null;
		if ( ! empty( $locale ) && is_string( $locale ) ) {
			setlocale( LC_ALL, $locale );
		}

		// Process download if token and download parameter is present
		if ( ! empty( $_GET['download'] ) ) {
			$this->update_download();
			exit;
		}

		// Waive license key requirement for free update check
		if ( ( $_POST['action'] ?? '' ) === 'update-check-free' ) {
			unset( self::$required_fields['license_key'] );
		}

		// Loop through required fields and check if exists
		foreach ( self::$required_fields as $field ) {
			if ( empty( $_POST[ $field ] ) || ( $field == 'endpoint' && strpos( $_POST[ $field ], ' ' ) !== false ) ) {
				wp_send_json_error( 
					array(
						'message'        => sprintf( 'Invalid data. Required fields are %s. Whitespace in endpoint is not allowed.', implode( ', ', self::$required_fields ) ),
						'request_params' => $_POST
					) 
				);
				exit;
			}

			$_POST[ $field ] = sanitize_text_field( $_POST[ $field ] );
		}

		// Now process free-update-check
		if ( $_POST['action'] == 'update-check-free' ) {
			$this->update_check_free( $_POST['app_name'] );
			exit;
		}

		// Check and get license data. It will terminate if the license is not usable.
		$license_data = $this->getLicenseData();

		// Process request
		switch ( $_POST['action'] ) {
			case 'activate-license' :
				$this->license_activate( $license_data );
				break;

			case 'update-check' :
				$this->update_check( $license_data );
				break;

			default :
				wp_send_json_error( array( 'message' => _x( 'Invalid action!', 'appstore', 'appstore' ) ) );
				exit;
		}
	}

	private function getLicenseData() {
		$app_id       = Apps::getAppIdByProductPostName( $_POST['app_name'] );
		$license_info = $app_id ? Licensing::getLicenseInfo( $_POST['license_key'], $app_id ) : null;

		// If no license found, then it is either malformed or maybe app id is not same for the license
		if ( ! is_array( $license_info ) || empty( $license_info ) ) {
			wp_send_json_error(
				array( 
					'message'   => _x( 'Invalid License Key', 'appstore', 'appstore' ),
					'activated' => false
				) 
			);
			exit;
		}

		// If the action is activate, then current endpoint must be null or same as provided endpoint (In case duplicate call) which means slot availabe for the dnpoint. 
		if ( $_POST['action'] == 'activate-license' && null !== $license_info['endpoint'] && $_POST['endpoint'] !== $license_info['endpoint']) {
			wp_send_json_error(
				array(
					'message'   => _x( 'The license key is in use somewhere else already.', 'appstore', 'appstore' ),
					'activated' => false
				)
			);
			exit;
		}

		// If the action is non activate, then the both endpoint must match to check update or download. 
		if ( $_POST['action'] !== 'activate-license' && $license_info['endpoint'] !== $_POST['endpoint'] ) {
			wp_send_json_error(
				array( 
					'message'   => _x( 'The license key is not associated with your endpoint.', 'appstore', 'appstore' ), 
					'activated' => false
				)
			);
			exit;
		}

		return $license_info;
	}

	/**
	 * Activate license key
	 *
	 * @param array $license
	 * @return void
	 */
	private function license_activate( array $license ) {
		global $wpdb;

		if ( $license['endpoint'] === $_POST['endpoint'] ) {
			$message = _x( 'The license is activated already', 'appstore', 'appstore' );
		} else {
			$wpdb->update(
				self::table( 'license_keys' ),
				array( 'endpoint' => $_POST['endpoint'] ),
				array( 'license_id' => $license['license_id'] )
			);
		
			$message = _x( 'License activated succefully', 'appstore', 'appstore' );
			Hit::registerHit( 'activate-license', null, $license['license_id'], $_POST['endpoint'] );
		}
		
		wp_send_json_success(
			array(
				'license_key' => $license['license_key'],
				'activated'   => true,
				'licensee'    => $license['licensee_name'],
				'expires_on'  => $license['expires_on'],
				'plan_name'   => $license['plan_name'],
				'message'     => $message,
				'endpoint'    => $_POST['endpoint'],
			)
		);
		exit;
	}

	/**
	 * Update check for app
	 *
	 * @param array $license
	 * @return void
	 */
	private function update_check( array $license ) {
		$release = Release::getRelease( $license['app_id'] );
		if ( ! $release ) {
			wp_send_json_error( array( 'message' => _x( 'No release found.' ) ) );
			exit;
		}

		Hit::registerHit( $_POST['action'], null, $license['license_id'], $_POST['endpoint'] );

		wp_send_json_success(
			array(
				'app_url'           => $release->app_url,
				'version'           => $release->version,
				'host_version'		=> array(),
				'release_datetime'  => $release->release_date,
				'release_timestamp' => $release->release_unix_timestamp,
				'changelog'         => $release->changelog,
				'download_url'      => get_home_url() . self::API_PATH . '/?download=' . urlencode( Licensing::encrypt( $release->app_id . ' ' . ( $license['license_id'] ?? 0 ) . ' ' . time() . ' ' . $_POST['endpoint'] ) ), // License id null means it's free app
			)
		);
		
		exit;
	}

	/**
	 * Check update for free product
	 *
	 * @param string $app_name
	 * @param string $endpoint
	 * @return void
	 */
	private function update_check_free( string $app_name ) {

		if ( ! Apps::isAppFree( $app_name ) ) {
			wp_send_json_error( array( 'message' => _x( 'The app you\'ve requested update for is not free. Please correct your credentials and try again.', 'appstore', 'appstore' ) ) );
			exit;
		}

		$this->update_check(
			array(
				'app_id' => Apps::getAppIdByProductPostName( $app_name ),
				'license_id' => null, // Means free app
			)
		);
	}

	private function update_download() {

		$parse = Licensing::decrypt( $_GET['download'] );
		$parse = $parse ? explode( ' ', $parse ) : array();

		// Exit if the token is malformed
		if ( count( $parse ) !== 4 || ! is_numeric( $parse[0] ) || ! is_numeric( $parse[1] ) || ! is_numeric( $parse[2] ) ) {
			wp_send_json_error( array( 'message' => _x( 'Invalid Request', 'appstore', 'appstore' ) ) );
			exit;
		}

		$app_id     = (int) $parse[0];
		$license_id = (int) $parse[1]; // 0 means free app
		$token_time = (int) $parse[2];
		$endpoint   = $parse[3];

		// Exit if link is older than defined time
		if ( $token_time < time() - ( self::DOWNLOAD_LINK_VALIDITY * 60 ) ) {
			wp_send_json_error( array( 'message' => sprintf( _x( 'Download link expired as it is older than %d minutes.', 'appstore', 'appstore' ), self::DOWNLOAD_LINK_VALIDITY ) ) );
			exit;
		}

		if ( ! $license_id && ! Apps::isAppFree( $app_id ) ) {
			wp_send_json_error( array( 'message' => _x( 'Sorry! The app is no more free to download. You need to activate license first.', 'appstore', 'appstore' ) ) );
			exit;
		}

		$release = Release::getRelease( $app_id );
		if ( ! $release ) {
			wp_send_json_error( array( 'message' => _x( 'Something went wrong. No release found for this app.', 'appstore', 'appstore' ) ) );
			exit;
		}

		$file_source = $release->file_path ?? $release->file_url;
		$file_name   = $release->app_name . ' - ' . $release->version . '.' . pathinfo( basename( $file_source ), PATHINFO_EXTENSION );
		if ( ! $file_source ) {
			wp_send_json_error( array( 'message' => _x( 'Something went wrong. Release file not found.', 'appstore', 'appstore' ) ) );
			exit;
		}

		// License id 0 means it's free app
		Hit::registerHit( 'update-download', $release->release_id, ($license_id===0 ? null : $license_id), $endpoint );
		
		nocache_headers();
		header( 'Content-Type: ' . $release->mime_type . '; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $file_name );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		readfile( $file_source );
		exit;
	}
}