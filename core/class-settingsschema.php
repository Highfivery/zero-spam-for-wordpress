<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Settings_Schema to this file name.
/**
 * Machine-readable description of every Zero Spam setting.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Core;

use ZeroSpam\Includes\Network_Settings;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Builds a schema of the setting sections and settings from the existing
 * registry (the `zerospam_setting_sections` and `zerospam_settings` filters),
 * with each setting's effective value and where it comes from.
 *
 * The registry is built once per request and site (Settings::get_settings()
 * rebuilds it on every call), so REST routes and services can ask for
 * settings as often as they like.
 *
 * Registry entries may carry these optional keys, all additive (since 6.0.0):
 * `default` (value when nothing is stored), `secret` (bool), `lockable`
 * (bool), `area` and `group` (where the setting belongs in the admin),
 * `depends_on` (array with `key` and `value`) and `order` (int). Without them
 * the schema derives a value: see setting_entry().
 *
 * Values are resolved with the precedence Network_Settings applies: locked
 * network value, then site override, then network default, then (for the
 * license key) the ZEROSPAM_LICENSE_KEY constant, then the plugin default.
 *
 * @since 6.0.0
 */
final class Settings_Schema {

	/**
	 * Where a value comes from.
	 */
	const SOURCE_LOCKED   = 'locked';
	const SOURCE_SITE     = 'site';
	const SOURCE_NETWORK  = 'network';
	const SOURCE_CONSTANT = 'constant';
	const SOURCE_DEFAULT  = 'default';

	/**
	 * Field types the registry uses. Settings of type `html` hold rendered
	 * markup, not a value.
	 *
	 * @var string[]
	 */
	const TYPES = array( 'checkbox', 'date', 'html', 'number', 'radio', 'select', 'text', 'textarea', 'url' );

	/**
	 * Settings that are always secret: write-only over REST, never shown in
	 * full. Webhook URLs count: they work as credentials. A registry entry can add to this list with `'secret' => true`
	 * but cannot remove from it.
	 *
	 * @var string[]
	 */
	const SECRET_KEYS = array(
		'zerospam_license',
		'project_honeypot_access_key',
		'ipbase_api_key',
		'ipinfo_access_token',
		'ipstack_api',
		'google_api',
		'alert_webhook_url',
	);

	/**
	 * Admin area and group of each module's settings (6.0 information
	 * architecture). Unknown modules (third-party sections) belong to
	 * Protection › Integrations.
	 *
	 * @var array<string, array{0: string, 1: string}>
	 */
	const MODULE_AREAS = array(
		'comments'         => array( 'protection', 'detection' ),
		'registration'     => array( 'protection', 'detection' ),
		'login'            => array( 'protection', 'detection' ),
		'davidwalsh'       => array( 'protection', 'detection' ),
		'security'         => array( 'protection', 'detection' ),
		'zerospam'         => array( 'protection', 'intelligence' ),
		'stop_forum_spam'  => array( 'protection', 'intelligence' ),
		'project_honeypot' => array( 'protection', 'intelligence' ),
		'ipbase'           => array( 'protection', 'intelligence' ),
		'ipinfo'           => array( 'protection', 'intelligence' ),
		'ipstack'          => array( 'protection', 'intelligence' ),
		'google'           => array( 'protection', 'intelligence' ),
		'settings'         => array( 'settings', 'general' ),
		'api-monitoring'   => array( 'settings', 'api' ),
		'debug'            => array( 'settings', 'debug' ),
	);

	/**
	 * Settings whose area and group differ from their module's.
	 *
	 * @var array<string, array{0: string, 1: string}>
	 */
	const KEY_AREAS = array(
		'ip_whitelist'          => array( 'rules', 'allowed-ips' ),
		'blocked_email_domains' => array( 'rules', 'email-domains' ),
		'allowed_words'         => array( 'rules', 'allowed-words' ),
		'disallowed_min_length' => array( 'rules', 'allowed-words' ),
		'trusted_proxies'       => array( 'settings', 'proxies' ),
	);

