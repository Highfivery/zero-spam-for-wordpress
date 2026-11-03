<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Protection_Controller to this file name.
/**
 * REST route describing what protects the site.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use ZeroSpam\Core\Admin\Shared_IP_Warning;
use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Core\User;
use ZeroSpam\Includes\DB;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * `zero-spam/v1/protection/modules`: every settings section (from
 * Settings_Schema) with whether it is on, whether it needs a key, whether
 * its plugin is active, which of its settings are locked, its last
 * detection and its detections in the last seven days; the integrations
 * whose plugin is not active; and what needs the administrator's attention.
 *
 * Detection counts come from one grouped query, cached for a minute and
 * refreshed at once by a new detection (tagged with the highest log ID).
 *
 * @since 6.0.0
 */
class Protection_Controller extends Base_Controller {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'zero-spam/v1';

	/**
	 * REST API base route.
	 *
	 * @var string
	 */
	protected $rest_base = 'protection/modules';

	/**
	 * Transient caching the detection counts.
	 *
	 * @var string
	 */
	const TRANSIENT = 'zerospam_protection_detections';

	/**
	 * Sections of the entry points every site should check; a site with one
	 * of them off is not fully protected (an attention item says so).
	 *
	 * @var string[]
	 */
	const ENTRY_POINTS = array( 'comments', 'login', 'registration' );

	/**
	 * Log fill (percent) from which it needs attention.
	 *
	 * @var int
	 */
	const LOG_FILL_WARNING = 90;

	/**
	 * The settings that switch each built-in section on. Sections not listed
	 * (third-party ones) use their checkbox settings named after the
	 * section, starting with `verify_` or ending with `_enabled`.
	 *
	 * @var array<string, string[]>
	 */
	const SWITCHES = array(
		'comments'         => array( 'verify_comments' ),
		'registration'     => array( 'verify_registrations' ),
		'login'            => array( 'verify_login' ),
		'davidwalsh'       => array( 'davidwalsh' ),
		'security'         => array( 'disable_xmlrpc', 'hide_wp_version', 'remove_resource_query_parameters', 'disable_emojis', 'disable_rss_feed' ),
		'zerospam'         => array( 'zerospam' ),
		'stop_forum_spam'  => array( 'stop_forum_spam' ),
		'project_honeypot' => array( 'project_honeypot' ),
		'api-monitoring'   => array( 'api_monitoring' ),
		'debug'            => array( 'debug' ),
		'givewp'           => array( 'verify_givewp' ),
		'contactform7'     => array( 'verify_contactform7' ),
		'wpforms'          => array( 'verify_wpforms' ),
		'formidable'       => array( 'verify_formidable' ),
		'fluent_form'      => array( 'verify_fluentforms' ),
		'mailchimp4wp'     => array( 'verify_mailchimp4wp' ),
		'woocommerce'      => array( 'verify_woocommerce_registrations', 'verify_woocommerce_checkout' ),
		'gravityforms'     => array( 'verify_gravityforms' ),
		'wp-user-avatar'   => array( 'verify_wp_user_avatar_registrations' ),
		'elementor'        => array( 'elementor_enabled' ),
	);

	/**
	 * Log types each built-in section records. Other sections: their own key.
	 *
	 * @var array<string, string[]>
	 */
	const LOG_TYPES = array(
		'comments'       => array( 'comment' ),
		'woocommerce'    => array( 'woocommerce_registration', 'woocommerce_checkout' ),
		'wp-user-avatar' => array( 'wp_user_avatar' ),
	);

	/**
	 * Plugins Zero Spam integrates with (see Plugin::init_modules()): section
	 * => plugin files (any of them active) and the plugin's name.
	 *
	 * @var array<string, array{files: string[], name: string}>
	 */
	const INTEGRATIONS = array(
		'givewp'         => array(
			'files' => array( 'give/give.php' ),
			'name'  => 'GiveWP',
		),
		'contactform7'   => array(
			'files' => array( 'contact-form-7/wp-contact-form-7.php' ),
			'name'  => 'Contact Form 7',
		),
		'wpforms'        => array(
			'files' => array( 'wpforms-lite/wpforms.php', 'wpforms/wpforms.php' ),
			'name'  => 'WPForms',
		),
		'formidable'     => array(
			'files' => array( 'formidable/formidable.php' ),
			'name'  => 'Formidable Forms',
		),
		'fluent_form'    => array(
			'files' => array( 'fluentform/fluentform.php' ),
			'name'  => 'Fluent Forms',
		),
		'mailchimp4wp'   => array(
			'files' => array( 'mailchimp-for-wp/mailchimp-for-wp.php' ),
			'name'  => 'Mailchimp for WordPress',
		),
		'woocommerce'    => array(
			'files' => array( 'woocommerce/woocommerce.php' ),
			'name'  => 'WooCommerce',
		),
		'gravityforms'   => array(
			'files' => array( 'gravityforms/gravityforms.php' ),
			'name'  => 'Gravity Forms',
		),
		'wp-user-avatar' => array(
			'files' => array( 'wp-user-avatar/wp-user-avatar.php' ),
			'name'  => 'ProfilePress',
		),
		'elementor'      => array(
			'files' => array( 'elementor-pro/elementor-pro.php' ),
			'name'  => 'Elementor Pro',
		),
	);

