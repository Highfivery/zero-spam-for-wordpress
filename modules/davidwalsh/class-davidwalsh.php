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
 *
 * Implements the David Walsh spam detection technique which uses JavaScript
 * to inject a hidden validation field. Since bots typically don't execute
 * JavaScript, they fail validation.
 *
 * Features:
 * - 16-character alphanumeric keys for enhanced security
 * - Dual-key system for caching compatibility (current + previous key valid)
 * - Daily key rotation via WP Cron
 * - REST API endpoint for AJAX key refresh on cached pages
 * - MutationObserver support for dynamically loaded forms
 * - Centralized selector management via filter
 */
class DavidWalsh {

	/**
	 * Option name for storing key data.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'zerospam_davidwalsh_data';

	/**
	 * Cron hook name for key rotation.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'zerospam_davidwalsh_rotate_key';

	/**
	 * Key length in characters.
	 *
	 * @var int
	 */
	const KEY_LENGTH = 16;

	/**
	 * Key TTL in seconds (24 hours).
	 *
	 * @var int
	 */
	const KEY_TTL = 86400;

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'zero-spam/v5';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ), 0 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( self::CRON_HOOK, array( $this, 'rotate_key' ) );

		// Schedule cron on plugin activation.
		register_activation_hook( ZEROSPAM, array( __CLASS__, 'schedule_cron' ) );
		register_deactivation_hook( ZEROSPAM, array( __CLASS__, 'unschedule_cron' ) );
	}

	/**
	 * Fires after WordPress has finished loading but before any headers are sent.
	 */
	public function init() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ), 10, 1 );
		add_filter( 'zerospam_failed_types', array( $this, 'failed_types' ), 10, 1 );

		// Ensure cron is scheduled (in case it was missed).
		self::maybe_schedule_cron();

		if (
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'davidwalsh' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ), 0 );
			add_action( 'login_enqueue_scripts', array( $this, 'scripts' ) );

			// Module-specific script hooks.
			add_action( 'zerospam_fluentforms_scripts', array( $this, 'enqueue_script' ) );
			add_action( 'zerospam_mailchimp4wp_scripts', array( $this, 'enqueue_script' ) );
			add_action( 'zerospam_gravityforms_scripts', array( $this, 'enqueue_script' ) );
			add_action( 'zerospam_formidable_scripts', array( $this, 'enqueue_script' ) );
			add_action( 'zerospam_elementor_scripts', array( $this, 'enqueue_script' ) );

			// Validation filter hooks.
			add_filter( 'zerospam_preprocess_comment_submission', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_preprocess_registration_submission', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_preprocess_cf7_submission', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_preprocess_wpforms_submission', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_preprocess_fluentform_submission', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_preprocess_login_attempt', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_preprocess_mailchimp4wp', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_process_woocommerce_registration', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_process_woocommerce_checkout', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_preprocess_gravityforms_submission', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_preprocess_formidable_submission', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_preprocess_elementor_submission', array( $this, 'validate_post' ), 10, 3 );
		}
	}

	/**
	 * Register REST API routes for AJAX key refresh.
	 */
	public function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/davidwalsh-key',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_key' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * REST API callback for getting the current David Walsh key.
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_get_key() {
		$key_data = self::get_key_data();

		$generated = ! empty( $key_data['rotated_at'] ) ? strtotime( $key_data['rotated_at'] ) : 0;

		return new \WP_REST_Response(
			array(
				'key'       => (string) $key_data['current_key'],
				'generated' => (int) $generated,
				'ttl'       => (int) self::KEY_TTL,
			),
			200
		);
	}

	/**
	 * Enqueues the script.
	 */
	public function enqueue_script() {
		wp_enqueue_script( 'zerospam-davidwalsh' );
	}

	/**
	 * Validates a submission against the David Walsh field.
	 *
	 * Accepts both current and previous keys to support caching scenarios
	 * where the page HTML contains a key from before the last rotation.
	 *
	 * @param array  $errors            Array of submission errors.
	 * @param array  $post              Form post array.
	 * @param string $detection_msg_key Detection message key.
	 * @return array Modified errors array.
	 */
	public function validate_post( $errors, $post, $detection_msg_key ) {
		$submitted_key = isset( $post['zerospam_david_walsh_key'] ) ? sanitize_text_field( $post['zerospam_david_walsh_key'] ) : '';
		$key_data      = self::get_key_data();

		// Accept either current or previous key (for caching compatibility).
		$valid_keys = array(
			$key_data['current_key'],
		);

		// Only include previous key if it exists.
		if ( ! empty( $key_data['previous_key'] ) ) {
			$valid_keys[] = $key_data['previous_key'];
		}

		if ( empty( $submitted_key ) || ! in_array( $submitted_key, $valid_keys, true ) ) {
			// Failed the David Walsh check.
			$error_message = \ZeroSpam\Core\Utilities::detection_message( $detection_msg_key );
			$errors['zerospam_david_walsh'] = $error_message;
		}

		return $errors;
	}

	/**
	 * Add to failed types.
	 *
	 * @param array $types Array of failed types.
	 * @return array Modified types array.
	 */
	public function failed_types( $types ) {
		$types['david_walsh'] = __( 'David Walsh', 'zero-spam' );
		return $types;
	}

	/**
	 * Admin setting sections.
	 *
	 * @param array $sections Array of admin setting sections.
	 * @return array Modified sections array.
	 */
	public function sections( $sections ) {
		$sections['davidwalsh'] = array(
			'title' => __( 'David Walsh', 'zero-spam' ),
			'icon'  => 'modules/davidwalsh/icon-david-walsh.png',
		);

		return $sections;
	}

	/**
	 * Admin settings.
	 *
	 * NOTE: This intentionally does NOT call wp_kses(). The Settings renderer is the single
	 * sanitizer/allowlist gatekeeper for html-type fields (prevents mismatched allowlists).
	 *
	 * @param array $settings Array of available settings.
	 * @return array Modified settings array.
	 */
	public function settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$options  = get_option( 'zero-spam-davidwalsh', array() );
		$key_data = self::get_key_data();

		$key_data = wp_parse_args(
			is_array( $key_data ) ? $key_data : array(),
			array(
				'rotated_at'   => '',
				'current_key'  => '',
				'previous_key' => '',
			)
		);

		// Get human-readable time until next rotation.
		$rotation_readable    = esc_html__( 'Unknown', 'zero-spam' );
		$rotated_at_timestamp = ! empty( $key_data['rotated_at'] ) ? strtotime( $key_data['rotated_at'] ) : false;

		if ( false !== $rotated_at_timestamp ) {
			$next_rotation = $rotated_at_timestamp + self::KEY_TTL;
			$time_until    = $next_rotation - time();
			$hours_until   = max( 0, (int) floor( $time_until / HOUR_IN_SECONDS ) );

			$rotation_readable = sprintf(
				/* translators: %d: number of hours until the next key rotation. */
				_n( '%d hour', '%d hours', $hours_until, 'zero-spam' ),
				$hours_until
			);
		}

		// How It Works.
		$how_it_works_features = array(
			esc_html__( 'Works invisibly — no CAPTCHAs or puzzles for your users.', 'zero-spam' ),
			esc_html__( 'Automatically protects comments, registrations, logins, and supported plugins.', 'zero-spam' ),
			esc_html__( 'Compatible with page caching through automatic key rotation.', 'zero-spam' ),
			esc_html__( 'No impact on user experience for legitimate visitors.', 'zero-spam' ),
		);

		$how_it_works_features_html = '';
		foreach ( $how_it_works_features as $feature ) {
			$how_it_works_features_html .= '<li>' . $feature . '</li>';
		}

		$settings['davidwalsh_howto'] = array(
			'title'   => __( 'How It Works', 'zero-spam' ),
			'desc'    => '',
			'section' => 'davidwalsh',
			'module'  => 'davidwalsh',
			'type'    => 'html',
			'html'    => sprintf(
				'<p><strong>%1$s</strong> %2$s</p><p><strong>%3$s</strong></p><ul class="zerospam-list zerospam-list--features">%4$s</ul>',
				esc_html__( 'The David Walsh technique is a JavaScript-based spam detection method', 'zero-spam' ),
				esc_html__(
					'that adds a hidden security key to your forms when users interact with them. Since automated spam bots typically do not execute JavaScript, they will not have this key — and their submissions will be blocked.',
					'zero-spam'
				),
				esc_html__( 'Key Features:', 'zero-spam' ),
				$how_it_works_features_html
			),
		);

		// Main enable/disable toggle.
		$learn_more_link_html = sprintf(
			'<a href="%1$s" target="_blank" rel="noreferrer noopener">%2$s</a>',
			esc_url( 'https://davidwalsh.name/wordpress-comment-spam#utm_source=wordpresszerospam&utm_medium=admin_link&utm_campaign=wordpresszerospam' ),
			esc_html__( 'Learn more about how it works (opens in a new tab)', 'zero-spam' )
		);

		$settings['davidwalsh'] = array(
			'title'       => __( 'Enable Protection', 'zero-spam' ),
			'desc'        => sprintf(
				/* translators: %s: External link (opens in a new tab). */
				__( 'Enable or disable the David Walsh spam detection technique. When enabled, a security key is automatically added to protected forms. %s', 'zero-spam' ),
				$learn_more_link_html
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

		// Current Key Status.
		$masked_key  = esc_html__( 'Unavailable', 'zero-spam' );
		$current_key = (string) $key_data['current_key'];

		if ( strlen( $current_key ) >= 8 ) {
			$masked_key = substr( $current_key, 0, 4 ) . '••••••••' . substr( $current_key, -4 );
		}

		$settings['davidwalsh_status'] = array(
			'title'   => __( 'Security Key Status', 'zero-spam' ),
			'desc'    => esc_html__(
				'Your security key automatically rotates every 24 hours to maintain optimal protection, especially for cached pages. The previous key remains valid for an additional 24 hours to prevent false positives during the transition.',
				'zero-spam'
			),
			'section' => 'davidwalsh',
			'module'  => 'davidwalsh',
			'type'    => 'html',
			'html'    => sprintf(
				'<code>%1$s</code> &nbsp;&mdash;&nbsp; %2$s <strong>%3$s</strong>',
				esc_html( $masked_key ),
				esc_html__( 'Next rotation in approximately', 'zero-spam' ),
				esc_html( $rotation_readable )
			),
		);

		// Testing Instructions.
		$testing_steps = array(
			array(
				'title' => esc_html__( '1. Test a protected form', 'zero-spam' ),
				'desc'  => esc_html__(
					'Open your website in a browser (not logged in as an admin) and locate a protected form, such as the comment form on a blog post.',
					'zero-spam'
				),
			),
			array(
				'title' => esc_html__( '2. Inspect the form', 'zero-spam' ),
				'desc'  => sprintf(
					/* translators: %s: Hidden input name. */
					__( 'Right-click on the form and select "Inspect" (or "Inspect Element"). Look for a hidden input field named %s. If you see it, the protection is active.', 'zero-spam' ),
					'<code>zerospam_david_walsh_key</code>'
				),
			),
			array(
				'title' => esc_html__( '3. Submit a test', 'zero-spam' ),
				'desc'  => esc_html__(
					'Submit a normal comment or form entry. If it goes through successfully, the protection is working correctly for legitimate users.',
					'zero-spam'
				),
			),
		);

		$testing_steps_html = '';
		foreach ( $testing_steps as $step ) {
			$testing_steps_html .= sprintf(
				'<li><strong>%1$s</strong> %2$s</li>',
				$step['title'],
				$step['desc']
			);
		}

		$troubleshooting_items = array(
			esc_html__( 'If the hidden field does not appear, JavaScript may be blocked or the form selector may not be recognized.', 'zero-spam' ),
			esc_html__( 'If legitimate submissions are blocked, clear your site cache and try again.', 'zero-spam' ),
			esc_html__( 'For custom forms, add the form’s CSS selector to the Custom Form Selectors field below.', 'zero-spam' ),
		);

		$troubleshooting_html = '';
		foreach ( $troubleshooting_items as $item ) {
			$troubleshooting_html .= '<li>' . $item . '</li>';
		}

		$settings['davidwalsh_testing'] = array(
			'title'   => __( 'How to Test', 'zero-spam' ),
			'desc'    => '',
			'section' => 'davidwalsh',
			'module'  => 'davidwalsh',
			'type'    => 'html',
			'html'    => sprintf(
				'<p><strong>%1$s</strong></p><ul class="zerospam-list zerospam-list--steps">%2$s</ul><p><strong>%3$s</strong></p><ul class="zerospam-list zerospam-list--features">%4$s</ul>',
				esc_html__( 'Follow these steps to verify the David Walsh technique is working on your site:', 'zero-spam' ),
				$testing_steps_html,
				esc_html__( 'Troubleshooting:', 'zero-spam' ),
				$troubleshooting_html
			),
		);

		// Protected Forms List.
		$all_selectors = (array) self::get_all_selectors();
		$pills_html    = '<ul class="zerospam-list zerospam-list--pills"><li>' . implode( '</li><li>', array_map( 'esc_html', $all_selectors ) ) . '</li></ul>';

		$settings['davidwalsh_protected_forms'] = array(
			'title'   => __( 'Currently Protected Forms', 'zero-spam' ),
			'desc'    => esc_html__(
				'The following form types are automatically protected when the David Walsh technique is enabled. These selectors are built-in and managed by the plugin:',
				'zero-spam'
			),
			'section' => 'davidwalsh',
			'module'  => 'davidwalsh',
			'type'    => 'html',
			'html'    => $pills_html,
		);

		// Custom Form Selectors.
		$custom_form_selector_value = ! empty( $options['davidwalsh_form_selectors'] ) ? (string) $options['davidwalsh_form_selectors'] : '';

		$custom_help_items = array(
			esc_html__( 'Right-click on your form and select "Inspect" (or "Inspect Element").', 'zero-spam' ),
			sprintf(
				/* translators: %s: the HTML <form> tag. */
				__( 'Look for the %s tag in the HTML.', 'zero-spam' ),
				'<code>&lt;form&gt;</code>'
			),
			sprintf(
				/* translators: 1: class attribute, 2: id attribute, 3: example class attribute, 4: example id attribute. */
				__( 'Note the %1$s or %2$s attribute (for example, %3$s or %4$s).', 'zero-spam' ),
				'<code>class</code>',
				'<code>id</code>',
				'<code>class="my-form"</code>',
				'<code>id="contact-form"</code>'
			),
			sprintf(
				/* translators: 1: CSS class selector example, 2: CSS ID selector example. */
				__( 'Enter it as %1$s (for a class) or %2$s (for an ID).', 'zero-spam' ),
				'<code>.my-form</code>',
				'<code>#contact-form</code>'
			),
		);

		$custom_help_html = '';
		foreach ( $custom_help_items as $help_item ) {
			$custom_help_html .= '<li>' . $help_item . '</li>';
		}

		$settings['davidwalsh_form_selectors'] = array(
			'title'   => __( 'Custom Form Selectors', 'zero-spam' ),
			'desc'    => '',
			'section' => 'davidwalsh',
			'module'  => 'davidwalsh',
			'type'    => 'html',
			'html'    => sprintf(
				'<h3 class="zero-spam-heading-3" id="davidwalsh_form_selectors_heading">%1$s</h3>
				<ol class="zerospam-list zerospam-list--decimal" id="davidwalsh_form_selectors_help">%2$s</ol>

				<label class="zero-spam-label" for="davidwalsh_form_selectors">%3$s</label>

				<input type="text"
					name="zero-spam-davidwalsh[davidwalsh_form_selectors]"
					id="davidwalsh_form_selectors"
					value="%4$s"
					class="large-text"
					placeholder="%5$s"
					aria-describedby="davidwalsh_form_selectors_help davidwalsh_form_selectors_example davidwalsh_form_selectors_desc" />

				<p class="zero-spam-field-example" id="davidwalsh_form_selectors_example">
					<strong>%6$s</strong> <code>%7$s</code>
				</p>

				<p class="zero-spam-field-desc" id="davidwalsh_form_selectors_desc">
					<strong>%8$s</strong> %9$s
				</p>',
				esc_html__( 'How to Find Your Form\'s Selector:', 'zero-spam' ),
				$custom_help_html,
				esc_html__( 'Define Custom Form Selectors', 'zero-spam' ),
				esc_attr( $custom_form_selector_value ),
				esc_attr_x( '.my-custom-form, #newsletter-form', 'Placeholder text for custom form selectors input.', 'zero-spam' ),
				esc_html__( 'Examples:', 'zero-spam' ),
				esc_html__( '.my-custom-form, #newsletter-signup, .theme-contact-form', 'zero-spam' ),
				esc_html__( 'For advanced users:', 'zero-spam' ),
				esc_html__(
					'Add CSS selectors for custom forms that should be protected. Separate multiple selectors with commas. Most popular form plugins are already protected automatically. Only add selectors for custom-built forms or unsupported themes.',
					'zero-spam'
				)
			),
		);

		return $settings;
	}

	/**
	 * Register scripts.
	 */
	public function scripts() {
		$key_data = self::get_key_data();

		wp_register_script(
			'zerospam-davidwalsh',
			plugin_dir_url( ZEROSPAM ) . 'modules/davidwalsh/assets/js/davidwalsh.js',
			array(),
			ZEROSPAM_VERSION,
			true
		);

		// Get all selectors via centralized method.
		$selectors = self::get_all_selectors();

		$generated = ! empty( $key_data['rotated_at'] ) ? strtotime( $key_data['rotated_at'] ) : 0;

		// Pass data to the script.
		wp_localize_script(
			'zerospam-davidwalsh',
			'ZeroSpamDavidWalsh',
			array(
				'key'       => (string) $key_data['current_key'],
				'generated' => (int) $generated,
				'ttl'       => (int) self::KEY_TTL,
				'selectors' => implode( ', ', (array) $selectors ),
				'restUrl'   => rest_url( self::REST_NAMESPACE . '/davidwalsh-key' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Get all form selectors that should use the David Walsh technique.
	 *
	 * Centralizes selector management and allows filtering by other modules.
	 *
	 * @return array Array of CSS selectors.
	 */
	public static function get_all_selectors() {
		$selectors = array(
			// Core WordPress forms.
			'.comment-form',
			'#commentform',
			'#registerform',
			'#loginform',

			// Third-party integrations.
			'.frm-fluent-form',
			'.mc4wp-form',
			'.wpforms-form',
			'.wpcf7-form',
			'.gform_wrapper form',
			'.frm-show-form',
			'.elementor-form',
			'.woocommerce-form-register',
			'.woocommerce-checkout',

			// WPDiscuz.
			'.wpd_comm_form',
		);

		// Add custom selectors from settings (retrieved directly to avoid recursion).
		$options          = get_option( 'zero-spam-davidwalsh', array() );
		$custom_selectors = ! empty( $options['davidwalsh_form_selectors'] ) ? $options['davidwalsh_form_selectors'] : false;

		if ( ! empty( $custom_selectors ) ) {
			$custom_array = array_map( 'trim', explode( ',', (string) $custom_selectors ) );
			$selectors    = array_merge( $selectors, array_filter( $custom_array ) );
		}

		/**
		 * Filter the David Walsh form selectors.
		 *
		 * @param array $selectors Array of CSS selectors.
		 */
		return apply_filters( 'zerospam_davidwalsh_selectors', $selectors );
	}

	/**
	 * Get key data with current and previous keys.
	 *
	 * @return array {
	 *     Key data array.
	 *
	 *     @type string $current_key  The current active key.
	 *     @type string $previous_key The previous key (for caching grace period).
	 *     @type string $rotated_at   MySQL datetime of last rotation.
	 * }
	 */
	public static function get_key_data() {
		$key_data = get_option( self::OPTION_NAME );

		// Initialize if doesn't exist or is in old format (single string key).
		if ( ! $key_data || ! is_array( $key_data ) || empty( $key_data['current_key'] ) ) {
			// Migrate from old single-key format if exists.
			$old_key = get_option( 'zerospam_davidwalsh' );

			$key_data = array(
				'current_key'  => wp_generate_password( self::KEY_LENGTH, false, false ),
				'previous_key' => ! empty( $old_key ) && is_string( $old_key ) ? $old_key : '',
				'rotated_at'   => current_time( 'mysql' ),
			);

			update_option( self::OPTION_NAME, $key_data, false );

			// Clean up old option if it exists and is the old format.
			if ( ! empty( $old_key ) && is_string( $old_key ) ) {
				delete_option( 'zerospam_davidwalsh' );
			}
		}

		return $key_data;
	}

	/**
	 * Rotate the David Walsh key.
	 *
	 * Moves current key to previous, generates new current key.
	 * Called via WP Cron daily.
	 */
	public static function rotate_key() {
		$key_data = self::get_key_data();

		$new_key_data = array(
			'current_key'  => wp_generate_password( self::KEY_LENGTH, false, false ),
			'previous_key' => $key_data['current_key'],
			'rotated_at'   => current_time( 'mysql' ),
		);

		update_option( self::OPTION_NAME, $new_key_data, false );
	}

	/**
	 * Schedule the daily cron event for key rotation.
	 */
	public static function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Maybe schedule cron if not already scheduled.
	 *
	 * Fallback for cases where activation hook didn't run.
	 */
	public static function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			self::schedule_cron();
		}
	}

	/**
	 * Unschedule the cron event.
	 */
	public static function unschedule_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Legacy method for backward compatibility.
	 *
	 * @deprecated Use get_key_data() instead.
	 *
	 * @param bool $regenerate Unused parameter, kept for compatibility.
	 * @return string The current David Walsh key.
	 */
	public static function get_davidwalsh( $regenerate = false ) {
		$key_data = self::get_key_data();
		return $key_data['current_key'];
	}
}