	/**
	 * Settings that only apply when another setting has a given value, as
	 * their descriptions say.
	 *
	 * @var array<string, array{key: string, value: string}>
	 */
	const DEPENDS_ON = array(
		'widget_visibility'    => array(
			'key'   => 'widget_enabled',
			'value' => 'enabled',
		),
		'blocked_message'      => array(
			'key'   => 'block_handler',
			'value' => '403',
		),
		'blocked_redirect_url' => array(
			'key'   => 'block_handler',
			'value' => 'redirect',
		),
	);

	/**
	 * Settings stored in an option of their own instead of `zero-spam-{module}`.
	 *
	 * @var array<string, string>
	 */
	const OWN_OPTIONS = array(
		'blocked_email_domains' => 'zerospam_blocked_email_domains',
	);

	/**
	 * Settings a PHP constant can provide when the site stores no value, and
	 * the constant's name (only those the plugin really reads).
	 *
	 * @var array<string, string>
	 */
	private static array $constants = array(
		'zerospam_license' => 'ZEROSPAM_LICENSE_KEY',
	);

	/**
	 * Per-request cache, by blog ID.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static array $cache = array();

	/**
	 * Forgets the cached registry whenever a setting is stored, so a value
	 * changed during a request is the one read afterwards.
	 */
	public static function register_hooks(): void {
		foreach ( array( 'added_option', 'updated_option', 'deleted_option' ) as $hook ) {
			add_action( $hook, array( self::class, 'reset_cache_for_option' ), 10, 1 );
		}

		foreach ( array( 'add_site_option_', 'update_site_option_', 'delete_site_option_' ) as $prefix ) {
			add_action( $prefix . Network_Settings::META_KEY, array( self::class, 'reset_cache' ), 10, 0 );
		}
	}

	/**
	 * Forgets the cached registry when a settings option changed.
	 *
	 * @param mixed $option Option name.
	 */
	public static function reset_cache_for_option( $option ): void {
		if ( is_string( $option ) && ( 0 === strpos( $option, 'zero-spam-' ) || in_array( $option, self::OWN_OPTIONS, true ) ) ) {
			self::reset_cache();
		}
	}

	/**
	 * The settings registry (Settings::get_settings()), built once per request.
	 *
	 * Before `wp_loaded` modules may not have registered their settings yet,
	 * so the registry is built but not kept.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function registry(): array {
		return self::remember(
			'registry',
			static function (): array {
				$registry = Settings::get_settings();

				return is_array( $registry ) ? $registry : array();
			}
		);
	}

	/**
	 * The setting sections (Settings::get_sections()), built once per request.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function sections(): array {
		return self::remember(
			'sections',
			static function (): array {
				$sections = Settings::get_sections();

				return is_array( $sections ) ? $sections : array();
			}
		);
	}

	/**
	 * The full schema: sections and settings, in registry order.
	 *
	 * @return array{sections: array<int, array<string, mixed>>, settings: array<int, array<string, mixed>>}
	 */
	public static function get(): array {
		return self::remember(
			'schema',
			static function (): array {
				$settings = array();
				$order    = 0;

				foreach ( self::registry() as $key => $setting ) {
					if ( ! Settings::is_valid_setting( (string) $key, $setting ) ) {
						continue;
					}

					$order     += 10;
					$settings[] = self::setting_entry( (string) $key, $setting, $order );
				}

				return array(
					'sections' => self::section_entries( $settings ),
					'settings' => $settings,
				);
			}
		);
	}

	/**
	 * One setting's schema entry.
	 *
	 * @param string $key Setting key.
	 * @return array<string, mixed>|null Null for an unknown key.
	 */
	public static function setting( string $key ): ?array {
		$by_key = self::remember(
			'by_key',
			static function (): array {
				$by_key = array();
				foreach ( self::get()['settings'] as $entry ) {
					$by_key[ $entry['key'] ] = $entry;
				}

				return $by_key;
			}
		);

		return $by_key[ $key ] ?? null;
	}

	/**
	 * Keys of every setting that holds a value (every type but `html`).
	 *
	 * @return string[]
	 */
	public static function value_keys(): array {
		$keys = array();
		foreach ( self::get()['settings'] as $entry ) {
			if ( 'html' !== $entry['type'] ) {
				$keys[] = $entry['key'];
			}
		}

		return $keys;
	}

