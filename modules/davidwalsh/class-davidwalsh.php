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
 * - Signed, per-visitor tokens (HMAC with a site secret) instead of one shared key
 * - Tokens expire after TOKEN_TTL and are only accepted MAX_USES times, so a token
 *   fetched once can't be replayed for bulk submissions
 * - REST API (with admin-ajax fallback) to fetch a fresh token on cached pages
 * - MutationObserver support for dynamically loaded forms
 * - Centralized selector management via filter
 */
class DavidWalsh {

	/**
	 * Option name for the legacy shared key data (before 5.7.12).
	 *
	 * Only read during the upgrade grace period, see validate_legacy_key().
	 *
	 * @var string
	 */
	const OPTION_NAME = 'zerospam_davidwalsh_data';

	/**
	 * Option name for the secret used to sign tokens.
	 *
	 * @var string
	 */
	const SECRET_OPTION = 'zerospam_davidwalsh_secret';

	/**
	 * Legacy cron hook name for key rotation (no longer scheduled).
	 *
	 * @var string
	 */
	const CRON_HOOK = 'zerospam_davidwalsh_rotate_key';

	/**
	 * Legacy key length in characters.
	 *
	 * @var int
	 */
	const KEY_LENGTH = 16;

	/**
	 * Token lifetime in seconds (12 hours).
	 *
	 * @var int
	 */
	const KEY_TTL = 43200;

	/**
	 * How many submissions a single token is accepted for.
	 *
	 * More than one so AJAX forms can be resubmitted after a validation
	 * error without reloading the page.
	 *
	 * @var int
	 */
	const MAX_USES = 3;

	/**
	 * How long legacy shared keys stay valid after upgrading, so pages
	 * cached before the update keep working.
	 *
	 * @var int
	 */
	const LEGACY_GRACE = DAY_IN_SECONDS;

	/**
	 * Admin-ajax action for fetching a token (fallback when REST is blocked).
	 *
	 * @var string
	 */
	const AJAX_ACTION = 'zerospam_davidwalsh_token';

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
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_get_key' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'ajax_get_key' ) );

