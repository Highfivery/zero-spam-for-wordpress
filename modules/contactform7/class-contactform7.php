<?php
/**
 * Contact Form 7 class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\ContactForm7;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Contact Form 7
 */
class ContactForm7 {
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
		add_filter( 'zerospam_settings', array( $this, 'settings' ), 10, 2 );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		if (
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_contactform7' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			// Load scripts.
			add_action( 'wpcf7_enqueue_scripts', array( $this, 'scripts' ), 10 );

			// Adds Zero Spam's honeypot field.
			add_filter( 'wpcf7_form_elements', array( $this, 'add_honeypot' ), 10, 1 );

			// Processes the form.
			add_filter( 'wpcf7_validate', array( $this, 'process_form' ), 10, 2 );
		}
	}

	/**
	 * Load the scripts
	 */
	public function scripts() {
		do_action( 'zerospam_wpcf7_scripts' );
	}

	/**
	 * Add a 'honeypot' field to the form
	 *
	 * @param string $this_replace_all_form_tags Form tags.
	 */
	public function add_honeypot( $this_replace_all_form_tags ) {
		$this_replace_all_form_tags .= \ZeroSpam\Core\Utilities::honeypot_field();

		return $this_replace_all_form_tags;
	}

	/**
	 * Preprocess submission
	 *
	 * @param WPCF7_Validation $result Validation.
	 * @param WPCF7_FormTag    $tag Form tag.
	 */
	public function process_form( $result, $tag ) {
		// @codingStandardsIgnoreLine
		$post = \ZeroSpam\Core\Utilities::sanitize_array( $_POST );

		// Check Zero Spam's honeypot field.
		$honeypot_field_name = \ZeroSpam\Core\Utilities::get_honeypot();

		// Get the error message.
		$error_message = \ZeroSpam\Core\Utilities::detection_message( 'contactform7_spam_message' );

		// Create the details array for logging & sharing data.
		$details = $post;

		$details['type'] = 'contactform7';

		// Begin validation checks.
		$validation_errors = array();

		// @codingStandardsIgnoreLine
		if ( isset( $post[ $honeypot_field_name ] ) && ! empty( $post[ $honeypot_field_name ] ) ) {
			// Failed the honeypot check.
			$validation_errors[] = 'honeypot';
		}

		// Fire hook for additional validation (ex. David Walsh script).
		$errors = apply_filters( 'zerospam_preprocess_cf7_submission', array(), $post, 'contactform7_spam_message' );

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
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_contactform7' ) ) {
					\ZeroSpam\Includes\DB::log( 'contactform7', $details );
				}

				// Share the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
					do_action( 'zerospam_share_detection', $details );
				}
			}

			$result->invalidate( $tag[0], $error_message );
		}

		return $result;
	}

	/**
	 * Add to the detection types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['contactform7'] = __( 'Contact Form 7', 'zero-spam' );

		return $types;
	}

	/**
	 * Admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 */
	public function sections( $sections ) {
		$sections['contactform7'] = array(
			'title' => __( 'Contact Form 7 Integration', 'zero-spam' ),
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 * @param array $options  Array of saved database options.
	 */
	public function settings( $settings, $options ) {
		$settings['verify_contactform7'] = array(
			'title'       => __( 'Protect CF7 Submissions', 'zero-spam' ),
			'section'     => 'contactform7',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Monitor CF7 submissions for malicious or automated spambots.', 'zero-spam' ),
			),
			'value'       => ! empty( $options['verify_contactform7'] ) ? $options['verify_contactform7'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'Your IP has been flagged as spam/malicious.', 'zero-spam' );

		$settings['contactform7_spam_message'] = array(
			'title'       => __( 'Spam/Malicious Message', 'zero-spam' ),
			'desc'        => __( 'When CF7 protection is enabled, the message displayed to the user when a submission has been detected as spam/malicious.', 'zero-spam' ),
			'section'     => 'contactform7',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['contactform7_spam_message'] ) ? $options['contactform7_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_contactform7'] = array(
			'title'       => __( 'Log Blocked CF7 Submissions', 'zero-spam' ),
			'section'     => 'contactform7',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'Enables logging blocked CF7 submissions. <strong>Recommended for enhanced protection.</strong>', 'zero-spam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => __( 'Enabled', 'zero-spam' ),
			),
			'value'       => ! empty( $options['log_blocked_contactform7'] ) ? $options['log_blocked_contactform7'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
