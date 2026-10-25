<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Dashboard_Widget to this file name.
/**
 * Zero Spam dashboard widgets (site and network).
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Core\Admin;

use ZeroSpam\Core\License_Status;
use ZeroSpam\Core\Settings as Core_Settings;
use ZeroSpam\Core\Utilities;
use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Rest\Stats_Controller;
use ZeroSpam\Includes\Rest\UI_Prefs_Controller;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * The "Zero Spam overview" widget on the site dashboard and the "Zero Spam
 * network overview" widget on the network dashboard (6.0 design).
 *
 * Rendered in PHP (includes/templates/dashboard-widget.php): the protection
 * level, detections in the last 30 days with the last 7 days against the 7
 * before, a server-rendered sparkline, and the latest detections (site) or the
 * sites with the most spam (network). On the site widget, administrators get a
 * Refresh button: assets/src/widget-refresh (plain JavaScript, no WordPress
 * dependencies) reads zero-spam/v1/stats/timeseries and zero-spam/v1/activity
 * and announces the result in a polite live region. No Chart.js, no jQuery.
 *
 * Who sees it is unchanged: the widget_enabled and widget_visibility settings
 * and the zerospam_dashboard_widget_visible filter.
 */
class Dashboard_Widget {

	/**
	 * Dashboard widget ID (kept from 5.x so saved positions and screen options stay).
	 *
	 * @var string
	 */
	const WIDGET_ID = 'zerospam_unified_widget';

	/**
	 * Script and style handle of the built assets/dist/widget-refresh.*.
	 *
	 * @var string
	 */
	const HANDLE = 'zerospam-widget';

	/**
	 * Transient holding the network widget's data.
	 *
	 * @var string
	 */
	const NETWORK_TRANSIENT = 'zerospam_dashboard_data_network';

	/**
	 * Transient of the 5.x site widget, still cleared for old installs.
	 *
	 * @var string
	 */
	const LEGACY_SITE_TRANSIENT = 'zerospam_dashboard_data_site';

	/**
	 * Days in the sparkline and the headline figure.
	 *
	 * @var int
	 */
	const DAYS = 30;

	/**
	 * Latest detections listed on the site widget.
	 *
	 * @var int
	 */
	const LATEST = 3;

	/**
	 * Sites listed on the network widget.
	 *
	 * @var int
	 */
	const TOP_SITES = 5;

	/**
	 * Most site log tables the network widget reads.
	 *
	 * @var int
	 */
	const MAX_SITES = 1000;

	/**
	 * Whether the current user may see the widget, decided once per request.
	 *
	 * @var bool|null
	 */
	private ?bool $access = null;

