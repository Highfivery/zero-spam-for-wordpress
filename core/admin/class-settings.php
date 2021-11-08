<?php
/**
 * Settings class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core\Admin;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Settings.
 *
 * @since 5.0.0
 */
class Settings {

	/**
	 * Admin constructor.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_action_import_settings', array( $this, 'import_settings' ) );

		if ( ! empty( $_REQUEST['zerospam-auto-configure'] ) ) {
			\ZeroSpam\Core\Settings::auto_configure();

			wp_safe_redirect( admin_url( 'options-general.php?page=wordpress-zero-spam-settings&zerospam-msg=WordPress Zero Spam has been auto-configured to the recommended settings.' ) );
			exit;
		}

		if ( ! empty( $_REQUEST['zerospam-regenerate-honeypot'] ) ) {
			self::regenerate_honeypot();

			wp_safe_redirect( admin_url( 'options-general.php?page=wordpress-zero-spam-settings&zerospam-msg=The WordPress Zero Spam honeypot ID has been successfully regenerated.' ) );
			exit;
		}

		if ( ! empty( $_REQUEST['zerospam-update-blocked-email-domains'] ) ) {
			\ZeroSpam\Core\Settings::update_blocked_email_domains();

			wp_safe_redirect( admin_url( 'options-general.php?page=wordpress-zero-spam-settings&zerospam-msg=The blocked email domains settings has been successfully updated with the recommended domains.' ) );
			exit;
		}

		if ( ! empty( $_REQUEST['zerospam-update-disallowed-words'] ) ) {
			\ZeroSpam\Core\Settings::update_disallowed_words();

			wp_safe_redirect( admin_url( 'options-general.php?page=wordpress-zero-spam-settings&zerospam-msg=Your site\'s disallowed words list has been successfully updated.' ) );
			exit;
		}

		if ( ! empty( $_REQUEST['zerospam-msg'] ) ) {
			add_action(
				'admin_notices',
				function() {
					add_settings_error( 'zerospam-notices', 'zerospam-msg', sanitize_text_field( wp_unslash( $_REQUEST['zerospam-msg'] ) ), 'success' );
				}
			);
		}
	}

	/**
	 * Imports settings.
	 *
	 * @since 5.1.0
	 */
	public function import_settings() {
		$redirect = ! empty( $_POST['redirect'] ) ? esc_url( sanitize_text_field( wp_unslash( $_POST['redirect'] ) ) ) : get_site_url();
		$redirect = wp_parse_url( $redirect );

		$redirect['query'] = str_replace(
			array(
				'zerospam-success=1',
				'zerospam-error=1',
			),
			'',
			$redirect['query']
		);

		$redirect = $redirect['scheme'] . '://' . $redirect['host'] . ( ! empty( $redirect['port'] ) ? ':' . $redirect['port'] : '' ) . $redirect['path'] . '?' . $redirect['query'];

		if ( isset( $_POST['zerospam'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zerospam'] ) ), 'import_settings' ) ) {
			$settings_json = sanitize_text_field( wp_unslash( $_POST['settings'] ) ); // @codingStandardsIgnoreLine
			if ( ! empty( $settings_json ) ) {
				$settings = json_decode( $settings_json, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					update_option( 'wpzerospam', $settings, true );

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
	 * Regenerates the honeypot ID.
	 */
	public function regenerate_honeypot() {
		\ZeroSpam\Core\Utilities::get_honeypot( true );
	}

	/**
	 * Admin menu
	 */
	public function admin_menu() {
		add_submenu_page(
			'options-general.php',
			__( 'Zero Spam Settings', 'zerospam' ),
			__( 'Zero Spam', 'zerospam' ),
			'manage_options',
			'wordpress-zero-spam-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Validates plugin settings before save.
	 */
	public function settings_validation( $input ) {
		update_option( 'zerospam_configured', 1 );

		return $input;
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting(
			'wpzerospam',
			'wpzerospam',
			array(
				'sanitize_callback' => array( $this, 'settings_validation' ),
			)
		);

		foreach ( ZeroSpam\Core\Settings::get_sections() as $key => $section ) {
			add_settings_section(
				'zerospam_' . $key,
				$section['title'],
				array( $this, 'settings_section' ),
				'wpzerospam'
			);
		}

		foreach ( ZeroSpam\Core\Settings::get_settings() as $key => $setting ) {
			$options = array(
				'label_for' => $key,
				'type'      => $setting['type'],
			);

			if ( ! empty( $setting['options'] ) ) {
				$options['options'] = $setting['options'];
			}

			if ( ! empty( $setting['value'] ) ) {
				$options['value'] = $setting['value'];
			}

			if ( ! empty( $setting['placeholder'] ) ) {
				$options['placeholder'] = $setting['placeholder'];
			}

			if ( ! empty( $setting['class'] ) ) {
				$options['class'] = $setting['class'];
			}

			if ( ! empty( $setting['desc'] ) ) {
				$options['desc'] = $setting['desc'];
			}

			if ( ! empty( $setting['suffix'] ) ) {
				$options['suffix'] = $setting['suffix'];
			}

			if ( ! empty( $setting['min'] ) ) {
				$options['min'] = $setting['min'];
			}

			if ( ! empty( $setting['max'] ) ) {
				$options['max'] = $setting['max'];
			}

			if ( ! empty( $setting['step'] ) ) {
				$options['step'] = $setting['step'];
			}

			if ( ! empty( $setting['html'] ) ) {
				$options['html'] = $setting['html'];
			}

			if ( ! empty( $setting['field_class'] ) ) {
				$options['field_class'] = $setting['field_class'];
			}

			if ( ! empty( $setting['multiple'] ) ) {
				$options['multiple'] = $setting['multiple'];
			}

			add_settings_field(
				$key,
				$setting['title'],
				array( $this, 'settings_field' ),
				'wpzerospam',
				'zerospam_' . $setting['section'],
				$options
			);
		}
	}

	/**
	 * Settings section
	 */
	public function settings_section( $arg ) {
	}

	/**
	 * Settings field
	 */
	public function settings_field( $args ) {
		switch ( $args['type'] ) {
			case 'html':
				echo $args['html'];
				break;
			case 'textarea':
				?>
				<textarea
					id="<?php echo esc_attr( $args['label_for'] ); ?>"
					name="wpzerospam[<?php echo esc_attr( $args['label_for'] ); ?>]"
					rows="5"
					<?php if ( ! empty( $args['field_class'] ) ) : ?>
						class="<?php echo esc_attr( $args['field_class'] ); ?>"
					<?php endif; ?>
					<?php if ( ! empty( $args['placeholder'] ) ) : ?>
						placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
					<?php endif; ?>
				><?php if( ! empty( $args['value'] ) ) : ?><?php echo esc_attr( $args['value'] ); ?><?php endif; ?></textarea>
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
					name="wpzerospam[<?php echo esc_attr( $args['label_for'] ); ?>]"
					type="<?php echo esc_attr( $args['type'] ); ?>"
					<?php if( ! empty( $args['value'] ) ) : ?>
						value="<?php echo esc_attr( $args['value'] ); ?>"
					<?php endif; ?>
					<?php if ( ! empty( $args['field_class'] ) ) : ?>
						class="<?php echo esc_attr( $args['field_class'] ); ?>"
					<?php endif; ?>
					<?php if ( ! empty( $args['placeholder'] ) ) : ?>
						placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
					<?php endif; ?>
					<?php if( ! empty( $args['min'] ) ) : ?>
						min="<?php echo esc_attr( $args['min'] ); ?>"
					<?php endif; ?>
					<?php if( ! empty( $args['max'] ) ) : ?>
						max="<?php echo esc_attr( $args['max'] ); ?>"
					<?php endif; ?>
					<?php if( ! empty( $args['step'] ) ) : ?>
						step="<?php echo esc_attr( $args['step'] ); ?>"
					<?php endif; ?>
				/>
				<?php
				break;
			case 'select':
				if ( empty( $args['options'] ) ) {
					return;
				}

				$name = 'wpzerospam[' . esc_attr( $args['label_for'] ) . ']';
				if ( ! empty( $args['multiple'] ) ) :
					$name = 'wpzerospam[' . esc_attr( $args['label_for'] ) . '][]';
				endif;
				?>
				<select
					id="<?php echo esc_attr( $args['label_for'] ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
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
							if ( ! empty( $args['multiple'] ) && is_array( $args['value'] ) ) :
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
								<?php esc_html_e( $label ); ?>
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
					$name     = 'wpzerospam[' . esc_attr( $args['label_for'] ) . ']';
					if ( count( $args['options'] ) > 1 && 'checkbox' === $args['type'] ) {
						$name .= '[' . esc_attr( $key ) . ']';
					}

					if ( ! empty( $args['value'] ) && $args['value'] == $key ) {
						$selected = true;
					}

					?>
					<label for="<?php echo esc_attr( $args['label_for'] . $key ); ?>">
						<input
							type="<?php echo esc_attr( $args['type'] ); ?>"
							id="<?php echo esc_attr( $args['label_for'] . $key ); ?>"
							name="<?php echo esc_attr( $name ); ?>"
							value="<?php echo esc_attr( $key ); ?>"
							<?php if ( ! empty( $args['field_class'] ) ) : ?>
								class="<?php echo esc_attr( $args['field_class'] ); ?>"
							<?php endif; ?>
							<?php if ( $selected ) : ?>
								checked="checked"
							<?php endif; ?>
						/>
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
					</label><br />
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
	}

	/**
	 * Settings page
	 */
	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php require ZEROSPAM_PATH . 'includes/templates/admin-callout.php'; ?>

			<?php if ( ! empty( $_GET['zerospam-error'] ) ): ?>
				<div class="notice notice-error is-dismissible">
					<p><strong>
						<?php
						switch( intval( $_GET['zerospam-error'] ) ) :
							case 1:
								esc_html_e( 'There was a problem importing the settings JSON. Please try again.', 'zerospam' );
								break;
						endswitch;
						?>
					</strong></p>
					<button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'zerospam' ); ?></span></button>
				</div>
			<?php elseif ( ! empty( $_GET['zerospam-success'] ) ): ?>
				<div class="notice notice-success is-dismissible">
					<p><strong><?php esc_html_e( 'The settings JSON has been successfully imported.', 'zerospam' ); ?></strong></p>
					<button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'zerospam' ); ?>.</span></button>
				</div>
			<?php endif; ?>

			<form action="options.php" method="post">
			<?php
			// Output security fields for the registered setting "wpzerospam".
			settings_fields( 'wpzerospam' );

			echo '<div class="zerospam-settings-tabs">';
			// Output setting sections and their fields.
			do_settings_sections( 'wpzerospam' );

			// Output save settings button.
			submit_button( 'Save Settings' );
			?>
			</form>

			<h3><?php esc_html_e( 'Settings Import/Export', 'zerospam' ); ?></h3>
			<p><?php esc_html_e( 'Quickly export and import your saved settings into other sites below.', 'zerospam' ); ?></p>
			<?php
			$settings      = ZeroSpam\Core\Settings::get_settings();
			$settings_json = array();
			foreach ( $settings as $key => $data ) {
				if ( isset( $data['value'] ) ) {
					$settings_json[ $key ] = $data['value'];
				}
			}
			?>
			<div class="zerospam-export-import-block">
				<div class="zerospam-export-import-block-column">
					<h4><?php esc_html_e( 'Settings JSON', 'zerospam' ); ?></h4>
					<textarea readonly class="large-text code" rows="10"><?php echo wp_json_encode( $settings_json ); ?></textarea>
				</div>
				<div class="zerospam-export-import-block-column">
					<h4><?php esc_html_e( 'Paste the settings JSON to import.', 'zerospam' ); ?></h4>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="zerospam-import-settings-form">
					<?php wp_nonce_field( 'import_settings', 'zerospam' ); ?>
					<input type="hidden" name="action" value="import_settings" />
					<input type="hidden" name="redirect" value="<?php echo esc_url( ZeroSpam\Core\Utilities::current_url() ); ?>" />
					<textarea class="large-text code" name="settings" rows="10"></textarea>
					<input type="submit" class="button button-primary" value="<?php esc_html_e( 'Import Settings', 'zerospam' ); ?>" />
					</form>
				</div>
			</div>
			<?php echo '</div>'; ?>
		</div>
		<?php
	}
}
