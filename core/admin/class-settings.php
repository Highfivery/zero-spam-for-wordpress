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
	}

	/**
	 * Imports settings
	 */
	public function import_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( admin_url( self::$base_admin_link . '&subview=export&zerospam-error=1' ) );
			exit;
		}

		$redirect_base = admin_url( self::$base_admin_link . '&subview=export' );

		if ( empty( $_POST['zerospam'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zerospam'] ) ), 'import_settings' ) ) {
			wp_safe_redirect( $redirect_base . '&zerospam-error=1' );
			exit;
		}

		if ( ! isset( $_POST['settings'] ) ) {
			wp_safe_redirect( $redirect_base . '&zerospam-error=1' );
			exit;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is validated by json_decode().
		$settings_json = wp_unslash( $_POST['settings'] );

		if ( '' === trim( $settings_json ) ) {
			wp_safe_redirect( $redirect_base . '&zerospam-error=1' );
			exit;
		}

		$settings = json_decode( $settings_json, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $settings ) ) {
			wp_safe_redirect( $redirect_base . '&zerospam-error=1' );
			exit;
		}

		foreach ( $settings as $module => $module_settings ) {
			$module = sanitize_key( $module );

			if ( '' === $module || ! is_array( $module_settings ) ) {
				continue;
			}

			update_option( "zero-spam-{$module}", $module_settings, true );
		}

		wp_safe_redirect( $redirect_base . '&zerospam-success=1' );
		exit;
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
		$base_admin_link = self::$base_admin_link;

		$action = ! empty( $_REQUEST['zerospam-action'] ) ? sanitize_key( wp_unslash( $_REQUEST['zerospam-action'] ) ) : '';

		if ( 'autoconfigure' === $action && check_admin_referer( 'autoconfigure', 'zero-spam' ) ) {
			\ZeroSpam\Core\Settings::auto_configure();

			$message = __( 'Zero Spam for WordPress has successfully been auto-configured with the recommended settings.', 'zero-spam' );

			$redirect_url = add_query_arg(
				array(
					'subview'       => 'settings',
					'zerospam-msg'  => rawurlencode( $message ),
					'zerospam-type' => 'success',
				),
				admin_url( $base_admin_link )
			);

			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( 'update-blocked-emails' === $action && check_admin_referer( 'update-blocked-emails', 'zero-spam' ) ) {
			\ZeroSpam\Core\Settings::update_blocked_email_domains();

			$message = __( 'Zero Spam for WordPress blocked email domains have been successfully updated.', 'zero-spam' );

			$redirect_url = add_query_arg(
				array(
					'subview'       => 'settings',
					'zerospam-msg'  => rawurlencode( $message ),
					'zerospam-type' => 'success',
				),
				admin_url( $base_admin_link )
			);

			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( 'regenerate-honeypot' === $action && check_admin_referer( 'regenerate-honeypot', 'zero-spam' ) ) {
			$this->regenerate_honeypot();

			$message = __( 'Zero Spam for WordPress honeypot ID has been successfully reset.', 'zero-spam' );

			$redirect_url = add_query_arg(
				array(
					'subview'       => 'settings',
					'zerospam-msg'  => rawurlencode( $message ),
					'zerospam-type' => 'success',
				),
				admin_url( $base_admin_link )
			);

			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( 'update-disallowed-words' === $action && check_admin_referer( 'update-disallowed-words', 'zero-spam' ) ) {
			\ZeroSpam\Core\Settings::update_disallowed_words();

			$message = __( "WordPress's disallowed words list has been successfully updated to the recommended.", 'zero-spam' );

			$redirect_url = add_query_arg(
				array(
					'subview'       => 'settings',
					'zerospam-msg'  => rawurlencode( $message ),
					'zerospam-type' => 'success',
				),
				admin_url( $base_admin_link )
			);

			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( 'delete-error-log' === $action && check_admin_referer( 'delete-error-log', 'zero-spam' ) ) {
			\ZeroSpam\Core\Utilities::delete_error_log();

			$message = __( 'Zero Spam for WordPress error log has been successfully deleted.', 'zero-spam' );

			$redirect_url = add_query_arg(
				array(
					'subview'       => 'errors',
					'zerospam-msg'  => rawurlencode( $message ),
					'zerospam-type' => 'success',
				),
				admin_url( $base_admin_link )
			);

			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( 'delete-location-block' === $action && check_admin_referer( 'delete-location-block', 'zero-spam' ) ) {
			$block_id = ! empty( $_REQUEST['zerospam-id'] ) ? absint( wp_unslash( $_REQUEST['zerospam-id'] ) ) : 0;

			if ( $block_id ) {
				\ZeroSpam\Includes\DB::delete( 'blocked', 'blocked_id', $block_id );
			}

			$message      = __( 'The record has been successfully deleted.', 'zero-spam' );
			$redirect_url = add_query_arg(
				array(
					'subview'       => 'blocked-locations',
					'zerospam-msg'  => rawurlencode( $message ),
					'zerospam-type' => 'success',
				),
				admin_url( 'index.php?page=wordpress-zero-spam-dashboard' )
			);

			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( 'delete-ip-block' === $action && check_admin_referer( 'delete-ip-block', 'zero-spam' ) ) {
			$block_id = ! empty( $_REQUEST['zerospam-id'] ) ? absint( wp_unslash( $_REQUEST['zerospam-id'] ) ) : 0;

			if ( $block_id ) {
				\ZeroSpam\Includes\DB::delete( 'blocked', 'blocked_id', $block_id );
			}

			$message      = __( 'The record has been successfully deleted.', 'zero-spam' );
			$redirect_url = add_query_arg(
				array(
					'subview'       => 'blocked-ips',
					'zerospam-msg'  => rawurlencode( $message ),
					'zerospam-type' => 'success',
				),
				admin_url( 'index.php?page=wordpress-zero-spam-dashboard' )
			);

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
	 * @return array
	 */
	public function settings_validation( $input ) {
		$input = is_array( $input ) ? $input : array();

		// If the Zero Spam license has been submitted, verify it.
		if ( ! empty( $input['zerospam_license'] ) ) {
			$license = \ZeroSpam\Modules\Zero_Spam::get_license( $input['zerospam_license'] );
			if ( empty( $license['license_key'] ) ) {
				\ZeroSpam\Core\Utilities::log( 'Zero Spam: invalid license key entered.' );
				$input['zerospam_license'] = __( 'Invalid license entered.', 'zero-spam' );
				$input['zerospam']         = false;
			}
		}

		// Check if the correct version of Gravity Forms is installed.
		if ( ! empty( $input['verify_gravityforms'] ) && 'enabled' === $input['verify_gravityforms'] ) {
			$data = get_plugin_data( ABSPATH . 'wp-content/plugins/gravityforms/gravityforms.php' );
			if ( ! empty( $data['Version'] ) && ! version_compare( $data['Version'], '2.7', '>=' ) ) {
				\ZeroSpam\Core\Utilities::log( 'Gravity Forms: requires at least v2.7' );
				$input['verify_gravityforms'] = false;
			}
		}

		// Handle blocked email domains separate option to prevent autoloading.
		if ( isset( $input['blocked_email_domains'] ) ) {
			if ( update_option( 'zerospam_blocked_email_domains', $input['blocked_email_domains'] ) ) {
				// Prevent autoloading large options.
				// @see https://10up.github.io/Engineering-Best-Practices/php/#performance
				wp_cache_delete( 'zerospam_blocked_email_domains', 'options' );
				global $wpdb;
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE $wpdb->options SET autoload = %s WHERE option_name = %s",
						'no',
						'zerospam_blocked_email_domains'
					)
				);
			}
			unset( $input['blocked_email_domains'] );
		}

		update_option( 'zerospam_configured', 1 );

		return $input;
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		foreach ( \ZeroSpam\Core\Settings::get_sections() as $key => $section ) {
			register_setting(
				"zero-spam-$key", // Group.
				"zero-spam-$key", // Name.
				array(
					'sanitize_callback' => array( $this, 'settings_validation' ),
				)
			);

			add_settings_section(
				'zero-spam-' . $key,
				$section['title'],
				array( $this, 'settings_section' ),
				'zero-spam-' . $key // Page.
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
				'zero-spam-' . $setting['module'], // Page.
				'zero-spam-' . $setting['module'], // Section.
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
		$setting_name = 'zero-spam-' . $args['module'] . '[' . $args['label_for'] . ']';

		$allowed_desc_html = array(
			'a'      => array(
				'target' => array(),
				'href'   => array(),
				'class'  => array(),
				'rel'    => array(),
			),
			'strong' => array(),
			'b'      => array(),
			'code'   => array(),
			'br'     => array(),
			'p'      => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'div'    => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'span'   => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'ul'     => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'ol'     => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'li'     => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
		);

		$allowed_field_html = array(
			'strong' => array(),
			'br'     => array(),
			'p'      => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'div'    => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'span'   => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'ul'     => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'ol'     => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'li'     => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'a'      => array(
				'href'   => array(),
				'target' => array(),
				'class'  => array(),
				'rel'    => array(),
			),
			'em'     => array(),
			'code'   => array(),
			'h1'     => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'h2'     => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'h3'     => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'h4'     => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'h5'     => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'h6'     => array(
				'id'    => array(),
				'class' => array(),
				'style' => array(),
			),
			'input'  => array(
				'id'               => array(),
				'name'             => array(),
				'type'             => array(),
				'value'            => array(),
				'class'            => array(),
				'placeholder'      => array(),
				'style'            => array(),
				'aria-describedby' => array(),
				'aria-label'       => array(),
				'aria-labelledby'  => array(),
			),
			'label'  => array(
				'id'    => array(),
				'for'   => array(),
				'class' => array(),
				'style' => array(),
			),
		);

		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . wp_kses( $args['desc'], $allowed_desc_html ) . '</p>';
		}

		echo '<div class="zerospam-form-field-container zerospam-form-field-container--' . esc_attr( $args['type'] ) . '">';

		switch ( $args['type'] ) {
			case 'html':
				echo wp_kses( $args['html'], $allowed_field_html );
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
				><?php
				if ( ! empty( $args['value'] ) ) :
					echo trim( esc_textarea( $args['value'] ) );
				endif;
				?></textarea>
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
					echo '</div>';
					return;
				}

				if ( ! empty( $args['multiple'] ) ) {
					$setting_name .= '[]';
				}
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
					<?php foreach ( $args['options'] as $key => $label ) : ?>
						<?php
						$selected = false;

						if ( ! empty( $args['value'] ) && ! empty( $args['multiple'] ) && is_array( $args['value'] ) ) {
							if ( in_array( $key, $args['value'], true ) ) {
								$selected = true;
							}
						} elseif ( ! empty( $args['value'] ) && $args['value'] == $key ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
							$selected = true;
						}
						?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php
				break;

			case 'checkbox':
			case 'radio':
				if ( empty( $args['options'] ) ) {
					echo '</div>';
					return;
				}

				echo '<div class="zerospam-form-field-group">';

				foreach ( $args['options'] as $key => $label ) {
					$field_name = $setting_name;
					$selected   = false;

					if ( count( $args['options'] ) > 1 && 'checkbox' === $args['type'] ) {
						$field_name .= '[' . $key . ']';
					}

					if ( ! empty( $args['value'] ) && $args['value'] == $key ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
						$selected = true;
					}
					?>
					<input
						type="<?php echo esc_attr( $args['type'] ); ?>"
						id="<?php echo esc_attr( $args['label_for'] . $key ); ?>"
						name="<?php echo esc_attr( $field_name ); ?>"
						value="<?php echo esc_attr( $key ); ?>"
						<?php if ( ! empty( $args['field_class'] ) ) : ?>
							class="<?php echo esc_attr( $args['field_class'] ); ?>"
						<?php endif; ?>
						<?php checked( $selected ); ?>
					/>

					<label for="<?php echo esc_attr( $args['label_for'] . $key ); ?>">
						<?php
						echo wp_kses(
							$label,
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
						);
						?>
					</label>
					<br />
					<?php
				}

				echo '</div>';
				break;
		}

		if ( ! empty( $args['suffix'] ) ) {
			echo wp_kses(
				$args['suffix'],
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
			);
		}

		echo '</div>';
	}

	/**
	 * Settings page
	 */
	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$base_admin_link = self::$base_admin_link;

		$subview = ! empty( $_REQUEST['subview'] )
			? sanitize_key( wp_unslash( $_REQUEST['subview'] ) )
			: 'settings';

		$sections = apply_filters( 'zerospam_setting_sections', array() );
		?>
		<?php require ZEROSPAM_PATH . 'includes/templates/admin-header.php'; ?>
		<div class="wrap">
			<div class="zerospam-dashboard">
				<div class="zerospam-dashboard__col">
					<ul class="zerospam-dashboard__sections">
						<li>
							<a href="<?php echo esc_url( admin_url( $base_admin_link . '&subview=documentation' ) ); ?>"
								class="zerospam-dashboard__menu-link <?php echo ( 'documentation' === $subview ) ? 'zerospam-dashboard__menu-link--active' : ''; ?>">
								<img
									src="<?php echo esc_url( plugin_dir_url( ZEROSPAM ) . 'assets/img/icon-docs.png' ); ?>"
									class="zerospam-dashboard__menu-icon"
									alt=""
									aria-hidden="true"
								/>
								<?php esc_html_e( 'Documentation', 'zero-spam' ); ?>
							</a>
						</li>

						<li>
							<a href="<?php echo esc_url( admin_url( $base_admin_link . '&subview=settings' ) ); ?>"
								class="zerospam-dashboard__menu-link <?php echo ( 'settings' === $subview ) ? 'zerospam-dashboard__menu-link--active' : ''; ?>">
								<img
									src="<?php echo esc_url( plugin_dir_url( ZEROSPAM ) . 'assets/img/icon-settings.svg' ); ?>"
									class="zerospam-dashboard__menu-icon"
									alt=""
									aria-hidden="true"
								/>
								<?php esc_html_e( 'Settings', 'zero-spam' ); ?>
							</a>
						</li>

						<?php foreach ( $sections as $key => $section ) : ?>
							<li>
								<a href="<?php echo esc_url( admin_url( $base_admin_link . '&subview=' . sanitize_key( (string) $key ) ) ); ?>"
									class="zerospam-dashboard__menu-link <?php echo ( sanitize_key( (string) $key ) === $subview ) ? 'zerospam-dashboard__menu-link--active' : ''; ?>">
									<?php if ( ! empty( $section['icon'] ) ) : ?>
										<img
											src="<?php echo esc_url( plugin_dir_url( ZEROSPAM ) . ltrim( (string) $section['icon'], '/' ) ); ?>"
											class="zerospam-dashboard__menu-icon"
											alt=""
											aria-hidden="true"
										/>
									<?php endif; ?>
									<?php echo esc_html( (string) $section['title'] ); ?>
								</a>
							</li>
						<?php endforeach; ?>

						<li>
							<a href="<?php echo esc_url( admin_url( $base_admin_link . '&subview=export' ) ); ?>"
								class="zerospam-dashboard__menu-link <?php echo ( 'export' === $subview ) ? 'zerospam-dashboard__menu-link--active' : ''; ?>">
								<img
									src="<?php echo esc_url( plugin_dir_url( ZEROSPAM ) . 'assets/img/icon-export.svg' ); ?>"
									class="zerospam-dashboard__menu-icon"
									alt=""
									aria-hidden="true"
								/>
								<?php esc_html_e( 'Import/Export Settings', 'zero-spam' ); ?>
							</a>
						</li>

						<li>
							<a href="<?php echo esc_url( admin_url( $base_admin_link . '&subview=errors' ) ); ?>"
								class="zerospam-dashboard__menu-link <?php echo ( 'errors' === $subview ) ? 'zerospam-dashboard__menu-link--active' : ''; ?>">
								<img
									src="<?php echo esc_url( plugin_dir_url( ZEROSPAM ) . 'assets/img/icon-error.svg' ); ?>"
									class="zerospam-dashboard__menu-icon"
									alt=""
									aria-hidden="true"
								/>
								<?php esc_html_e( 'Error Log', 'zero-spam' ); ?>
							</a>
						</li>
					</ul>
				</div>

				<div class="zerospam-dashboard__col">
					<?php if ( ! empty( $_REQUEST['zerospam-msg'] ) ) : ?>
						<div class="zerospam-block zerospam-block--notice zerospam-block--<?php echo ! empty( $_REQUEST['zerospam-type'] ) ? esc_attr( sanitize_key( wp_unslash( $_REQUEST['zerospam-type'] ) ) ) : 'default'; ?>">
							<div class="zerospam-block__content">
								<?php echo esc_html( sanitize_text_field( wp_unslash( $_REQUEST['zerospam-msg'] ) ) ); ?>
							</div>
						</div>
					<?php endif; ?>

					<?php
					foreach ( $sections as $key => $section ) :
						if ( sanitize_key( (string) $key ) === $subview && ! empty( $section['supports'] ) ) :
							?>
							<div class="zerospam-block">
								<div class="zerospam-block__content zerospam-block__content--supports">
									<strong><?php esc_html_e( 'Supported Signals', 'zero-spam' ); ?>:</strong>
									<?php
									foreach ( $section['supports'] as $s ) :
										switch ( $s ) :
											case 'honeypot':
												echo '<img class="zerospam-small-icon" src="' . esc_url( plugin_dir_url( ZEROSPAM ) . 'assets/img/icon-honeypot.svg' ) . '" alt="' . esc_attr__( 'Honeypot', 'zero-spam' ) . '" />';
												break;
											case 'email':
												echo '<img class="zerospam-small-icon" src="' . esc_url( plugin_dir_url( ZEROSPAM ) . 'assets/img/icon-email.svg' ) . '" alt="' . esc_attr__( 'Email Protection', 'zero-spam' ) . '" title="' . esc_attr__( 'Email Protection', 'zero-spam' ) . '" />';
												break;
											case 'davidwalsh':
												echo '<img class="zerospam-small-icon" src="' . esc_url( plugin_dir_url( ZEROSPAM ) . 'modules/davidwalsh/icon-david-walsh.png' ) . '" alt="' . esc_attr__( 'David Walsh', 'zero-spam' ) . '" />';
												break;
											case 'words':
												echo '<img class="zerospam-small-icon" src="' . esc_url( plugin_dir_url( ZEROSPAM ) . 'assets/img/icon-words.svg' ) . '" alt="' . esc_attr__( 'Disallowed Words', 'zero-spam' ) . '" title="' . esc_attr__( 'Disallowed Words', 'zero-spam' ) . '" />';
												break;
										endswitch;
									endforeach;
									?>
								</div>
							</div>
							<?php
						endif;
					endforeach;
					?>

					<?php if ( 'documentation' === $subview ) : ?>
						<?php require ZEROSPAM_PATH . 'includes/templates/admin-documentation.php'; ?>
					<?php elseif ( ! in_array( $subview, array( 'export', 'errors' ), true ) ) : ?>
						<?php
						switch ( $subview ) :
							case 'zerospam':
								$license_key         = \ZeroSpam\Core\Settings::get_settings( 'zerospam_license' );
								$license_key_enabled = \ZeroSpam\Core\Settings::get_settings( 'zerospam' );
								if ( ! $license_key || 'enabled' !== $license_key_enabled ) :
									?>
									<div class="zerospam-block zerospam-block--callout">
										<div class="zerospam-block__content">
											<div class="zerospam-block__grid">
												<div class="zerospam-block__grid-column">
													<?php
													printf(
														wp_kses(
															/* translators: 1: Zero Spam URL, 2: Zero Spam URL. */
															__(
																'<h3>Super-charge your protection with a <a href="%1$s" target="_blank" rel="noopener noreferrer">Zero Spam license</a>!</h3><p>Enable enhanced protection with a Zero Spam API license — one of the largest, most comprehensive, constantly-growing global malicious IP and email databases available. Once enabled, all visitors will be checked against Zero Spam\'s blacklist and can help prevent <a href="%2$s" target="_blank" rel="noopener noreferrer">DDoS attacks</a> &amp; fraudsters looking to test stolen credit card numbers.</p><p>Zero Spam is comprised of a global detection network of over 30,000+ apps and sites that monitor traffic and usage in real-time to detect malicious activity. <a href="%2$s" target="_blank" rel="noopener noreferrer">Subscribe today</a> for enhanced protection.</p>',
																'zero-spam'
															),
															array(
																'h3'     => array(),
																'p'      => array(),
																'a'      => array(
																	'href'   => array(),
																	'class'  => array(),
																	'rel'    => array(),
																	'target' => array(),
																),
																'strong' => array(),
															)
														),
														esc_url( ZEROSPAM_URL . 'subscribe/' ),
														esc_url( ZEROSPAM_URL . 'subscribe/' )
													);
													?>
													<p>
														<a href="<?php echo esc_url( ZEROSPAM_URL . 'subscribe/' ); ?>" target="_blank" rel="noreferrer noopener" class="button button-primary">
															<?php esc_html_e( 'Get a License', 'zero-spam' ); ?> →
														</a>
														<a href="<?php echo esc_url( ZEROSPAM_URL ); ?>" target="_blank" rel="noreferrer noopener" class="button">
															<?php esc_html_e( 'Learn More', 'zero-spam' ); ?> →
														</a>
													</p>
												</div>
												<div class="zerospam-block__grid-column">
													<ul class="zerospam-list zerospam-list--checks">
														<li><?php esc_html_e( 'Real-time malicious IP monitoring', 'zero-spam' ); ?></li>
														<li><?php esc_html_e( 'Block reported spammy email addresses', 'zero-spam' ); ?></li>
														<li><?php esc_html_e( 'Helps prevent DDoS attacks', 'zero-spam' ); ?></li>
														<li><?php esc_html_e( 'Detailed IP & email address reports', 'zero-spam' ); ?></li>
														<li><?php esc_html_e( 'Helps prevents stolen credit card testing', 'zero-spam' ); ?></li>
													</ul>
												</div>
											</div>
										</div>
									</div>
									<?php
								endif;
								break;
						endswitch;
						?>

						<form action="options.php" method="post" class="zerospam-form">
							<?php
							settings_fields( "zero-spam-$subview" );
							do_settings_sections( "zero-spam-$subview" );
							submit_button( esc_html__( 'Save Settings →', 'zero-spam' ) );
							?>
						</form>

					<?php elseif ( 'export' === $subview ) : ?>

						<?php if ( ! empty( $_GET['zerospam-error'] ) ) : ?>
							<div class="notice notice-error is-dismissible">
								<p><strong>
									<?php
									switch ( absint( $_GET['zerospam-error'] ) ) :
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
								<button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'zero-spam' ); ?></span></button>
							</div>
						<?php endif; ?>

						<div class="zerospam-form">
							<h2><?php esc_html_e( 'Settings Import/Export', 'zero-spam' ); ?></h2>
							<?php
							$modules = \ZeroSpam\Core\Settings::get_settings_by_module();
							foreach ( $modules as $module => $module_settings ) :
								foreach ( $module_settings as $setting_key => $setting ) :
									$modules[ $module ][ $setting_key ] = ! empty( $setting['value'] ) ? $setting['value'] : false;
								endforeach;
							endforeach;
							?>
							<div class="zerospam-export-import-block">
								<div class="zerospam-export-import-block-column">
									<h4><?php esc_html_e( 'Export Settings', 'zero-spam' ); ?></h4>
									<textarea readonly class="large-text code" rows="25"><?php echo esc_textarea( wp_json_encode( $modules ) ); ?></textarea>
								</div>
								<div class="zerospam-export-import-block-column">
									<h4><?php esc_html_e( 'Import Settings', 'zero-spam' ); ?></h4>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="zerospam-import-settings-form">
										<?php wp_nonce_field( 'import_settings', 'zerospam' ); ?>
										<input type="hidden" name="action" value="import_settings" />
										<textarea class="large-text code" name="settings" rows="25"></textarea>
										<input type="submit" class="button button-primary" value="<?php echo esc_attr__( 'Import Settings →', 'zero-spam' ); ?>" />
									</form>
								</div>
							</div>
						</div>

					<?php elseif ( 'errors' === $subview ) : ?>

						<?php
						$log = \ZeroSpam\Core\Utilities::get_error_log();
						if ( ! $log ) :
							?>
							<div class="zerospam-block">
								<div class="zerospam-block__content">
									<?php esc_html_e( 'No errors have been reported.', 'zero-spam' ); ?>
								</div>
							</div>
						<?php else : ?>
							<div class="zerospam-form">
								<textarea readonly class="large-text code" rows="30"><?php echo esc_textarea( (string) $log ); ?></textarea>
								<a
									href="<?php echo esc_url( wp_nonce_url( admin_url( $base_admin_link . '&zerospam-action=delete-error-log' ), 'delete-error-log', 'zero-spam' ) ); ?>"
									class="button button-primary"
								>
									<?php esc_html_e( 'Clear Error Log →', 'zero-spam' ); ?>
								</a>
							</div>
						<?php endif; ?>

					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
