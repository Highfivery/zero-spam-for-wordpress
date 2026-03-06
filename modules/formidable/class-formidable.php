<?php
/**
 * Formidable integration module
 *
 * Malicious user detection techniques available:
 *
 * 1. Zero Spam honeypot field
 * 2. David Walsh technique
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\Formidable;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Formidable
 */
class Formidable {
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
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_formidable' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			add_action( 'frm_entry_form', array( $this, 'honeypot' ), 10, 1 );
			add_filter( 'frm_validate_entry', array( $this, 'preprocess_submission' ), 10, 2 );

			// Load David Walsh scripts.
			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'davidwalsh' ) ) {
				add_action( 'frm_enqueue_form_scripts', array( $this, 'add_scripts' ), 10 );
			}
		}
	}

	/**
	 * Add to the types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['formidable'] = array(
			'label' => __( 'Formidable', 'zero-spam' ),
			'color' => '#f04d21',
		);

		return $types;
	}

	/**
	 * Formidable sections
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['formidable'] = array(
			'title'    => __( 'Formidable', 'zero-spam' ),
			'icon'     => 'modules/formidable/icon-formidable.png',
			'supports' => array( 'honeypot', 'davidwalsh', 'email', 'words' ),
		);

		return $sections;
	}

	/**
	 * Formidable settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-formidable' );

		$settings['verify_formidable'] = array(
			'title'       => __( 'Protect Formidable Submissions', 'zero-spam' ),
			'desc'        => __( 'Stop spam from Formidable Forms.', 'zero-spam' ),
			'section'     => 'formidable',
			'module'      => 'formidable',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['verify_formidable'] ) ? $options['verify_formidable'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'Your IP has been flagged as spam/malicious.', 'zero-spam' );

		$settings['formidable_spam_message'] = array(
			'title'       => __( 'Flagged Message', 'zero-spam' ),
			'desc'        => __( 'The message shown when Formidable Forms detects spam.', 'zero-spam' ),
			'section'     => 'formidable',
			'module'      => 'formidable',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['formidable_spam_message'] ) ? $options['formidable_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['verify_formidable_blocked_email_domains'] = array(
			'title'       => __( 'Check Blocked Email Domains', 'zero-spam' ),
			'desc'        => __( 'Block Formidable submissions containing email addresses from blocked domains.', 'zero-spam' ),
			'section'     => 'formidable',
			'module'      => 'formidable',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['verify_formidable_blocked_email_domains'] ) ? $options['verify_formidable_blocked_email_domains'] : false,
			'recommended' => 'enabled',
		);

		$settings['verify_formidable_disallowed_words'] = array(
			'title'       => __( 'Check Disallowed Words', 'zero-spam' ),
			'desc'        => __( 'Block Formidable submissions containing words from the WordPress disallowed words list.', 'zero-spam' ),
			'section'     => 'formidable',
			'module'      => 'formidable',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['verify_formidable_disallowed_words'] ) ? $options['verify_formidable_disallowed_words'] : false,
			'recommended' => 'enabled',
		);

		$settings['log_blocked_formidable'] = array(
			'title'       => __( 'Log Blocked Formidable Submissions', 'zero-spam' ),
			'section'     => 'formidable',
			'module'      => 'formidable',
			'type'        => 'checkbox',
			'desc'        => __( 'Keep a record of blocked Formidable Forms submissions.', 'zero-spam' ),
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['log_blocked_formidable'] ) ? $options['log_blocked_formidable'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}

	/**
	 * Add a 'honeypot' field to the form
	 *
	 * @param array $form_data Form data and settings.
	 */
	public function honeypot( $form_data ) {
		// @codingStandardsIgnoreLine
		echo \ZeroSpam\Core\Utilities::honeypot_field();
	}

	/**
	 * Load the David Walsh scripts for Formidable Forms.
	 *
	 * @see https://formidableforms.com/knowledgebase/frm_enqueue_form_scripts/
	 */
	public function add_scripts() {
		// Trigger the custom action to enqueue the David Walsh script.
		do_action( 'zerospam_formidable_scripts' );
	}

	/**
	 * Preprocess submission
	 *
	 * @param array $errors Array of errors.
	 * @param array $values Array of values.
	 */
	public function preprocess_submission( $errors, $values ) {
		// @codingStandardsIgnoreLine
		$post = \ZeroSpam\Core\Utilities::sanitize_array( $_POST );

		// Get the error message.
		$error_message = \ZeroSpam\Core\Utilities::detection_message( 'formidable_spam_message' );

		// Create the details array for logging & sharing data.
		$details = $values;

		$details['type'] = 'formidable';

		// Begin validation checks.
		$validation_errors = array();

		// Check Zero Spam's honeypot field.
		$honeypot_field_name = \ZeroSpam\Core\Utilities::get_honeypot();
		// @codingStandardsIgnoreLine
		if ( isset( $post[ $honeypot_field_name ] ) && ! empty( $post[ $honeypot_field_name ] ) ) {
			// Failed the honeypot check.
			$details['failed'] = 'honeypot';

			$validation_errors[] = 'honeypot';
		}

		// Check submitted fields for blocked email domains and disallowed words.
		$check_blocked_emails = 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_formidable_blocked_email_domains' );
		$check_disallowed     = 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_formidable_disallowed_words' );

		if ( $check_blocked_emails || $check_disallowed ) {
			// Formidable form fields use item_meta array.
			$fields_to_check = ! empty( $post['item_meta'] ) && is_array( $post['item_meta'] ) ? $post['item_meta'] : $post;

			foreach ( $fields_to_check as $value ) {
				if ( ! is_string( $value ) || empty( trim( $value ) ) ) {
					continue;
				}

				$value = trim( $value );

				// Check for blocked email domains.
				if ( $check_blocked_emails && \ZeroSpam\Core\Utilities::is_email( $value ) && \ZeroSpam\Core\Utilities::is_email_domain_blocked( $value ) ) {
					$validation_errors[] = 'blocked_email_domain';
					break;
				}

				// Check against disallowed words list.
				if ( $check_disallowed && \ZeroSpam\Core\Utilities::is_disallowed( $value ) ) {
					$validation_errors[] = 'disallowed_list';
					break;
				}
			}
		}

		// Fire hook for additional validation (ex. David Walsh).
		if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'davidwalsh' ) ) {
			$filtered_errors = apply_filters( 'zerospam_preprocess_formidable_submission', array(), $post, 'formidable_spam_message' );

			if ( ! empty( $filtered_errors ) ) {
				foreach ( $filtered_errors as $key => $message ) {
					$validation_errors[] = str_replace( 'zerospam_', '', $key );
				}
			}
		}

		if ( ! empty( $validation_errors ) ) {
			// Failed validations, log & send details if enabled.
			foreach ( $validation_errors as $key => $fail ) {
				$details['failed'] = $fail;

				// Log the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_formidable' ) ) {
					\ZeroSpam\Includes\DB::log( 'formidable', $details );
				}

				// Share the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
					do_action( 'zerospam_share_detection', $details );
				}
			}

			$errors['zerospam_honeypot'] = $error_message;
		}

		return $errors;
	}
}
