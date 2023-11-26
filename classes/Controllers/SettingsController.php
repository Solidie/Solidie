<?php

namespace Solidie\Controllers;

use Solidie\Models\AdminSetting;

class SettingsController {
	const PREREQUISITES = array(
		'saveAdminSettings' => array(
			'role' => 'administrator'
		)
	);

	/**
	 * Admin Dashboard Settings save
	 *
	 * @return void
	 */
	public static function saveAdminSettings() {
		if ( empty( $_POST['solidie_settings'] ) || ! is_array( $_POST['solidie_settings'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid Payload!', 'solidie' ) ) );

		} else {
			$saved = AdminSetting::save( $_POST['solidie_settings'] );
			if ( $saved === true ) {
				wp_send_json_success( array( 'message' => __( 'Settings Saved Successfully!', 'solidie' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Could not save settings!', 'solidie' ) ) );
			}
		}
		exit;
	}

}