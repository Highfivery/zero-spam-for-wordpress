<?php
/**
 * Autoloader class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * WordPress Zero Spam autoloader.
 *
 * WordPress Zero Spam autoloader handler class is responsible for loading the
 * different classes needed to run the plugin.
 *
 * @since 5.0.0
 */
class Autoloader {

	/**
	 * Default path for autoloader.
	 *
	 * @var string
	 */
	private static $default_path;

	/**
	 * Default namespace for autoloader.
	 *
	 * @var string
	 */
	private static $default_namespace;

	/**
	 * Run autoloader.
	 *
	 * Register a function as `__autoload()` implementation.
	 *
	 * @param string $default_path
	 * @param string $default_namespace
	 *
	 * @since 5.0.0
	 * @access public
	 * @static
	 */
	public static function run( $default_path = '', $default_namespace = '' ) {
		if ( '' === $default_path ) {
			$default_path = ZEROSPAM_PATH;
		}

		if ( '' === $default_namespace ) {
			$default_namespace = __NAMESPACE__;
		}

		self::$default_path      = $default_path;
		self::$default_namespace = $default_namespace;

		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Load class.
	 *
	 * For a given class name, require the class file.
	 *
	 * @since 5.0.0
	 * @access private
	 * @static
	 *
	 * @param string $relative_class_name Class name.
	 */
	private static function load_class( $relative_class_name ) {
		$filename = strtolower(
			str_replace( '_', '', $relative_class_name )
		);

		$filename_parts = explode( '\\', $filename );
		$last_part      = array_key_last( $filename_parts );

		$filename_parts[ $last_part ] = 'class-' . $filename_parts[ $last_part ];

		$filename = implode( '/', $filename_parts );
		$filename = self::$default_path . $filename . '.php';

		if ( is_readable( $filename ) ) {
			require $filename;
		}
	}

	/**
	 * Autoload.
	 *
	 * For a given class, check if it exist and load it.
	 *
	 * @since 5.0.0
	 * @access private
	 * @static
	 *
	 * @param string $class Class name.
	 */
	private static function autoload( $class ) {
		if ( 0 !== strpos( $class, self::$default_namespace . '\\' ) ) {
			return;
		}

		$relative_class_name = preg_replace( '/^' . self::$default_namespace . '\\\/', '', $class );

		$final_class_name = self::$default_namespace . '\\' . $relative_class_name;

		if ( ! class_exists( $final_class_name ) ) {
			self::load_class( $relative_class_name );
		}
	}
}
