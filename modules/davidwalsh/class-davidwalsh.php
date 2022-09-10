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
 * David Walsh
 */
class DavidWalsh {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ), 0 );
	}

	/**
	 * Fires after WordPress has finished loading but before any headers are sent.
	 */
	public function init() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ), 10, 1 );
		add_filter( 'zerospam_failed_types', array( $this, 'failed_types' ), 10, 1 );

		if (
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'davidwalsh' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ), 0 );
			add_action( 'login_enqueue_scripts', array( $this, 'scripts' ) );
			add_action( 'zerospam_fluentforms_scripts', array( $this, 'enqueue_script' ) );
			add_action( 'zerospam_mailchimp4wp_scripts', array( $this, 'enqueue_script' ) );

			add_filter( 'zerospam_preprocess_comment_submission', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_preprocess_registration_submission', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_preprocess_cf7_submission', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_preprocess_wpforms_submission', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_preprocess_fluentform_submission', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_preprocess_login_attempt', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_preprocess_mailchimp4wp', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_process_woocommerce_registration', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_process_givewp_submission', array( $this, 'validate_post' ), 10, 3 );
		}
	}

	/**
	 * Enqueues the script
	 */
	public function enqueue_script() {
		wp_enqueue_script( 'zerospam-davidwalsh' );
	}

	/**
	 * Validates a submission against the David Walsh field.
	 *
	 * @param array  $errors            Array of submission errors.
	 * @param array  $post              Form post array.
	 * @param string $detection_msg_key Detection message key.
	 */
	public function validate_post( $errors, $post, $detection_msg_key ) {
		if ( empty( $post['zerospam_david_walsh_key'] ) || self::get_davidwalsh() !== $post['zerospam_david_walsh_key'] ) {
			// Failed the David Walsh check.
			$error_message = \ZeroSpam\Core\Utilities::detection_message( $detection_msg_key );

			$errors['zerospam_david_walsh'] = $error_message;
		}

		return $errors;
	}

	/**
	 * Add to failed types
	 *
	 * @param array $types Array of failed types.
	 */
	public function failed_types( $types ) {
		$types['david_walsh'] = __( 'David Walsh', 'zero-spam' );

		return $types;
	}

	/**
	 * Admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 */
	public function sections( $sections ) {
		$sections['davidwalsh'] = array(
			'title' => __( 'David Walsh', 'zero-spam' ),
			'icon'  => 'modules/davidwalsh/icon-david-walsh.png'
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-davidwalsh' );

		$settings['davidwalsh'] = array(
			'title'       => __( 'David Walsh Technique', 'zero-spam' ),
			'desc'        => sprintf(
				wp_kses(
					/* translators: %s: url */
					__( 'Enables the <a href="%s" target="_blank" rel="noreferrer noopener">David Walsh technique</a>. <strong>Requires JavaScript be enabled.</strong>', 'zero-spam' ),
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
			'module'      => 'davidwalsh',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Enabled', 'zero-spam' ),
			),
			'value'       => ! empty( $options['davidwalsh'] ) ? $options['davidwalsh'] : false,
			'recommended' => 'enabled',
		);

		$settings['davidwalsh_form_selectors'] = array(
			'title'       => __( 'Custom Form Selectors', 'zero-spam' ),
			'desc'        => sprintf(
				wp_kses(
					/* translators: %s: url */
					__( 'Comma-seperated list of custom form selectors that should use the <a href="%s" target="_blank" rel="noreferrer noopener">David Walsh technique</a>.', 'zero-spam' ),
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
			'module'      => 'davidwalsh',
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
