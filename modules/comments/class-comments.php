<?php
/**
 * Comments class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\Comments;

use ZeroSpam;
use WP_Error;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Comments
 */
class Comments {
	/**
	 * Comments constructor
	 */
	public function __construct() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		if ( 'enabled' === ZeroSpam\Core\Settings::get_settings( 'verify_comments' ) && ZeroSpam\Core\Access::process() ) {
			add_action( 'comment_form_before', array( $this, 'comment_form_before' ) );
			add_filter( 'comment_form_defaults', array( $this, 'honeypot' ) );
			add_action( 'preprocess_comment', array( $this, 'preprocess_comments' ) );
		}
	}

	/**
	 * Add to the types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['comment'] = __( 'Comment', 'zerospam' );

		return $types;
	}

	/**
	 * Fires before the comment form.
	 */
	public function comment_form_before() {
		do_action( 'zerospam_comment_form_before' );
	}

	/**
	 * Preprocess comments
	 *
	 * @param array $commentdata Comment data array.
	 */
	public function preprocess_comments( $commentdata ) {
		$block_user = false;
		$block_type = false;

		// Check honeypot.
		// @codingStandardsIgnoreLine
		if ( ! empty( $_REQUEST[ ZeroSpam\Core\Utilities::get_honeypot() ] ) ) {
			$block_user = true;
			$block_type = 'honeypot';
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
			$block_user = true;
			$block_type = 'disallowed_list';
		}

		if ( $block_user && $block_type ) {
			$details = array(
				'failed' => $block_type,
			);
			$details = array_merge( $details, $commentdata );

			// Log if enabled.
			if ( 'enabled' === ZeroSpam\Core\Settings::get_settings( 'log_blocked_comments' ) ) {
				ZeroSpam\Includes\DB::log( 'comment', $details );
			}

			// Share the detection if enabled.
			if ( 'enabled' === ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
				$details['type'] = 'comment';
				do_action( 'zerospam_share_detection', $details );
			}

			$message = ZeroSpam\Core\Utilities::detection_message( 'comment_spam_message' );

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
				esc_html( ZeroSpam\Core\Utilities::detection_title( 'comment_spam_message' ) ),
				array(
					'response' => 403,
				)
			);
		}

		return apply_filters( 'zerospam_preprocess_comment', $commentdata );
	}

	/**
	 * Add a 'honeypot' field to the comment form
	 *
	 * @param array $defaults The default comment form arguments.
	 */
	public function honeypot( $defaults ) {
		$defaults['fields']['wpzerospam_hp'] = ZeroSpam\Core\Utilities::honeypot_field();

		return $defaults;
	}

	/**
	 * Comment sections
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['comments'] = array(
			'title' => __( 'Comments Integration', 'zerospam' ),
		);

		return $sections;
	}

	/**
	 * Comment settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'wpzerospam' );

		$settings['verify_comments'] = array(
			'title'       => __( 'Protect Comments', 'zerospam' ),
			'section'     => 'comments',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Monitor comments for malicious or automated spambots.', 'zerospam' ),
			),
			'value'       => ! empty( $options['verify_comments'] ) ? $options['verify_comments'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'You have been flagged as spam/malicious by WordPress Zero Spam.', 'zerospam' );

		$settings['comment_spam_message'] = array(
			'title'       => __( 'Spam/Malicious Message', 'zerospam' ),
			'desc'        => __( 'When comment protection is enabled, the message displayed to the user when a comment has been detected as spam/malicious.', 'zerospam' ),
			'section'     => 'comments',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['comment_spam_message'] ) ? $options['comment_spam_message'] : $message,
		);

		$settings['log_blocked_comments'] = array(
			'title'       => __( 'Log Blocked Comments', 'zerospam' ),
			'section'     => 'comments',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'Enables logging blocked comments. <strong>Recommended for enhanced protection.</strong>', 'zerospam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'value'       => ! empty( $options['log_blocked_comments'] ) ? $options['log_blocked_comments'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
