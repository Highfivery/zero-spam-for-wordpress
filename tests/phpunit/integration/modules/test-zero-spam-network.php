<?php
/**
 * Tests for the Zero Spam network access check's logged failed key.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\Rest\Stats_Controller;
use ZeroSpam\Modules\Zero_Spam;
use ZeroSpam\Tests\TestCase;

/**
 * A visitor the network rates above the confidence minimum is blocked with
 * the stable failed key `zero_spam` (since 6.0.0, so statistics can count
 * the network's detections) and the score kept in the details; the key has
 * a readable label.
 *
 * @covers \ZeroSpam\Modules\Zero_Spam::access_check
 * @covers \ZeroSpam\Modules\Zero_Spam::failed_types
 */
class Test_Zero_Spam_Network extends TestCase {

	/**
	 * A high-confidence answer blocks with the zero_spam key.
	 */
	public function test_high_confidence_block_logs_the_stable_key(): void {
		$ip = '203.0.113.50';
		$this->set_setting( 'zerospam_license', 'test-license', 'zerospam' );
		$this->set_setting( 'zerospam_confidence_min', 30, 'zerospam' );
		$this->mock_http(
			'#wp-json/v3/query#',
			array(
				'status'        => 200,
				'body_response' => array(
					'ip_addresses' => array(
						$ip => array( 'confidence' => 0.92 ),
					),
				),
			)
		);

		$checks = ( new Zero_Spam() )->access_check( array(), $ip );

		$this->assertTrue( $checks['zero_spam']['blocked'] );
		$this->assertSame( 'blocked', $checks['zero_spam']['type'] );
		$this->assertSame( Stats_Controller::NETWORK_FAILED, $checks['zero_spam']['details']['failed'] );
		$this->assertEqualsWithDelta( 92.0, (float) $checks['zero_spam']['details']['confidence_score'], 0.001 );
	}

	/**
	 * The key has a label in the failed-check list.
	 */
	public function test_failed_type_label(): void {
		$types = ( new Zero_Spam() )->failed_types( array() );

		$this->assertSame( 'Zero Spam network (high confidence)', $types['zero_spam'] );
	}
}