	/**
	 * Whether a setting is secret.
	 *
	 * @param string $key Setting key.
	 */
	public static function is_secret( string $key ): bool {
		if ( in_array( $key, self::SECRET_KEYS, true ) ) {
			return true;
		}

		$registry = self::registry();

		return ! empty( $registry[ $key ]['secret'] );
	}

	/**
	 * A setting's effective value and where it comes from.
	 *
	 * The value is the registry's (what the plugin uses); the source follows
	 * Network_Settings::resolve_all_settings().
	 *
	 * @param string $key Setting key.
	 * @return array{value: mixed, source: string, locked: bool}|null Null for an unknown key.
	 */
	public static function resolve( string $key ): ?array {
		$registry = self::registry();

		if ( ! isset( $registry[ $key ] ) || ! is_array( $registry[ $key ] ) || empty( $registry[ $key ]['module'] ) ) {
			return null;
		}

		$setting = $registry[ $key ];
		$value   = $setting['value'] ?? null;
		$network = self::network_entry( $key );

		if ( null !== $network && ! empty( $network['locked'] ) ) {
			return array(
				'value'  => $value,
				'source' => self::SOURCE_LOCKED,
				'locked' => true,
			);
		}

		$site = self::site_value( $key, (string) $setting['module'] );

		if ( $site['stored'] ) {
			$source = self::SOURCE_SITE;

			// An empty stored license falls back to the constant (see Zero_Spam::settings()).
			if ( empty( $site['value'] ) && null !== self::constant_value( $key ) ) {
				$source = self::SOURCE_CONSTANT;
			}
		} elseif ( null !== $network && isset( $network['value'] ) ) {
			$source = self::SOURCE_NETWORK;
		} elseif ( isset( self::OWN_OPTIONS[ $key ] ) && false !== get_option( self::OWN_OPTIONS[ $key ] ) ) {
			// As in the registry, a network default wins over the separate option.
			$source = self::SOURCE_SITE;
		} elseif ( null !== self::constant_value( $key ) ) {
			$source = self::SOURCE_CONSTANT;
		} else {
			$source = self::SOURCE_DEFAULT;
		}

		return array(
			'value'  => $value,
			'source' => $source,
			'locked' => false,
		);
	}

	/**
	 * A secret's masked form.
	 *
	 * `set` says whether it has a value and `source` where it comes from. A
	 * `hint` with its last four characters is included, except on a network
	 * when the value comes from the network or a constant: a site
	 * administrator never sees part of a key they did not enter.
	 *
	 * @param string $key Setting key.
	 * @return array{set: bool, source: string, hint?: string}
	 */
	public static function masked( string $key ): array {
		$resolved = self::resolve( $key );
		$value    = null === $resolved || ! is_scalar( $resolved['value'] ) || is_bool( $resolved['value'] ) ? '' : trim( (string) $resolved['value'] );
		$source   = null === $resolved ? self::SOURCE_DEFAULT : $resolved['source'];
		$masked   = array(
			'set'    => '' !== $value,
			'source' => $source,
		);

		$from_elsewhere = in_array( $source, array( self::SOURCE_NETWORK, self::SOURCE_LOCKED, self::SOURCE_CONSTANT ), true );

		if ( '' !== $value && ! ( is_multisite() && $from_elsewhere ) ) {
			$masked['hint'] = self::hint( $value );
		}

		return $masked;
	}

	/**
	 * The hint shown for a secret: four bullets and its last four characters
	 * (only the bullets for a value shorter than eight characters).
	 *
	 * @param string $value Secret value.
	 */
	public static function hint( string $value ): string {
		$bullets = str_repeat( "\u{2022}", 4 );

		if ( strlen( $value ) < 8 ) {
			return $bullets;
		}

		return $bullets . substr( $value, -4 );
	}

	/**
	 * Name of the option a setting is stored in on a site.
	 *
	 * @param string $key    Setting key.
	 * @param string $module Module key.
	 */
	public static function option_name( string $key, string $module ): string {
		return self::OWN_OPTIONS[ $key ] ?? "zero-spam-{$module}";
	}