		register_deactivation_hook( ZEROSPAM, array( __CLASS__, 'unschedule_cron' ) );
	}

	/**
	 * Fires after WordPress has finished loading but before any headers are sent.
	 */
	public function init() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ), 10, 1 );
		add_filter( 'zerospam_failed_types', array( $this, 'failed_types' ), 10, 1 );

		// Keys are no longer rotated by cron (tokens carry their own expiry).
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			self::unschedule_cron();
		}

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
			add_filter( 'zerospam_preprocess_woocommerce_registration', array( $this, 'validate_post' ), 10, 3 );
			add_filter( 'zerospam_preprocess_woocommerce_checkout', array( $this, 'validate_post' ), 10, 3 );
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
	 * REST API callback for getting a fresh David Walsh token.
	 *
	 * Public by design: the token is what the page's JavaScript adds to forms,
	 * so it's no more secret than the page itself. Each call returns a new
	 * token that expires and is only accepted MAX_USES times — there is no
	 * shared site-wide key to leak.
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_get_key() {
		$response = new \WP_REST_Response( self::get_token_response(), 200 );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );

		return $response;
	}

	/**
	 * Admin-ajax callback for getting a fresh David Walsh token.
	 *
	 * Fallback for sites where the REST API is blocked for visitors.
	 */
	public function ajax_get_key() {
		nocache_headers();
		wp_send_json( self::get_token_response() );
	}

	/**
	 * Builds the token payload returned to the browser.
	 *
	 * @return array
	 */
	public static function get_token_response() {
		$token = self::generate_token();

		return array(
			'key'       => $token,
			'generated' => (int) self::get_token_time( $token ),
			'ttl'       => (int) self::KEY_TTL,
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
	 * Accepts a valid, unexpired token that hasn't been used up.
	 *
	 * @param array  $errors            Array of submission errors.
	 * @param array  $post              Form post array.
	 * @param string $detection_msg_key Detection message key.
	 * @return array Modified errors array.
	 */
	public function validate_post( $errors, $post, $detection_msg_key ) {
		$submitted_key = isset( $post['zerospam_david_walsh_key'] ) && is_string( $post['zerospam_david_walsh_key'] ) ? sanitize_text_field( $post['zerospam_david_walsh_key'] ) : '';

		if ( ! self::validate_token( $submitted_key ) ) {
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

		$options = get_option( 'zero-spam-davidwalsh', array() );

		// How It Works.
		$how_it_works_features = array(
			esc_html__( 'Works invisibly — no CAPTCHAs or puzzles for your users.', 'zero-spam' ),
			esc_html__( 'Automatically protects comments, registrations, logins, and supported plugins.', 'zero-spam' ),
			esc_html__( 'Compatible with page caching — visitors get a fresh, short-lived key automatically.', 'zero-spam' ),
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

		// Key Status.
		$ttl_hours = (int) ( self::KEY_TTL / HOUR_IN_SECONDS );

		$settings['davidwalsh_status'] = array(
			'title'   => __( 'Security Key Status', 'zero-spam' ),
			'desc'    => '',
			'section' => 'davidwalsh',
			'module'  => 'davidwalsh',
			'type'    => 'html',
			'html'    => esc_html(
				sprintf(
					/* translators: 1: number of hours a key is valid for, 2: number of submissions a key can be used for. */
					__( 'Each visitor gets their own signed security key. A key expires after %1$d hours and can be used for up to %2$d submissions, so a key copied from your site can\'t be reused to send spam in bulk.', 'zero-spam' ),
					$ttl_hours,
					self::MAX_USES
				)
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
		wp_register_script(
			'zerospam-davidwalsh',
			plugin_dir_url( ZEROSPAM ) . 'modules/davidwalsh/assets/js/davidwalsh.js',
			array(),
			ZEROSPAM_VERSION,
			true
		);

		// Get all selectors via centralized method.
		$selectors = self::get_all_selectors();

		// Pass data to the script.
		wp_localize_script(
			'zerospam-davidwalsh',
			'ZeroSpamDavidWalsh',
			array_merge(
				self::get_token_response(),
				array(
					'selectors' => implode( ', ', (array) $selectors ),
					'restUrl'   => rest_url( self::REST_NAMESPACE . '/davidwalsh-key' ),
					'ajaxUrl'   => add_query_arg( 'action', self::AJAX_ACTION, admin_url( 'admin-ajax.php' ) ),
				)
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
	 * Gets (creating if needed) the secret used to sign tokens.
	 *
	 * @return string
	 */
	public static function get_secret() {
		$secret = get_option( self::SECRET_OPTION );

		if ( empty( $secret ) || ! is_string( $secret ) ) {
			$secret = wp_generate_password( 64, true, true );
			update_option( self::SECRET_OPTION, $secret, true );
		}

		return $secret;
	}

	/**
	 * Signs a token payload.
	 *
	 * @param string $payload Token payload ("{issued}.{random}").
	 * @return string
	 */
	private static function sign( $payload ) {
		return substr( hash_hmac( 'sha256', $payload, self::get_secret() ), 0, 32 );
	}

	/**
	 * Generates a new signed token.
	 *
	 * Format: "{issued time, base 36}.{random}.{signature}".
	 *
	 * @return string
	 */
	public static function generate_token() {
		$payload = base_convert( (string) time(), 10, 36 ) . '.' . wp_generate_password( 12, false, false );

		return $payload . '.' . self::sign( $payload );
	}

	/**
	 * Gets the issue time of a token.
	 *
	 * @param string $token Token.
	 * @return int Unix timestamp, or 0 if the token is malformed.
	 */
	public static function get_token_time( $token ) {
		if ( ! is_string( $token ) || ! preg_match( '/^([0-9a-z]{1,10})\.([0-9A-Za-z]{12})\.([0-9a-f]{32})$/', $token, $parts ) ) {
			return 0;
		}

		return (int) base_convert( $parts[1], 36, 10 );
	}

	/**
	 * Checks a submitted token: signature, expiry and number of uses.
	 *
	 * Each successful check counts as one use of the token (counted once per
	 * request, even if several integrations validate the same submission).
	 *
	 * @param string $token Submitted token.
	 * @return bool
	 */
	public static function validate_token( $token ) {
		static $accepted = array();

		if ( empty( $token ) || ! is_string( $token ) ) {
			return false;
		}

		if ( isset( $accepted[ $token ] ) ) {
			return true;
		}

		$issued = self::get_token_time( $token );
		if ( ! $issued ) {
			return self::validate_legacy_key( $token );
		}

		// Allow a minute of clock skew between web servers.
		$age = time() - $issued;
		if ( $age < -60 || $age > self::KEY_TTL ) {
			return false;
		}

		$last_dot = strrpos( $token, '.' );
		$payload  = substr( $token, 0, $last_dot );
		if ( ! hash_equals( self::sign( $payload ), substr( $token, $last_dot + 1 ) ) ) {
			return false;
		}

		$uses_key = 'zerospam_dw_' . md5( $token );
		$uses     = (int) get_transient( $uses_key );
		if ( $uses >= self::MAX_USES ) {
			return false;
		}

		set_transient( $uses_key, $uses + 1, self::KEY_TTL + MINUTE_IN_SECONDS );
		$accepted[ $token ] = true;

		return true;
	}

	/**
	 * Accepts the pre-5.7.12 shared keys for a short time after upgrading,
	 * so pages cached before the update don't block visitors.
	 *
	 * @param string $key Submitted key.
	 * @return bool
	 */
	private static function validate_legacy_key( $key ) {
		$key_data = get_option( self::OPTION_NAME );
		if ( ! is_array( $key_data ) ) {
			return false;
		}

		// The grace period starts when the plugin is updated (see Migrations).
		if ( empty( $key_data['grace_until'] ) ) {
			return false;
		}

		if ( time() > (int) $key_data['grace_until'] ) {
			delete_option( self::OPTION_NAME );
			return false;
		}

		$valid_keys = array_filter(
			array(
				isset( $key_data['current_key'] ) ? (string) $key_data['current_key'] : '',
				isset( $key_data['previous_key'] ) ? (string) $key_data['previous_key'] : '',
			)
		);

		foreach ( $valid_keys as $valid_key ) {
			if ( hash_equals( $valid_key, $key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Starts the grace period for the pre-5.7.12 shared keys. Runs once on update.
	 *
	 * @return true
	 */
	public static function start_legacy_grace_period() {
		self::unschedule_cron();

		$key_data = get_option( self::OPTION_NAME );
		if ( is_array( $key_data ) ) {
			$key_data['grace_until'] = time() + self::LEGACY_GRACE;
			update_option( self::OPTION_NAME, $key_data, false );
		}

		return true;
	}

	/**
	 * Legacy: rotating keys is no longer needed (tokens carry their own expiry).
	 *
	 * @deprecated 5.7.12
	 */
	public static function rotate_key() {
		self::unschedule_cron();
	}

	/**
	 * Legacy: no cron is scheduled anymore.
	 *
	 * @deprecated 5.7.12
	 */
	public static function schedule_cron() {}

	/**
	 * Legacy: no cron is scheduled anymore.
	 *
	 * @deprecated 5.7.12
	 */
	public static function maybe_schedule_cron() {}

	/**
	 * Removes the legacy key rotation cron event.
	 */
	public static function unschedule_cron() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Legacy method for backward compatibility.
	 *
	 * @deprecated Use generate_token() instead.
	 *
	 * @param bool $regenerate Unused parameter, kept for compatibility.
	 * @return string A new David Walsh token.
	 */
	public static function get_davidwalsh( $regenerate = false ) {
		return self::generate_token();
	}
}
