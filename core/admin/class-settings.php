<?php
/**
 * Settings class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core\Admin;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Settings
 */
class Settings {
	/**
	 * Base admin link
	 *
	 * @var string $base_admin_link Base admin link
	 */
	public static $base_admin_link = 'options-general.php?page=wordpress-zero-spam-settings';

	/**
	 * Admin constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_action_import_settings', array( $this, 'import_settings' ) );

		// @codingStandardsIgnoreLine
		if ( ! empty( $_REQUEST['zerospam-msg'] ) ) {
			add_action(
				'admin_notices',
				function() {
					// @codingStandardsIgnoreLine
					add_settings_error( 'zerospam-notices', 'zerospam-msg', sanitize_text_field( wp_unslash( $_REQUEST['zerospam-msg'] ) ), 'success' );
				}
			);
		}
	}

	/**
	 * Imports settings
	 */
	public function import_settings() {
		$base_admin_link = self::$base_admin_link;

		$redirect['query'] = str_replace(
			array(
				'zerospam-success=1',
				'zerospam-error=1',
			),
			'',
			$redirect['query']
		);

		$redirect = admin_url( "$base_admin_link&subview=export" );

		if ( isset( $_POST['zerospam'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zerospam'] ) ), 'import_settings' ) ) {
			$settings_json = sanitize_text_field( wp_unslash( $_POST['settings'] ) ); // @codingStandardsIgnoreLine
			if ( ! empty( $settings_json ) ) {
				$settings = json_decode( $settings_json, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					foreach ( $settings as $module => $module_settings ) {
						update_option( "zero-spam-$module", $module_settings, true );
					}

					wp_safe_redirect( $redirect . '&zerospam-success=1' );
					exit;
				} else {
					wp_safe_redirect( $redirect . '&zerospam-error=1' );
					exit;
				}
			}
		} else {
			wp_safe_redirect( $redirect . '&zerospam-error=1' );
			exit;
		}
	}

	/**
	 * Regenerates the honeypot ID
	 */
	public function regenerate_honeypot() {
		\ZeroSpam\Core\Utilities::get_honeypot( true );
	}

	/**
	 * Processes nonce actions
	 */
	public function process_nonce_actions() {
		if (
			! empty( $_REQUEST['zerospam-action'] ) &&
			'autoconfigure' === $_REQUEST['zerospam-action'] &&
			check_admin_referer( 'autoconfigure', 'zero-spam' )
		) {
			\ZeroSpam\Core\Settings::auto_configure();

			$message      = __( 'WordPress Zero Spam has successfully been auto-configured with the recommended settings.', 'zero-spam' );
			$redirect_url = 'options-general.php?page=wordpress-zero-spam-settings&tab=settings&zerospam-msg=' . $message;

			wp_safe_redirect( $redirect_url );
			exit;
		} elseif (
			! empty( $_REQUEST['zerospam-action'] ) &&
			'update-blocked-emails' === $_REQUEST['zerospam-action'] &&
			check_admin_referer( 'update-blocked-emails', 'zero-spam' )
		) {
			\ZeroSpam\Core\Settings::update_blocked_email_domains();

			$message      = __( 'WordPress Zero Spam\'s blocked email domains have been successfully updated to the recommended.', 'zero-spam' );
			$redirect_url = 'options-general.php?page=wordpress-zero-spam-settings&tab=settings&zerospam-msg=' . $message;

			wp_safe_redirect( $redirect_url );
			exit;
		} elseif (
			! empty( $_REQUEST['zerospam-action'] ) &&
			'regenerate-honeypot' === $_REQUEST['zerospam-action'] &&
			check_admin_referer( 'regenerate-honeypot', 'zero-spam' )
		) {
			self::regenerate_honeypot();

			$message      = __( 'WordPress Zero Spam\'s honeypot ID has been successfully reset.', 'zero-spam' );
			$redirect_url = 'options-general.php?page=wordpress-zero-spam-settings&tab=settings&zerospam-msg=' . $message;

			wp_safe_redirect( $redirect_url );
			exit;
		} elseif (
			! empty( $_REQUEST['zerospam-action'] ) &&
			'update-disallowed-words' === $_REQUEST['zerospam-action'] &&
			check_admin_referer( 'update-disallowed-words', 'zero-spam' )
		) {
			\ZeroSpam\Core\Settings::update_disallowed_words();

			$message      = __( 'WordPress\'s disallowed words list has been successfully updated to the recommended.', 'zero-spam' );
			$redirect_url = 'options-general.php?page=wordpress-zero-spam-settings&tab=settings&zerospam-msg=' . $message;

			wp_safe_redirect( $redirect_url );
			exit;
		} elseif (
			! empty( $_REQUEST['zerospam-action'] ) &&
			'delete-error-log' === $_REQUEST['zerospam-action'] &&
			check_admin_referer( 'delete-error-log', 'zero-spam' )
		) {
			\ZeroSpam\Core\Utilities::delete_error_log();

			$message      = __( 'WordPress Zero Spam\'s error log has been successfully deleted.', 'zero-spam' );
			$redirect_url = 'options-general.php?page=wordpress-zero-spam-settings&tab=error&zerospam-msg=' . $message;

			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Admin menu
	 */
	public function admin_menu() {
		$this->process_nonce_actions();

		add_submenu_page(
			'options-general.php',
			__( 'Zero Spam Settings', 'zero-spam' ),
			__( 'Zero Spam', 'zero-spam' ),
			'manage_options',
			'wordpress-zero-spam-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Validates plugin settings before save
	 *
	 * @param array $input Input array.
	 */
	public function settings_validation( $input ) {
		update_option( 'zerospam_configured', 1 );

		return $input;
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		foreach ( \ZeroSpam\Core\Settings::get_sections() as $key => $section ) {
			register_setting(
				"zero-spam", // Group
				"zero-spam-$key", // Name
				array(
					'sanitize_callback' => array( $this, 'settings_validation' ),
				)
			);

			add_settings_section(
				'zero-spam-' . $key,
				$section['title'],
				array( $this, 'settings_section' ),
				'zero-spam-' . $key // Page
			);
		}

		foreach ( \ZeroSpam\Core\Settings::get_settings() as $key => $setting ) {
			$options = array_merge(
				array(
					'label_for' => $key,
					'type'      => $setting['type'],
				),
				$setting
			);

			add_settings_field(
				$key,
				! empty( $setting['title'] ) ? $setting['title'] : false,
				array( $this, 'settings_field' ),
				'zero-spam-' . $setting['module'], // Page
				'zero-spam-' . $setting['module'], // Section
				$options
			);
		}
	}

	/**
	 * Settings section
	 *
	 * @param array $args Section arguments.
	 */
	public function settings_section( $args ) {
	}

	/**
	 * Settings field
	 *
	 * @param array $args Field arguments.
	 */
	public function settings_field( $args ) {
		$setting_name = 'zero-spam-' . $args['module'] .'[' . $args['label_for'] . ']';

		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . wp_kses(
				$args['desc'],
				array(
					'a'      => array(
						'target' => array(),
						'href'   => array(),
						'class'  => array(),
						'rel'    => array(),
					),
					'strong' => array(),
					'b'      => array(),
					'code'   => array(),
				)
			) . '</p>';
		}

		switch ( $args['type'] ) {
			case 'html':
				echo wp_kses(
					$args['html'],
					array(
						'strong' => array(),
						'a'      => array(
							'href'   => array(),
							'target' => array(),
							'class'  => array(),
							'rel'    => array(),
						),
						'em'     => array(),
						'code'   => array(),
						'h1'     => array(
							'style' => array(),
						),
						'h2'     => array(
							'style' => array(),
						),
						'h3'     => array(
							'style' => array(),
						),
						'h4'     => array(
							'style' => array(),
						),
						'h5'     => array(
							'style' => array(),
						),
						'h6'     => array(
							'style' => array(),
						),
					)
				);
				break;
			case 'textarea':
				?>
				<textarea
					id="<?php echo esc_attr( $args['label_for'] ); ?>"
					name="<?php echo esc_attr( $setting_name ); ?>"
					rows="5"
					<?php if ( ! empty( $args['field_class'] ) ) : ?>
						class="<?php echo esc_attr( $args['field_class'] ); ?>"
					<?php endif; ?>
					<?php if ( ! empty( $args['placeholder'] ) ) : ?>
						placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
					<?php endif; ?>
				><?php if ( ! empty( $args['value'] ) ) : ?><?php echo trim( esc_attr( $args['value'] ) ); ?><?php endif; ?></textarea>
				<?php
				break;
			case 'url':
			case 'text':
			case 'password':
			case 'number':
			case 'email':
				?>
				<input
					id="<?php echo esc_attr( $args['label_for'] ); ?>"
					name="<?php echo esc_attr( $setting_name ); ?>"
					type="<?php echo esc_attr( $args['type'] ); ?>"
					<?php if ( ! empty( $args['value'] ) ) : ?>
						value="<?php echo esc_attr( $args['value'] ); ?>"
					<?php endif; ?>
					<?php if ( ! empty( $args['field_class'] ) ) : ?>
						class="<?php echo esc_attr( $args['field_class'] ); ?>"
					<?php endif; ?>
					<?php if ( ! empty( $args['placeholder'] ) ) : ?>
						placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
					<?php endif; ?>
					<?php if ( ! empty( $args['min'] ) ) : ?>
						min="<?php echo esc_attr( $args['min'] ); ?>"
					<?php endif; ?>
					<?php if ( ! empty( $args['max'] ) ) : ?>
						max="<?php echo esc_attr( $args['max'] ); ?>"
					<?php endif; ?>
					<?php if ( ! empty( $args['step'] ) ) : ?>
						step="<?php echo esc_attr( $args['step'] ); ?>"
					<?php endif; ?>
				/>
				<?php
				break;
			case 'select':
				if ( empty( $args['options'] ) ) {
					return;
				}

				if ( ! empty( $args['multiple'] ) ) :
					$setting_name .= '[]';
				endif;
				?>
				<select
					id="<?php echo esc_attr( $args['label_for'] ); ?>"
					name="<?php echo esc_attr( $setting_name ); ?>"
					<?php if ( ! empty( $args['multiple'] ) ) : ?>
						multiple
					<?php endif; ?>
					<?php if ( ! empty( $args['field_class'] ) ) : ?>
						class="<?php echo esc_attr( $args['field_class'] ); ?>"
					<?php endif; ?>
				>
						<?php
						foreach ( $args['options'] as $key => $label ) :
							$selected = false;
							if ( ! empty( $args['value'] ) && ! empty( $args['multiple'] ) && is_array( $args['value'] ) ) :
								if ( in_array( $key, $args['value'], true ) ) :
									$selected = true;
								endif;
							else :
								if ( ! empty( $args['value'] ) && $args['value'] == $key ) {
									$selected = true;
								}
							endif;
							?>
							<option
								value="<?php echo esc_attr( $key ); ?>"
								<?php if ( $selected ) : ?>
									selected="selected"
								<?php endif; ?>
							>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
				</select>
				<?php
				break;
			case 'checkbox':
			case 'radio':
				if ( empty( $args['options'] ) ) {
					return;
				}

				foreach ( $args['options'] as $key => $label ) {
					$selected = false;
					if ( count( $args['options'] ) > 1 && 'checkbox' === $args['type'] ) {
						$setting_name .= '[' . $key . ']';
					}

					if ( ! empty( $args['value'] ) && $args['value'] == $key ) {
						$selected = true;
					}
					?>
						<input
							type="<?php echo esc_attr( $args['type'] ); ?>"
							id="<?php echo esc_attr( $args['label_for'] . $key ); ?>"
							name="<?php echo esc_attr( $setting_name ); ?>"
							value="<?php echo esc_attr( $key ); ?>"
							<?php if ( ! empty( $args['field_class'] ) ) : ?>
								class="<?php echo esc_attr( $args['field_class'] ); ?>"
							<?php endif; ?>
							<?php if ( $selected ) : ?>
								checked="checked"
							<?php endif; ?>
						/>

						<label for="<?php echo esc_attr( $args['label_for'] . $key ); ?>">
							<?php
							echo wp_kses(
								$label,
								array(
									'a' => array(
										'target' => array(),
										'href'   => array(),
										'class'  => array(),
										'rel'    => array(),
									),
									'strong' => array(),
									'b'      => array(),
									'code'   => array(),
								)
							);
							?>
						</label>
						<br />
				<?php
				}
				break;
		}

		if ( ! empty( $args['suffix'] ) ) {
			echo wp_kses(
				$args['suffix'],
				array(
					'a' => array(
						'target' => array(),
						'href'   => array(),
						'class'  => array(),
						'rel'    => array(),
					),
					'strong' => array(),
					'b'      => array(),
					'code'   => array(),
				)
			);
		}
	}

	/**
	 * Settings page
	 */
	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$base_admin_link = self::$base_admin_link;

		// @codingStandardsIgnoreLine
		$subview = ! empty( $_REQUEST['subview'] ) ? sanitize_text_field( $_REQUEST['subview'] ) : 'settings';

		$sections = apply_filters( 'zerospam_setting_sections', [] );
		?>
		<?php require ZEROSPAM_PATH . 'includes/templates/admin-header.php'; ?>
		<div class="wrap">
			<div class="zerospam-dashboard">
				<div class="zerospam-dashboard__col">
					<ul class="zerospam-dashboard__sections">
						<li><a href="<?php echo esc_url( admin_url( "$base_admin_link&subview=settings" ) ); ?>" class="zerospam-dashboard__menu-link <?php if ( 'settings' === $subview ) : echo 'zerospam-dashboard__menu-link--active'; endif; ?>"><?php esc_html_e( 'Settings', 'zero-spam' ); ?></a></li>
						<?php foreach ( $sections as $key => $section ) : ?>
							<li><a href="<?php echo esc_url( admin_url( "$base_admin_link&subview=$key" ) ); ?>" class="zerospam-dashboard__menu-link <?php if ( $key === $subview ) : echo 'zerospam-dashboard__menu-link--active'; endif; ?>"><?php echo $section['title']; ?></a></li>
						<?php endforeach; ?>
						<li><a href="<?php echo esc_url( admin_url( "$base_admin_link&subview=export" ) ); ?>" class="zerospam-dashboard__menu-link <?php if ( 'export' === $subview ) : echo 'zerospam-dashboard__menu-link--active'; endif; ?>"><?php esc_html_e( 'Import/Export Settings', 'zero-spam' ); ?></a></li>
						<li><a href="<?php echo esc_url( admin_url( "$base_admin_link&subview=errors" ) ); ?>" class="zerospam-dashboard__menu-link <?php if ( 'errors' === $subview ) : echo 'zerospam-dashboard__menu-link--active'; endif; ?>"><?php esc_html_e( 'Error Log', 'zero-spam' ); ?></a></li>
					</ul>
				</div>
				<div class="zerospam-dashboard__col">
					<?php if ( ! in_array( $subview, array(  'export', 'errors' ) ) ) : ?>
						<form action="options.php" method="post" class="zerospam-form">
						<?php
						// Output security fields for the registered setting "zero-spam".
						settings_fields( "zero-spam" );

						// Output setting sections and their fields.
						do_settings_sections( "zero-spam-$subview" );

						// Output save settings button.
						submit_button( 'Save Settings →' );
						?>
						</form>
					<?php elseif( $subview === 'export' ) : ?>
						<?php if ( ! empty( $_GET['zerospam-error'] ) ) : ?>
							<div class="notice notice-error is-dismissible">
								<p><strong>
									<?php
									switch( intval( $_GET['zerospam-error'] ) ) :
										case 1:
											esc_html_e( 'There was a problem importing the settings JSON. Please try again.', 'zero-spam' );
											break;
									endswitch;
									?>
								</strong></p>
								<button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'zero-spam' ); ?></span></button>
							</div>
						<?php elseif ( ! empty( $_GET['zerospam-success'] ) ) : ?>
							<div class="notice notice-success is-dismissible">
								<p><strong><?php esc_html_e( 'The settings JSON has been successfully imported.', 'zero-spam' ); ?></strong></p>
								<button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'zero-spam' ); ?>.</span></button>
							</div>
						<?php endif; ?>

						<div class="zerospam-form">
							<h2><?php esc_html_e( 'Settings Import/Export', 'zero-spam' ); ?></h2>
							<?php
							$modules = \ZeroSpam\Core\Settings::get_settings_by_module();
							foreach ( $modules as $module => $settings ) :
								foreach ( $settings as $setting_key => $setting ) :
									$modules[ $module ][ $setting_key ] = ! empty( $setting['value'] ) ? $setting['value'] : false;
								endforeach;
							endforeach;
							?>
							<div class="zerospam-export-import-block">
								<div class="zerospam-export-import-block-column">
									<h4><?php esc_html_e( 'Export Settings', 'zero-spam' ); ?></h4>
									<textarea readonly class="large-text code" rows="25"><?php echo wp_json_encode( $modules ); ?></textarea>
								</div>
								<div class="zerospam-export-import-block-column">
									<h4><?php esc_html_e( 'Import Settings', 'zero-spam' ); ?></h4>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="zerospam-import-settings-form">
									<?php wp_nonce_field( 'import_settings', 'zerospam' ); ?>
									<input type="hidden" name="action" value="import_settings" />
									<textarea class="large-text code" name="settings" rows="25"></textarea>
									<input type="submit" class="button button-primary" value="<?php esc_html_e( 'Import Settings →', 'zero-spam' ); ?>" />
									</form>
								</div>
							</div>
						</div>
					<?php
						elseif( $subview === 'errors' ) :
						$log = \ZeroSpam\Core\Utilities::get_error_log();
						if ( ! $log ) :
							esc_html_e( 'Yay! No errors have been reported.', 'zero-spam' );
						else:
							?>
							<textarea readonly class="large-text code" rows="30"><?php echo esc_html( $log ); ?></textarea>
							<a
								href="<?php echo esc_url( wp_nonce_url( admin_url( "$base_admin_link&zerospam-action=delete-error-log" ), 'delete-error-log', 'zero-spam' ) ); ?>"
								class="button button-primary"
							>
								<?php esc_html_e( 'Clear Error Log', 'zero-spam' ); ?>
							</a>
						<?php endif; ?>

					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