	/**
	 * Registers the route.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
				),
			)
		);
	}

	/**
	 * The modules and what needs attention.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$fields = $this->requested_fields( $request );
		$counts = $this->detection_counts();
		$data   = array();

		if ( ! $fields || rest_is_field_included( 'modules', $fields ) || rest_is_field_included( 'attention', $fields ) ) {
			$modules = $this->modules( $counts['types'] );
		} else {
			$modules = array();
		}

		if ( ! $fields || rest_is_field_included( 'modules', $fields ) ) {
			$data['modules'] = $modules;
		}

		if ( ! $fields || rest_is_field_included( 'attention', $fields ) ) {
			$data['attention'] = $this->attention( $modules, $counts['log_count'] );
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Every section, then the integrations whose plugin is not active.
	 *
	 * @param array<string, array{last: string|null, week: int}> $types Detections by log type.
	 * @return array<int, array<string, mixed>>
	 */
	private function modules( array $types ): array {
		$schema     = Settings_Schema::get( false );
		$by_section = array();

		foreach ( $schema['settings'] as $entry ) {
			$by_section[ $entry['section'] ][] = $entry;
		}

		$modules = array();
		$seen    = array();

		foreach ( $schema['sections'] as $section ) {
			$key          = (string) $section['key'];
			$seen[ $key ] = true;
			$settings     = $by_section[ $key ] ?? array();
			$modules[]    = $this->module_entry( $section, $settings, $types );
		}

		foreach ( self::INTEGRATIONS as $key => $integration ) {
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$modules[] = array(
				'key'            => $key,
				'title'          => $integration['name'],
				'area'           => 'protection',
				'group'          => 'integrations',
				'available'      => false,
				'enabled'        => false,
				'switches'       => array(),
				'needs_key'      => false,
				'key_setting'    => null,
				'plugin'         => array(
					'name'   => $integration['name'],
					'active' => false,
				),
				'locked'         => false,
				'locked_keys'    => array(),
				'last_detection' => null,
				'detections_7d'  => 0,
			);
		}

		return $modules;
	}

	/**
	 * One section's entry.
	 *
	 * @param array<string, mixed>                               $section  Section schema entry.
	 * @param array<int, array<string, mixed>>                   $settings Its setting schema entries.
	 * @param array<string, array{last: string|null, week: int}> $types    Detections by log type.
	 * @return array<string, mixed>
	 */
	private function module_entry( array $section, array $settings, array $types ): array {
		$key      = (string) $section['key'];
		$switches = $this->switches( $key, $settings );
		$by_key   = array_column( $settings, null, 'key' );

		$key_setting = null;
		if ( 'intelligence' === $section['group'] ) {
			foreach ( $settings as $entry ) {
				if ( ! empty( $entry['secret'] ) ) {
					$key_setting = (string) $entry['key'];
					break;
				}
			}
		}

		$needs_key = null !== $key_setting && empty( $by_key[ $key_setting ]['value']['set'] );

		if ( $switches ) {
			$enabled = false;
			foreach ( $switches as $switch ) {
				if ( 'enabled' === ( $by_key[ $switch ]['value'] ?? null ) ) {
					$enabled = true;
					break;
				}
			}
		} else {
			// A key-only service (geolocation) is on once its key is set.
			$enabled = null !== $key_setting ? ! $needs_key : null;
		}

		$locked_keys = array();
		foreach ( $settings as $entry ) {
			if ( ! empty( $entry['locked'] ) ) {
				$locked_keys[] = (string) $entry['key'];
			}
		}

		$last = null;
		$week = 0;
		foreach ( self::LOG_TYPES[ $key ] ?? array( $key ) as $type ) {
			if ( ! isset( $types[ $type ] ) ) {
				continue;
			}

			$week += $types[ $type ]['week'];
			if ( null !== $types[ $type ]['last'] && ( null === $last || $types[ $type ]['last'] > $last ) ) {
				$last = $types[ $type ]['last'];
			}
		}

		$plugin = null;
		if ( isset( self::INTEGRATIONS[ $key ] ) ) {
			$plugin = array(
				'name'   => self::INTEGRATIONS[ $key ]['name'],
				'active' => self::plugin_active( $key ),
			);
		}

		return array(
			'key'            => $key,
			'title'          => (string) $section['title'],
			'area'           => (string) $section['area'],
			'group'          => (string) $section['group'],
			'available'      => true,
			'enabled'        => $enabled,
			'switches'       => $switches,
			'needs_key'      => $needs_key,
			'key_setting'    => $key_setting,
			'plugin'         => $plugin,
			'locked'         => (bool) array_intersect( $switches, $locked_keys ),
			'locked_keys'    => $locked_keys,
			'last_detection' => null !== $last ? mysql_to_rfc3339( $last ) : null,
			'detections_7d'  => $week,
		);
	}