	/**
	 * Empties the per-request cache (after settings change, and between tests).
	 */
	public static function reset_cache(): void {
		self::$cache = array();
	}

	/**
	 * A value from the per-request cache, computing it when missing.
	 *
	 * @param string   $name     Cache entry.
	 * @param callable $producer Computes the value.
	 * @return mixed
	 */
	private static function remember( string $name, callable $producer ) {
		$blog_id = get_current_blog_id();

		if ( isset( self::$cache[ $blog_id ] ) && array_key_exists( $name, self::$cache[ $blog_id ] ) ) {
			return self::$cache[ $blog_id ][ $name ];
		}

		$value = $producer();

		if ( did_action( 'wp_loaded' ) ) {
			self::$cache[ $blog_id ][ $name ] = $value;
		}

		return $value;
	}

	/**
	 * Builds one setting's entry.
	 *
	 * Derived values: `default` is the registry's `default` key, else the value
	 * the registry reports when nothing is stored (see defaults()); `lockable`
	 * is true for every type but `html`; `area` and `group` come from
	 * MODULE_AREAS and KEY_AREAS; `order` is the registry position.
	 *
	 * @param string               $key     Setting key.
	 * @param array<string, mixed> $setting Registry entry.
	 * @param int                  $order   Registry position.
	 * @return array<string, mixed>
	 */
	private static function setting_entry( string $key, array $setting, int $order ): array {
		$module   = (string) $setting['module'];
		$type     = (string) $setting['type'];
		$is_html  = 'html' === $type;
		$secret   = self::is_secret( $key );
		$resolved = self::resolve( $key );
		$area     = self::area_of( $key, $module, $setting );

		$entry = array(
			'key'         => $key,
			'option'      => self::option_name( $key, $module ),
			'module'      => $module,
			'section'     => isset( $setting['section'] ) ? (string) $setting['section'] : $module,
			'title'       => self::plain_text( $setting['title'] ?? '' ),
			'description' => self::rich_text( $setting['desc'] ?? '' ),
			'type'        => $type,
			'multiple'    => ! empty( $setting['multiple'] ),
			'options'     => self::options_of( $setting ),
			'min'         => self::number_or_null( $setting['min'] ?? null ),
			'max'         => self::number_or_null( $setting['max'] ?? null ),
			'step'        => self::number_or_null( $setting['step'] ?? null ),
			'default'     => null,
			'recommended' => $secret || $is_html ? null : ( $setting['recommended'] ?? null ),
			'lockable'    => ! $is_html && ( ! isset( $setting['lockable'] ) || (bool) $setting['lockable'] ),
			'secret'      => $secret,
			'depends_on'  => self::depends_on_of( $key, $setting ),
			'area'        => $area[0],
			'group'       => $area[1],
			'order'       => isset( $setting['order'] ) && is_numeric( $setting['order'] ) ? (int) $setting['order'] : $order,
			'locked'      => null !== $resolved && $resolved['locked'],
			'source'      => null !== $resolved ? $resolved['source'] : self::SOURCE_DEFAULT,
			'value'       => null,
		);

		if ( $is_html ) {
			return $entry;
		}

		if ( ! $secret ) {
			$entry['default'] = array_key_exists( 'default', $setting ) ? $setting['default'] : ( self::defaults()[ $key ] ?? null );
			$entry['value']   = null !== $resolved ? $resolved['value'] : null;
		} else {
			$entry['value'] = self::masked( $key );
		}

		return $entry;
	}

