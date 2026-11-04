<?php
/**
 * Registration integration module
 *
 * Covers wp-login.php's registration form on a single site and wp-signup.php
 * on a network, which fires none of that form's hooks.
 *
 * Malicious user detection techniques available:
 *
 * 1. Zero Spam honeypot field
 * 2. Checks blocked email domains
 * 3. Uses the David Walsh technique
 * 4. Stop Forum Spam lookup of the email and IP
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\Registration;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Registration
 */
class Registration {
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
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_registrations' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			add_action( 'register_form', array( $this, 'add_scripts' ) );
			add_action( 'register_form', array( $this, 'add_honeypot_field' ) );
			add_filter( 'registration_errors', array( $this, 'process_form' ), 10, 3 );

			// A network's sign-up page. Both of its steps print and check the fields.
			add_action( 'before_signup_form', array( $this, 'add_scripts' ) );
			add_action( 'signup_hidden_fields', array( $this, 'add_honeypot_field' ) );
			add_action( 'before_signup_header', array( $this, 'watch_signup' ) );
		}
	}

	/**
	 * Add to the detection types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['registration'] = array(
			'label' => __( 'Registration', 'zero-spam' ),
			'color' => '#3858e9',
		);

		return $types;
	}

	/**
	 * Load the scripts.
	 *
	 * Uses centralized David Walsh script - selectors are managed in class-davidwalsh.php.
	 */
	public function add_scripts() {
		// Only add scripts if David Walsh is enabled.
		if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'davidwalsh' ) ) {
			wp_enqueue_script( 'zerospam-davidwalsh' );
		}
	}

	/**
	 * Preprocess registrations
	 *
	 * @param WP_Error $errors A WP_Error object containing any errors encountered during registration.
	 * @param string   $sanitized_user_login User's username after it has been sanitized.
	 * @param string   $user_email User's email.
	 */
	public function process_form( $errors, $sanitized_user_login, $user_email ) {
		if ( $this->is_spam( (string) $sanitized_user_login, (string) $user_email ) ) {
			$errors->add( 'zerospam_error', \ZeroSpam\Core\Utilities::detection_message( 'registration_spam_message' ) );
		}

		return $errors;
	}

	/**
	 * Starts checking sign-ups, on wp-signup.php only.
	 *
	 * Core also runs `wpmu_validate_user_signup` for callers with no form of
	 * ours (wp user create, the REST API, Add User, other plugins' forms), which
	 * have no honeypot field or David Walsh key and must not be refused for it.
	 */
	public function watch_signup() {
		add_filter( 'wpmu_validate_user_signup', array( $this, 'process_signup' ) );
	}

	/**
	 * Preprocess a network's sign-ups.
	 *
	 * Runs on both steps of wp-signup.php: the second validates the user again,
	 * and a bot can post it directly. The page prints the `generic` error
	 * above the form, without escaping it.
	 *
	 * @param array $result The sign-up: user_name, orig_username, user_email and errors.
	 * @return array
	 */
	public function process_signup( $result ) {
		if ( ! is_array( $result ) || ! isset( $result['errors'] ) || ! is_wp_error( $result['errors'] ) ) {
			return $result;
		}

		$user_login = isset( $result['user_name'] ) && is_string( $result['user_name'] ) ? $result['user_name'] : '';
		$user_email = isset( $result['user_email'] ) && is_string( $result['user_email'] ) ? $result['user_email'] : '';

		if ( $this->is_spam( $user_login, $user_email ) ) {
			$result['errors']->add( 'generic', wp_kses_post( \ZeroSpam\Core\Utilities::detection_message( 'registration_spam_message' ) ) );
		}

		return $result;
	}

	/**
	 * Checks a registration, then logs and shares each reason it failed.
	 *
	 * @param string $user_login Username, after WordPress sanitized it.
	 * @param string $user_email Email address.
	 * @return bool True when the registration must be refused.
	 */
	private function is_spam( string $user_login, string $user_email ): bool {
		// @codingStandardsIgnoreLine
		$post = \ZeroSpam\Core\Utilities::sanitize_array( $_POST );

		// Create the details array for logging & sharing data.
		$details = array(
			'user_login' => $user_login,
			'user_email' => $user_email,
			'type'       => 'registration',
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
		if ( ! empty( $user_email ) && ! \ZeroSpam\Core\Utilities::is_email( $user_email ) ) {
			$validation_errors[] = 'invalid_email';
		}

		// Check blocked email domains.
		if (
			! empty( $user_email ) &&
			\ZeroSpam\Core\Utilities::is_email_domain_blocked( $user_email )
		) {
			// Email domain has been blocked.
			$validation_errors[] = 'blocked_email_domain';
		}

		// Fire hook for additional validation (ex. David Walsh script). Checks that
		// read the email get the address WordPress is registering.
		$post['user_email'] = $user_email;

		$filtered_errors = apply_filters( 'zerospam_preprocess_registration_submission', array(), $post, 'registration_spam_message' );

		if ( ! empty( $filtered_errors ) ) {
			foreach ( $filtered_errors as $key => $message ) {
				$validation_errors[] = str_replace( 'zerospam_', '', $key );
			}
		}

		// Failed validations, log & send details if enabled.
		foreach ( $validation_errors as $fail ) {
			$details['failed'] = $fail;

			// Log the detection if enabled.
			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_registrations' ) ) {
				\ZeroSpam\Includes\DB::log( 'registration', $details );
			}

			// Share the detection if enabled.
			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
				do_action( 'zerospam_share_detection', $details );
			}
		}

		return ! empty( $validation_errors );
	}

	/**
	 * Add a 'honeypot' field to the registration form
	 */
	public function add_honeypot_field() {
		// @codingStandardsIgnoreLine
		echo \ZeroSpam\Core\Utilities::honeypot_field();
	}

	/**
	 * Admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 */
	public function sections( $sections ) {
		$sections['registration'] = array(
			'title'    => __( 'Registration', 'zero-spam' ),
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
		$options = get_option( 'zero-spam-registration' );

		$settings['verify_registrations'] = array(
			'title'       => __( 'Protect Registrations', 'zero-spam' ),
			'desc'        => __( 'Stop spam bots from creating fake user accounts.', 'zero-spam' ),
			'section'     => 'registration',
			'module'      => 'registration',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['verify_registrations'] ) ? $options['verify_registrations'] : false,
			'recommended' => 'enabled',
		);

		$message = \ZeroSpam\Core\Utilities::spam_message();

		$settings['registration_spam_message'] = array(
			'title'       => __( 'Flagged Message', 'zero-spam' ),
			'desc'        => __( 'The message shown to spam bots trying to register.', 'zero-spam' ),
			'section'     => 'registration',
			'module'      => 'registration',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['registration_spam_message'] ) ? $options['registration_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_registrations'] = array(
			'title'       => __( 'Log Blocked Registrations', 'zero-spam' ),
			'section'     => 'registration',
			'module'      => 'registration',
			'type'        => 'checkbox',
			'desc'        => __( 'Keep a record of all blocked registration attempts in the database.', 'zero-spam' ),
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['log_blocked_registrations'] ) ? $options['log_blocked_registrations'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