	/**
	 * The settings that switch a section on.
	 *
	 * @param string                           $key      Section key.
	 * @param array<int, array<string, mixed>> $settings Its setting schema entries.
	 * @return string[]
	 */
	private function switches( string $key, array $settings ): array {
		$keys = array_map( 'strval', array_column( $settings, 'key' ) );

		if ( isset( self::SWITCHES[ $key ] ) ) {
			return array_values( array_intersect( self::SWITCHES[ $key ], $keys ) );
		}

		$switches = array();
		foreach ( $settings as $entry ) {
			$name = (string) $entry['key'];

			if ( 'checkbox' === $entry['type'] && ( $name === $key || str_starts_with( $name, 'verify_' ) || str_ends_with( $name, '_enabled' ) ) ) {
				$switches[] = $name;
			}
		}

		return $switches;
	}

	/**
	 * What needs attention.
	 *
	 * @param array<int, array<string, mixed>> $modules   Modules.
	 * @param int                              $log_count Rows in the log.
	 * @return array<int, array<string, mixed>>
	 */
	private function attention( array $modules, int $log_count ): array {
		$items = array();

		// The core entry points first: these are the checks every site relies on.
		$texts = array(
			'comments'     => array(
				__( 'Comment protection is off', 'zero-spam' ),
				__( 'Comments posted on your site are not checked for spam. Turn on comment protection.', 'zero-spam' ),
			),
			'login'        => array(
				__( 'Login protection is off', 'zero-spam' ),
				__( 'Login attempts are not checked, so bots can try passwords freely. Turn on login protection.', 'zero-spam' ),
			),
			'registration' => array(
				__( 'Registration protection is off', 'zero-spam' ),
				__( 'New sign-ups are not checked for spam accounts. Turn on registration protection.', 'zero-spam' ),
			),
		);
		foreach ( $modules as $module ) {
			if ( in_array( $module['key'] ?? '', self::ENTRY_POINTS, true ) && false === ( $module['enabled'] ?? null ) ) {
				$items[] = array(
					'id'       => 'entry_point_off:' . $module['key'],
					'severity' => 'warning',
					'title'    => $texts[ $module['key'] ][0],
					'message'  => $texts[ $module['key'] ][1],
					'module'   => $module['key'],
				);
			}
		}

		foreach ( $modules as $module ) {
			if ( empty( $module['available'] ) || empty( $module['plugin']['active'] ) || false !== $module['enabled'] ) {
				continue;
			}

			$items[] = array(
				'id'       => 'integration_unprotected:' . $module['key'],
				'severity' => 'warning',
				/* translators: %s: plugin name, such as WPForms */
				'title'    => sprintf( __( '%s isn\'t protected yet', 'zero-spam' ), $module['plugin']['name'] ),
				/* translators: %s: plugin name, such as WPForms */
				'message'  => sprintf( __( '%s is active, but Zero Spam does not check its forms for spam. Turn on its protection.', 'zero-spam' ), $module['plugin']['name'] ),
				'module'   => $module['key'],
			);
		}

		$max = Stats_Controller::max_logs();
		if ( $max > 0 && $log_count * 100 >= $max * self::LOG_FILL_WARNING ) {
			$percent = (int) min( 100, floor( $log_count / $max * 100 ) );
			$items[] = array(
				'id'       => 'log_fill',
				'severity' => 'info',
				/* translators: %d: percentage */
				'title'    => sprintf( __( 'Activity log is %d%% full', 'zero-spam' ), $percent ),
				/* translators: 1: entries used, 2: maximum entries */
				'message'  => sprintf( __( '%1$s of %2$s entries used. The oldest entries are removed automatically.', 'zero-spam' ), number_format_i18n( $log_count ), number_format_i18n( $max ) ),
				'count'    => $log_count,
				'max'      => $max,
				'percent'  => $percent,
			);
		}

		$verdict = Shared_IP_Warning::get_verdict();
		if ( null !== $verdict && ! Shared_IP_Warning::is_dismissed( $verdict ) ) {
			$items[] = array(
				'id'       => 'shared_ip',
				'severity' => 'warning',
				'title'    => 'trusted_proxy' === $verdict['reason']
					? __( 'A trusted proxy\'s address was logged as a visitor', 'zero-spam' )
					: __( 'Many visitors seem to share one IP address', 'zero-spam' ),
				'message'  => 'trusted_proxy' === $verdict['reason']
					/* translators: %s: IP address */
					? sprintf( __( 'The address %s belongs to a proxy Zero Spam trusts. Check that the proxy forwards visitor addresses (X-Forwarded-For).', 'zero-spam' ), $verdict['ip'] )
					/* translators: %s: IP address */
					: sprintf( __( 'The latest detections all came from %s. Your site is probably behind a proxy or CDN Zero Spam does not know about, and every visitor could be blocked together. Add it to the trusted proxies.', 'zero-spam' ), $verdict['ip'] ),
				'ip'       => $verdict['ip'],
				'reason'   => $verdict['reason'],
			);
		}

		if ( self::untrusted_proxy_request() ) {
			$items[] = array(
				'id'       => 'proxy_untrusted',
				'severity' => 'warning',
				'title'    => __( 'Your site is reached through a proxy Zero Spam does not trust', 'zero-spam' ),
				/* translators: %s: IP address */
				'message'  => sprintf( __( 'This request came through %s with a forwarding header, but that address is not a trusted proxy, so visitors may all get its address. If it is your CDN or load balancer, add it to the trusted proxies.', 'zero-spam' ), User::get_remote_addr() ),
				'ip'       => User::get_remote_addr(),
			);
		}

		return $items;
	}