	/**
	 * Section entries, with the module, area and group of their settings.
	 *
	 * @param array<int, array<string, mixed>> $settings Setting entries.
	 * @return array<int, array<string, mixed>>
	 */
	private static function section_entries( array $settings ): array {
		$module_of = array();
		foreach ( $settings as $entry ) {
			if ( ! isset( $module_of[ $entry['section'] ] ) ) {
				$module_of[ $entry['section'] ] = $entry['module'];
			}
		}

		$sections = array();
		$order    = 0;

		foreach ( self::sections() as $key => $section ) {
			$key     = (string) $key;
			$section = is_array( $section ) ? $section : array();
			$module  = $module_of[ $key ] ?? $key;
			$area    = self::area_of( '', $module, $section );
			$order  += 10;

			$sections[] = array(
				'key'         => $key,
				'title'       => self::plain_text( $section['title'] ?? '' ),
				'description' => self::rich_text( $section['desc'] ?? ( $section['description'] ?? '' ) ),
				'module'      => $module,
				'area'        => $area[0],
				'group'       => $area[1],
				'order'       => isset( $section['order'] ) && is_numeric( $section['order'] ) ? (int) $section['order'] : $order,
			);
		}

		return $sections;
	}

	/**
	 * Area and group of a setting or section.
	 *
	 * @param string               $key    Setting key ('' for a section).
	 * @param string               $module Module key.
	 * @param array<string, mixed> $entry  Registry entry, which may set `area` and `group`.
	 * @return array{0: string, 1: string}
	 */
	private static function area_of( string $key, string $module, array $entry ): array {
		$default = self::KEY_AREAS[ $key ] ?? ( self::MODULE_AREAS[ $module ] ?? array( 'protection', 'integrations' ) );

		return array(
			isset( $entry['area'] ) && is_string( $entry['area'] ) && '' !== $entry['area'] ? sanitize_key( $entry['area'] ) : $default[0],
			isset( $entry['group'] ) && is_string( $entry['group'] ) && '' !== $entry['group'] ? sanitize_key( $entry['group'] ) : $default[1],
		);
	}

	/**
	 * A setting's dependency, from the registry or DEPENDS_ON.
	 *
	 * @param string               $key     Setting key.
	 * @param array<string, mixed> $setting Registry entry.
	 * @return array{key: string, value: string}|null
	 */
	private static function depends_on_of( string $key, array $setting ): ?array {
		$depends = $setting['depends_on'] ?? ( self::DEPENDS_ON[ $key ] ?? null );

		if ( ! is_array( $depends ) || empty( $depends['key'] ) || ! is_scalar( $depends['key'] ) ) {
			return null;
		}

		return array(
			'key'   => (string) $depends['key'],
			'value' => isset( $depends['value'] ) && is_scalar( $depends['value'] ) ? (string) $depends['value'] : '',
		);
	}

	/**
	 * Choices of a checkbox, radio or select setting.
	 *
	 * @param array<string, mixed> $setting Registry entry.
	 * @return array<int, array{value: string, label: string}>|null
	 */
	private static function options_of( array $setting ): ?array {
		if ( empty( $setting['options'] ) || ! is_array( $setting['options'] ) ) {
			return null;
		}

		$options = array();
		foreach ( $setting['options'] as $value => $label ) {
			$options[] = array(
				'value' => (string) $value,
				'label' => is_string( $label ) ? self::plain_text( $label ) : '',
			);
		}

		return $options;
	}

	/**
	 * The value every setting has when nothing is stored, network values and
	 * constants left out.
	 *
	 * Built from the registry with every module option, the separate
	 * blocked-email-domains option and the network settings reading as empty.
	 *
	 * @return array<string, mixed>
	 */
	private static function defaults(): array {
		return self::remember(
			'defaults',
			static function (): array {
				$modules = array();
				foreach ( self::registry() as $setting ) {
					if ( is_array( $setting ) && ! empty( $setting['module'] ) ) {
						$modules[ (string) $setting['module'] ] = true;
					}
				}

				$empty_array  = static function (): array {
					return array();
				};
				$empty_string = static function (): string {
					return '';
				};
				$no_network   = static function (): array {
					return array( 'settings' => array() );
				};

				$filters = array(
					'pre_site_transient_' . Network_Settings::CACHE_KEY => $no_network,
					'pre_site_option_' . Network_Settings::META_KEY     => $no_network,
				);
				foreach ( array_keys( $modules ) as $module ) {
					$filters[ "pre_option_zero-spam-{$module}" ] = $empty_array;
				}
				foreach ( self::OWN_OPTIONS as $option ) {
					$filters[ "pre_option_{$option}" ] = $empty_string;
				}

				foreach ( $filters as $hook => $callback ) {
					add_filter( $hook, $callback, PHP_INT_MAX );
				}

				$core = Settings::$settings;

				try {
					$registry = Settings::get_settings();
				} finally {
					foreach ( $filters as $hook => $callback ) {
						remove_filter( $hook, $callback, PHP_INT_MAX );
					}
					Settings::$settings = $core;
				}

				$defaults = array();
				foreach ( is_array( $registry ) ? $registry : array() as $key => $setting ) {
					if ( is_array( $setting ) && array_key_exists( 'value', $setting ) ) {
						$defaults[ (string) $key ] = $setting['value'];
					}
				}

				return $defaults;
			}
		);
	}

