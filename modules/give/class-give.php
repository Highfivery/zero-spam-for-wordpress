<?php
/**
 * Give class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\Give;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Give
 */
class Give {
	/**
	 * Add-on constructor
	 */
	public function __construct() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_givewp' ) && \ZeroSpam\Core\Access::process() ) {
			// Adds Zero Spam's honeypot field.
			add_action( 'give_donation_form_bottom', array( $this, 'add_honeypot' ), 10 );

			// Processes the form.
			add_action( 'give_checkout_error_checks', array( $this, 'process_form' ), 10, 1 );

			// Load scripts.
			// @todo - integrate the david walsh technique.
		}
	}

	/**
	 * Adds Zero Spam's honeypot field.
	 */
	public function add_honeypot() {
		// @codingStandardsIgnoreLine
		echo \ZeroSpam\Core\Utilities::honeypot_field();
	}

	/**
	 * Processes a donation submission.
	 *
	 * @param array $valid_data List of Valid Data.
	 */
	public function process_form( $valid_data ) {
		// Get post values.
		// @codingStandardsIgnoreLine
		$post_data = give_clean( $_POST );

		// Check Zero Spam's honeypot field.
		$honeypot_field_name = \ZeroSpam\Core\Utilities::get_honeypot();

		// Get the error message.
		$error_message = \ZeroSpam\Core\Utilities::detection_message( 'login_spam_message' );

		// Create the details array for logging & sharing data.
		$details = array(
			'data' => $post_data,
			'type' => 'givewp',
		);

		// Begin validation checks.
		$validation_errors = array();

		if ( isset( $post_data[ $honeypot_field_name ] ) && ! empty( $post_data[ $honeypot_field_name ] ) ) {
			// Failed the honeypot check.
			$details['failed'] = 'honeypot';

			$validation_errors[] = 'honeypot';
		}

		if ( ! empty( $validation_errors ) ) {
			// Failed validations, log & send details if enabled.
			foreach ( $validation_errors as $key => $fail ) {
				$details['failed'] = $fail;

				// Log the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_givewp' ) ) {
					\ZeroSpam\Includes\DB::log( 'givewp', $details );
				}

				// Share the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
					do_action( 'zerospam_share_detection', $details );
				}
			}

			give_set_error( 'zerospam_honeypot', $error_message );
		}
	}

	/**
	 * Add to the types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['givewp'] = __( 'GiveWP', 'zerospam' );

		return $types;
	}

	/**
	 * Admin section
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['givewp'] = array(
			'title' => __( 'GiveWP Integration', 'zerospam' ),
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'wpzerospam' );

		$settings['verify_givewp'] = array(
			'title'       => __( 'Protect GiveWP Submissions', 'zerospam' ),
			'section'     => 'givewp',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Monitor GiveWP submissions for malicious or automated spambots.', 'zerospam' ),
			),
			'value'       => ! empty( $options['verify_givewp'] ) ? $options['verify_givewp'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'Your IP has been flagged as spam/malicious.', 'zerospam' );

		$settings['givewp_spam_message'] = array(
			'title'       => __( 'Spam/Malicious Message', 'zerospam' ),
			'desc'        => __( 'When GiveWP protection is enabled, the message displayed to the user when a submission has been detected as spam/malicious.', 'zerospam' ),
			'section'     => 'givewp',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['givewp_spam_message'] ) ? $options['givewp_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_givewp'] = array(
			'title'       => __( 'Log Blocked GiveWP Submissions', 'zerospam' ),
			'section'     => 'givewp',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'Enables logging blocked GiveWP submissions. <strong>Recommended for enhanced protection.</strong>', 'zerospam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'value'       => ! empty( $options['log_blocked_givewp'] ) ? $options['log_blocked_givewp'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