	/**
	 * Detections by log type (last one and the last seven days) and the rows
	 * in the log, from one grouped query and one count, cached.
	 *
	 * @return array{types: array<string, array{last: string|null, week: int}>, log_count: int}
	 */
	private function detection_counts(): array {
		global $wpdb;

		$tag    = Stats_Controller::max_log_id();
		$cached = get_transient( self::TRANSIENT );

		if ( is_array( $cached ) && isset( $cached['tag'], $cached['types'], $cached['log_count'] ) && $cached['tag'] === $tag ) {
			return array(
				'types'     => (array) $cached['types'],
				'log_count' => (int) $cached['log_count'],
			);
		}

		$table = $wpdb->prefix . DB::$tables['log'];
		$since = gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - WEEK_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- The log stores site-local times.

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Grouped counts of the plugin's own table, cached above.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT `log_type`, MAX(`date_recorded`) AS last, SUM(`date_recorded` >= %s) AS week, COUNT(*) AS total FROM %i GROUP BY `log_type` LIMIT 500',
				$since,
				$table
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$types     = array();
		$log_count = 0;
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$types[ (string) $row['log_type'] ] = array(
				'last' => null !== $row['last'] ? (string) $row['last'] : null,
				'week' => (int) $row['week'],
			);
			$log_count                         += (int) $row['total'];
		}

		set_transient(
			self::TRANSIENT,
			array(
				'tag'       => $tag,
				'types'     => $types,
				'log_count' => $log_count,
			),
			MINUTE_IN_SECONDS
		);

		return array(
			'types'     => $types,
			'log_count' => $log_count,
		);
	}

	/**
	 * Whether an integration's plugin is active.
	 *
	 * @param string $key Section key.
	 */
	private static function plugin_active( string $key ): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( self::INTEGRATIONS[ $key ]['files'] ?? array() as $file ) {
			if ( is_plugin_active( $file ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether this request came through a proxy that is not trusted: it
	 * carries a forwarding header but its address is not a trusted proxy.
	 */
	private static function untrusted_proxy_request(): bool {
		$remote = User::get_remote_addr();

		if ( '' === $remote || User::is_trusted_proxy( $remote ) ) {
			return false;
		}

		foreach ( User::PROXY_HEADERS as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				return true;
			}
		}

		return false;
	}
}
