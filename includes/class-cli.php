<?php
/**
 * Plugin CLI Commands
 *
 * @package ZeroSpam
 */
class ZeroSpamCLI {
	/**
	 * Auto-configure the plugin with recommended settings
	 */
	public function autoconfigure() {
		\ZeroSpam\Core\Settings::auto_configure();
		WP_CLI::success( __( 'Zero Spam has been successfully auto-configured using the recommended defaults.', 'zero-spam' ) );
	}

	/**
	 * Outputs settings
	 */
	public function settings() {
		$zerospam_settings = \ZeroSpam\Core\Settings::get_settings();
		$settings          = array();

		foreach ( $zerospam_settings as $key => $setting ) {
			$settings[] = array(
				'setting' => $key,
				'value'   => isset( $setting['value'] ) ? $setting['value'] : false,
			);
		}

		$fields = array( 'setting', 'value' );
		WP_CLI\Utils\format_items( 'table', $settings, $fields );
	}

	/**
	 * Update a plugin setting(s)
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Array of settings to update.
	 */
	public function set( $args, $assoc_args ) {
		$settings = \ZeroSpam\Core\Settings::get_settings();

		if ( $assoc_args ) {
			foreach ( $assoc_args as $key => $value ) {
				if ( ! isset( $settings[ $key ] ) ) {
					WP_CLI::error( $key . ' is not a valid setting.' );
				} else {
					if ( \ZeroSpam\Core\Utilities::update_setting( $key, $value ) ) {
						WP_CLI::success( '\'' . $key . '\' has been successfully updated to \'' . $value . '\'.' );
					} else {
						WP_CLI::error( 'There was a problem updating ' . $key . ' See the zerospam.log for more details.' );
					}
				}
			}
		} else {
			WP_CLI::error( __( 'Opps! You didn\'t specify a setting to set (ex. wp zerospam set --share_data=enabled).', 'zero-spam' ) );
		}
	}
}

WP_CLI::add_command( 'zerospam', 'ZeroSpamCLI' );
