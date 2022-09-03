<?php
/**
 * Contact Form 7 integration module
 *
 * Malicious user detection techniques available:
 *
 * 1. Zero Spam honeypot field
 * 2. Uses the David Walsh technique
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
		add_filter( 'zerospam_settings', array( $this, 'settings' ), 10, 1 );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		if (
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_contactform7' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			// Load scripts.
			add_action( 'wpcf7_enqueue_scripts', array( $this, 'add_scripts' ), 10 );

			// Adds Zero Spam's honeypot field.
			add_filter( 'wpcf7_form_elements', array( $this, 'add_honeypot' ), 10, 1 );

			// Processes the form.
			add_filter( 'wpcf7_validate', array( $this, 'process_form' ), 10, 2 );
		}
	}

	/**
	 * Load the scripts
	 */
	public function add_scripts() {
		if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_contactform7' ) ) {
			wp_enqueue_script( 'zerospam-davidwalsh' );
			add_action( 'wp_footer', function() {
				echo '<script type="text/javascript">jQuery(".wpcf7-form").ZeroSpamDavidWalsh();</script>';
			}, 999 );
		}
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
			'title'    => __( 'Contact Form 7', 'zero-spam' ),
			'icon'     => 'modules/contactform7/icon-cf7.png',
			'supports' => array( 'honeypot', 'davidwalsh' ),
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-contactform7' );

		$settings['verify_contactform7'] = array(
			'title'       => __( 'Protect CF7 Submissions', 'zero-spam' ),
			'desc'        => __( 'Protects & monitors Contact Form 7 submissions.', 'zero-spam' ),
			'module'      => 'contactform7',
			'section'     => 'contactform7',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['verify_contactform7'] ) ? $options['verify_contactform7'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'Your IP has been flagged as spam/malicious.', 'zero-spam' );

		$settings['contactform7_spam_message'] = array(
			'title'       => __( 'Flagged Message', 'zero-spam' ),
			'desc'        => __( 'Message displayed when a submission has been flagged.', 'zero-spam' ),
			'module'      => 'contactform7',
			'section'     => 'contactform7',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['contactform7_spam_message'] ) ? $options['contactform7_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_contactform7'] = array(
			'title'       => __( 'Log Blocked CF7 Submissions', 'zero-spam' ),
			'module'      => 'contactform7',
			'section'     => 'contactform7',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'When enabled, stores blocked Contact Form 7 submissions in the database.', 'zero-spam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['log_blocked_contactform7'] ) ? $options['log_blocked_contactform7'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
