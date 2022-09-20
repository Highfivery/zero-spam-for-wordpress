<?php
/**
 * Gravity Forms plugin integration module
 *
 * Malicious user detection techniques available:
 *
 * 1. Zero Spam honeypot field
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\GravityForms;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Gravity Forms
 */
class GravityForms {
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
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_gravityforms' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			// Adds Zero Spam's honeypot field.
			add_filter( 'gform_form_tag', array( $this, 'add_honeypot' ), 60, 2 );

			// Processes the form.
			add_action( 'gform_abort_submission_with_confirmation', array( $this, 'process_form' ), 10, 2 );
			add_filter( 'gform_confirmation', array( $this, 'confirmation_message' ), 10, 4 );
			/*
			// Load scripts.
			add_action( 'wp_print_scripts', array( $this, 'add_scripts' ), 999 );
			*/
		}
	}

	/**
	 * Add to the types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['gravityforms'] = array(
			'label' => __( 'Gravity Forms', 'zero-spam' ),
			'color' => '#f15a29',
		);

		return $types;
	}

	/**
	 * Admin section
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['gravityforms'] = array(
			'title'    => __( 'Gravity Forms', 'zero-spam' ),
			'icon'     => 'modules/gravityforms/icon-gravity-forms.svg',
			'supports' => array( 'honeypot' ),
		);

		return $sections;
	}

	/**
	 * Load the scripts
	 *
	 * @see https://givewp.com/documentation/developers/conditionally-load-give-styles-and-scripts/
	 */
	public function add_scripts() {
		//wp_enqueue_script( 'zerospam-davidwalsh' );
		//wp_add_inline_script( 'zerospam-davidwalsh', 'jQuery(".give-form").ZeroSpamDavidWalsh();' );
	}


	/**
	 * Adds Zero Spam's honeypot field.
	 *
	 * @see https://docs.gravityforms.com/gform_form_tag/#h-add-hidden-input
	 *
	 * @param string $form_tag The string containing the <form> tag
	 * @param array $form The current form object to be filtered.
	 */
	public function add_honeypot( $form_tag,  $form ) {
		$form_tag .= \ZeroSpam\Core\Utilities::honeypot_field();

		return $form_tag;
	}

	public function confirmation_message( $confirmation, $form, $entry, $ajax ) {
		if ( empty( $entry ) || rgar( $entry, 'status' ) === 'spam' ) {
			$error_message = \ZeroSpam\Core\Utilities::detection_message( 'gravityforms_spam_message' );

			return $error_message;
		}

		return $confirmation;
	}

	/**
	 * Processes a donation submission.
	 *
	 * @param boolean $do_abort Indicates if the submission should abort without saving the entry. Default is false. Will be true if the anti-spam honeypot is enabled and the honeypot identified the submission as spam.
	 * @param FormObject $form The form currently being processed.
	 */
	public function process_form( $do_abort, $form ) {
		// // If submission is already marked to be aborted early, don't change it.
		if ( $do_abort ) {
			return true;
		}

		// @codingStandardsIgnoreLine
		$post = \ZeroSpam\Core\Utilities::sanitize_array( $_POST );

		// Check Zero Spam's honeypot field.
		$honeypot_field_name = \ZeroSpam\Core\Utilities::get_honeypot();

		// Create the details array for logging & sharing data.
		$details = $post;

		$details['type'] = 'gravityforms';

		// Begin validation checks.
		$validation_errors = array();

		// @codingStandardsIgnoreLine
		if ( isset( $post[ $honeypot_field_name ] ) && ! empty( $post[ $honeypot_field_name ] ) ) {
			// Failed the honeypot check.
			$validation_errors[] = 'honeypot';
		}

		if ( ! empty( $validation_errors ) ) {
			do_action( 'zero_spam_detection', $details, $validation_errors );
			$do_abort = true;

			// Failed validations, log & send details if enabled.
			foreach ( $validation_errors as $key => $fail ) {
				$details['failed'] = $fail;

				// Log the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_gravityforms' ) ) {
					\ZeroSpam\Includes\DB::log( 'gravityforms', $details );
				}

				// Share the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
					do_action( 'zerospam_share_detection', $details );
				}
			}
		}

		return $do_abort;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-gravityforms' );

		$settings['verify_gravityforms'] = array(
			'title'       => sprintf(
				wp_kses(
					/* translators: %s: url */
					__( 'Protect <a href="%s" target="_blank" rel="noreferrer noopener">Gravity Form</a> Submissions', 'zero-spam' ),
					array(
						'a'    => array(
							'href'   => array(),
							'class'  => array(),
							'target' => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://www.gravityforms.com/' )
			),
			'desc'        => __( 'Protects & monitors Gravity Form submissions (requires >=v2.7 to enable).', 'zero-spam' ),
			'section'     => 'gravityforms',
			'module'      => 'gravityforms',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['verify_gravityforms'] ) ? $options['verify_gravityforms'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'We were unable to process your submission due to possible malicious activity.', 'zero-spam' );
		$settings['gravityforms_spam_message'] = array(
			'title'       => __( 'Flagged Message', 'zero-spam' ),
			'desc'        => __( 'Message displayed when a submission has been flagged.', 'zero-spam' ),
			'section'     => 'gravityforms',
			'module'      => 'gravityforms',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['gravityforms_spam_message'] ) ? $options['gravityforms_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_gravityforms'] = array(
			'title'       => __( 'Log Blocked Gravity Form Submissions', 'zero-spam' ),
			'section'     => 'gravityforms',
			'module'      => 'gravityforms',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'When enabled, stores blocked form submissions in the database.', 'zero-spam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => false
			),
			'value'       => ! empty( $options['log_blocked_gravityforms'] ) ? $options['log_blocked_gravityforms'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
