<?php
/**
 * Comments class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\Comments;

use WP_Error;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Comments
 */
class Comments {
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
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_comments' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			add_action( 'comment_form_before', array( $this, 'scripts' ) );
			add_filter( 'comment_form_defaults', array( $this, 'honeypot' ) );
			add_action( 'preprocess_comment', array( $this, 'preprocess_comments' ) );
		}
	}

	/**
	 * Add to the detection types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['comment'] = __( 'Comment', 'zero-spam' );

		return $types;
	}

	/**
	 * Load the scripts
	 */
	public function scripts() {
		do_action( 'zerospam_comment_scripts' );
	}

	/**
	 * Preprocess comments
	 *
	 * @param array $commentdata Comment data array.
	 */
	public function preprocess_comments( $commentdata ) {
		// @codingStandardsIgnoreLine
		$post = \ZeroSpam\Core\Utilities::sanitize_array( $_POST );

		// Get the error message.
		$error_message = \ZeroSpam\Core\Utilities::detection_message( 'comment_spam_message' );

		// Create the details array for logging & sharing data.
		$details = $commentdata;

		$details['type'] = 'comment';

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

		// Check blocked email domains.
		if (
			! empty( $commentdata['comment_author_email'] ) &&
			\ZeroSpam\Core\Utilities::is_email_domain_blocked( $commentdata['comment_author_email'] )
		) {
			// Email domain has been blocked.
			$validation_errors[] = 'blocked_email_domain';
		}

		// Fire hook for additional validation (ex. David Walsh script).
		$post['comment_author_email'] = $commentdata['comment_author_email'];

		$filtered_errors = apply_filters( 'zerospam_preprocess_comment_submission', array(), $post, 'comment_spam_message' );

		if ( ! empty( $filtered_errors ) ) {
			foreach ( $filtered_errors as $key => $message ) {
				$validation_errors[] = str_replace( 'zerospam_', '', $key );
			}
		}

		// Check comment disallowed list.
		$disallowed_check = array(
			'author'  => ! empty( $commentdata['comment_author'] ) ? $commentdata['comment_author'] : false,
			'email'   => ! empty( $commentdata['comment_author_email'] ) ? $commentdata['comment_author_email'] : false,
			'url'     => ! empty( $commentdata['comment_author_url'] ) ? $commentdata['comment_author_url'] : false,
			'content' => ! empty( $commentdata['comment_content'] ) ? $commentdata['comment_content'] : false,
			'ip'      => \ZeroSpam\Core\User::get_ip(),
			'agent'   => ! empty( $commentdata['comment_agent'] ) ? $commentdata['comment_agent'] : false,
		);

		if ( wp_check_comment_disallowed_list(
			$disallowed_check['author'],
			$disallowed_check['email'],
			$disallowed_check['url'],
			$disallowed_check['content'],
			$disallowed_check['ip'],
			$disallowed_check['agent'],
		) ) {
			$validation_errors[] = 'disallowed_list';
		}

		if ( ! empty( $validation_errors ) ) {
			// Failed validations, log & send details if enabled.
			foreach ( $validation_errors as $key => $fail ) {
				$details['failed'] = $fail;

				// Log the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_comments' ) ) {
					\ZeroSpam\Includes\DB::log( 'comment', $details );
				}

				// Share the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
					do_action( 'zerospam_share_detection', $details );
				}
			}

			wp_die(
				wp_kses(
					$error_message,
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
	 * Add a 'honeypot' field to the comment form
	 *
	 * @param array $defaults The default comment form arguments.
	 */
	public function honeypot( $defaults ) {
		$defaults['fields']['wpzerospam_hp'] = \ZeroSpam\Core\Utilities::honeypot_field();

		return $defaults;
	}

	/**
	 * Admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 */
	public function sections( $sections ) {
		$sections['comments'] = array(
			'title' => __( 'Comments Integration', 'zero-spam' ),
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
		$settings['verify_comments'] = array(
			'title'       => __( 'Protect Comments', 'zero-spam' ),
			'section'     => 'comments',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Monitor comments for malicious or automated spambots.', 'zero-spam' ),
			),
			'value'       => ! empty( $options['verify_comments'] ) ? $options['verify_comments'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'Your IP has been flagged as spam/malicious.', 'zero-spam' );

		$settings['comment_spam_message'] = array(
			'title'       => __( 'Spam/Malicious Message', 'zero-spam' ),
			'desc'        => __( 'When comment protection is enabled, the message displayed to the user when a comment has been detected as spam/malicious.', 'zero-spam' ),
			'section'     => 'comments',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['comment_spam_message'] ) ? $options['comment_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_comments'] = array(
			'title'       => __( 'Log Blocked Comments', 'zero-spam' ),
			'section'     => 'comments',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'Enables logging blocked comments. <strong>Recommended for enhanced protection.</strong>', 'zero-spam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => __( 'Enabled', 'zero-spam' ),
			),
			'value'       => ! empty( $options['log_blocked_comments'] ) ? $options['log_blocked_comments'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
