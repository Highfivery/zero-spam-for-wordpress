<?php
/**
 * Ninja Forms class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\NinjaForms;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Ninja Forms
 */
class NinjaForms {
	/**
	 * Add-on constructor.
	 */
	public function __construct() {
	//	add_filter( 'ninja_forms_register_fields', array($this, 'register_fields'));

		// Preprocess Ninja Form submissions.
		//add_filter( 'ninja_forms_submit_data', array( $this, 'preprocess_form' ), 10, 1 );
	}


	public function add_honeypot( $form_id, $is_preview ) {
		return \ZeroSpam\Core\Utilities::honeypot_field();
	}

	public function preprocess_form( $form_data ) {
		print_r($form_data);
		die();

		return $form_data;
	}
}
