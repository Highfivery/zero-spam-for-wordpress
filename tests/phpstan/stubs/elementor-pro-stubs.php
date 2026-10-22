<?php
/**
 * Hand-written stubs for the parts of the Elementor Pro forms API that
 * modules/elementor/class-elementor.php uses, so PHPStan can check the calls
 * without Elementor Pro installed. Signatures follow Elementor Pro 3.x
 * (modules/forms/classes/form-record.php and ajax-handler.php).
 *
 * @package ZeroSpam
 */

namespace ElementorPro\Modules\Forms\Classes;

/**
 * A submitted form: its fields and the form's settings.
 */
class Form_Record {
	/**
	 * Returns one of the form's settings.
	 *
	 * @param string $setting Setting key (e.g. form_name).
	 * @return mixed
	 */
	public function get_form_settings( $setting ) {}
}

/**
 * Handles the AJAX form submission and collects validation errors.
 */
class Ajax_Handler {
	/**
	 * Adds a validation error; any error stops the submission.
	 *
	 * @param string $field   Field ID, or '' for the form.
	 * @param string $message Message shown next to the field.
	 * @return $this
	 */
	public function add_error( $field, $message = '' ) {}

	/**
	 * Adds a message shown above the form when the submission fails.
	 *
	 * @param string $message Message.
	 * @return $this
	 */
	public function add_error_message( $message ) {}
}
