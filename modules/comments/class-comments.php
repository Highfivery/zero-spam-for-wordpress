<?php
/**
 * Comments class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\Comments;

use ZeroSpam;
use WP_Error;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Comments.
 *
 * @since 5.0.0
 */
class Comments {
	/**
	 * Comments constructor.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function __construct() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		$settings = ZeroSpam\Core\Settings::get_settings();
		if ( ! empty( $settings['verify_comments']['value'] ) && 'enabled' === $settings['verify_comments']['value'] ) {
			add_filter( 'comment_form_defaults', array( $this, 'honeypot' ) );
			add_action( 'preprocess_comment', array( $this, 'preprocess_comments' ) );
		}
	}

	/**
	 * Add to the types array.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function types( $types ) {
		$types['comment'] = __( 'Comment', 'zerospam' );

		return $types;
	}

	/**
	 * Preprocess comments.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function preprocess_comments( $commentdata  ) {
		$settings = ZeroSpam\Core\Settings::get_settings();
		$honeypot = ZeroSpam\Core\Utilities::get_honeypot();

		// Check honeypot.
		if (
			! empty( $_REQUEST[ $honeypot ] )
		) {
			if ( ! empty( $settings['log_blocked_comments']['value'] ) && 'enabled' === $settings['log_blocked_comments']['value'] ) {
				$details = array(
					'failed' => 'honeypot',
				);
				$details = array_merge( $details, $commentdata );
				ZeroSpam\Includes\DB::log( 'comment', $details );
			}

			$message = __( 'You have been flagged as spam/malicious by WordPress Zero Spam.', 'zerospam' );
			if ( ! empty( $settings['comment_spam_message']['value'] ) ) {
				$message = $settings['comment_spam_message']['value'];
			}

			wp_die(
				$message,
				__( 'Blocked by WordPress Zero Spam', 'zerospam' ),
				array(
					'response' => 403,
				)
			);
		}

		return apply_filters( 'zerospam_preprocess_comment', $commentdata );
	}

	/**
	 * Add a 'honeypot' field to the comment form.
	 *
	 * @since 5.0.0
	 *
	 * @return array The default comment form arguments.
	 */
	function honeypot( $defaults ) {
		$defaults['fields']['wpzerospam_hp'] = ZeroSpam\Core\Utilities::honeypot_field();

		return $defaults;
	}

	/**
	 * Comment sections.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function sections( $sections ) {
		$sections['comments'] = array(
			'title' => __( 'Comment Settings', 'zerospam' ),
		);

		return $sections;
	}

	/**
	 * Botscout settings.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function settings( $settings ) {
		$options = get_option( 'wpzerospam' );

		$settings['verify_comments'] = array(
			'title'   => __( 'Detect Spam/Malicious Comments', 'zerospam' ),
			'section' => 'comments',
			'type'    => 'checkbox',
			'options' => array(
				'enabled' => __( 'Monitor comments for malicious or automated spambots.', 'zerospam' ),
			),
			'value'   => ! empty( $options['verify_comments'] ) ? $options['verify_comments'] : false,
		);

		if ( ! empty( $options['verify_comments'] ) && 'enabled' === $options['verify_comments'] ) {
			$message = __( 'You have been flagged as spam/malicious by WordPress Zero Spam.', 'zerospam' );
			$settings['comment_spam_message'] = array(
				'title'       => __( 'Comment Spam/Malicious Message', 'zerospam' ),
				'desc'        => __( 'Displayed to the user when a comment is detected as spam/malicious.', 'zerospam' ),
				'section'     => 'comments',
				'type'        => 'text',
				'class'       => 'large-text',
				'placeholder' => $message,
				'value'       => ! empty( $options['comment_spam_message'] ) ? $options['comment_spam_message'] : $message,
			);
		}

		$settings['log_blocked_comments'] = array(
			'title'   => __( 'Log Blocked Comments', 'zerospam' ),
			'section' => 'comments',
			'type'    => 'checkbox',
			'desc'    => __( 'Enables logging blocked comments. High traffic sites should leave this disabled.', 'zerospam' ),
			'options' => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'value'   => ! empty( $options['log_blocked_comments'] ) ? $options['log_blocked_comments'] : false,
		);

		return $settings;
	}
}
