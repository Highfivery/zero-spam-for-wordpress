<?php
/**
 * David Walsh class
 *
 * See https://davidwalsh.name/wordpress-comment-spam.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\DavidWalsh;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Zero Spam
 */
class DavidWalsh {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );

		if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'davidwalsh' ) && \ZeroSpam\Core\Access::process() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ), 0 );
			add_action( 'login_enqueue_scripts', array( $this, 'scripts' ) );

			add_action( 'zerospam_comment_form_before', array( $this, 'enqueue_script' ) );
			// See https://contactform7.com/loading-javascript-and-stylesheet-only-when-it-is-necessary/.
			add_action( 'zerospam_wpcf7_enqueue_scripts', array( $this, 'enqueue_script' ) );
			add_action( 'zerospam_register_form', array( $this, 'enqueue_script' ) );
			add_action( 'zerospam_wpforms_frontend_output', array( $this, 'enqueue_script' ) );

			add_filter( 'zerospam_preprocess_comment', array( $this, 'preprocess_comments' ), 10, 1 );
			add_filter( 'zerospam_registration_errors', array( $this, 'preprocess_registration' ), 10, 3 );
			add_filter( 'zerospam_preprocess_cf7_submission', array( $this, 'preprocess_cf7_submission' ), 10, 2 );
			add_action( 'zerospam_preprocess_wpforms_submission', array( $this, 'preprocess_wpforms_submission' ), 10, 1 );
		}
	}

	/**
	 * Enqueues the script.
	 */
	public function enqueue_script() {
		wp_enqueue_script( 'zerospam-davidwalsh' );
	}

	/**
	 * Preprocess CF7 submission.
	 */
	public function preprocess_cf7_submission( $result, $tag ) {
		if ( empty( $_REQUEST['zerospam_david_walsh_key'] ) || self::get_davidwalsh() !== $_REQUEST['zerospam_david_walsh_key'] ) {
			$message = \ZeroSpam\Core\Utilities::detection_message( 'contactform7_spam_message' );
			$result->invalidate( $tag[0], $message );

			$details = array(
				'result'    => $result,
				'tag'       => $tag,
				'failed'    => 'david_walsh',
			);

			// Log if enabled.
			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_contactform7' ) ) {
				\ZeroSpam\Includes\DB::log( 'contactform7', $details );
			}

			// Share the detection if enabled.
			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
				$details['type'] = 'contactform7';
				do_action( 'zerospam_share_detection', $details );
			}
		}

		return $result;
	}

	/**
	 * Preprocess WPForms submission
	 */
	public function preprocess_wpforms_submission( $form_data ) {
		if ( empty( $_REQUEST['zerospam_david_walsh_key'] ) || self::get_davidwalsh() !== $_REQUEST['zerospam_david_walsh_key'] ) {
			$message = \ZeroSpam\Core\Utilities::detection_message( 'wpforms_spam_message' );
			wpforms()->process->errors[ $form_data['id'] ]['header'] = $message;

			$details = array(
				'form_data' => $form_data,
				'failed'    => 'david_walsh',
			);

			// Log if enabled.
			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_wpforms' ) ) {
				\ZeroSpam\Includes\DB::log( 'wpforms', $details );
			}

			// Share the detection if enabled.
			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
				$details['type'] = 'wpforms';
				do_action( 'zerospam_share_detection', $details );
			}
		}
	}

	/**
	 * Preprocess registrations
	 *
	 * @param WP_Error $errors A WP_Error object containing any errors encountered during registration.
	 * @param string   $sanitized_user_login User's username after it has been sanitized.
	 * @param string   $user_email User's email.
	 */
	public function preprocess_registration( $errors, $sanitized_user_login, $user_email ) {
		if ( empty( $_REQUEST['zerospam_david_walsh_key'] ) || self::get_davidwalsh() !== $_REQUEST['zerospam_david_walsh_key'] ) {

			$details = array(
				'user_login' => $sanitized_user_login,
				'user_email' => $user_email,
				'failed'     => 'david_walsh',
			);

			// Log if enabled.
			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_registrations' ) ) {
				\ZeroSpam\Includes\DB::log( 'registration', $details );
			}

			// Share the detection if enabled.
			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
				$details['type'] = 'registration';
				do_action( 'zerospam_share_detection', $details );
			}

			$message = \ZeroSpam\Core\Utilities::detection_message( 'registration_spam_message' );

			$errors->add( 'zerospam_error', $message );
		}

		return $errors;
	}

	/**
	 * Preprocess comments
	 *
	 * @param array $commentdata Comment data array.
	 */
	public function preprocess_comments( $commentdata ) {
		if ( empty( $_REQUEST['zerospam_david_walsh_key'] ) || self::get_davidwalsh() !== $_REQUEST['zerospam_david_walsh_key'] ) {

			$details = array(
				'failed' => 'david_walsh',
			);
			$details = array_merge( $details, $commentdata );

			// Log if enabled.
			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_comments' ) ) {
				\ZeroSpam\Includes\DB::log( 'comment', $details );
			}

			// Share the detection if enabled.
			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
				$details['type'] = 'comment';
				do_action( 'zerospam_share_detection', $details );
			}

			$message = \ZeroSpam\Core\Utilities::detection_message( 'comment_spam_message' );

			wp_die(
				wp_kses(
					$message,
					array(
						'a'      => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
						'strong' => array(),
					)
				),
				esc_html( \ZeroSpam\Core\Utilities::detection_title( 'comment_spam_message' ) ),
				array(
					'response' => 403,
				)
			);
		}

		return $commentdata;
	}

	/**
	 * David Walsh settings section
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['davidwalsh'] = array(
			'title' => __( 'David Walsh Detection Settings', 'zerospam' ),
		);

		return $sections;
	}

	/**
	 * David Walsh settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'wpzerospam' );

		$settings['davidwalsh'] = array(
			'title'       => __( 'David Walsh Technique', 'zerospam' ),
			'desc'        => sprintf(
				wp_kses(
					/* translators: %s: url */
					__( 'Enables the <a href="%s" target="_blank" rel="noreferrer noopener">David Walsh technique</a>. <strong>Requires JavaScript be enabled.</strong>', 'zerospam' ),
					array(
						'a' => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
						'strong' => array(),
					)
				),
				esc_url( 'https://davidwalsh.name/wordpress-comment-spam#utm_source=wordpresszerospam&utm_medium=admin_link&utm_campaign=wordpresszerospam' )
			),
			'section'     => 'davidwalsh',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'value'       => ! empty( $options['davidwalsh'] ) ? $options['davidwalsh'] : false,
			'recommended' => 'enabled',
		);

		$settings['davidwalsh_form_selectors'] = array(
			'title'       => __( 'Custom Form Selectors', 'zerospam' ),
			'desc'        => sprintf(
				wp_kses(
					/* translators: %s: url */
					__( 'Comma-seperated list of custom form selectors that should use the <a href="%s" target="_blank" rel="noreferrer noopener">David Walsh technique</a>.', 'zerospam' ),
					array(
						'a' => array(
							'target' => array(),
							'href'   => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://davidwalsh.name/wordpress-comment-spam#utm_source=wordpresszerospam&utm_medium=admin_link&utm_campaign=wordpresszerospam' )
			),
			'section'     => 'davidwalsh',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => '.custom-form',
			'value'       => ! empty( $options['davidwalsh_form_selectors'] ) ? $options['davidwalsh_form_selectors'] : false,
		);

		return $settings;
	}

	/**
	 * Register scripts
	 */
	public function scripts() {
		wp_register_script(
			'zerospam-davidwalsh',
			plugin_dir_url( ZEROSPAM ) . 'modules/davidwalsh/assets/js/davidwalsh.js',
			array( 'jquery' ),
			ZEROSPAM_VERSION,
			true
		);

		// Pass the davidwalsh key to the script.
		wp_localize_script(
			'zerospam-davidwalsh',
			'ZeroSpamDavidWalsh',
			array(
				'key'       => self::get_davidwalsh(),
				'selectors' => \ZeroSpam\Core\Settings::get_settings( 'davidwalsh_form_selectors' ),
			)
		);
	}

	/**
	 * Returns the generated DavidWalsh key for checking submissions.
	 */
	public static function get_davidwalsh( $regenerate = false ) {
		$key = get_option( 'zerospam_davidwalsh' );
		if ( ! $key || $regenerate ) {
			$key = wp_generate_password( 5, false, false );
			update_option( 'zerospam_davidwalsh', $key );
		}

		return $key;
	}
}
