<?php
/**
 * The dispatcher where all the ajax request pass through after validation
 *
 * @package solidie
 */

namespace Solidie\Setup;

use Solidie\Main;
use Solidie\Models\User;
use Solidie\Helpers\_Array;
use Solidie\Controllers\ContentController;
use Solidie\Controllers\SettingsController;
use Solidie\Controllers\CategoryController;
use Error;

/**
 * Dispatcher class
 */
class Dispatcher {

	/**
	 * Dispatcher registration in constructor
	 *
	 * @return void
	 * @throws Error If there is any duplicate ajax handler across controllers.
	 */
	public function __construct() {
		// Register ajax handlers only if it is ajax call
		if ( ! wp_doing_ajax() ) {
			return;
		}

		add_action( 'plugins_loaded', array( $this, 'registerControllers' ), 11 );
	}

	/**
	 * Register ajax request handlers
	 *
	 * @return void
	 */
	public function registerControllers(){

		$controllers = array(
			ContentController::class,
			SettingsController::class,
			CategoryController::class
		);

		$registered_methods = array();
		$controllers        = apply_filters( 'solidie_controllers', $controllers );

		// Loop through controllers classes
		foreach ( $controllers as $class ) {

			// Loop through controller methods in the class
			foreach ( $class::PREREQUISITES as $method => $prerequisites ) {
				if ( in_array( $method, $registered_methods, true ) ) {
					throw new Error( __( 'Duplicate endpoint ' . $method . ' not possible' ) );
				}

				// Determine ajax handler types
				$handlers    = array();
				$handlers [] = 'wp_ajax_' . Main::$configs->app_name . '_' . $method;

				// Check if norpriv necessary
				if ( ( $prerequisites['nopriv'] ?? false ) === true ) {
					$handlers[] = 'wp_ajax_nopriv_' . Main::$configs->app_name . '_' . $method;
				}

				// Loop through the handlers and register
				foreach ( $handlers as $handler ) {
					add_action(
						$handler,
						function() use ( $class, $method, $prerequisites ) {
							$this->dispatch( $class, $method, $prerequisites );
						}
					);
				}

				$registered_methods[] = $method;
			}
		}
	}

	/**
	 * Dispatch request to target handler after doing verifications
	 *
	 * @param string $class         The class to dispatch the request to
	 * @param string $method        The method of the class to invoke
	 * @param array  $prerequisites Controller access prerequisites
	 *
	 * @return void
	 */
	public function dispatch( $class, $method, $prerequisites ) {
		// Determine post/get data
		$is_post = isset( $_SERVER['REQUEST_METHOD'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) === 'post' : null;
		$data    = $is_post ? $_POST : $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data    = _Array::stripslashesRecursive( _Array::getArray( $data ) );
		$files   = is_array( $_FILES ) ? $_FILES : array();

		// Verify nonce first of all
		/* $matched = wp_verify_nonce( ( $data['nonce'] ?? '' ), $data['nonce_action'] ?? '' );
		if ( ! $matched ) {
			wp_send_json_error( array( 'message' => __( 'Session Expired! Reloading the page might help resolve.', 'solidie' ) ) );
		} */

		// Verify required user role
		$_required_roles = $prerequisites['role'] ?? array();
		$_required_roles = is_array( $_required_roles ) ? $_required_roles : array( $_required_roles );
		if ( ! User::validateRole( get_current_user_id(), $_required_roles ) ) {
			wp_send_json_error( array( 'message' => __( 'Access Denied!', 'solidie' ) ) );
		}

		// Now pass to the action handler function
		if ( class_exists( $class ) && method_exists( $class, $method ) ) {
			$class::$method( $data, $files );
		} else {
			wp_send_json_error( array( 'message' => __( 'Invalid Endpoint!', 'solidie' ) ) );
		}
	}
}
