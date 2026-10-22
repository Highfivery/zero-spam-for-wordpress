<?php
/**
 * Adds integration for Elementor Pro forms
 *
 * Malicious user detection techniques available:
 *
 * 1. David Walsh technique
 * 2. Email domain validation
 * 3. Disallowed words validation
 *
 * Only loaded when Elementor Pro is active (see includes/class-plugin.php).
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\Elementor;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Elementor
 */
class Elementor {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Fires after WordPress has finished loading but before any headers are sent.
	 */
	public function init() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ), 10, 1 );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		if (
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'elementor_enabled' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			// Per-field validation, fired by Form_Record::validate() for each field type.
			add_action( 'elementor_pro/forms/validation/email', array( $this, 'validate_email' ), 10, 3 );
			add_action( 'elementor_pro/forms/validation/text', array( $this, 'validate_text' ), 10, 3 );
			add_action( 'elementor_pro/forms/validation/textarea', array( $this, 'validate_text' ), 10, 3 );

			// David Walsh validation, fired once per submission after the field validation.
			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'davidwalsh' ) ) {
				add_action( 'elementor_pro/forms/validation', array( $this, 'validate_davidwalsh' ), 10, 2 );
				add_action( 'elementor/frontend/after_enqueue_scripts', array( $this, 'add_scripts' ), 10 );
			}
		}
	}

	/**
	 * Admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 * @return array
	 */
	public function sections( $sections ) {
		$sections['elementor'] = array(
			'title'    => __( 'Elementor', 'zero-spam' ),
			'icon'     => 'modules/elementor/icon-elementor.svg',
			'supports' => array( 'davidwalsh', 'email', 'words' ),
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 * @return array
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-elementor' );

		$settings['elementor_enabled'] = array(
			'title'       => __( 'Protect Form Submissions', 'zero-spam' ),
			'desc'        => __( 'Stop spam from Elementor Pro forms.', 'zero-spam' ),
			'section'     => 'elementor',
			'module'      => 'elementor',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['elementor_enabled'] ) ? $options['elementor_enabled'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'We were unable to process your submission due to possible malicious activity.', 'zero-spam' );

		$settings['elementor_flagged_msg'] = array(
			'title'       => __( 'Flagged Message', 'zero-spam' ),
			'desc'        => __( 'The message shown when Elementor forms detect spam.', 'zero-spam' ),
			'section'     => 'elementor',
			'module'      => 'elementor',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['elementor_flagged_msg'] ) ? $options['elementor_flagged_msg'] : $message,
			'recommended' => $message,
		);

		// The key must stay "{module}_log_flagged_attempts": Plugin::flagged_attempt() reads it.
		$settings['elementor_log_flagged_attempts'] = array(
			'title'       => __( 'Log Flagged Attempts', 'zero-spam' ),
			'section'     => 'elementor',
			'module'      => 'elementor',
			'type'        => 'checkbox',
			'desc'        => __( 'Keep a record of blocked Elementor form submissions.', 'zero-spam' ),
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['elementor_log_flagged_attempts'] ) ? $options['elementor_log_flagged_attempts'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}

	/**
	 * Add to the detection types array.
	 *
	 * @param array $types Array of available detection types.
	 * @return array Modified types array.
	 */
	public function types( $types ) {
		$types['elementor'] = array(
			'label' => __( 'Elementor', 'zero-spam' ),
			'color' => '#92003B',
		);

		return $types;
	}

	/**
	 * Rejects an email field whose domain is on the blocked email domains list.
	 *
	 * Fired by `elementor_pro/forms/validation/email`.
	 *
	 * @param array                                            $field        Form field (id, type, title, value, raw_value, required).
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record       Form record.
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler Ajax handler.
	 */
	public function validate_email( $field, $record, $ajax_handler ) {
		$value = self::field_value( $field );
		if ( '' === $value || ! \ZeroSpam\Core\Utilities::is_email_domain_blocked( $value ) ) {
			return;
		}

		$this->flag( 'blocked_email_domain', self::field_details( $field, $record ) );
		$ajax_handler->add_error( self::field_id( $field ), self::message() );
	}

	/**
	 * Rejects a text or textarea field that contains a disallowed word.
	 *
	 * Fired by `elementor_pro/forms/validation/text` and `…/textarea`.
	 *
	 * @param array                                            $field        Form field (id, type, title, value, raw_value, required).
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record       Form record.
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler Ajax handler.
	 */
	public function validate_text( $field, $record, $ajax_handler ) {
		$value = self::field_value( $field );
		if ( '' === $value || ! \ZeroSpam\Core\Utilities::is_disallowed( $value ) ) {
			return;
		}

		$this->flag( 'disallowed_list', self::field_details( $field, $record ) );
		$ajax_handler->add_error( self::field_id( $field ), self::message() );
	}

	/**
	 * Load the David Walsh script on pages with Elementor content.
	 *
	 * Fired by `elementor/frontend/after_enqueue_scripts`.
	 */
	public function add_scripts() {
		do_action( 'zerospam_elementor_scripts' );
	}

	/**
	 * Validates the David Walsh token on form submission.
	 *
	 * Fired by `elementor_pro/forms/validation`. Elementor Pro only stops the
	 * submission when the handler has errors, so the form-level message alone
	 * is not enough: an error is added too.
	 *
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record       Form record.
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler Ajax handler.
	 */
	public function validate_davidwalsh( $record, $ajax_handler ) {
		// @codingStandardsIgnoreLine
		$post = \ZeroSpam\Core\Utilities::sanitize_array( $_POST );

		$errors = apply_filters( 'zerospam_preprocess_elementor_submission', array(), $post, 'elementor_flagged_msg' );
		if ( empty( $errors ) ) {
			return;
		}

		$message = self::message();
		$ajax_handler->add_error_message( $message );
		$ajax_handler->add_error( '', $message );

		$this->flag(
			'david_walsh',
			array(
				'form' => self::form_name( $record ),
				'post' => $post,
			)
		);
	}

	/**
	 * Logs and shares a detection through `zero_spam_flagged_attempt`, which
	 * honours the `elementor_log_flagged_attempts` and `share_data` settings.
	 *
	 * @param string $failed  Failed check (a key of the zerospam_failed_types filter).
	 * @param array  $details Submission details.
	 */
	private function flag( string $failed, array $details ): void {
		do_action( 'zero_spam_flagged_attempt', 'elementor', $failed, $details );
	}

	/**
	 * The flagged message, escaped: Elementor Pro inserts error messages into
	 * the page as HTML.
	 */
	private static function message(): string {
		return esc_html( \ZeroSpam\Core\Utilities::detection_message( 'elementor_flagged_msg' ) );
	}

	/**
	 * The ID of a field, or '' when it has none.
	 *
	 * @param mixed $field Form field (an array from Elementor Pro).
	 */
	private static function field_id( mixed $field ): string {
		return is_array( $field ) && isset( $field['id'] ) && is_scalar( $field['id'] ) ? (string) $field['id'] : '';
	}

	/**
	 * The submitted value of a field, trimmed, or '' when it is not a string.
	 *
	 * @param mixed $field Form field (an array from Elementor Pro).
	 */
	private static function field_value( mixed $field ): string {
		return is_array( $field ) && isset( $field['value'] ) && is_string( $field['value'] ) ? trim( $field['value'] ) : '';
	}

	/**
	 * Details of a flagged field for the log.
	 *
	 * @param mixed $field  Form field (an array from Elementor Pro).
	 * @param mixed $record Form record (an ElementorPro\Modules\Forms\Classes\Form_Record).
	 * @return array{form: string, field: string, value: string}
	 */
	private static function field_details( mixed $field, mixed $record ): array {
		return array(
			'form'  => self::form_name( $record ),
			'field' => self::field_id( $field ),
			'value' => self::field_value( $field ),
		);
	}

	/**
	 * The form name from the form record's settings, or '' when unavailable.
	 *
	 * @param mixed $record Form record (an ElementorPro\Modules\Forms\Classes\Form_Record).
	 */
	private static function form_name( mixed $record ): string {
		$name = is_object( $record ) && method_exists( $record, 'get_form_settings' ) ? $record->get_form_settings( 'form_name' ) : '';

		return is_string( $name ) ? $name : '';
	}
}
