<?php
/**
 * Tests for the Elementor Pro forms module.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Settings;
use ZeroSpam\Modules\DavidWalsh\DavidWalsh;
use ZeroSpam\Modules\Elementor\Elementor;
use ZeroSpam\Plugin;
use ZeroSpam\Tests\TestCase;

/**
 * The module loads only with Elementor Pro, and its validators stop spam
 * through Elementor Pro's Ajax_Handler the way Elementor Pro expects.
 *
 * Elementor Pro is not installed in the test environment: its form record
 * and AJAX handler are replaced by small recording doubles.
 *
 * @covers \ZeroSpam\Modules\Elementor\Elementor
 * @covers \ZeroSpam\Plugin
 */
class Test_Elementor extends TestCase {

	/**
	 * Elementor Pro's main plugin file, as is_plugin_active() sees it.
	 */
	const ELEMENTOR_PRO = 'elementor-pro/elementor-pro.php';

	/**
	 * Clears the submitted form data.
	 */
	public function tear_down(): void {
		$_POST = array();

		parent::tear_down();
	}

	/**
	 * Marks Elementor Pro as active (restored with the database after the test).
	 */
	private function activate_elementor_pro(): void {
		update_option( 'active_plugins', array_merge( (array) get_option( 'active_plugins', array() ), array( self::ELEMENTOR_PRO ) ) );
	}

	/**
	 * Runs Plugin::init_modules() again, as it runs when the plugin loads.
	 */
	private function run_init_modules(): void {
		$method = new ReflectionMethod( Plugin::class, 'init_modules' );
		$method->invoke( Plugin::instance() );
	}