	/**
	 * The network settings entry of a key, or null (single site, or none).
	 *
	 * @param string $key Setting key.
	 * @return array<string, mixed>|null
	 */
	private static function network_entry( string $key ): ?array {
		if ( ! is_multisite() ) {
			return null;
		}

		$network = self::remember(
			'network',
			static function (): array {
				$stored = get_site_transient( Network_Settings::CACHE_KEY );

				if ( false === $stored ) {
					$stored = get_site_option( Network_Settings::META_KEY, array() );
				}

				return self::normalize_network_store( $stored );
			}
		);

		return isset( $network['settings'][ $key ] ) && is_array( $network['settings'][ $key ] ) ? $network['settings'][ $key ] : null;
	}

	/**
	 * The network settings store in the shape Network_Settings uses
	 * (`settings` => key => `value`, `locked`, …).
	 *
	 * @param mixed $stored Stored option.
	 * @return array{settings: array<string, mixed>}
	 */
	public static function normalize_network_store( $stored ): array {
		if ( ! is_array( $stored ) ) {
			return array( 'settings' => array() );
		}

		if ( ! isset( $stored['settings'] ) ) {
			return array( 'settings' => $stored );
		}

		if ( ! is_array( $stored['settings'] ) ) {
			$stored['settings'] = array();
		}

		return $stored;
	}

	/**
	 * Whether the site's module option stores the key (the test
	 * Network_Settings uses for a site override), and the stored value.
	 *
	 * @param string $key    Setting key.
	 * @param string $module Module key.
	 * @return array{stored: bool, value: mixed}
	 */
	private static function site_value( string $key, string $module ): array {
		$option = get_option( "zero-spam-{$module}", array() );

		if ( is_array( $option ) && isset( $option[ $key ] ) ) {
			return array(
				'stored' => true,
				'value'  => $option[ $key ],
			);
		}

		return array(
			'stored' => false,
			'value'  => null,
		);
	}

	/**
	 * The value of the constant that provides a setting, or null.
	 *
	 * @param string $key Setting key.
	 */
	private static function constant_value( string $key ): ?string {
		$name = self::$constants[ $key ] ?? '';

		if ( '' === $name || ! defined( $name ) ) {
			return null;
		}

		$value = constant( $name );

		return is_scalar( $value ) && '' !== trim( (string) $value ) ? (string) $value : null;
	}

	/**
	 * A number, or null for anything else.
	 *
	 * @param mixed $value Value.
	 * @return int|float|null
	 */
	private static function number_or_null( $value ) {
		if ( ! is_numeric( $value ) ) {
			return null;
		}

		return str_contains( (string) $value, '.' ) ? (float) $value : (int) $value;
	}

	/**
	 * Text without markup or entities.
	 *
	 * @param mixed $text Text.
	 */
	private static function plain_text( $text ): string {
		return is_string( $text ) ? html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) : '';
	}

	/**
	 * Text keeping only links, emphasis and code.
	 *
	 * @param mixed $text Text.
	 */
	private static function rich_text( $text ): string {
		if ( ! is_string( $text ) ) {
			return '';
		}

		return wp_kses(
			$text,
			array(
				'a'      => array(
					'href'   => true,
					'target' => true,
					'rel'    => true,
				),
				'br'     => array(),
				'code'   => array(),
				'em'     => array(),
				'strong' => array(),
			)
		);
	}
}
