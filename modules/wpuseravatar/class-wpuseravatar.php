<?php
/**
 * ProfilePress (wp-user-avatar) integration
 *
 * Malicious user detection techniques available:
 *
 * 1. Zero Spam honeypot field
 * 2. Checks blocked email domains
 * 3. Uses the David Walsh technique
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\WPUserAvatar;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Module class
 */
class WPUserAvatar {
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
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_wp_user_avatar_registrations' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			// Can't currently support the David Walsh method due to how the plugin has been built, see https://wordpress.org/support/topic/zero-spam-support/#post-16762404
			//add_filter( 'ppress_registration_form', array( $this, 'add_scripts' ), 10, 1 );
			add_filter( 'ppress_form_field_structure', array( $this, 'add_honeypot_field' ), 10, 2 );
			add_filter( 'ppress_registration_validation', array( $this, 'process_registration_form' ), 10, 3 );
		}
	}

	/**
	 * Add to the detection types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['wp_user_avatar'] = array(
			'label' => __( 'ProfilePress', 'zero-spam' ),
			'color' => '#326EE0',
		);

		return $types;
	}

	/**
	 * Load the scripts
	 */
	/*public function add_scripts( $structure ) {
		// Only add scripts to the appropriate pages.
		if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'davidwalsh' ) ) {
			wp_enqueue_script( 'zerospam-davidwalsh' );
			wp_add_inline_script( 'zerospam-davidwalsh-wp-user-avatar', 'jQuery(".pp-registration-form-wrapper form").ZeroSpamDavidWalsh();' );
		}

		return $structure;
	}*/

	/**
	 * Preprocess registrations
	 */
	public function process_registration_form($reg_errors, $form_id, $user_data) {
		$post = \ZeroSpam\Core\Utilities::sanitize_array( $_POST );

		// Get the error message.
		$error_message = \ZeroSpam\Core\Utilities::detection_message( 'wp_user_avatar_spam_message' );

		// Create the details array for logging & sharing data.
		$details = array(
			'reg_username'         => ! empty( $post['reg_username'] ) ? $post['reg_username'] : false,
			'reg_email'            => ! empty( $post['reg_email'] ) ? $post['reg_email'] : false,
			'reg_first_name'       => ! empty( $post['reg_first_name'] ) ? $post['reg_first_name'] : false,
			'reg_last_name'        => ! empty( $post['reg_last_name'] ) ? $post['reg_last_name'] : false,
			'signup_referrer_page' => ! empty( $post['signup_referrer_page'] ) ? $post['signup_referrer_page'] : false,
			'signup_form_id'       => ! empty( $post['signup_form_id'] ) ? $post['signup_form_id'] : false,
			'pp_current_url'       => ! empty( $post['pp_current_url'] ) ? $post['pp_current_url'] : false,
			'type'                 => 'wp_user_avatar',
		);

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

		// Check email.
		if ( ! empty( $post['reg_email'] ) && ! \ZeroSpam\Core\Utilities::is_email( $post['reg_email'] ) ) {
			$validation_errors[] = 'invalid_email';
		}

		// Check blocked email domains.
		if (
			! empty( $post['reg_email'] ) &&
			\ZeroSpam\Core\Utilities::is_email_domain_blocked( $post['reg_email'] )
		) {
			// Email domain has been blocked.
			$validation_errors[] = 'blocked_email_domain';
		}

		// Fire hook for additional validation (ex. David Walsh script).
		$filtered_errors = apply_filters( 'zerospam_preprocess_wp_user_avatar_registration_submission', array(), $post, 'wp_user_avatar_spam_message' );

		if ( ! empty( $filtered_errors ) ) {
			foreach ( $filtered_errors as $key => $message ) {
				$validation_errors[] = str_replace( 'zerospam_', '', $key );
			}
		}

		if ( ! empty( $validation_errors ) ) {
			// Failed validations, log & send details if enabled.
			foreach ( $validation_errors as $key => $fail ) {
				$details['failed'] = $fail;

				// Log the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_wp_user_avatar_registrations' ) ) {
					\ZeroSpam\Includes\DB::log( 'wp_user_avatar', $details );
				}

				// Share the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
					do_action( 'zerospam_share_detection', $details );
				}
			}

			$reg_errors->add( 'zerospam_error', $error_message );
		}

		return $reg_errors;
	}

	/**
	 * Add a 'honeypot' field to the registration form
	 */
	public function add_honeypot_field( $registration_structure, $id ) {

		$registration_structure .= \ZeroSpam\Core\Utilities::honeypot_field();

		return $registration_structure;
	}

	/**
	 * Admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 */
	public function sections( $sections ) {
		$sections['wp-user-avatar'] = array(
			'title'    => __( 'ProfilePress', 'zero-spam' ),
			'icon'     => 'modules/wpuseravatar/icon-profilepress.png',
			'supports' => array( 'honeypot', 'email' ),
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-wp-user-avatar' );

		$settings['verify_wp_user_avatar_registrations'] = array(
			'title'       => __( 'Protect Registrations', 'zero-spam' ),
			'desc'        => __( 'Protects & monitors registration submissions.', 'zero-spam' ),
			'section'     => 'wp-user-avatar',
			'module'      => 'wp-user-avatar',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['verify_wp_user_avatar_registrations'] ) ? $options['verify_wp_user_avatar_registrations'] : false,
			'recommended' => 'enabled',
		);

		$settings['log_blocked_wp_user_avatar_registrations'] = array(
			'title'       => __( 'Log Blocked Registrations', 'zero-spam' ),
			'section'     => 'wp-user-avatar',
			'module'      => 'wp-user-avatar',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'When enabled, stores blocked registration submissions in the database.', 'zero-spam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['log_blocked_wp_user_avatar_registrations'] ) ? $options['log_blocked_wp_user_avatar_registrations'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'Your IP has been flagged as spam/malicious.', 'zero-spam' );

		$settings['wp_user_avatar_spam_message'] = array(
			'title'       => __( 'Flagged Message', 'zero-spam' ),
			'desc'        => __( 'Message displayed when a submission has been flagged.', 'zero-spam' ),
			'section'     => 'wp-user-avatar',
			'module'      => 'wp-user-avatar',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['wp_user_avatar_spam_message'] ) ? $options['wp_user_avatar_spam_message'] : $message,
			'recommended' => $message,
		);

		return $settings;
	}
}
