<?php
/**
 * Gravity Forms plugin integration module
 *
 * Malicious user detection techniques available:
 *
 * 1. Zero Spam honeypot field
 * 2. Checks blocked email domains
 * 3. Uses the David Walsh technique (legacy forms only)
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
			add_filter( 'gform_pre_render', array( $this, 'add_honeypot' ) );

      // Processes the form.
			add_action( 'gform_field_validation', array( $this, 'honeypot_validation' ), 10, 4 );
      /*
			// Load scripts.
			add_action( 'wp_print_scripts', array( $this, 'add_scripts' ), 999 );
      */
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
   * Verifys the submitted honeypot field is valid.
   *
   * @see https://docs.gravityforms.com/gform_field_validation/
   *
   * @param array $result The validation result to be filtered.
   * @param string|array $value The field value to be validated. Multi-input fields like Address will pass an array of values.
   * @param FormObject $form Current form object.
   * @param FieldObject $field Current field object.
   */
  public function honeypot_validation( $result, $value, $form, $field ) {
    if ( ! empty( $field['inputName'] ) && "zerospam_honeypot" === $field['inputName'] && $value ) {
      $result['is_valid'] = false;
      $result['message'] = 'Please enter a value less than 10';
    }

    return $result;
  }

	/**
	 * Adds Zero Spam's honeypot field.
   *
   * @see https://docs.gravityforms.com/gform_pre_render/
   *
   * @param array $form The current form object to be filtered.
	 */
	public function add_honeypot( $form ) {
    $honeypot_field_id = \GFFormsModel::get_next_field_id( $form['fields'] );
    $honeypot_key      = \ZeroSpam\Core\Utilities::get_honeypot();

    $properties = array(
      'id'                => $honeypot_field_id,
      'type'              => 'hidden',
      'inputName'         => 'zerospam_honeypot',
    );

    // Check if the honeypot field has already been added.
    $has_honeypot = false;
    foreach ( $form['fields'] as $key => $field ) {
      if ( "zerospam_honeypot" === $field['inputName'] ) {
        $has_honeypot = true;
        break;
      }
    }

    if ( ! $has_honeypot ) {
      $form['fields'][] = \GF_Fields::create( $properties );

      \GFAPI::update_form( $form );
    }

    return $form;
	}

	/**
	 * Processes a donation submission.
	 *
	 * @param array $form The form currently being processed.
	 */
	public function process_form( $form ) {
		// Get post values.
		// @codingStandardsIgnoreLine
		/*$post_data = give_clean( $_POST );

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
		}*/
	}

	/**
	 * Add to the types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['gravityforms'] = __( 'Gravity Forms', 'zero-spam' );

		return $types;
	}

	/**
	 * Admin section
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['gravityforms'] = array(
			'title' => __( 'Gravity Forms Integration', 'zero-spam' ),
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
			'section'     => 'gravityforms',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Monitor Gravity Form submissions for malicious or automated spambots.', 'zero-spam' ),
			),
			'value'       => ! empty( $options['verify_gravityforms'] ) ? $options['verify_gravityforms'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'We\'re sorry, but we\'re unable to process the transaction. Your IP has been flagged as possible spam.', 'zero-spam' );

		$settings['gravityforms_spam_message'] = array(
			'title'       => __( 'Spam/Malicious Message', 'zero-spam' ),
			'desc'        => __( 'When Gravity Forms protection is enabled, the message displayed to the user when a submission has been detected as spam/malicious.', 'zero-spam' ),
			'section'     => 'gravityforms',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['gravityforms_spam_message'] ) ? $options['gravityforms_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_gravityforms'] = array(
			'title'       => __( 'Log Blocked Gravity Form Submissions', 'zero-spam' ),
			'section'     => 'gravityforms',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'Enables logging blocked Gravity Form submissions. <strong>Recommended for enhanced protection.</strong>', 'zero-spam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => __( 'Enabled', 'zero-spam' ),
			),
			'value'       => ! empty( $options['log_blocked_gravityforms'] ) ? $options['log_blocked_gravityforms'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
