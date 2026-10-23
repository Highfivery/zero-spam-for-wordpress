<?php
/**
 * A REST controller exposing Base_Controller's protected helpers to tests.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

namespace ZeroSpam\Tests;

use ZeroSpam\Includes\Rest\Base_Controller;

/**
 * A minimal controller exposing the protected helpers.
 */
class BaseControllerProbe extends Base_Controller {

	/**
	 * Calls a protected helper.
	 *
	 * @param string       $method Method.
	 * @param array<mixed> $args   Arguments.
	 * @return mixed
	 */
	public function call( string $method, array $args = array() ) {
		return $this->$method( ...$args );
	}

	/**
	 * Calls the protected static cache helper.
	 *
	 * @param string   $key      Key.
	 * @param callable $producer Producer.
	 * @return mixed
	 */
	public static function cache( string $key, callable $producer ) {
		return self::request_cache( $key, $producer );
	}
}
