<?php
/**
 * WPForms integration module
 *
 * Malicious user detection techniques available:
 *
 * 1. Zero Spam honeypot field
 * 2. Uses the David Walsh technique
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\WPForms;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * WPForms
 */
class WPForms {
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
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_wpforms' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			// Adds Zero Spam's honeypot field.
			add_action( 'wpforms_frontend_output', array( $this, 'honeypot' ), 10, 1 );

			// Load scripts.
			add_action( 'wpforms_frontend_output', array( $this, 'add_scripts' ) );

			// Processes the form.
			add_action( 'wpforms_process', array( $this, 'preprocess_submission' ), 10, 3 );
		}
	}

	/**
	 * Adds Zero Spam's honeypot field.
	 */
	public function honeypot() {
		// @codingStandardsIgnoreLine
		echo \ZeroSpam\Core\Utilities::honeypot_field();
	}

	/**
	 * Preprocess submission
	 *
	 * @param array $fields    Sanitized entry field values/properties.
	 * @param array $entry     Original $_POST global.
	 * @param array $form_data Form settings/data.
	 */
	public function preprocess_submission( $fields, $entry, $form_data ) {
		// @codingStandardsIgnoreLine
		$post = \ZeroSpam\Core\Utilities::sanitize_array( $_POST );

		// Get the error message.
		$error_message = \ZeroSpam\Core\Utilities::detection_message( 'wpforms_spam_message' );

		// Create the details array for logging & sharing data.
		$details = $fields;
		$details = array_merge( $details, $entry );
		$details = array_merge( $details, $form_data );

		$details['type'] = 'wpforms';

		// Begin validation checks.
		$validation_errors = array();

		// Check individual fields.
		if ( ! empty( $post['wpforms'] ) && ! empty( $post['wpforms']['fields'] ) ) {
			foreach ( $post['wpforms']['fields'] as $key => $field ) {
				if ( is_array( $field ) ) {
					foreach ( $field as $k => $value ) {
						if ( \ZeroSpam\Core\Utilities::is_email( $value ) && \ZeroSpam\Core\Utilities::is_email_domain_blocked( $value ) ) {
							// Email address found & is blocked.
							$validation_errors[] = 'blocked_email_domain';
						} else {
							// Check against disallowed list.
							if ( \ZeroSpam\Core\Utilities::is_disallowed( $value ) ) {
								$validation_errors[] = 'disallowed_list';
							}
						}
					}
				} else {
					if ( \ZeroSpam\Core\Utilities::is_email( $field ) && \ZeroSpam\Core\Utilities::is_email_domain_blocked( $field ) ) {
						// Email address found & is blocked.
						$validation_errors[] = 'blocked_email_domain';
					} else {
						// Check against disallowed list.
						if ( \ZeroSpam\Core\Utilities::is_disallowed( $field ) ) {
							$validation_errors[] = 'disallowed_list';
						}
					}
				}
			}
		}

		// Check Zero Spam's honeypot field.
		$honeypot_field_name = \ZeroSpam\Core\Utilities::get_honeypot();
		// @codingStandardsIgnoreLine
		if ( isset( $post[ $honeypot_field_name ] ) && ! empty( $post[ $honeypot_field_name ] ) ) {
			// Failed the honeypot check.
			$details['failed'] = 'honeypot';

			$validation_errors[] = 'honeypot';
		}

		// Fire hook for additional validation (ex. David Walsh script).
		$errors = apply_filters( 'zerospam_preprocess_wpforms_submission', array(), $post, 'wpforms_spam_message' );

		if ( ! empty( $errors ) ) {
			foreach ( $errors as $key => $message ) {
				$validation_errors[] = str_replace( 'zerospam_', '', $key );
			}
		}

		if ( ! empty( $validation_errors ) ) {
			// Failed validations, log & send details if enabled.
			foreach ( $validation_errors as $key => $fail ) {
				$details['failed'] = $fail;

				// Log the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_wpforms' ) ) {
					\ZeroSpam\Includes\DB::log( 'wpforms', $details );
				}

				// Share the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
					do_action( 'zerospam_share_detection', $details );
				}
			}

			wpforms()->process->errors[ $form_data['id'] ]['header'] = $error_message;
		}
	}

	/**
	 * Add to the detection types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['wpforms'] = array(
			'label' => __( 'WPForms', 'zero-spam' ),
			'color' => '#e27730',
		);

		return $types;
	}

	/**
	 * Load the scripts
	 */
	public function add_scripts() {
		// Only add scripts to the appropriate pages.
		if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_wpforms' ) ) {
			wp_enqueue_script( 'zerospam-davidwalsh' );
			add_action( 'wp_enqueue_scripts', function() {
				wp_add_inline_script('zerospam-davidwalsh', '(function($) {$(function() { $(".wpforms-form").ZeroSpamDavidWalsh(); })})(jQuery)' );
			} );
		}
	}

	/**
	 * Admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 */
	public function sections( $sections ) {
		$sections['wpforms'] = array(
			'title'    => __( 'WPForms', 'zero-spam' ),
			'icon'     => 'modules/wpforms/icon-wpforms.svg',
			'supports' => array( 'honeypot', 'davidwalsh', 'email', 'words' ),
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-wpforms' );

		$settings['verify_wpforms'] = array(
			'title'       => __( 'Protect WPForms Submissions', 'zero-spam' ),
			'desc'        => __( 'Protects & monitors WPForms submissions.', 'zero-spam' ),
			'section'     => 'wpforms',
			'module'      => 'wpforms',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['verify_wpforms'] ) ? $options['verify_wpforms'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'Your IP has been flagged as spam/malicious.', 'zero-spam' );

		$settings['wpforms_spam_message'] = array(
			'title'       => __( 'Flagged Message', 'zero-spam' ),
			'desc'        => __( 'Message displayed when a submission has been flagged.', 'zero-spam' ),
			'section'     => 'wpforms',
			'module'      => 'wpforms',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['wpforms_spam_message'] ) ? $options['wpforms_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_wpforms'] = array(
			'title'       => __( 'Log Blocked WPForms Submissions', 'zero-spam' ),
			'section'     => 'wpforms',
			'module'      => 'wpforms',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'When enabled, stores blocked WPForms submissions in the database.', 'zero-spam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['log_blocked_wpforms'] ) ? $options['log_blocked_wpforms'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
