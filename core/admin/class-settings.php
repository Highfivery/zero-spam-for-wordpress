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
		register_setting(
			'wpzerospam',
			'wpzerospam',
			array(
				'sanitize_callback' => array( $this, 'settings_validation' ),
			)
		);

		foreach ( \ZeroSpam\Core\Settings::get_sections() as $key => $section ) {
			add_settings_section(
				'zerospam_' . $key,
				$section['title'],
				array( $this, 'settings_section' ),
				'wpzerospam'
			);
		}

		foreach ( \ZeroSpam\Core\Settings::get_settings() as $key => $setting ) {
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
				! empty( $setting['title'] ) ? $setting['title'] : false,
				array( $this, 'settings_field' ),
				'wpzerospam',
				'zerospam_' . $setting['section'],
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
					name="wpzerospam[<?php echo esc_attr( $args['label_for'] ); ?>]"
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
					name="wpzerospam[<?php echo esc_attr( $args['label_for'] ); ?>]"
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

		$base_admin_link = 'options-general.php?page=wordpress-zero-spam-settings';
		// @codingStandardsIgnoreLine
		$current_tab = ! empty( $_REQUEST['tab'] ) ? sanitize_text_field( $_REQUEST['tab'] ) : 'settings';
		$admin_tabs  = array(
			'settings' => array(
				'title'    => __( 'Settings', 'zero-spam' ),
				'template' => 'settings',
			),
			'export'   => array(
				'title'    => __( 'Export/Import Settings', 'zero-spam' ),
				'template' => 'export',
			),
			'error'    => array(
				'title'    => __( 'Error Log', 'zero-spam' ),
				'template' => 'errors',
			),
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php require ZEROSPAM_PATH . 'includes/templates/admin-callout.php'; ?>

			<nav class="nav-tab-wrapper" style="margin-bottom: 16px;">
				<?php
				foreach ( $admin_tabs as $key => $tab ) :
					$admin_url = admin_url( $base_admin_link . '&amp;tab=' . $key );
					$classes   = array( 'nav-tab' );

					if ( $current_tab === $key ) :
						$classes[] = 'nav-tab-active';
					endif;
					?>
					<a
						href="<?php echo esc_url( $admin_url ); ?>"
						class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
					>
						<?php echo esc_html( $tab['title'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php require ZEROSPAM_PATH . 'includes/templates/settings/' . $admin_tabs[ $current_tab ]['template'] . '.php'; ?>
		</div>
		<?php
	}
}
