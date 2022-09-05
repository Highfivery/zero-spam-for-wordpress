<?php
/**
 * GiveWP plugin integration module
 *
 * Malicious user detection techniques available:
 *
 * 1. Zero Spam honeypot field
 * 2. Checks blocked email domains
 * 3. Uses the David Walsh technique (legacy forms only)
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
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_givewp' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			// Adds Zero Spam's honeypot field.
			add_action( 'give_donation_form_bottom', array( $this, 'add_honeypot' ), 10 );

			// Processes the form.
			add_action( 'give_checkout_error_checks', array( $this, 'process_form' ), 10, 1 );

			// Load scripts.
			add_action( 'wp_print_scripts', array( $this, 'add_scripts' ), 999 );
		}
	}

	/**
	 * Load the scripts
	 *
	 * @see https://givewp.com/documentation/developers/conditionally-load-give-styles-and-scripts/
	 */
	public function add_scripts() {
		global $post;

		// Only add scripts to the appropriate pages.
		if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_givewp' ) ) {
			if (
				// Register and enqueue scripts on single GiveWP Form pages
				is_singular('give_forms') ||
				// Now check for whether the shortcode 'give_form' exists
				( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'give_form' ) )
			) {
				wp_enqueue_script( 'zerospam-davidwalsh' );
				wp_add_inline_script( 'zerospam-davidwalsh', 'jQuery(".give-form").ZeroSpamDavidWalsh();' );
			}
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
		$error_message = \ZeroSpam\Core\Utilities::detection_message( 'givewp_spam_message' );

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

		// Check email.
		if ( ! empty( $post_data['give_email'] ) && ! \ZeroSpam\Core\Utilities::is_email( $post_data['give_email'] ) ) {
			$validation_errors[] = 'invalid_email';
		}

		// Check blocked email domains.
		if (
			! empty( $post_data['give_email'] ) &&
			\ZeroSpam\Core\Utilities::is_email_domain_blocked( $post_data['give_email'] )
		) {
			// Email domain has been blocked.
			$validation_errors[] = 'blocked_email_domain';
		}

		// Fire hook for additional validation (ex. David Walsh script). Only works for legacy forms.
		$form_post_meta = get_post_meta( $post_data['give-form-id'] );
		if ( in_array( 'legacy', $form_post_meta['_give_form_template'] ) ) {
			$filtered_errors = apply_filters( 'zerospam_process_givewp_submission', array(), $post_data, 'givewp_spam_message' );

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
		$types['givewp'] = __( 'GiveWP', 'zero-spam' );

		return $types;
	}

	/**
	 * Admin section
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['givewp'] = array(
			'title'    => __( 'GiveWP', 'zero-spam' ),
			'icon'     => 'modules/give/icon-givewp.png',
			'supports' => array( 'honeypot', 'email', 'davidwalsh' ),
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-givewp' );

		$settings['verify_givewp'] = array(
			'title'       => sprintf(
				wp_kses(
					/* translators: %s: url */
					__( 'Protect <a href="%s" target="_blank" rel="noreferrer noopener">GiveWP</a> Submissions', 'zero-spam' ),
					array(
						'a'    => array(
							'href'   => array(),
							'class'  => array(),
							'target' => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://givewp.com/ref/1118/' )
			),
			'desc'        => __( 'Protects & monitors donation forms.', 'zero-spam' ),
			'section'     => 'givewp',
			'module'      => 'givewp',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['verify_givewp'] ) ? $options['verify_givewp'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'We\'re sorry, but we\'re unable to process the transaction. Your IP has been flagged as possible spam.', 'zero-spam' );

		$settings['givewp_spam_message'] = array(
			'title'       => __( 'Flagged Message', 'zero-spam' ),
			'desc'        => __( 'Message displayed when a submission has been flagged.', 'zero-spam' ),
			'section'     => 'givewp',
			'module'      => 'givewp',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['givewp_spam_message'] ) ? $options['givewp_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_givewp'] = array(
			'title'       => __( 'Log Blocked GiveWP Submissions', 'zero-spam' ),
			'section'     => 'givewp',
			'module'      => 'givewp',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'When enabled, stores blocked donation submissions in the database.', 'zero-spam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['log_blocked_givewp'] ) ? $options['log_blocked_givewp'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
