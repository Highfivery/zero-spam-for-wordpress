<?php
/**
 * Adds integration for Elementor Pro forms
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\Elementor;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Elementor
 */
class Elementor {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		wp_add_inline_style(
			'zero-spam-admin',
			'
				.zerospam-type-elementor::before {
					background-image: url("../../modules/elementor/icon-elementor.svg");
				}
			'
		);
	}

	/**
	 * Fires after WordPress has finished loading but before any headers are sent
	 */
	public function init() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ), 10, 1 );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		if (
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'elementor_enabled' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			add_action( 'elementor_pro/forms/validation/email', array( $this, 'validate_email' ), 10, 3 );
			add_action( 'elementor_pro/forms/validation/text', array( $this, 'validate_text' ), 10, 3 );
			add_action( 'elementor_pro/forms/validation/textarea', array( $this, 'validate_text' ), 10, 3 );
			add_action( 'elementor_pro/forms/validation/html', array( $this, 'validate_text' ), 10, 3 );
		}
	}

	/**
	 * Admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 */
	public function sections( $sections ) {
		$sections['elementor'] = array(
			'title'    => __( 'Elementor', 'zero-spam' ),
			'icon'     => 'modules/elementor/icon-elementor.svg',
			'supports' => array( 'email', 'words' ),
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-elementor' );

		$settings['elementor_enabled'] = array(
			'title'       => wp_kses(
				__( 'Protect Form Submissions', 'zero-spam' ),
				array(
					'a'    => array(
						'href'   => array(),
						'class'  => array(),
						'target' => array(),
						'rel'    => array(),
					),
				)
			),
			'desc'        => __( 'Protects & monitors Elementor form submissions.', 'zero-spam' ),
			'module'      => 'elementor',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['elementor_enabled'] ) ? $options['elementor_enabled'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'We were unable to process your submission due to possible malicious activity.', 'zero-spam' );
		$settings['elementor_flagged_msg'] = array(
			'title'       => __( 'Flagged Message', 'zero-spam' ),
			'desc'        => __( 'Message displayed when a submission has been flagged.', 'zero-spam' ),
			'module'      => 'elementor',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['elementor_flagged_msg'] ) ? $options['elementor_flagged_msg'] : $message,
			'recommended' => $message,
		);

		$settings['elementor_log_flagged_attempts'] = array(
			'title'       => __( 'Log Flagged Attempts', 'zero-spam' ),
			'module'      => 'elementor',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'When enabled, stores blocked form submissions in the database.', 'zero-spam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => false
			),
			'value'       => ! empty( $options['elementor_log_flagged_attempts'] ) ? $options['elementor_log_flagged_attempts'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}

	/**
	 * Register custom fields
	 *
	 * @param \ElementorPro\Modules\Forms\Registrars\Form_Fields_Registrar $form_fields_registrar
	 */
	public function register_fields( $form_fields_registrar ) {
		//require_once ZEROSPAM_PATH . 'modules/elementor/fields/class-honeypot.php';
	}

	/**
	 * Validates form on submission
	 */
	public function validate_form( $record, $ajax_handler ) {

	}

	/**
	 * Validates an email address
	 */
	public function validate_email( $field, $record, $ajax_handler ) {
		if ( \ZeroSpam\Core\Utilities::is_email_domain_blocked( $field['value'] ) ) {
			$ajax_handler->add_error( $field['id'], \ZeroSpam\Core\Utilities::detection_message( 'elementor_flagged_msg' ) );
			return;
		}
	}

	/**
	 * Validates text content
	 */
	public function validate_text( $field, $record, $ajax_handler ) {
		if ( \ZeroSpam\Core\Utilities::is_disallowed( $field['value'] ) ) {
			do_action(
				'zero_spam_flagged_attempt',
				'elementor',
				'disallowed_list',
				array(
					'field'  => $field,
					'record' => $record,
				)
			);

			$ajax_handler->add_error( $field['id'], \ZeroSpam\Core\Utilities::detection_message( 'elementor_flagged_msg' ) );
			return;
		}
	}
}
