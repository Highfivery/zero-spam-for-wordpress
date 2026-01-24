<?php
/**
 * Network Settings Page - Admin Interface
 *
 * Creates the dedicated Network Admin page for managing Zero Spam settings
 * across all sites in a multisite network.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes\Admin;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Network Settings Page class
 */
class Network_Settings_Page {

	/**
	 * Settings Manager instance
	 *
	 * @var \ZeroSpam\Includes\Network_Settings_Manager
	 */
	private $settings_manager;

	/**
	 * Network Settings instance
	 *
	 * @var \ZeroSpam\Includes\Network_Settings
	 */
	private $network_settings;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Only for multisite.
		if ( ! is_multisite() ) {
			return;
		}

		$this->settings_manager = new \ZeroSpam\Includes\Network_Settings_Manager();
		$this->network_settings = new \ZeroSpam\Includes\Network_Settings();

		// Network admin UI hooks - only in network admin.
		if ( is_network_admin() ) {
			add_action( 'network_admin_menu', array( $this, 'add_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		}
		
		// AJAX handlers - must be registered globally, not just in network admin context.
		add_action( 'wp_ajax_zerospam_network_set_setting', array( $this, 'ajax_set_setting' ) );
		add_action( 'wp_ajax_zerospam_network_lock_setting', array( $this, 'ajax_lock_setting' ) );
		add_action( 'wp_ajax_zerospam_network_unlock_setting', array( $this, 'ajax_unlock_setting' ) );
		add_action( 'wp_ajax_zerospam_network_apply_all', array( $this, 'ajax_apply_all' ) );
		add_action( 'wp_ajax_zerospam_network_reset_site', array( $this, 'ajax_reset_site' ) );
		add_action( 'wp_ajax_zerospam_network_get_comparison', array( $this, 'ajax_get_comparison' ) );
		add_action( 'wp_ajax_zerospam_network_export', array( $this, 'ajax_export' ) );
		add_action( 'wp_ajax_zerospam_network_import', array( $this, 'ajax_import' ) );
		add_action( 'wp_ajax_zerospam_network_apply_template', array( $this, 'ajax_apply_template' ) );
		add_action( 'wp_ajax_zerospam_network_save_template', array( $this, 'ajax_save_template' ) );
		add_action( 'wp_ajax_zerospam_network_delete_template', array( $this, 'ajax_delete_template' ) );
	}

	/**
	 * Add network admin menu
	 */
	public function add_menu() {
		add_submenu_page(
			'settings.php',
			__( 'Zero Spam Network Settings', 'zero-spam' ),
			__( 'Zero Spam Network', 'zero-spam' ),
			'manage_network_options',
			'zerospam-network-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue assets
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( $hook_suffix ) {
		// Check if this is our network settings page.
		// Network admin pages can have various hook suffix formats.
		if ( false === strpos( $hook_suffix, 'zerospam-network-settings' ) ) {
			return;
		}

		wp_enqueue_style(
			'zerospam-network-settings',
			plugin_dir_url( ZEROSPAM ) . 'assets/css/network-settings.css',
			array(),
			ZEROSPAM_VERSION
		);

		wp_enqueue_script(
			'zerospam-network-settings',
			plugin_dir_url( ZEROSPAM ) . 'assets/js/network-settings.js',
			array( 'jquery' ),
			ZEROSPAM_VERSION,
			true
		);

		wp_localize_script(
			'zerospam-network-settings',
			'zeroSpamNetwork',
			array(
				'nonce'    => wp_create_nonce( 'zerospam_network_settings' ),
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'strings'  => array(
					'confirm_apply'  => __( 'This will apply network settings to all sites. Continue?', 'zero-spam' ),
					'confirm_reset'  => __( 'This will reset the site to network defaults. Continue?', 'zero-spam' ),
					'confirm_import' => __( 'This will import settings. Existing settings may be overwritten. Continue?', 'zero-spam' ),
					'success'        => __( 'Success!', 'zero-spam' ),
					'error'          => __( 'An error occurred.', 'zero-spam' ),
					'locked'         => __( 'Locked', 'zero-spam' ),
					'unlocked'       => __( 'Unlocked', 'zero-spam' ),
				),
			)
		);
	}

	/**
	 * Render the page
	 */
	public function render_page() {
		// Check permissions.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'zero-spam' ) );
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'overview';

		?>
		<div class="wrap zerospam-network-settings">
			<h1><?php esc_html_e( 'Zero Spam Network Settings', 'zero-spam' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Manage Zero Spam settings across all sites in your network.', 'zero-spam' ); ?>
			</p>

			<nav class="nav-tab-wrapper">
				<a href="?page=zerospam-network-settings&tab=overview" class="nav-tab <?php echo 'overview' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Overview', 'zero-spam' ); ?>
				</a>
				<a href="?page=zerospam-network-settings&tab=settings" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'zero-spam' ); ?>
				</a>
				<a href="?page=zerospam-network-settings&tab=templates" class="nav-tab <?php echo 'templates' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Templates', 'zero-spam' ); ?>
				</a>
				<a href="?page=zerospam-network-settings&tab=audit" class="nav-tab <?php echo 'audit' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Audit Log', 'zero-spam' ); ?>
				</a>
				<a href="?page=zerospam-network-settings&tab=comparison" class="nav-tab <?php echo 'comparison' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Comparison', 'zero-spam' ); ?>
				</a>
				<a href="?page=zerospam-network-settings&tab=import-export" class="nav-tab <?php echo 'import-export' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Import/Export', 'zero-spam' ); ?>
				</a>
			</nav>

			<div class="zerospam-tab-content">
				<?php
				switch ( $active_tab ) {
					case 'overview':
						$this->render_overview_tab();
						break;
					case 'settings':
						$this->render_settings_tab();
						break;
					case 'templates':
						$this->render_templates_tab();
						break;
					case 'audit':
						$this->render_audit_tab();
						break;
					case 'comparison':
						$this->render_comparison_tab();
						break;
					case 'import-export':
						$this->render_import_export_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Overview Tab
	 */
	private function render_overview_tab() {
		$sites           = get_sites( array( 'number' => 0 ) );
		$total_sites     = count( $sites );
		$settings        = $this->settings_manager->get_all_with_status();
		$locked_count    = 0;
		$override_count  = 0;

		foreach ( $settings as $config ) {
			if ( $config['locked'] ) {
				$locked_count++;
			}
			$override_count += $config['overridden'];
		}

		?>
		<div class="zerospam-overview-section">
			<h2><?php esc_html_e( 'Network Summary', 'zero-spam' ); ?></h2>
			
			<div class="zerospam-stats-grid">
				<div class="zerospam-stat-card">
					<div class="stat-value"><?php echo esc_html( $total_sites ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Total Sites', 'zero-spam' ); ?></div>
				</div>

				<div class="zerospam-stat-card">
					<div class="stat-value"><?php echo esc_html( count( $settings ) ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Network Settings', 'zero-spam' ); ?></div>
				</div>

				<div class="zerospam-stat-card">
					<div class="stat-value"><?php echo esc_html( $locked_count ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Locked Settings', 'zero-spam' ); ?></div>
				</div>

				<div class="zerospam-stat-card">
					<div class="stat-value"><?php echo esc_html( $override_count ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Site Overrides', 'zero-spam' ); ?></div>
				</div>
			</div>

			<h3><?php esc_html_e( 'Quick Actions', 'zero-spam' ); ?></h3>
			<div class="zerospam-quick-actions">
				<button type="button" class="button button-primary" id="apply-to-all-sites">
					<?php esc_html_e( 'Apply to All Sites', 'zero-spam' ); ?>
				</button>
				<a href="?page=zerospam-network-settings&tab=comparison" class="button">
					<?php esc_html_e( 'Compare Sites', 'zero-spam' ); ?>
				</a>
				<a href="?page=zerospam-network-settings&tab=import-export" class="button">
					<?php esc_html_e( 'Export Configuration', 'zero-spam' ); ?>
				</a>
			</div>

			<?php if ( $override_count > 0 ) : ?>
			<div class="notice notice-info">
				<p>
					<?php
					printf(
						/* translators: %d: number of sites with custom overrides */
						esc_html__( 'There are %d site overrides across your network. Review the Comparison tab to see details.', 'zero-spam' ),
						esc_html( $override_count )
					);
					?>
				</p>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Settings Tab
	 */
	private function render_settings_tab() {
		$settings          = $this->settings_manager->get_all_with_status();
		$plugin_settings   = \ZeroSpam\Core\Settings::get_settings();
		$registered_fields = \ZeroSpam\Core\Settings::get_settings_by_module();

		// Group settings into logical categories.
		$grouped_settings = $this->group_settings( $registered_fields );

		?>
		<div class="zerospam-settings-section">
			<h2><?php esc_html_e( 'Network Settings', 'zero-spam' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Configure default settings for all sites. Lock settings to prevent site admins from changing them.', 'zero-spam' ); ?>
			</p>

			<?php foreach ( $grouped_settings as $group_id => $group ) : ?>
		<div class="settings-group">
			<div class="settings-group-header">
				<div class="group-header-content">
					<h3>
						<span class="dashicons dashicons-<?php echo esc_attr( $group['icon'] ); ?>"></span>
						<?php echo esc_html( $group['title'] ); ?>
					</h3>
					<p class="group-description"><?php echo esc_html( $group['description'] ); ?></p>
				</div>
				<button type="button" class="button settings-group-toggle" data-group="<?php echo esc_attr( $group_id ); ?>">
					<span class="dashicons dashicons-arrow-down-alt2"></span>
					<span class="button-text"><?php esc_html_e( 'Expand', 'zero-spam' ); ?></span>
				</button>
			</div>
					
					<div class="settings-group-content" id="group-<?php echo esc_attr( $group_id ); ?>" style="display: none;">
						<table class="wp-list-table widefat striped zerospam-settings-table">
							<thead>
								<tr>
									<th style="width: 30%;"><?php esc_html_e( 'Setting', 'zero-spam' ); ?></th>
									<th style="width: 25%;"><?php esc_html_e( 'Value', 'zero-spam' ); ?></th>
									<th style="width: 15%;"><?php esc_html_e( 'Sites', 'zero-spam' ); ?></th>
									<th style="width: 10%;"><?php esc_html_e( 'Lock', 'zero-spam' ); ?></th>
									<th style="width: 20%;"><?php esc_html_e( 'Actions', 'zero-spam' ); ?></th>
								</tr>
							</thead>
							<tbody>
							<?php
							foreach ( $group['settings'] as $field_key => $field ) {
								$setting_key = $field_key;
								$config      = $settings[ $setting_key ] ?? null;
								$current_value = $config ? $config['value'] : ( $plugin_settings[ $setting_key ]['value'] ?? '' );
								$locked        = $config && $config['locked'];
								$using_default = $config ? $config['using_default'] : 0;
								$overridden    = $config ? $config['overridden'] : 0;
								$total_sites   = $config ? $config['total_sites'] : count( get_sites( array( 'number' => 0 ) ) );

								// Get simplified description.
								$simple_desc = $this->get_simple_description( $setting_key, $field );

								?>
								<tr data-setting-key="<?php echo esc_attr( $setting_key ); ?>">
									<td>
										<strong><?php echo esc_html( $field['title'] ?? $setting_key ); ?></strong>
										<?php if ( ! empty( $simple_desc ) ) : ?>
											<p class="description"><?php echo wp_kses_post( $simple_desc ); ?></p>
										<?php endif; ?>
									</td>
									<td>
										<?php $this->render_setting_field( $setting_key, $field, $current_value ); ?>
									</td>
									<td>
										<div class="setting-status">
											<?php
											if ( $locked ) {
												echo '<span class="locked-badge">🔒</span>';
											}
											?>
											<div class="status-text">
												<?php
												printf(
													'%1$d/%2$d',
													esc_html( $using_default ),
													esc_html( $total_sites )
												);
												if ( $overridden > 0 ) {
													echo '<br><small style="color: #d63638;">(' . esc_html( $overridden ) . ' custom)</small>';
												}
												?>
											</div>
										</div>
									</td>
									<td>
										<button type="button" class="button button-small toggle-lock" data-locked="<?php echo $locked ? '1' : '0'; ?>" title="<?php echo $locked ? esc_attr__( 'Unlock to let sites customize', 'zero-spam' ) : esc_attr__( 'Lock to enforce on all sites', 'zero-spam' ); ?>">
											<?php echo $locked ? '🔓' : '🔒'; ?>
										</button>
									</td>
									<td>
										<button type="button" class="button button-primary button-small save-setting">
											<?php esc_html_e( 'Save', 'zero-spam' ); ?>
										</button>
										<?php if ( $overridden > 0 ) : ?>
											<button type="button" class="button button-small view-details" data-setting="<?php echo esc_attr( $setting_key ); ?>" title="<?php esc_attr_e( 'View which sites have custom values', 'zero-spam' ); ?>">
												<?php echo esc_html( $overridden ); ?>
											</button>
										<?php endif; ?>
									</td>
								</tr>
								<?php
							}
							?>
							</tbody>
						</table>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render a setting field
	 *
	 * @param string $key   Setting key.
	 * @param array  $field Field config.
	 * @param mixed  $value Current value.
	 */
	private function render_setting_field( $key, $field, $value ) {
		$type = $field['type'] ?? 'text';

		switch ( $type ) {
			case 'checkbox':
				$options = $field['options'] ?? array( 'enabled' => __( 'Enable', 'zero-spam' ) );
				foreach ( $options as $option_value => $option_label ) :
					?>
					<label>
						<input type="checkbox" 
							name="<?php echo esc_attr( $key ); ?>" 
							value="<?php echo esc_attr( $option_value ); ?>" 
							<?php checked( $value, $option_value ); ?> />
						<?php echo esc_html( is_string( $option_label ) ? $option_label : __( 'Enable', 'zero-spam' ) ); ?>
					</label><br />
					<?php
				endforeach;
				break;

			case 'radio':
				$options = $field['options'] ?? array();
				$allowed_html = array(
					'a'    => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
					'code' => array(),
				);
				foreach ( $options as $option_value => $option_label ) :
					?>
					<label style="display: block; margin-bottom: 8px;">
						<input type="radio" 
							name="<?php echo esc_attr( $key ); ?>" 
							value="<?php echo esc_attr( $option_value ); ?>" 
							<?php checked( $value, $option_value ); ?> />
						<?php echo wp_kses( $option_label, $allowed_html ); ?>
					</label>
					<?php
				endforeach;
				break;

			case 'select':
				$options  = $field['options'] ?? array();
				$multiple = ! empty( $field['multiple'] );
				?>
				<select name="<?php echo esc_attr( $key ); ?><?php echo $multiple ? '[]' : ''; ?>" 
					<?php echo $multiple ? 'multiple size="5" style="height: auto;"' : ''; ?>>
					<?php foreach ( $options as $option_value => $option_label ) : ?>
						<option value="<?php echo esc_attr( $option_value ); ?>" 
							<?php 
							if ( $multiple ) {
								selected( in_array( $option_value, (array) $value, true ), true );
							} else {
								selected( $value, $option_value ); 
							}
							?>>
							<?php echo esc_html( $option_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php
				break;

			case 'textarea':
				$field_class = $field['field_class'] ?? 'large-text';
				$placeholder = $field['placeholder'] ?? '';
				?>
				<textarea 
					name="<?php echo esc_attr( $key ); ?>" 
					class="<?php echo esc_attr( $field_class ); ?>" 
					rows="5"
					placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
				<?php
				break;

			case 'number':
				$field_class = $field['field_class'] ?? 'small-text';
				$placeholder = $field['placeholder'] ?? '';
				?>
				<input type="number" 
					name="<?php echo esc_attr( $key ); ?>" 
					value="<?php echo esc_attr( $value ); ?>" 
					class="<?php echo esc_attr( $field_class ); ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>" />
				<?php
				break;

			case 'url':
				$field_class = $field['field_class'] ?? 'regular-text';
				$placeholder = $field['placeholder'] ?? '';
				?>
				<input type="url" 
					name="<?php echo esc_attr( $key ); ?>" 
					value="<?php echo esc_attr( $value ); ?>" 
					class="<?php echo esc_attr( $field_class ); ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>" />
				<?php
				break;

			case 'text':
			default:
				$field_class = $field['field_class'] ?? 'regular-text';
				$placeholder = $field['placeholder'] ?? '';
				?>
				<input type="text" 
					name="<?php echo esc_attr( $key ); ?>" 
					value="<?php echo esc_attr( $value ); ?>" 
					class="<?php echo esc_attr( $field_class ); ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>" />
				<?php
				break;
		}
	}

	/**
	 * Group settings into logical categories
	 *
	 * @param array $registered_fields All registered fields.
	 * @return array Grouped settings.
	 */
	private function group_settings( $registered_fields ) {
		$groups = array(
			'protection'     => array(
				'title'       => __( 'Spam Protection', 'zero-spam' ),
				'description' => __( 'Control how spam is detected and blocked', 'zero-spam' ),
				'icon'        => 'shield-alt',
				'settings'    => array(),
			),
			'blocking'       => array(
				'title'       => __( 'Blocking Behavior', 'zero-spam' ),
				'description' => __( 'What happens when someone is blocked', 'zero-spam' ),
				'icon'        => 'dismiss',
				'settings'    => array(),
			),
			'email_blocking' => array(
				'title'       => __( 'Email & Domain Blocking', 'zero-spam' ),
				'description' => __( 'Block specific email addresses and domains', 'zero-spam' ),
				'icon'        => 'email-alt',
				'settings'    => array(),
			),
			'logging'        => array(
				'title'       => __( 'Logging & Data', 'zero-spam' ),
				'description' => __( 'Track blocked visitors and share data', 'zero-spam' ),
				'icon'        => 'database',
				'settings'    => array(),
			),
			'interface'      => array(
				'title'       => __( 'Interface & Display', 'zero-spam' ),
				'description' => __( 'Control what admins can see', 'zero-spam' ),
				'icon'        => 'admin-appearance',
				'settings'    => array(),
			),
		);

		// Categorize each setting.
		foreach ( $registered_fields as $section => $fields ) {
			foreach ( $fields as $field_key => $field ) {
				// Skip HTML-only fields.
				if ( 'html' === ( $field['type'] ?? '' ) ) {
					continue;
				}

				// Determine category.
				$category = $this->categorize_setting( $field_key );
				if ( isset( $groups[ $category ] ) ) {
					$groups[ $category ]['settings'][ $field_key ] = $field;
				}
			}
		}

		// Remove empty groups.
		return array_filter( $groups, function( $group ) {
			return ! empty( $group['settings'] );
		} );
	}

	/**
	 * Categorize a setting
	 *
	 * @param string $key Setting key.
	 * @return string Category ID.
	 */
	private function categorize_setting( $key ) {
		// Protection settings - spam detection.
		if ( in_array( $key, array( 'verify_wpzerospam', 'stop_forum_spam', 'project_honeypot' ), true ) ) {
			return 'protection';
		}

		// Blocking behavior - what happens to blocked visitors.
		if ( in_array( $key, array( 'block_handler', 'block_method', 'blocked_message', 'blocked_redirect_url' ), true ) ) {
			return 'blocking';
		}

		// Email/domain blocking.
		if ( in_array( $key, array( 'ip_whitelist', 'blocked_email_domains' ), true ) ) {
			return 'email_blocking';
		}

		// Logging settings.
		if ( in_array( $key, array( 'log_blocked_ips', 'max_logs', 'share_data' ), true ) ) {
			return 'logging';
		}

		// Interface settings.
		if ( in_array( $key, array( 'widget_visibility' ), true ) ) {
			return 'interface';
		}

		// Default to protection.
		return 'protection';
	}

	/**
	 * Get simplified description for a setting
	 *
	 * @param string $key   Setting key.
	 * @param array  $field Field config.
	 * @return string Simple description.
	 */
	private function get_simple_description( $key, $field ) {
		// Simple descriptions for every field.
		$descriptions = array(
			// Protection.
			'verify_wpzerospam'        => __( 'Checks if an email or visitor looks like spam using Zero Spam\'s database', 'zero-spam' ),
			'stop_forum_spam'          => __( 'Checks if a visitor has been reported as a spammer by other websites', 'zero-spam' ),
			'project_honeypot'         => __( 'Checks if a visitor is a known spammer from Project Honeypot\'s list', 'zero-spam' ),
			
			// Blocking Behavior.
			'block_handler'            => __( 'Choose to show an error message or send them to another website', 'zero-spam' ),
			'block_method'             => __( 'How to stop spammers - using server files (.htaccess) or PHP code', 'zero-spam' ),
			'blocked_message'          => __( 'The message shown to people we block (when using error message)', 'zero-spam' ),
			'blocked_redirect_url'     => __( 'The website address to send blocked visitors to (when using redirect)', 'zero-spam' ),
			
			// Email/Domain Blocking.
			'ip_whitelist'             => __( 'Computer addresses (IPs) that should never be blocked, one per line', 'zero-spam' ),
			'blocked_email_domains'    => __( 'Block anyone using these email domains (like "fakeemail.com"), one per line', 'zero-spam' ),
			
			// Logging.
			'log_blocked_ips'          => __( 'Save a record every time we block someone (uses database space)', 'zero-spam' ),
			'max_logs'                 => __( 'How many blocked visitor records to keep before deleting old ones', 'zero-spam' ),
			'share_data'               => __( 'Help everyone by sharing spam data (no personal information is shared)', 'zero-spam' ),
			
			// Interface.
			'widget_visibility'        => __( 'Choose which admin users can see the spam statistics widget', 'zero-spam' ),
		);

		return $descriptions[ $key ] ?? '';
	}

	/**
	 * Render Templates Tab
	 */
	private function render_templates_tab() {
		$templates_manager = new \ZeroSpam\Includes\Network_Templates();
		$templates         = $templates_manager->get_all_templates();

		?>
		<div class="zerospam-templates-section">
			<h2><?php esc_html_e( 'Settings Templates', 'zero-spam' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Apply pre-configured settings templates to quickly configure your network.', 'zero-spam' ); ?>
			</p>

			<div class="zerospam-templates-grid">
				<?php foreach ( $templates as $slug => $template ) : ?>
					<div class="template-card <?php echo 'built_in' === $template['type'] ? 'built-in' : 'custom'; ?>" data-slug="<?php echo esc_attr( $slug ); ?>">
						<h3>
							<?php echo esc_html( $template['name'] ); ?>
							<?php if ( 'built_in' === $template['type'] ) : ?>
								<span class="badge built-in-badge"><?php esc_html_e( 'Built-in', 'zero-spam' ); ?></span>
							<?php else : ?>
								<span class="badge custom-badge"><?php esc_html_e( 'Custom', 'zero-spam' ); ?></span>
							<?php endif; ?>
						</h3>
						<p class="template-description">
							<?php echo esc_html( $template['description'] ?? '' ); ?>
						</p>
						<div class="template-meta">
							<span><?php echo esc_html( count( $template['settings'] ) ); ?> <?php esc_html_e( 'settings', 'zero-spam' ); ?></span>
							<?php if ( 'custom' === $template['type'] ) : ?>
								<span><?php echo esc_html( $template['created_at'] ?? '' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="template-actions">
							<button type="button" class="button button-primary apply-template-network" data-slug="<?php echo esc_attr( $slug ); ?>">
								<?php esc_html_e( 'Apply to Network', 'zero-spam' ); ?>
							</button>
							<button type="button" class="button apply-template-sites" data-slug="<?php echo esc_attr( $slug ); ?>">
								<?php esc_html_e( 'Apply to Sites', 'zero-spam' ); ?>
							</button>
							<?php if ( 'custom' === $template['type'] ) : ?>
								<button type="button" class="button edit-template" data-slug="<?php echo esc_attr( $slug ); ?>">
									<?php esc_html_e( 'Edit', 'zero-spam' ); ?>
								</button>
								<button type="button" class="button delete-template" data-slug="<?php echo esc_attr( $slug ); ?>">
									<?php esc_html_e( 'Delete', 'zero-spam' ); ?>
								</button>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<hr />

			<h3><?php esc_html_e( 'Create Custom Template', 'zero-spam' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Save your current network settings as a custom template for future use.', 'zero-spam' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="template-name"><?php esc_html_e( 'Template Name', 'zero-spam' ); ?></label>
					</th>
					<td>
						<input type="text" id="template-name" class="regular-text" placeholder="<?php esc_attr_e( 'My Custom Template', 'zero-spam' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="template-slug"><?php esc_html_e( 'Template Slug', 'zero-spam' ); ?></label>
					</th>
					<td>
						<input type="text" id="template-slug" class="regular-text" placeholder="<?php esc_attr_e( 'my-custom-template', 'zero-spam' ); ?>" />
						<p class="description"><?php esc_html_e( 'Lowercase letters, numbers, and hyphens only.', 'zero-spam' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="template-description"><?php esc_html_e( 'Description', 'zero-spam' ); ?></label>
					</th>
					<td>
						<textarea id="template-description" class="large-text" rows="3" placeholder="<?php esc_attr_e( 'Describe this template...', 'zero-spam' ); ?>"></textarea>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="button" class="button button-primary" id="save-current-as-template">
					<?php esc_html_e( 'Save Current Settings as Template', 'zero-spam' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	/**
	 * Render Audit Tab
	 */
	private function render_audit_tab() {
		$audit_log = $this->settings_manager->get_audit_log(
			array(
				'limit' => 50,
			)
		);

		?>
		<div class="zerospam-audit-section">
			<h2><?php esc_html_e( 'Audit Log', 'zero-spam' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Track all changes made to network settings.', 'zero-spam' ); ?>
			</p>

			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'User', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Action', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Setting', 'zero-spam' ); ?></th>
						<th><?php esc_html_e( 'Changes', 'zero-spam' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! empty( $audit_log ) ) : ?>
					<?php foreach ( $audit_log as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( $entry['date_created'] ); ?></td>
							<td><?php echo esc_html( $entry['user_login'] ); ?></td>
							<td><?php echo esc_html( ucfirst( $entry['action_type'] ) ); ?></td>
							<td><?php echo esc_html( $entry['setting_key'] ?? __( 'N/A', 'zero-spam' ) ); ?></td>
							<td>
								<?php if ( $entry['old_value'] || $entry['new_value'] ) : ?>
									<code><?php echo esc_html( $entry['old_value'] ); ?></code> →
									<code><?php echo esc_html( $entry['new_value'] ); ?></code>
								<?php elseif ( $entry['affected_sites'] ) : ?>
									<?php
									$affected = json_decode( $entry['affected_sites'], true );
									if ( is_array( $affected ) ) {
										printf(
											/* translators: %d: number of affected sites */
											esc_html__( '%d sites affected', 'zero-spam' ),
											count( $affected )
										);
									}
									?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="5"><?php esc_html_e( 'No audit entries yet.', 'zero-spam' ); ?></td>
					</tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render Comparison Tab
	 */
	private function render_comparison_tab() {
		?>
		<div class="zerospam-comparison-section">
			<h2><?php esc_html_e( 'Site Comparison', 'zero-spam' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Compare settings across all sites in your network.', 'zero-spam' ); ?>
			</p>

			<button type="button" class="button button-primary" id="load-comparison">
				<?php esc_html_e( 'Load Comparison', 'zero-spam' ); ?>
			</button>

			<div id="comparison-results" style="display:none;"></div>
		</div>
		<?php
	}

	/**
	 * Render Import/Export Tab
	 */
	private function render_import_export_tab() {
		?>
		<div class="zerospam-import-export-section">
			<h2><?php esc_html_e( 'Import/Export', 'zero-spam' ); ?></h2>

			<div class="export-section">
				<h3><?php esc_html_e( 'Export Settings', 'zero-spam' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Export your network settings to a JSON file for backup or migration.', 'zero-spam' ); ?>
				</p>
				<button type="button" class="button button-primary" id="export-settings">
					<?php esc_html_e( 'Export as JSON', 'zero-spam' ); ?>
				</button>
			</div>

			<hr />

			<div class="import-section">
				<h3><?php esc_html_e( 'Import Settings', 'zero-spam' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Import network settings from a JSON file.', 'zero-spam' ); ?>
				</p>

				<label>
					<input type="radio" name="import_mode" value="merge" checked />
					<?php esc_html_e( 'Merge (keep existing, add new)', 'zero-spam' ); ?>
				</label><br />
				<label>
					<input type="radio" name="import_mode" value="replace" />
					<?php esc_html_e( 'Replace (overwrite all)', 'zero-spam' ); ?>
				</label><br />
				<label>
					<input type="radio" name="import_mode" value="add_only" />
					<?php esc_html_e( 'Add Only (skip existing)', 'zero-spam' ); ?>
				</label>

				<p>
					<input type="file" id="import-file" accept=".json" />
				</p>

				<button type="button" class="button button-primary" id="import-settings">
					<?php esc_html_e( 'Import Settings', 'zero-spam' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: Set setting
	 */
	public function ajax_set_setting() {
		check_ajax_referer( 'zerospam_network_settings', 'nonce' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'zero-spam' ) ) );
		}

		$key    = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		$locked = isset( $_POST['locked'] ) && '1' === $_POST['locked'];

		// Handle value - could be string or array (for multi-selects).
		$value = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';
		
		// Sanitize based on type.
		if ( is_array( $value ) ) {
			// Multi-select: sanitize each value.
			$value = array_map( 'sanitize_text_field', $value );
		} else {
			// Single value: sanitize as text.
			$value = sanitize_text_field( $value );
		}

		if ( ! $key ) {
			wp_send_json_error( array( 'message' => __( 'Invalid setting key', 'zero-spam' ) ) );
		}

		$result = $this->settings_manager->set_setting( $key, $value, $locked );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Setting saved successfully!', 'zero-spam' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save setting', 'zero-spam' ) ) );
		}
	}

	/**
	 * AJAX: Lock setting
	 */
	public function ajax_lock_setting() {
		check_ajax_referer( 'zerospam_network_settings', 'nonce' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'zero-spam' ) ) );
		}

		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';

		if ( ! $key ) {
			wp_send_json_error( array( 'message' => __( 'Invalid setting key', 'zero-spam' ) ) );
		}

		$result = $this->settings_manager->lock_setting( $key );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Setting locked', 'zero-spam' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to lock setting', 'zero-spam' ) ) );
		}
	}

	/**
	 * AJAX: Unlock setting
	 */
	public function ajax_unlock_setting() {
		check_ajax_referer( 'zerospam_network_settings', 'nonce' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'zero-spam' ) ) );
		}

		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';

		if ( ! $key ) {
			wp_send_json_error( array( 'message' => __( 'Invalid setting key', 'zero-spam' ) ) );
		}

		$result = $this->settings_manager->unlock_setting( $key );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Setting unlocked', 'zero-spam' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to unlock setting', 'zero-spam' ) ) );
		}
	}

	/**
	 * AJAX: Apply to all sites
	 */
	public function ajax_apply_all() {
		check_ajax_referer( 'zerospam_network_settings', 'nonce' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'zero-spam' ) ) );
		}

		$force = isset( $_POST['force'] ) && '1' === $_POST['force'];
		$mode  = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'all';

		$result = $this->settings_manager->apply_to_sites( $force, array(), $mode );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Reset site
	 */
	public function ajax_reset_site() {
		check_ajax_referer( 'zerospam_network_settings', 'nonce' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'zero-spam' ) ) );
		}

		$site_id = isset( $_POST['site_id'] ) ? absint( $_POST['site_id'] ) : 0;

		if ( ! $site_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid site ID', 'zero-spam' ) ) );
		}

		$result = $this->settings_manager->reset_site( $site_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Site reset to network defaults', 'zero-spam' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to reset site', 'zero-spam' ) ) );
		}
	}

	/**
	 * AJAX: Get comparison
	 */
	public function ajax_get_comparison() {
		check_ajax_referer( 'zerospam_network_settings', 'nonce' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'zero-spam' ) ) );
		}

		// Build comparison data.
		$sites            = get_sites( array( 'number' => 0 ) );
		$settings         = $this->settings_manager->get_all_with_status();
		$comparison_data  = array();

		foreach ( $settings as $key => $config ) {
			$comparison_data[ $key ] = array(
				'network_value' => $config['value'],
				'locked'        => $config['locked'],
				'sites'         => array(),
			);

			foreach ( $sites as $site ) {
				$effective = $this->network_settings->get_effective_value( $key, $site->blog_id );
				$comparison_data[ $key ]['sites'][ $site->blog_id ] = array(
					'value'  => $effective['value'],
					'source' => $effective['source'],
				);
			}
		}

		wp_send_json_success( array( 'comparison' => $comparison_data, 'sites' => $sites ) );
	}

	/**
	 * AJAX: Export
	 */
	public function ajax_export() {
		check_ajax_referer( 'zerospam_network_settings', 'nonce' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'zero-spam' ) ) );
		}

		$json = $this->settings_manager->export_settings( 'json' );

		if ( $json ) {
			wp_send_json_success( array( 'json' => $json ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to export settings', 'zero-spam' ) ) );
		}
	}

	/**
	 * AJAX: Import
	 */
	public function ajax_import() {
		check_ajax_referer( 'zerospam_network_settings', 'nonce' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'zero-spam' ) ) );
		}

		$json = isset( $_POST['json'] ) ? wp_unslash( $_POST['json'] ) : '';
		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'merge';

		if ( ! $json ) {
			wp_send_json_error( array( 'message' => __( 'No data provided', 'zero-spam' ) ) );
		}

		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid JSON format', 'zero-spam' ) ) );
		}

		$result = $this->settings_manager->import_settings( $data, $mode );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Apply template
	 */
	public function ajax_apply_template() {
		check_ajax_referer( 'zerospam_network_settings', 'nonce' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'zero-spam' ) ) );
		}

		$slug   = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
		$scope  = isset( $_POST['scope'] ) ? sanitize_text_field( wp_unslash( $_POST['scope'] ) ) : 'network';
		$lock   = isset( $_POST['lock'] ) && '1' === $_POST['lock'];

		if ( ! $slug ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template slug', 'zero-spam' ) ) );
		}

		$templates_manager = new \ZeroSpam\Includes\Network_Templates();

		if ( 'network' === $scope ) {
			$result = $templates_manager->apply_to_network( $slug, $lock );

			if ( $result ) {
				wp_send_json_success( array( 'message' => __( 'Template applied to network', 'zero-spam' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Failed to apply template', 'zero-spam' ) ) );
			}
		} else {
			// Apply to all sites.
			$sites    = get_sites( array( 'number' => 0 ) );
			$site_ids = wp_list_pluck( $sites, 'blog_id' );
			$result   = $templates_manager->apply_to_sites( $slug, $site_ids, false );

			if ( $result['success'] ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( $result );
			}
		}
	}

	/**
	 * AJAX: Save template
	 */
	public function ajax_save_template() {
		check_ajax_referer( 'zerospam_network_settings', 'nonce' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'zero-spam' ) ) );
		}

		$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$slug        = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

		if ( ! $name || ! $slug ) {
			wp_send_json_error( array( 'message' => __( 'Name and slug are required', 'zero-spam' ) ) );
		}

		$templates_manager = new \ZeroSpam\Includes\Network_Templates();
		$template_id       = $templates_manager->save_current_as_template( $name, $slug, $description );

		if ( $template_id ) {
			wp_send_json_success( array( 'message' => __( 'Template saved successfully', 'zero-spam' ), 'template_id' => $template_id ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save template', 'zero-spam' ) ) );
		}
	}

	/**
	 * AJAX: Delete template
	 */
	public function ajax_delete_template() {
		check_ajax_referer( 'zerospam_network_settings', 'nonce' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'zero-spam' ) ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';

		if ( ! $slug ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template slug', 'zero-spam' ) ) );
		}

		$templates_manager = new \ZeroSpam\Includes\Network_Templates();
		$result            = $templates_manager->delete_template( $slug );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Template deleted successfully', 'zero-spam' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete template', 'zero-spam' ) ) );
		}
	}
}
