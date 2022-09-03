<?php
/**
 * Updates class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Updates class
 */
class Updates {
  /**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'check' ) );
	}

  /**
   * Checks for updates
   */
  public function check() {
    $last_update = get_option( 'zero-spam-last-update' );
    if ( ! $last_update || ZEROSPAM_VERSION !== $last_update ) {

      // Update old settings value to new.
      // @TODO: Delete the wpzerospam option once enough time has passed to allow people to upgrade.
      $old_settings = get_option( 'wpzerospam' );
      if ( $old_settings ) {
        $modules = \ZeroSpam\Core\Settings::get_settings_by_module();
        foreach ( $modules as $module => $settings ) {
          $updated_settings = array();
          foreach ( $settings as $key => $attr ) {
            $updated_settings[ $key ] = ! empty( $attr['value'] ) ? $attr['value'] : false;

            if ( ! empty( $old_settings[ $key ] ) ) {
              $updated_settings[ $key ] = $old_settings[ $key ];
            }
          }

          update_option( "zero-spam-$module", $updated_settings, true );
        }

        update_option( 'zero-spam-last-update', ZEROSPAM_VERSION );
      }

    }
  }
}
