<?php
/**
 * A stand-in for the WP_CLI class, for tests of the plugin's command classes.
 *
 * Not loaded by the bootstrap (plugin code may check for WP-CLI); tests that
 * need it require it themselves.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

/**
 * Records what a command printed. error() throws instead of exiting.
 */
class WP_CLI {

	/**
	 * Output, in order, as [ type, message ].
	 *
	 * @var array<int, array{0: string, 1: string}>
	 */
	public static array $messages = array();

	/**
	 * Forgets recorded output.
	 */
	public static function reset(): void {
		self::$messages = array();
	}

	/**
	 * Messages of one type.
	 *
	 * @param string $type success, warning, error or log.
	 * @return string[]
	 */
	public static function of( string $type ): array {
		$found = array();
		foreach ( self::$messages as $message ) {
			if ( $type === $message[0] ) {
				$found[] = $message[1];
			}
		}

		return $found;
	}

	/**
	 * Records a success message.
	 *
	 * @param mixed $message Message.
	 */
	public static function success( $message ): void {
		self::$messages[] = array( 'success', (string) $message );
	}

	/**
	 * Records a warning.
	 *
	 * @param mixed $message Message.
	 */
	public static function warning( $message ): void {
		self::$messages[] = array( 'warning', (string) $message );
	}

	/**
	 * Records a log line.
	 *
	 * @param mixed $message Message.
	 */
	public static function log( $message ): void {
		self::$messages[] = array( 'log', (string) $message );
	}

	/**
	 * Records an error and stops the command, as WP-CLI does.
	 *
	 * @param mixed $message Message.
	 * @throws RuntimeException Always.
	 */
	public static function error( $message ): void {
		self::$messages[] = array( 'error', (string) $message );

		throw new RuntimeException( (string) $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught by the test, never rendered.
	}

	/**
	 * Returns text unchanged.
	 *
	 * @param string $text Text with color tokens.
	 */
	public static function colorize( $text ): string {
		return (string) $text;
	}

	/**
	 * Ignores command registration.
	 *
	 * @param mixed ...$args Arguments.
	 */
	public static function add_command( ...$args ): void {
		unset( $args );
	}
}
