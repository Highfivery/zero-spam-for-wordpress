<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Termination_Intercepted to this file name.
/**
 * Thrown instead of ending the request while Access intercepts termination.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Core;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Raised by Access::terminate_execution() inside Access::intercept_termination(),
 * which catches it. Never reaches a visitor.
 *
 * @since 6.0.0
 */
final class Termination_Intercepted extends \RuntimeException {
}