	/**
	 * Whether the deprecated AJAX handler has warned in this request.
	 *
	 * @var bool
	 */
	private static bool $ajax_warned = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ), 10 );
		add_action( 'wp_network_dashboard_setup', array( $this, 'register_widget' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Deprecated since 6.0.0 (build plan C10): the widget refreshes through the REST API.
		add_action( 'wp_ajax_zerospam_refresh_dashboard', array( $this, 'ajax_refresh_data' ) );

		// Settings changes (visibility, Enhanced Protection) show at once.
		add_action( 'update_option_zero-spam-settings', array( $this, 'clear_widget_cache' ) );
	}

	/**
	 * Determine if the current user has access to the dashboard widget.
	 *
	 * Checks the widget_enabled toggle first, then verifies the current user's
	 * roles against the widget_visibility setting. Super admins on multisite
	 * always have access when the widget is enabled.
	 *
	 * @since 5.7.9
	 *
	 * @return bool Whether the current user can see the widget.
	 */
	private function has_widget_access(): bool {
		if ( null === $this->access ) {
			$this->access = $this->check_widget_access();
		}

		return $this->access;
	}

	/**
	 * The access rules (see has_widget_access()).
	 */
	private function check_widget_access(): bool {
		/**
		 * Filters whether the dashboard widget is visible to the current user.
		 *
		 * Returning a non-null value from this filter will short-circuit
		 * the built-in role checks entirely.
		 *
		 * @since 5.7.9
		 *
		 * @param bool|null $has_access Null to use default logic, or bool to override.
		 */
		$filtered = apply_filters( 'zerospam_dashboard_widget_visible', null );

		if ( null !== $filtered ) {
			return (bool) $filtered;
		}

		$settings = Core_Settings::get_settings();

		// Check master toggle first.
		if ( empty( $settings['widget_enabled']['value'] ) || 'enabled' !== $settings['widget_enabled']['value'] ) {
			return false;
		}

		// Super admins always have access when widget is enabled.
		if ( is_multisite() && is_super_admin() ) {
			return true;
		}

		// Determine visible roles.
		$visible_roles = $this->get_visible_roles( $settings );

		if ( empty( $visible_roles ) ) {
			return false;
		}

		$user = wp_get_current_user();

		if ( empty( $user->roles ) || ! is_array( $user->roles ) ) {
			return false;
		}

		return ! empty( array_intersect( $visible_roles, $user->roles ) );
	}

	/**
	 * Parse the widget_visibility setting into a clean array of role slugs.
	 *
	 * @since 5.7.9
	 *
	 * @param array<string, mixed> $settings Full plugin settings array.
	 * @return array<int|string, mixed> Role slugs that should see the widget.
	 */
	private function get_visible_roles( array $settings ): array {
		// Default to administrator when setting has never been configured.
		$default = array( 'administrator' );

		if ( ! isset( $settings['widget_visibility']['value'] ) ) {
			return $default;
		}

		$value = $settings['widget_visibility']['value'];

		// Explicitly saved empty array means "no roles" — hide from everyone.
		if ( is_array( $value ) ) {
			return $value;
		}

		// Single string value.
		if ( is_string( $value ) && '' !== $value ) {
			return array( $value );
		}

		// Empty string means the user cleared all selections — hide from everyone.
		if ( '' === $value ) {
			return array();
		}

		// false (never configured) or anything else: the default.
		return $default;
	}

	/**
	 * Whether the current screen is the network dashboard.
	 */
	private function is_network(): bool {
		return is_multisite() && is_network_admin();
	}

	/**
	 * Register the dashboard widget.
	 */
	public function register_widget(): void {
		if ( ! $this->has_widget_access() ) {
			return;
		}

		$is_network = $this->is_network();
		$name       = $is_network ? __( 'Zero Spam network overview', 'zero-spam' ) : __( 'Zero Spam overview', 'zero-spam' );

		// The postbox title (and its Screen Options label) carries the flat mark, hidden from assistive technology.
		$title = '<span class="zs-w-title">'
			. Icons::svg( 'zs-mark-flat', 'zs-w-mark', 24, 23 )
			. '<span>' . esc_html( $name ) . '</span></span>';

		wp_add_dashboard_widget( self::WIDGET_ID, $title, array( $this, 'render_widget' ) );
	}

	/**
	 * Enqueue widget assets: only on the dashboard (site or network), only
	 * when the widget is shown, and the script only for users who get the
	 * Refresh button.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ): void {
		if ( 'index.php' !== $hook || ! $this->has_widget_access() ) {
			return;
		}

		$asset = self::asset();

		wp_enqueue_style(
			self::HANDLE,
			plugins_url( 'assets/dist/widget-refresh.css', ZEROSPAM ),
			array(),
			$asset['version']
		);
		wp_style_add_data( self::HANDLE, 'rtl', 'replace' );

		if ( ! $this->can_refresh() ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			plugins_url( 'assets/dist/widget-refresh.js', ZEROSPAM ),
			$asset['dependencies'],
			$asset['version'],
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_localize_script( self::HANDLE, 'zerospamWidget', $this->script_config() );
	}

	/**
	 * Dependencies and version of the built widget assets (assets/dist/widget-refresh.asset.php).
	 *
	 * @return array{dependencies: string[], version: string}
	 */
	public static function asset(): array {
		$file  = ZEROSPAM_PATH . 'assets/dist/widget-refresh.asset.php';
		$asset = is_readable( $file ) ? require $file : array();

		return array(
			'dependencies' => isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? array_values( array_map( 'strval', $asset['dependencies'] ) ) : array(),
			'version'      => isset( $asset['version'] ) && is_string( $asset['version'] ) ? $asset['version'] : ZEROSPAM_VERSION,
		);
	}

	/**
	 * Whether the current user gets the Refresh button: the site widget, for
	 * users the stats and activity routes answer (manage_options).
	 */
	private function can_refresh(): bool {
		return ! $this->is_network() && current_user_can( 'manage_options' );
	}

	/**
	 * What widget-refresh.js needs: the REST root and nonce, the log types
	 * that are not detections, the user's locale and the translated strings.
	 *
	 * @return array<string, mixed>
	 */
	private function script_config(): array {
		return array(
			'root'   => esc_url_raw( rest_url( 'zero-spam/v1/' ) ),
			'nonce'  => wp_create_nonce( 'wp_rest' ),
			'skip'   => DB::NON_DETECTION_TYPES,
			'days'   => self::DAYS,
			'latest' => self::LATEST,
			'locale' => str_replace( '_', '-', get_user_locale() ),
			'i18n'   => self::strings(),
		);
	}

	/**
	 * Strings shared by the template and the script, so a refreshed widget
	 * reads exactly like the one the server rendered.
	 *
	 * @return array<string, string>
	 */
	public static function strings(): array {
		return array(
			/* translators: 1: detections in the last 7 days, 2: how many more, as a percentage ("12%") or a number */
			'deltaUp'    => __( 'Last 7 days: %1$s, up %2$s from the 7 days before.', 'zero-spam' ),
			/* translators: 1: detections in the last 7 days, 2: how many fewer, as a percentage ("12%") or a number */
			'deltaDown'  => __( 'Last 7 days: %1$s, down %2$s from the 7 days before.', 'zero-spam' ),
			/* translators: %s: detections in the last 7 days */
			'deltaSame'  => __( 'Last 7 days: %s, the same as the 7 days before.', 'zero-spam' ),
			/* translators: %s: a whole number, for example 12 in "12%" */
			'percent'    => __( '%s%%', 'zero-spam' ),
			/* translators: 1: most detections on one day, 2: detections today */
			'spark'      => __( 'Spam blocked per day over the last 30 days: at most %1$s on one day, %2$s today.', 'zero-spam' ),
			'empty'      => __( 'No spam detected yet. Your site is protected; blocked spam will appear here.', 'zero-spam' ),
			'refreshing' => __( 'Refreshing…', 'zero-spam' ),
			'refreshed'  => __( 'Data refreshed.', 'zero-spam' ),
			'failed'     => __( 'Couldn’t refresh the data. Check your connection and try again.', 'zero-spam' ),
		);
	}

	/**
	 * Deprecated AJAX refresh (`wp_ajax_zerospam_refresh_dashboard`).
	 *
	 * The widget refreshes through zero-spam/v1/stats/timeseries and
	 * zero-spam/v1/activity since 6.0.0. The action stays registered for
	 * anything that still calls it (build plan C10): it answers with the
	 * widget's data and reports the deprecation once per request.
	 *
	 * @deprecated 6.0.0 Use the zero-spam/v1/stats/timeseries REST route.
	 */
	public function ajax_refresh_data(): void {
		if ( ! self::$ajax_warned ) {
			self::$ajax_warned = true;
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'The zerospam_refresh_dashboard AJAX action is deprecated; use the zero-spam/v1/stats/timeseries REST route.', 'zero-spam' ),
				'6.0.0'
			);
		}

		check_ajax_referer( 'zerospam_dashboard_refresh', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'zero-spam' ) ) );
		}

		$this->clear_widget_cache();

		// Network data only for network administrators: the client's flag alone is never trusted.
		$is_network = is_multisite()
			&& ! empty( $_POST['is_network'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by check_ajax_referer() above.
			&& current_user_can( 'manage_network_options' );

		wp_send_json_success(
			array(
				'data'    => $is_network ? $this->network_data() : $this->site_data(),
				'message' => __( 'Dashboard data refreshed.', 'zero-spam' ),
			)
		);
	}

	/**
	 * Clear widget data transients when settings change.
	 *
	 * Hooked to `update_option_zero-spam-settings` so visibility and other
	 * setting changes take effect immediately.
	 *
	 * @since 5.7.9
	 */
	public function clear_widget_cache(): void {
		delete_transient( self::LEGACY_SITE_TRANSIENT );
		delete_transient( self::NETWORK_TRANSIENT );
	}

	/**
	 * Render the dashboard widget.
	 */
	public function render_widget(): void {
		try {
			$is_network = $this->is_network();
			$prefs      = UI_Prefs_Controller::for_user( get_current_user_id() );
			$data       = $is_network ? $this->network_data() : $this->site_data();

			$this->load_template(
				ZEROSPAM_PATH . 'includes/templates/dashboard-widget.php',
				array(
					'scope'        => $is_network ? 'network' : 'site',
					'theme'        => Utilities::resolved_theme( 'widget' ),
					'reduce'       => ! empty( $prefs['reduce_motion'] ),
					'level'        => self::level(),
					'data'         => $data,
					'figures'      => self::figures( $data['days'] ),
					'spark'        => Sparkline::paths( $data['days'] ),
					'strings'      => self::strings(),
					'activity_url' => current_user_can( $is_network ? 'manage_network_options' : 'manage_options' ) ? Utilities::activity_url( $is_network ) : '',
					'can_refresh'  => $this->can_refresh(),
				)
			);
		} catch ( \Throwable $e ) {
			echo '<div class="zs-widget"><p class="zs-w-error">';
			esc_html_e( 'The Zero Spam dashboard widget couldn’t load. Refresh the page; if it keeps happening, check Settings › Error log.', 'zero-spam' );
			echo '</p></div>';
		}
	}

	/**
	 * Includes the template with its arguments.
	 *
	 * @param string               $path Template file.
	 * @param array<string, mixed> $args Arguments, read by the template as $args.
	 */
	private function load_template( string $path, array $args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $args is read by the included template.
		include $path;
	}

	/**
	 * The protection level line: Basic, or Enhanced with the license status
	 * (License_Status, the same answer as the header and the notices).
	 *
	 * @return array{level: string, tone: string, text: string}
	 */
	public static function level(): array {
		if ( ! License_Status::is_enabled() || ! License_Status::has_key() ) {
			return array(
				'level' => 'basic',
				'tone'  => 'quiet',
				'text'  => __( 'Basic', 'zero-spam' ),
			);
		}

		switch ( License_Status::get()['status'] ) {
			case License_Status::VALID:
				return array(
					'level' => 'enhanced',
					'tone'  => 'ok',
					'text'  => __( 'Enhanced · Active', 'zero-spam' ),
				);
			case License_Status::INVALID:
				return array(
					'level' => 'enhanced',
					'tone'  => 'warn',
					'text'  => __( 'Enhanced · License not valid', 'zero-spam' ),
				);
			case License_Status::QUOTA_EXCEEDED:
				return array(
					'level' => 'enhanced',
					'tone'  => 'warn',
					'text'  => __( 'Enhanced · Query limit reached', 'zero-spam' ),
				);
			default:
				return array(
					'level' => 'enhanced',
					'tone'  => 'quiet',
					'text'  => __( 'Enhanced · Couldn’t verify the license right now', 'zero-spam' ),
				);
		}
	}

	/**
	 * The headline figures from daily totals (oldest first): the total, the
	 * last 7 days and the 7 days before. widget-refresh's figures() is the same
	 * calculation.
	 *
	 * @param array<int|string> $days Detections per day, oldest first.
	 * @return array{total: int, week: int, previous: int, delta: int, percent: int|null}
	 */
	public static function figures( array $days ): array {
		$days     = array_map( 'intval', array_values( $days ) );
		$week     = array_sum( array_slice( $days, -7 ) );
		$previous = count( $days ) > 7 ? array_sum( array_slice( $days, max( 0, count( $days ) - 14 ), min( 7, count( $days ) - 7 ) ) ) : 0;
		$delta    = $week - $previous;

		return array(
			'total'    => array_sum( $days ),
			'week'     => $week,
			'previous' => $previous,
			'delta'    => $delta,
			'percent'  => $previous > 0 ? (int) round( abs( $delta ) / $previous * 100 ) : null,
		);
	}

	/**
	 * The "Last 7 days" sentence and its direction (up, down or same).
	 *
	 * @param array{total: int, week: int, previous: int, delta: int, percent: int|null} $figures Figures (figures()).
	 * @return array{text: string, direction: string}
	 */
	public static function delta_text( array $figures ): array {
		$strings = self::strings();
		$week    = number_format_i18n( $figures['week'] );

		if ( 0 === $figures['delta'] ) {
			return array(
				'text'      => sprintf( $strings['deltaSame'], $week ),
				'direction' => 'same',
			);
		}

		$amount = null === $figures['percent']
			? number_format_i18n( abs( $figures['delta'] ) )
			: sprintf( $strings['percent'], number_format_i18n( $figures['percent'] ) );
		$up     = $figures['delta'] > 0;

		return array(
			'text'      => sprintf( $up ? $strings['deltaUp'] : $strings['deltaDown'], $week, $amount ),
			'direction' => $up ? 'up' : 'down',
		);
	}

	/**
	 * The sparkline's text alternative.
	 *
	 * @param array<int|string> $days Detections per day, oldest first.
	 */
	public static function spark_text( array $days ): string {
		$days = array_map( 'intval', array_values( $days ) );

		return sprintf(
			self::strings()['spark'],
			number_format_i18n( $days ? max( $days ) : 0 ),
			number_format_i18n( $days ? (int) end( $days ) : 0 )
		);
	}

	/**
	 * The site widget's data: daily detections for 30 days (the
	 * stats/timeseries cache, tagged with the highest log ID, so a new
	 * detection shows at once) and the latest detections.
	 *
	 * @return array{table_missing: bool, days: int[], latest: array<int, array{type: string, label: string, ip: string, date_gmt: string, time: int}>}
	 */
	private function site_data(): array {
		global $wpdb;

		$table = $wpdb->prefix . DB::$tables['log'];

		if ( ! $this->table_exists( $table ) ) {
			return array(
				'table_missing' => true,
				'days'          => array_fill( 0, self::DAYS, 0 ),
				'latest'        => array(),
			);
		}

		$series = ( new Stats_Controller() )->timeseries_data( self::DAYS );

		return array(
			'table_missing' => false,
			'days'          => array_map( 'intval', array_column( $series['days'], 'total' ) ),
			'latest'        => $this->latest_detections( $table ),
		);
	}

	/**
	 * The newest detections: one primary-key read.
	 *
	 * @param string $table Log table.
	 * @return array<int, array{type: string, label: string, ip: string, date_gmt: string, time: int}>
	 */
	private function latest_detections( string $table ): array {
		global $wpdb;

		$detections = DB::detections_sql();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $detections is prepared by DB::detections_sql(); a LIMIT 3 read of the plugin's own table by primary key.
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT log_type, user_ip, date_recorded FROM %i WHERE {$detections} ORDER BY log_id DESC LIMIT %d", $table, self::LATEST ), ARRAY_A );
		$labels = Stats_Controller::type_labels();
		$items  = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$type     = (string) $row['log_type'];
			$date_gmt = get_gmt_from_date( (string) $row['date_recorded'] );
			$items[]  = array(
				'type'     => $type,
				'label'    => $labels[ $type ] ?? $type,
				'ip'       => (string) $row['user_ip'],
				'date_gmt' => mysql_to_rfc3339( $date_gmt ),
				'time'     => (int) strtotime( $date_gmt . ' UTC' ),
			);
		}

		return $items;
	}

	/**
	 * The network widget's data: daily detections for 30 days summed over
	 * every site's log table, the number of sites and the sites with the most
	 * detections. Two queries (the table list and one UNION ALL), cached for
	 * five minutes.
	 *
	 * Each site logs to its own table, so the 5.x widget, which read only the
	 * main site's table, missed every other site.
	 *
	 * @return array{table_missing: bool, days: int[], sites: int, top_sites: array<int, array{name: string, url: string, count: int}>}
	 */
	private function network_data(): array {
		$cached = get_transient( self::NETWORK_TRANSIENT );

		if ( is_array( $cached ) && isset( $cached['days'], $cached['sites'], $cached['top_sites'], $cached['table_missing'] ) && is_array( $cached['days'] ) ) {
			return $cached;
		}

		$data = $this->read_network_data();

		set_transient( self::NETWORK_TRANSIENT, $data, 5 * MINUTE_IN_SECONDS );

		return $data;
	}

	/**
	 * Reads the network widget's data (network_data()).
	 *
	 * @return array{table_missing: bool, days: int[], sites: int, top_sites: array<int, array{name: string, url: string, count: int}>}
	 */
	private function read_network_data(): array {
		global $wpdb;

		$tables = $this->network_log_tables();
		$now    = (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- The logs store site-local times.
		$days   = array();

		for ( $i = self::DAYS - 1; $i >= 0; $i-- ) {
			$days[ gmdate( 'Y-m-d', $now - $i * DAY_IN_SECONDS ) ] = 0;
		}

		$data = array(
			'table_missing' => ! $tables,
			'days'          => array_values( $days ),
			'sites'         => (int) get_blog_count(),
			'top_sites'     => array(),
		);

		if ( ! $tables ) {
			return $data;
		}

		$since      = array_key_first( $days ) . ' 00:00:00';
		$detections = DB::detections_sql();
		$selects    = array();

		foreach ( $tables as $blog_id => $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $detections is prepared by DB::detections_sql().
			$selects[] = $wpdb->prepare( "(SELECT %d AS blog_id, DATE(date_recorded) AS day, COUNT(*) AS n FROM %i WHERE date_recorded >= %s AND {$detections} GROUP BY day)", $blog_id, $table, $since );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Every part is prepared above; at most 31 rows per site, cached in a transient.
		$rows = $wpdb->get_results( implode( ' UNION ALL ', $selects ), ARRAY_A );

		$per_site = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$day = (string) $row['day'];

			if ( ! isset( $days[ $day ] ) ) {
				continue;
			}

			$days[ $day ]                     += (int) $row['n'];
			$per_site[ (int) $row['blog_id'] ] = ( $per_site[ (int) $row['blog_id'] ] ?? 0 ) + (int) $row['n'];
		}

		arsort( $per_site );

		foreach ( array_slice( $per_site, 0, self::TOP_SITES, true ) as $blog_id => $count ) {
			$details             = get_blog_details( $blog_id );
			$data['top_sites'][] = array(
				'name'  => $details ? (string) $details->blogname : __( 'Unknown site', 'zero-spam' ),
				'url'   => $details ? get_admin_url( $blog_id ) : '',
				'count' => $count,
			);
		}

		$data['days'] = array_values( $days );

		return $data;
	}

	/**
	 * Every site's log table, by blog ID (at most MAX_SITES, lowest IDs first).
	 *
	 * @return array<int, string>
	 */
	private function network_log_tables(): array {
		global $wpdb;

		$suffix = DB::$tables['log'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lists the plugin's own tables; cached with the widget data.
		$names = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->base_prefix ) . '%' . $wpdb->esc_like( $suffix ) ) );
		$main  = get_main_site_id();
		$found = array();

		foreach ( is_array( $names ) ? $names : array() as $name ) {
			$name = (string) $name;

			if ( $wpdb->base_prefix . $suffix === $name ) {
				$found[ $main ] = $name;
			} elseif ( preg_match( '/^' . preg_quote( $wpdb->base_prefix, '/' ) . '(\d+)_' . preg_quote( $suffix, '/' ) . '$/', $name, $match ) ) {
				$found[ (int) $match[1] ] = $name;
			}
		}

		ksort( $found );

		return array_slice( $found, 0, self::MAX_SITES, true );
	}

	/**
	 * Check if a database table exists.
	 *
	 * @since 5.7.9
	 *
	 * @param string $table Full table name including prefix.
	 */
	private function table_exists( string $table ): bool {
		global $wpdb;

		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
		$wpdb->suppress_errors( $suppress );

		return $result;
	}
}