	/**
	 * Every hook callback that belongs to an Elementor module instance.
	 *
	 * @return string[] Hook names.
	 */
	private function hooks_with_elementor_callbacks(): array {
		global $wp_filter;

		$hooks = array();
		foreach ( $wp_filter as $hook_name => $hook ) {
			foreach ( $hook->callbacks as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof Elementor ) {
						$hooks[] = (string) $hook_name;
					}
				}
			}
		}

		return $hooks;
	}

	/**
	 * Hooks a fresh module, as WordPress's init would.
	 */
	private function init_module(): Elementor {
		$module = new Elementor();
		$module->init();
		self::reset_static_caches();

		return $module;
	}

	/**
	 * Enables the module's protection.
	 */
	private function enable(): void {
		$this->set_setting( 'elementor_enabled', 'enabled', 'elementor' );
	}

	/**
	 * A recording stand-in for ElementorPro\Modules\Forms\Classes\Ajax_Handler.
	 */
	private function ajax_handler(): object {
		return new class() {
			/**
			 * Field errors, keyed by field ID ('' for the form).
			 *
			 * @var array<string, string>
			 */
			public array $errors = array();

			/**
			 * Form-level error messages.
			 *
			 * @var string[]
			 */
			public array $messages = array();

			/**
			 * Records a field error.
			 *
			 * @param string $field   Field ID.
			 * @param string $message Message.
			 */
			public function add_error( $field, $message = '' ): self {
				$this->errors[ $field ] = $message;

				return $this;
			}

			/**
			 * Records a form-level message.
			 *
			 * @param string $message Message.
			 */
			public function add_error_message( $message ): self {
				$this->messages[] = $message;

				return $this;
			}
		};
	}

	/**
	 * A stand-in for ElementorPro\Modules\Forms\Classes\Form_Record.
	 */
	private function record(): object {
		return new class() {
			/**
			 * Returns a form setting.
			 *
			 * @param string $setting Setting key.
			 * @return mixed
			 */
			public function get_form_settings( $setting ) {
				return 'form_name' === $setting ? 'Contact' : null;
			}
		};
	}

	/**
	 * A form field as Elementor Pro passes it to the validation hooks.
	 *
	 * @param string $type  Field type.
	 * @param string $value Submitted value.
	 * @return array<string, mixed>
	 */
	private function field( string $type, string $value ): array {
		return array(
			'id'        => 'field_' . $type,
			'type'      => $type,
			'title'     => ucfirst( $type ),
			'value'     => $value,
			'raw_value' => $value,
			'required'  => false,
		);
	}

	/**
	 * Without Elementor Pro (the test environment), the plugin does not load the module.
	 */
	public function test_module_is_not_loaded_without_elementor_pro(): void {
		$this->assertSame( array(), $this->hooks_with_elementor_callbacks(), 'Nothing from the module is hooked after the plugin loaded.' );

		$this->run_init_modules();

		$this->assertSame( array(), $this->hooks_with_elementor_callbacks() );
		$this->assertArrayNotHasKey( 'elementor', apply_filters( 'zerospam_setting_sections', array() ) );
	}

	/**
	 * With Elementor Pro active, the plugin loads the module.
	 */
	public function test_module_is_loaded_with_elementor_pro(): void {
		$this->activate_elementor_pro();

		$this->run_init_modules();

		$this->assertSame( array( 'init' ), $this->hooks_with_elementor_callbacks() );
	}

	/**
	 * The module registers its section and settings, stored in zero-spam-elementor.
	 */
	public function test_registers_settings_section(): void {
		$this->activate_elementor_pro();
		$this->init_module();

		$sections = apply_filters( 'zerospam_setting_sections', array() );
		$this->assertArrayHasKey( 'elementor', $sections );
		$this->assertSame( 'modules/elementor/icon-elementor.svg', $sections['elementor']['icon'] );

		$registry = Settings::get_settings();
		foreach ( array( 'elementor_enabled', 'elementor_flagged_msg', 'elementor_log_flagged_attempts' ) as $key ) {
			$this->assertSame( 'elementor', $registry[ $key ]['module'] ?? null, "{$key} is stored in zero-spam-elementor." );
			$this->assertSame( 'elementor', $registry[ $key ]['section'] ?? null, "{$key} is in the Elementor section." );
		}

		$this->assertArrayHasKey( 'elementor', apply_filters( 'zerospam_types', array() ) );
	}

	/**
	 * Form validation is only hooked when protection is enabled.
	 */
	public function test_validation_is_hooked_only_when_enabled(): void {
		$module = $this->init_module();
		$this->assertFalse( has_action( 'elementor_pro/forms/validation/text', array( $module, 'validate_text' ) ) );

		$this->enable();
		$module = $this->init_module();

		$this->assertSame( 10, has_action( 'elementor_pro/forms/validation/email', array( $module, 'validate_email' ) ) );
		$this->assertSame( 10, has_action( 'elementor_pro/forms/validation/text', array( $module, 'validate_text' ) ) );
		$this->assertSame( 10, has_action( 'elementor_pro/forms/validation/textarea', array( $module, 'validate_text' ) ) );
		$this->assertFalse( has_action( 'elementor_pro/forms/validation/html', array( $module, 'validate_text' ) ), 'HTML fields carry no visitor input.' );
	}

	/**
	 * A disallowed word in a text field is rejected on that field, with the message escaped, and logged.
	 */
	public function test_disallowed_word_is_rejected_and_logged(): void {
		update_option( 'disallowed_keys', 'cheap-pills' );
		$this->set_setting( 'elementor_flagged_msg', '<b>Blocked</b>', 'elementor' );
		$this->set_setting( 'elementor_log_flagged_attempts', 'enabled', 'elementor' );
		$module  = $this->init_module();
		$handler = $this->ajax_handler();

		$module->validate_text( $this->field( 'textarea', 'Buy cheap-pills now' ), $this->record(), $handler );

		$this->assertSame( array( 'field_textarea' => '&lt;b&gt;Blocked&lt;/b&gt;' ), $handler->errors );
		$this->assertSame( 1, $this->count_log_rows( array( 'log_type' => 'elementor' ) ) );
	}

	/**
	 * Clean text passes.
	 */
	public function test_clean_text_passes(): void {
		update_option( 'disallowed_keys', 'cheap-pills' );
		$handler = $this->ajax_handler();

		( new Elementor() )->validate_text( $this->field( 'text', 'Hello there' ), $this->record(), $handler );

		$this->assertSame( array(), $handler->errors );
	}

	/**
	 * An email on a blocked domain is rejected; others pass.
	 */
	public function test_blocked_email_domain_is_rejected(): void {
		update_option( 'zerospam_blocked_email_domains', "spam.test\n" );
		self::reset_static_caches();
		$handler = $this->ajax_handler();
		$module  = new Elementor();

		$module->validate_email( $this->field( 'email', 'someone@example.org' ), $this->record(), $handler );
		$this->assertSame( array(), $handler->errors );

		$module->validate_email( $this->field( 'email', 'bot@spam.test' ), $this->record(), $handler );
		$this->assertArrayHasKey( 'field_email', $handler->errors );
	}

	/**
	 * A submission without a valid David Walsh token is stopped, not just given a message.
	 */
	public function test_missing_david_walsh_token_stops_submission(): void {
		$davidwalsh = ( new ReflectionClass( DavidWalsh::class ) )->newInstanceWithoutConstructor();
		add_filter( 'zerospam_preprocess_elementor_submission', array( $davidwalsh, 'validate_post' ), 10, 3 );
		$handler = $this->ajax_handler();

		$_POST = array( 'form_fields' => array( 'name' => 'Bot' ) );
		( new Elementor() )->validate_davidwalsh( $this->record(), $handler );

		$this->assertNotSame( array(), $handler->errors, 'Elementor Pro only stops a submission that has errors.' );
		$this->assertCount( 1, $handler->messages );
	}

	/**
	 * A submission with a valid David Walsh token passes.
	 */
	public function test_valid_david_walsh_token_passes(): void {
		$davidwalsh = ( new ReflectionClass( DavidWalsh::class ) )->newInstanceWithoutConstructor();
		add_filter( 'zerospam_preprocess_elementor_submission', array( $davidwalsh, 'validate_post' ), 10, 3 );
		$handler = $this->ajax_handler();

		$_POST = array(
			'form_fields'              => array( 'name' => 'Person' ),
			'zerospam_david_walsh_key' => DavidWalsh::generate_token(),
		);
		( new Elementor() )->validate_davidwalsh( $this->record(), $handler );

		$this->assertSame( array(), $handler->errors );
		$this->assertSame( array(), $handler->messages );
	}
}
