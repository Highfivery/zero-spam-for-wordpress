<?php
/**
 * Security Note: Blocks direct access to the plugin PHP files.
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Zero_Spam {

	/**
	 * Static property to hold our singleton instance.
	 *
	 * @since 1.5.1
	 * @var (boolean|object) $instance Description.
	 */
	public static $instance = false;

	/**
	 * Holds all of WordPress Zero Spam settings.
	 *
	 * @since 1.5.0
	 * @access private
	 * @var array $settings {
	 *     WordPress Zero Spam settings array.
	 *
	 *     @type array $zerospam_general_settings WordPress Zero Spam general
	 *                                            settings.
	 *     @type string $page settings page.
	 *     @type string $db_version Current database version.
	 *     @type string $img_dir Plugin image directory.
	 *     @type array $tabs {
	 *         Holds all of the WordPress Zero Spam setting pages.
     *
	 *         @type string $zerospam_general_settings General Settings page.
	 *         @type string $zerospam_ip_block Blocked IP page.
	 *     }
	 *     @type plugins $tabs {
	 *         Holds all of the supported plugins that are installed.
     *
	 *         @type boolean $cf7 Contact Form 7.
	 *         @type boolean $gf Gravity Forms.
	 *     }
	 * }
	 */
	private $settings = array(
		'zerospam_general_settings' => array(),
		'page'                      => 'options-general.php',
		'db_version'                => '0.0.2',
		'img_dir'                   => 'img',
		'tabs'                      => array(
			'zerospam_general_settings' => 'General Settings',
			'zerospam_ip_block'         => 'Blocked IPs',
		),
		'plugins'                   => array(
			'cf7' => false,
			'gf'  => false,
			'bp'  => false
		)
	);

	/**
	 * Returns an instance.
	 *
	 * If an instance exists, this returns it.  If not, it creates one and
	 * retuns it.
	 *
	 * @since 1.5.1
	 *
	 * @return object
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Plugin initialization.
	 *
	 * Initializes the plugins functionality.
	 *
	 * @since 1.0.0
	 *
	 */
	public function __construct() {

		// Change pref page if network activated
		if ( is_plugin_active_for_network( plugin_basename( ZEROSPAM_PLUGIN ) ) ) {
			$this->settings['page'] = 'settings.php';
		}

		// Check for supported, installed plugins.
		$this->_plugin_check();

		// Load the plugin settings.
		$this->_load_settings();

		// Call the plugin WordPress action hooks.
		$this->_actions();

		// Call the plugin WordPress filters.
		$this->_filters();

		// Called when the plugin is activated.
		register_activation_hook( __FILE__, array( &$this, 'install' ) );
	}

	/**
	 * Uses init.
	 *
	 * Adds WordPress actions using the plugin API.
	 *
	 * @since 1.5.0
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/init
	 *
	 * @return void
	 */
	public function init() {
		// Check is logging spam is enabled, if so add the Spammer Log page.
		if (
			isset( $this->settings['zerospam_general_settings']['log_spammers'] ) &&
			'1' == $this->settings['zerospam_general_settings']['log_spammers']
		) {
			$this->settings['tabs']['zerospam_spammer_logs'] = 'Spammer Log';
		}
	}

	/**
	 * Uses admin_menu.
	 *
	 * Used to add extra submenus and menu options to the admin panel's menu
	 * structure.
	 *
	 * @since 1.5.0
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/admin_menu
	 *
	 * @return void
	 */
	public function admin_menu() {

		if ( is_plugin_active_for_network( plugin_basename( ZEROSPAM_PLUGIN ) ) ) {
			$hook_suffix = add_submenu_page(
				'settings.php',
				__( 'Zero Spam Settings', 'zerospam' ),
				__( 'Zero Spam', 'zerospam' ),
				'manage_network',
				'zerospam',
				array( &$this, 'settings_page' )
			);
		} else {
			// Register plugin settings page.
			$hook_suffix = add_options_page(
				__( 'Zero Spam Settings', 'zerospam' ),
				__( 'Zero Spam', 'zerospam' ),
				'manage_options',
				'zerospam',
				array( &$this, 'settings_page' )
			);
		}

		// Load WordPress Zero Spam settings from the database.
		add_action( "load-{$hook_suffix}", array( &$this, 'load_zerospam_settings' ) );
	}

	/**
	 * Admin Scripts
	 *
	 * Adds CSS and JS files to the admin pages.
	 *
	 * @since 1.5.0
	 *
	 * @return void | boolean
	 */
	public function load_zerospam_settings() {
		if ( $this->settings['page'] !== $GLOBALS['pagenow'] ) {
			return false;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->settings['img_dir'] = 'img-dev';

			wp_enqueue_style( 'zerospam-admin', plugins_url( 'build/css-dev/style.css', ZEROSPAM_PLUGIN ) );
			wp_enqueue_script( 'zerospam-charts', plugins_url( 'build/js-dev/charts.js', ZEROSPAM_PLUGIN ), array( 'jquery' ) );
		} else {
			$this->settings['img_dir'] = 'img';

			wp_enqueue_style( 'zerospam-admin', plugins_url( 'build/css/style.css', ZEROSPAM_PLUGIN ) );
			wp_enqueue_script( 'zerospam-charts', plugins_url( 'build/js/charts.min.js', ZEROSPAM_PLUGIN ), array( 'jquery' ) );
		}
	}

	/**
	 * Plugin options page.
	 *
	 * Rendering goes here, checks for active tab and replaces key with the related
	 * settings key. Uses the _options_tabs method to render the tabs.
	 *
	 * @since 1.5.0
	 */
	public function settings_page() {
		$plugin = get_plugin_data( ZEROSPAM_PLUGIN );
		$tab    = isset( $_GET['tab'] ) ? $_GET['tab'] : 'zerospam_general_settings';
		$page   = isset( $_GET['p'] ) ? $_GET['p'] : 1;
		$action = is_plugin_active_for_network( plugin_basename( ZEROSPAM_PLUGIN ) ) ? 'edit.php?action=zerospam' : 'options.php';
		?>
		<div class="wrap">
			<h2><?php echo __( 'WordPress Zero Spam', 'zerospam' ); ?></h2>
			<?php $this->_options_tabs(); ?>
			<div class="zerospam__row">
				<div class="zerospam__right">
				<?php require_once( ZEROSPAM_ROOT . 'inc/admin-sidebar.tpl.php' ); ?>
				</div>
				<div class="zerospam__left">
				<?php
					if (
						'zerospam_spammer_logs' == $tab &&
						'1' == $this->settings['zerospam_general_settings']['log_spammers']
					) {
						$ajax_nonce       = wp_create_nonce( 'zero-spam' );

						$limit = 10;
						$args = array(
							'limit' => $limit,
							'offset' => ($page - 1) * $limit
						);
						$spam            = $this->_get_spam( $args );
						$spam            = $this->_parse_spam_ary( $spam );
						$all_spam        = $this->_get_spam();
						$all_spam        = $this->_parse_spam_ary( $all_spam );

						if ( count( $all_spam['raw'] ) ) {
							$starting_date =  end( $all_spam['raw'] )->date;
							$num_days      = $this->_num_days( $starting_date );
							$per_day       = $num_days ? number_format( ( count( $all_spam['raw'] ) / $num_days ), 2 ) : 0;
						}

						if (
							isset( $this->settings['zerospam_general_settings']['ip_location_support'] ) &&
							'1' == $this->settings['zerospam_general_settings']['ip_location_support']
						) {
							$ip_location_support = true;
						} else {
							$ip_location_support = false;
						}

						require_once( ZEROSPAM_ROOT . 'inc/spammer-logs.tpl.php' );
					} elseif ( $tab == 'zerospam_ip_block' ) {
						$limit = 10;
						$args = array(
							'limit' => $limit,
							'offset' => ($page - 1) * $limit
						);
						$ips = $this->_get_blocked_ips( $args );

						require_once( ZEROSPAM_ROOT . 'inc/ip-block.tpl.php' );
					} else {
						require_once( ZEROSPAM_ROOT . 'inc/general-settings.tpl.php' );
					} ?>
				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * Renders a pager.
	 *
	 * @since 1.5.1
	 * @access private
	 *
	 * @param int $num_pages Total number of pages.
	 * @param string $tab Current page tab.
	 * @param int $page Current page number.
	 * @param int $total Total number of records
	 */
	private function _pager( $limit = 10, $total_num, $page, $tab ) {
		$max_pages = 11;
		$num_pages = ceil( $total_num / $limit );
		$cnt       = 0;

		$start = 1;
		if ( $page > 5 ) {
			$start = ( $page - 4 );
		}

		if ( 1 != $page ) {
			if ( 2 != $page ) {
				$pre_html = '<li><a href="' . $this->_admin_url() . '?page=zerospam&tab=' . $tab . '&p=1"><i class="fa fa-angle-double-left"></i></a>';
			}
			$pre_html .= '<li><a href="' . $this->_admin_url() . '?page=zerospam&tab=' . $tab . '&p=' . ( $page - 1 ) . '"><i class="fa fa-angle-left"></i></a>';
		}

		echo '<ul class="zero-spam__pager">';
		if ( isset( $pre_html ) ) {
			echo $pre_html;
		}
		for ( $i = $start; $i <= $num_pages; $i ++ ) {
			$cnt ++;
			if ( $cnt >= $max_pages ) {
				break;
			}

			if ( $num_pages != $page ) {
				$post_html = '<li><a href="' . $this->_admin_url() . '?page=zerospam&tab=' . $tab . '&p=' . ( $page + 1 ) . '"><i class="fa fa-angle-right"></i></a>';
				if ( ( $page + 1 ) != $num_pages ) {
					$post_html .= '<li><a href="' . $this->_admin_url() . '?page=zerospam&tab=' . $tab . '&p=1"><i class="fa fa-angle-double-right"></i></a>';
				}
			}

			$class = '';
			if ( $page == $i ) {
				$class = ' class="zero-spam__page-selected"';
			}
			echo '<li><a href="' . $this->_admin_url() . '?page=zerospam&tab=' . $tab . '&p=' . $i . '"' . $class . '>' . $i . '</a>';
		}

		if( isset( $post_html ) ) {
			echo $post_html;
		}
		echo '</ul>';

        ?>
		<div class="zero-spam__page-info">
			<?php echo __( 'Page ', 'zerospam' ) . number_format( $page, 0 ) . ' of ' . number_format( $num_pages, 0 ); ?>
			(<?php echo number_format( $total_num, 0 ) . __( ' total records found', 'zerospam' ); ?>)
		</div>
		<?php
	}

	/**
	 * Sets site's compatible plugins.
	 *
	 * Checks if Contact Form 7 and Gravity Forms plugins are activated.
	 *
	 * @since 1.5.0
	 * @access private
	 *
	 * @return void
	 */
	private function _plugin_check() {
		// Contact From 7 support
		if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
			$this->settings['plugins']['cf7'] = true;
		}

		// Gravity Form support.
		if ( is_plugin_active( 'gravityforms/gravityforms.php' ) ) {
			$this->settings['plugins']['gf'] = true;
		}

		// BuddyPress support.
		 if ( function_exists( 'bp_is_active' ) ) {
			$this->settings['plugins']['bp'] = true;
		}
	}

	/**
	 * Returns information about the supplied IP address.
	 *
	 * @since 1.5.2
	 * @see http://freegeoip.net/
	 * @access private
	 *
	 * @param $ip string IP address to get info for.
	 *
	 * @return array An array with the IP address details.
	 */
	private function _get_ip_info( $ip ) {
		global $wpdb;

		// Check DB
		$table_name = $wpdb->prefix . 'zerospam_ip_data';
		$data       = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE ip = %s", $ip ) );

		// Retrieve from API
		if ( ! $data ) {
			// Ignore local hosts.
			if ( $ip == '127.0.0.1' ) {
				return false;
			}

			// @ used to suppress API usage block warning.
			$json = @file_get_contents( 'http://freegeoip.net/json/' . $ip );

			$data = json_decode( $json );

			if ( $data ) {
				$wpdb->insert( $table_name, array(
						'ip'            => $ip,
						'country_code'  => $data->country_code,
						'country_name'  => $data->country_name,
						'region_code'   => $data->region_code,
						'region_name'   => $data->region_name,
						'city'          => $data->city,
						'zipcode'       => $data->zipcode,
						'latitude'      => $data->latitude,
						'longitude'     => $data->longitude,
						'metro_code'    => $data->metro_code,
						'area_code'     => $data->area_code
					),
					array(
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%d',
						'%d',
						'%d',
						'%d'
					)
				);
			}
		}

		if ( FALSE != $data ) {
			return $data;
		}

		return false;
	}

	/**
	 * Parses the spammer ary from the DB
	 *
	 * @since 1.5.0
	 * @access private
	 *
	 * @return void | object
	 */
	private function _parse_spam_ary( $ary ) {
		$return = array(
			'by_date'              => array(),
			'by_spam_count'        => array(),
			'raw'                  => $ary,
			'comment_spam'         => 0,
			'registration_spam'    => 0,
			'cf7_spam'             => 0,
			'gf_spam'              => 0,
			'bp_registration_spam' => 0,
			'unique_spammers'      => array(),
			'by_day'               => array(
				'Sun' => 0,
				'Mon' => 0,
				'Tue' => 0,
				'Wed' => 0,
				'Thu' => 0,
				'Fri' => 0,
				'Sat' => 0
			),
		);

		foreach ( $ary as $key => $obj ) {
			// By day
			$return['by_day'][ date( 'D', strtotime( $obj->date ) ) ]++;

			// By date
			if ( ! isset( $return['by_date'][ substr( $obj->date, 0, 10 ) ] ) ) {
				$return['by_date'][ substr( $obj->date, 0, 10 ) ] = array(
					'data'                 => array(),
					'comment_spam'         => 0,
					'registration_spam'    => 0,
					'cf7_spam'             => 0,
					'gf_spam'              => 0,
					'bp_registration_spam' => 0
				);
			}

			// By date
			$return['by_date'][ substr( $obj->date, 0, 10 ) ]['data'][] = array(
				'zerospam_id' => $obj->zerospam_id,
				'type'        => $obj->type,
				'ip'          => $obj->ip,
				'date'        => $obj->date,
			);

			// By spam count
			if ( ! isset( $return['by_spam_count'][ $obj->ip ] ) ) {
				$return['by_spam_count'][ $obj->ip ] = 0;
			}
			$return['by_spam_count'][ $obj->ip ]++;

			// Spam type
			if ( 1 == $obj->type) {

				// Registration spam.
				$return['by_date'][ substr( $obj->date, 0, 10 ) ]['registration_spam']++;
				$return['registration_spam']++;
			} elseif ( 2 == $obj->type ) {

				// Comment spam.
				$return['by_date'][ substr( $obj->date, 0, 10 ) ]['comment_spam']++;
				$return['comment_spam']++;
			} elseif ( 3 == $obj->type ) {

				// Contact Form 7 spam.
				$return['by_date'][ substr( $obj->date, 0, 10 ) ]['cf7_spam']++;
				$return['cf7_spam']++;
			} elseif ( 4 == $obj->type ) {

				// Gravity Form spam.
				$return['by_date'][ substr( $obj->date, 0, 10 ) ]['gf_spam']++;
				$return['gf_spam']++;
			} elseif ( 5 == $obj->type ) {

				// BuddyPress spam.
				$return['by_date'][ substr( $obj->date, 0, 10 ) ]['bp_registration_spam']++;
				$return['bp_registration_spam']++;
			}

			// Unique spammers
			if ( ! in_array( $obj->ip, $return['unique_spammers'] ) ) {
			  $return['unique_spammers'][] = $obj->ip;
			}

		}

		return $return;
	}

	/**
	 * Returns the percent of 2 numbers.
	 *
	 * @since 1.5.1
	 * @access private
	 */
	private function _get_percent( $num1, $num2 ) {
		return number_format( ( $num1 / $num2 ) * 100, 2 );
	}

	/**
	 * Uses admin_init.
	 *
	 * Triggered before any other hook when a user accesses the admin area.
	 *
	 * @since 1.5.0
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/admin_init
	 */
	public function admin_init() {
		$this->_register_settings();
	}

	/**
	 * WP generator meta tag option.
	 *
	 * Field callback, renders radio inputs, note the name and value.
	 *
	 * @since 1.5.0
	 */
	public function field_wp_generator() {
		if ( ! isset( $this->settings['zerospam_general_settings']['wp_generator'] ) ) {
			$this->settings['zerospam_general_settings']['wp_generator'] = '0';
		}
		?>
		<label for="wp_generator_remove">
			<input type="checkbox" id="wp_generator_remove" name="zerospam_general_settings[wp_generator]" value="1" <?php if ( isset( $this->settings['zerospam_general_settings']['wp_generator']) ): checked( $this->settings['zerospam_general_settings']['wp_generator'] ); endif; ?> /> <?php echo __( 'Hide', 'zerospam' ); ?>
		 </label>

		<p class="description"><?php echo __( 'It can be considered a security risk to make your WordPress version visible and public you should hide it.', 'zerospam' ); ?></p>
		<?php
	}

	/**
	 * Auto block option.
	 *
	 * Field callback, renders checkbox input, note the name and value.
	 *
	 * @since 1.5.1
	 *
	 * @return string HTML output for auto block tag.
	 */
	public function field_auto_block() {
		?>
		<label for="auto_block">
			<input type="checkbox" id="auto_block" name="zerospam_general_settings[auto_block]" value="1" <?php if ( isset( $this->settings['zerospam_general_settings']['auto_block']) ): checked( $this->settings['zerospam_general_settings']['auto_block'] ); endif; ?> /> <?php echo __( 'Enabled', 'zerospam' ); ?>
		 </label>

		<p class="description"><?php echo __( 'With auto IP block enabled, users who are identified as spam will automatically be blocked from the site.', 'zerospam' ); ?></p>
		<?php
	}

	/**
	 * Log spammers option.
	 *
	 * Field callback, renders radio inputs, note the name and value.
	 *
	 * @since 1.5.0
	 */
	public function field_log_spammers() {
		?>
		<label for="log_spammers">
			<input type="checkbox" id="log_spammers" name="zerospam_general_settings[log_spammers]" value="1" <?php if( isset( $this->settings['zerospam_general_settings']['log_spammers'] ) ) : checked( $this->settings['zerospam_general_settings']['log_spammers'] ); endif; ?> /> <?php echo __( 'Enabled', 'zerospam' ); ?>
		</label>
		<?php
	}

	/**
	 * Spam comment message option.
	 *
	 * Field callback, renders a text input, note the name and value.
	 *
	 * @since 1.5.0
	 */
	public function field_spammer_msg_comment() {
		?>
		<label for="spammer_msg_comment">
			<input type="text" class="regular-text" name="zerospam_general_settings[spammer_msg_comment]" value="<?php echo esc_attr( $this->settings['zerospam_general_settings']['spammer_msg_comment'] ); ?>">
		<p class="description"><?php echo __( 'Enter a short message to display when a spam comment has been detected.', 'zerospam' ); ?></p>
		</label>
		<?php
	}

	/**
	 * Blocked IP message option.
	 *
	 * Field callback, renders a text input, note the name and value.
	 *
	 * @since 1.5.1
	 */
	public function field_blocked_ip_msg() {
		?>
		<label for="blocked_ip_msg">
			<input type="text" class="regular-text" name="zerospam_general_settings[blocked_ip_msg]" value="<?php echo esc_attr( $this->settings['zerospam_general_settings']['blocked_ip_msg'] ); ?>">
		<p class="description"><?php echo __( 'Enter a short message to display when a blocked IP visits the site.', 'zerospam' ); ?></p>
		</label>
		<?php
	}

	/**
	 * Spam registration message option.
	 *
	 * Field callback, renders a text input, note the name and value.
	 *
	 * @since 1.5.0
	 */
	public function field_spammer_msg_registration() {
		?>
		<label for="spammer_msg_registration">
			<input type="text" class="regular-text" name="zerospam_general_settings[spammer_msg_registration]" value="<?php echo esc_attr( $this->settings['zerospam_general_settings']['spammer_msg_registration'] ); ?>">
		<p class="description"><?php echo __( 'Enter a short message to display when a spam registration has been detected (HTML allowed).', 'zerospam' ); ?></p>
		</label>
		<?php
	}

	/**
	 * Contact Form 7 spam message option.
	 *
	 * Field callback, renders a text input, note the name and value.
	 *
	 * @since 1.5.0
	 */
	public function field_spammer_msg_contact_form_7() {
		?>
		<label for="spammer_msg_contact_form_7">
			<input type="text" class="regular-text" name="zerospam_general_settings[spammer_msg_contact_form_7]" value="<?php echo esc_attr( $this->settings['zerospam_general_settings']['spammer_msg_contact_form_7'] ); ?>">
			<p class="description"><?php echo __( 'Enter a short message to display when a spam registration has been detected (HTML allowed).', 'zerospam' ); ?></p>
		</label>
		<?php
	}

	/**
	 * Contact Form 7 support option.
	 *
	 * Field callback, renders a checkbox input, note the name and value.
	 *
	 * @since 1.5.0
	 */
	public function field_cf7_support() {
		?>
		<label for="cf7_support">
			<input type="checkbox" id="cf7_support" name="zerospam_general_settings[cf7_support]" value="1" <?php if( isset( $this->settings['zerospam_general_settings']['cf7_support'] ) ) : checked( $this->settings['zerospam_general_settings']['cf7_support'] ); endif; ?> /> <?php echo __( 'Enabled', 'zerospam' ); ?>
		</label>
		<?php
	}

	/**
	 * BuddyPress spam message option.
	 *
	 * Field callback, renders a text input, note the name and value.
	 *
	 * @since 1.5.2
	 */
	public function field_spammer_msg_bp() {
		?>
		<label for="spammer_msg_bp">
			<input type="text" class="regular-text" name="zerospam_general_settings[spammer_msg_bp]" value="<?php echo esc_attr( $this->settings['zerospam_general_settings']['spammer_msg_bp'] ); ?>">
			<p class="description"><?php echo __( 'Enter a short message to display when a spam BuddyPress registration has been detected (HTML allowed).', 'zerospam' ); ?></p>
		</label>
		<?php
	}

	/**
	 * BuddyPress support option.
	 *
	 * Field callback, renders a checkbox input, note the name and value.
	 *
	 * @since 1.5.2
	 */
	public function field_bp_support() {
		?>
		<label for="bp_support">
			<input type="checkbox" id="bp_support" name="zerospam_general_settings[bp_support]" value="1" <?php if( isset( $this->settings['zerospam_general_settings']['bp_support'] ) ) : checked( $this->settings['zerospam_general_settings']['bp_support'] ); endif; ?> /> <?php echo __( 'Enabled', 'zerospam' ); ?>
		</label>
		<?php
	}

	/**
	 * Gravity Forms support option.
	 *
	 * Field callback, renders a checkbox input, note the name and value.
	 *
	 * @since 1.5.0
	 */
	public function field_gf_support() {
		?>
		<label for="gf_support">
			<input type="checkbox" id="gf_support" name="zerospam_general_settings[gf_support]" value="1" <?php if( isset( $this->settings['zerospam_general_settings']['gf_support'] ) ) : checked( $this->settings['zerospam_general_settings']['gf_support'] ); endif; ?> /> <?php echo __( 'Enabled', 'zerospam' ); ?>
		</label>
		<?php
	}

	/**
	 * IP location API field.
	 *
	 * Field callback, renders a checkbox input, note the name and value.
	 *
	 * @since 1.5.2
	 */
	public function field_ip_location_support() {
		?>
		<label for="ip_location_support">
			<input type="checkbox" id="gf_support" name="zerospam_general_settings[ip_location_support]" value="1" <?php if( isset( $this->settings['zerospam_general_settings']['ip_location_support'] ) ) : checked( $this->settings['zerospam_general_settings']['ip_location_support'] ); endif; ?> /> <?php echo __( 'Enabled', 'zerospam' ); ?>
			<p class="description">
				<?php echo __( 'IP location data provided by', 'zerospam' ); ?> <a href="http://freegeoip.net/" target="_blank">freegeoip.net</a>. <?php echo __( 'API usage is limited to 10,000 queries per hour.', 'zerospam' ); ?><br>
				<?php echo __( 'Disable this option if you experience slow load times on the', 'zerospam' ); ?> <a href="<?php echo $this->_admin_url() . '?page=zerospam&tab=zerospam_spammer_logs'; ?>"><?php echo __( 'Spammer Log', 'zerospam' ); ?></a> <?php echo __( 'page', 'zerospam' ); ?>.
			</p>
		</label>
		<?php
	}

	/**
	 * Comment support option.
	 *
	 * Field callback, renders a checkbox input, note the name and value.
	 *
	 * @since 1.5.0
	 */
	public function field_comment_support() {
		?>
		<label for="comment_support">
			<input type="checkbox" id="comment_support" name="zerospam_general_settings[comment_support]" value="1" <?php if( isset( $this->settings['zerospam_general_settings']['comment_support'] ) ) : checked( $this->settings['zerospam_general_settings']['comment_support'] ); endif; ?> /> <?php echo __( 'Enabled', 'zerospam' ); ?>
		</label>
		<?php
	}

	/**
	 * Registration support option.
	 *
	 * Field callback, renders a checkbox input, note the name and value.
	 *
	 * @since 1.5.0
	 */
	public function field_registration_support() {
		?>
		<label for="registration_support">
			<input type="checkbox" id="registration_support" name="zerospam_general_settings[registration_support]" value="1" <?php if( isset( $this->settings['zerospam_general_settings']['registration_support'] ) ) : checked( $this->settings['zerospam_general_settings']['registration_support'] ); endif; ?> /> <?php echo __( 'Enabled', 'zerospam' ); ?>
		</label>
		<?php
	}

	/**
	 * Returns spammer array from DB
	 *
	 * @since 1.5.0
	 * @access private
	 *
	 * @param array $args Array of arguments.
	 *
	 * @return object
	 */
	private function _get_spam( $args = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'zerospam_log';

		$order_by = isset( $args['order_by'] ) ? ' ORDER BY ' . $args['order_by'] : ' ORDER BY date DESC';

		$offset = isset( $args['offset'] ) ? $args['offset'] : false;
		$limit = isset( $args['limit'] ) ? $args['limit'] : false;
		if ( $offset && $limit ) {
			$limit = ' LIMIT ' . $offset . ', ' . $limit;
		} elseif( $limit ) {
			$limit = ' LIMIT ' . $limit;
		}

		$query = 'SELECT * FROM ' . $table_name . $order_by . $limit;
		$results = $wpdb->get_results( $query );

		return $results;
	}

	/**
	 * Returns the total number of spam detections.
	 *
	 * @since 1.5.1
	 * @access private
	 */
	private function _get_spam_count() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'zerospam_log';
		$query = $wpdb->get_row( 'SELECT COUNT(*) AS count FROM ' . $table_name );
		return $query->count;
	}

	/**
	 * Returns the total number of blocked IPs.
	 *
	 * @since 1.5.1
	 * @access private
	 */
	private function _get_blocked_ip_count() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'zerospam_blocked_ips';
		$query = $wpdb->get_row( 'SELECT COUNT(*) AS count FROM ' . $table_name );
		return $query->count;
	}


	/**
	 * Add setting link to plugin.
	 *
	 * Applied to the list of links to display on the plugins page (beside the activate/deactivate links).
	 *
	 * @since 1.5.0
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
	 */
	public function plugin_action_links( $links ) {
		$link = array( '<a href="' . $this->_admin_url() . '?page=zerospam">' . __( 'Settings', 'zerospam' ) . '</a>' );

		return array_merge( $links, $link );
	}

	/**
	 * Uses plugins_loaded.
	 *
	 * This hook is called once any activated plugins have been loaded. Is
	 * generally used for immediate filter setup, or plugin overrides.
	 *
	 * @since 1.5.0
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/plugins_loaded
	 */
	public function plugins_loaded() {
		if ( get_option( 'zerospam_db_version' ) != $this->settings['db_version'] ) {
			$this->install();
		}

		// Check if user IP has been blocked.
		$this->_ip_check();
	}

	/**
	 * Installs the plugins DB tables.
	 *
	 * @since 1.5.0
	 *
	 * @link http://codex.wordpress.org/Creating_Tables_with_Plugins
	 */
	public function install() {
		global $wpdb;

		$log_table_name = $wpdb->prefix . 'zerospam_log';
		$ip_table_name  = $wpdb->prefix . 'zerospam_blocked_ips';
		$ip_data_table_name  = $wpdb->prefix . 'zerospam_ip_data';

		/*
		 * We'll set the default character set and collation for this table.
		 * If we don't do this, some characters could end up being converted
		 * to just ?'s when saved in our table.
		 */
		$charset_collate = '';

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE {$wpdb->collate}";
		}

		$sql = false;

		if ( $wpdb->get_var( 'SHOW TABLES LIKE \'' . $log_table_name . '\'') != $log_table_name ) {
			$sql = "CREATE TABLE $log_table_name (
				zerospam_id mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
				type int(1) unsigned NOT NULL,
				ip varchar(15) NOT NULL,
				date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				page varchar(255) DEFAULT NULL,
				PRIMARY KEY  (zerospam_id),
				KEY type (type)
			) $charset_collate;";
		}

		if ( $wpdb->get_var( 'SHOW TABLES LIKE \'' . $ip_table_name . '\'' ) != $ip_table_name ) {
			$sql .= "CREATE TABLE $ip_table_name (
			zerospam_ip_id mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
			ip varchar(15) NOT NULL,
			type enum('permanent','temporary') NOT NULL DEFAULT 'temporary',
			start_date datetime DEFAULT NULL,
			end_date datetime DEFAULT NULL,
			reason varchar(255) DEFAULT NULL,
			PRIMARY KEY  (zerospam_ip_id),
			UNIQUE KEY ip (ip)
		) $charset_collate;";
		}

		// 0.1.0 Update
		if ( get_option( 'zerospam_db_version' ) == '0.0.1' ) {
			if ( $wpdb->get_var( 'SHOW TABLES LIKE \'' . $ip_data_table_name . '\'' ) != $ip_data_table_name ) {
				$sql .= "CREATE TABLE $ip_data_table_name (
				ip_data_id int(10) unsigned NOT NULL AUTO_INCREMENT,
				ip varchar(15) NOT NULL,
				country_code varchar(2) DEFAULT NULL,
				country_name varchar(255) DEFAULT NULL,
				region_code varchar(2) DEFAULT NULL,
				region_name varchar(255) DEFAULT NULL,
				city varchar(255) DEFAULT NULL,
				zipcode varchar(10) DEFAULT NULL,
				latitude float DEFAULT NULL,
				longitude float DEFAULT NULL,
				metro_code int(11) DEFAULT NULL,
				area_code int(11) DEFAULT NULL,
				PRIMARY KEY  (ip_data_id),
				UNIQUE KEY ip (ip)
				) $charset_collate;";
			}
		}

		if ( $sql ) {
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}

		update_option( 'zerospam_db_version', $this->settings['db_version'] );

		$options = (array) $this->settings['zerospam_general_settings'];
		$options['registration_support'] = 1;
		$options['comment_support']      = 1;
		$options['log_spammers']         = 1;
		$options['wp_generator']         = 1;
		$options['cf7_support']          = 1;
		$options['gf_support']           = 1;
		$options['bp_support']           = 1;
		$options['ip_location_support']  = 1;

		if ( is_plugin_active_for_network( plugin_basename( ZEROSPAM_PLUGIN ) ) ) {
			update_site_option( 'zerospam_general_settings', $options );
		} else {
			update_option( 'zerospam_general_settings', $options );
		}

	}

	/**
	 * Registers the settings.
	 *
	 * Appends the key to the plugin settings tabs array.
	 *
	 * @since 1.5.0
	 * @access private
	 */
	private function _register_settings() {
		register_setting( 'zerospam_general_settings', 'zerospam_general_settings' );

		add_settings_section( 'section_general', __( 'General Settings', 'zerospam' ), false, 'zerospam_general_settings' );
		add_settings_section( 'section_messages', __( 'Messages', 'zerospam' ), false, 'zerospam_general_settings' );

		add_settings_field( 'wp_generator', __( 'WP Generator Meta Tag', 'zerospam' ), array( &$this, 'field_wp_generator' ), 'zerospam_general_settings', 'section_general' );
		add_settings_field( 'log_spammers', __( 'Log Spammers', 'zerospam' ), array( &$this, 'field_log_spammers' ), 'zerospam_general_settings', 'section_general' );

		if ( isset( $this->settings['zerospam_general_settings']['log_spammers'] ) && ( '1' == $this->settings['zerospam_general_settings']['log_spammers'] ) ) {
			// IP location API support.
			add_settings_field( 'ip_location_support', __( 'IP Location Support', 'zerospam' ), array( &$this, 'field_ip_location_support' ), 'zerospam_general_settings', 'section_general' );

			// Auto IP block support.
			add_settings_field( 'auto_block', __( 'Auto IP Block', 'zerospam' ), array( &$this, 'field_auto_block' ), 'zerospam_general_settings', 'section_general' );
		}

		add_settings_field( 'blocked_ip_msg', __( 'Blocked IP Message', 'zerospam' ), array( &$this, 'field_blocked_ip_msg' ), 'zerospam_general_settings', 'section_messages' );

		add_settings_field( 'comment_support', __( 'Comment Support', 'zerospam' ), array( &$this, 'field_comment_support' ), 'zerospam_general_settings', 'section_general' );

		// Comment support.
		if ( isset( $this->settings['zerospam_general_settings']['comment_support'] ) && ( '1' == $this->settings['zerospam_general_settings']['comment_support'] ) ) {
			add_settings_field( 'spammer_msg_comment', __( 'Spam Comment Message', 'zerospam' ), array( &$this, 'field_spammer_msg_comment' ), 'zerospam_general_settings', 'section_messages' );
		}

		// Registration support.
		add_settings_field( 'registration_support', __( 'Registration Support', 'zerospam' ), array( &$this, 'field_registration_support' ), 'zerospam_general_settings', 'section_general' );
		if ( isset( $this->settings['zerospam_general_settings']['registration_support'] ) && ( '1' == $this->settings['zerospam_general_settings']['registration_support'] ) ) {
			add_settings_field( 'spammer_msg_registration', __( 'Spam Registration Message', 'zerospam' ), array( &$this, 'field_spammer_msg_registration' ), 'zerospam_general_settings', 'section_messages' );
		}

		// Contact Form 7 support.
		if ( $this->settings['plugins']['cf7'] ) {
			add_settings_field( 'cf7_support', __( 'Contact Form 7 Support', 'zerospam' ), array( &$this, 'field_cf7_support' ), 'zerospam_general_settings', 'section_general' );

			if ( isset( $this->settings['zerospam_general_settings']['cf7_support'] ) && ( '1' == $this->settings['zerospam_general_settings']['cf7_support'] ) ) {
				add_settings_field( 'spammer_msg_contact_form_7', __( 'Contact Form 7 Spam Message', 'zerospam' ), array( &$this, 'field_spammer_msg_contact_form_7' ), 'zerospam_general_settings', 'section_messages' );
			}
		}

		// Gravity Forms support.
		if ( $this->settings['plugins']['gf'] ) {
			add_settings_field( 'gf_support', __( 'Gravity Forms Support', 'zerospam' ), array( &$this, 'field_gf_support' ), 'zerospam_general_settings', 'section_general' );
		}

		// BuddyPress support.
		if ( $this->settings['plugins']['bp'] ) {
			add_settings_field( 'bp_support', __( 'BuddyPress Support', 'zerospam' ), array( &$this, 'field_bp_support' ), 'zerospam_general_settings', 'section_general' );

			if ( isset( $this->settings['zerospam_general_settings']['bp_support'] ) && ( '1' == $this->settings['zerospam_general_settings']['bp_support'] ) ) {
				add_settings_field( 'spammer_msg_bp', __( 'BuddyPress Spam Message', 'zerospam' ), array( &$this, 'field_spammer_msg_bp' ), 'zerospam_general_settings', 'section_messages' );
			}
		}
	}

	/**
	 * Checks if the current IP is blocked.
	 *
	 * @since 1.5.0
	 * @access private
	 */
	private function _ip_check() {
		if ( $this->_is_blocked(  $this->_get_ip(), false ) ) {
			do_action( 'zero_spam_ip_blocked' );
			die( __( $this->settings['zerospam_general_settings']['blocked_ip_msg'], 'zerospam' ) );
		}
	}

	/**
	 * Logs spam.
	 *
	 * @since 1.5.0
	 * @access private
	 *
	 * @param string (registration|comment) Type of spam
	 */
	private function _log_spam( $type ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'zerospam_log';
		$ip = $this->_get_ip();

		switch( $type ) {
			case 'registration':
				$type = 1;
				break;
			case 'comment':
				$type = 2;
				break;
			case 'cf7':
				$type = 3;
				break;
			case 'gf':
				$type = 4;
				break;
			case 'buddypress-registration':
				$type = 5;
				break;
		}

		$wpdb->insert( $table_name, array(
				'type' => $type,
				'ip'   => $ip,
				'page' => $this->_get_url(),
			),
			array(
				'%s',
				'%s',
				'%s',
			)
		);

		// Check auto block ip.
		if ( isset( $this->settings['zerospam_general_settings']['auto_block'] ) && ( '1' == $this->settings['zerospam_general_settings']['auto_block'] ) ) {
			$this->_block_ip( array(
				'ip'         => $ip,
				'type'       => 'permanent',
				'reason'     => __( 'Auto block triggered on ', 'zerospam' ) . date( 'r' ) . '.'
			));
		}
	}

	/**
	 * Blocks an IP address.
	 *
	 * Adds an IP to the blocked list so the user can't access the site.
	 *
	 * @since 1.5.0
	 * @access private
	 *
	 * @param array $args Array of arguments.
	 */
	private function _block_ip( $args ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'zerospam_blocked_ips';
		$ip         = isset( $args['ip'] ) ? $args['ip'] : false;
		$type       = isset( $args['type'] ) ? $args['type'] : 'temporary';

		if ( $ip ) {
			// Check is IP has already been blocked.
			if ( $this->_is_blocked( $ip, false ) ) {

				// Update existing record.
				$wpdb->update(
					$table_name,
					array(
						'type'       => $type,
						'start_date' => isset( $args['start_date'] ) ? $args['start_date'] : null,
						'end_date'   => isset( $args['end_date'] ) ? $args['end_date'] : null,
						'reason'     => $args['reason'],
					),
					array( 'ip' => $ip ),
					array(
						'%s',
						'%s',
						'%s',
						'%s',
					),
					array( '%s' )
				);
			} else {

				// Insert new record.
				$insert = array(
					'ip'   => $ip,
					'type' => $type,
				);

				if ( 'temporary' == $type ) {
					$insert['start_date'] = $args['start_date'];
					$insert['end_date'] = $args['end_date'];
				}

				if ( isset( $args['reason'] ) && $args['reason'] ) {
					$insert['reason'] = $args['reason'];
				}

				$wpdb->insert(
					$table_name,
					$insert,
					array(
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
					)
				);
			}
		}
	}

	/**
	 * Returns the current URL.
	 *
	 * @since 1.5.0
	 * @access private
	 *
	 * @return string The current URL the user is on.
	 */
	private function _get_url() {
		$pageURL = 'http';

		if ( isset( $_SERVER["HTTPS"] ) && $_SERVER["HTTPS"] == "on") {
			$pageURL .= "s";
		}

		$pageURL .= "://";

		if ( '80' != $_SERVER["SERVER_PORT"] ) {
			$pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
		} else {
			$pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
		}

		return $pageURL;
	}

	/**
	 * Returns a user's IP address
	 *
	 * @since 1.5.0
	 * @access private
	 *
	 * @return string The current user's IP address.
	 */
	private function _get_ip() {
		$ipaddress = '';
		if ( getenv('HTTP_CLIENT_IP') ) {
			$ipaddress = getenv('HTTP_CLIENT_IP');
		} else if ( getenv('HTTP_X_FORWARDED_FOR') ) {
			$ipaddress = getenv('HTTP_X_FORWARDED_FOR');
		} else if ( getenv('HTTP_X_FORWARDED') ) {
			$ipaddress = getenv('HTTP_X_FORWARDED');
		} else if ( getenv('HTTP_FORWARDED_FOR') ) {
			$ipaddress = getenv('HTTP_FORWARDED_FOR');
		} else if ( getenv('HTTP_FORWARDED') ) {
			$ipaddress = getenv('HTTP_FORWARDED');
		} else if ( getenv('REMOTE_ADDR') ) {
			$ipaddress = getenv('REMOTE_ADDR');
		} else {
			$ipaddress = 'UNKNOWN';
		}

		return $ipaddress;
	}

	/**
	 * Renders setting tabs.
	 *
	 * Walks through the object's tabs array and prints them one by one.
	 * Provides the heading for the settings_page method.
	 *
	 * @since 1.5.0
	 * @access private
	 */
	private function _options_tabs() {
		$current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'zerospam_general_settings';
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $this->settings['tabs'] as $key => $name ) {
			$active = $current_tab == $key ? 'nav-tab-active' : '';
			echo '<a class="nav-tab ' . $active . '" href="?page=zerospam&tab=' . $key . '">' . $name . '</a>';
		}
		echo '</h2>';
	}

	/**
	 * Load the settings / defaults.
	 *
	 * Load the settings from the database, and merge with the defaults where required.
	 *
	 * @since 1.5.0
	 * @access private
	 */
	private function _load_settings() {
		$default_settings =  array(
			'spammer_msg_comment'         => 'There was a problem processing your comment.',
			'spammer_msg_registration'    => '<strong>ERROR</strong>: There was a problem processing your registration.',
			'spammer_msg_contact_form_7'  => 'There was a problem processing your comment.',
			'spammer_msg_bp'              => 'There was a problem processing your registration.',
			'blocked_ip_msg'              => 'Access denied.'
		);

		// Merge and update new changes
		if ( isset( $_POST['zerospam_general_settings'] ) ) {
			$saved_settings =  $_POST['zerospam_general_settings'];
			if ( is_plugin_active_for_network( plugin_basename( ZEROSPAM_PLUGIN ) ) ) {
				update_site_option( 'zerospam_general_settings', $saved_settings );
			} else {
				update_option( 'zerospam_general_settings', $saved_settings );
			}
		}

		// Retrieve the settings
		if ( is_plugin_active_for_network( plugin_basename( ZEROSPAM_PLUGIN ) ) ) {
			$saved_settings = (array) get_site_option( 'zerospam_general_settings' );
		} else {
			$saved_settings = (array) get_option( 'zerospam_general_settings' );
		}

		$this->settings['zerospam_general_settings'] = array_merge(
			$default_settings,
			$saved_settings
		);
	}

	/**
	 * WordPress actions.
	 *
	 * Adds WordPress actions using the plugin API.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference
	 */
	private function _actions() {
		add_action( 'plugins_loaded', array( &$this, 'plugins_loaded' ) );
		add_action( 'init', array( &$this, 'init' ) );
		if ( is_plugin_active_for_network( plugin_basename( ZEROSPAM_PLUGIN ) ) ) {
			add_action( 'network_admin_menu', array( &$this, 'admin_menu' ) );
			add_action( 'network_admin_edit_zerospam', array( &$this, 'update_network_setting' ) );
		}
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
		add_action( 'wp_enqueue_scripts', array( &$this, 'wp_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts' ) );
		add_action( 'login_footer', array( &$this, 'wp_enqueue_scripts' ) );

		// AJAX actions.

		// Block an IP.
		add_action( 'wp_ajax_block_ip', array( &$this, 'wp_ajax_block_ip' ) );

		// Get the Block IP form.
		add_action( 'wp_ajax_block_ip_form', array( &$this, 'wp_ajax_block_ip_form' ) );

		// Get a blocked IP's record.
		add_action( 'wp_ajax_get_blocked_ip', array( &$this, 'wp_ajax_get_blocked_ip' ) );

		// Delete a blocked IP.
		add_action( 'wp_ajax_trash_ip_block', array( &$this, 'wp_ajax_trash_ip_block' ) );

		// Reset the spammer log.
		add_action( 'wp_ajax_reset_log', array( &$this, 'wp_ajax_reset_log' ) );

		// Get the location of an IP.
		add_action( 'wp_ajax_get_location', array( &$this, 'wp_ajax_get_location' ) );

		// Get spam by IP.
		add_action( 'wp_ajax_get_ip_spam', array( &$this, 'wp_ajax_get_ip_spam' ) );

		if ( isset( $this->settings['zerospam_general_settings']['comment_support'] ) && ( '1' == $this->settings['zerospam_general_settings']['comment_support'] ) ) {
			add_action( 'preprocess_comment', array( &$this, 'preprocess_comment' ) );
		}

		if ( isset( $this->settings['zerospam_general_settings']['bp_support'] ) && ( '1' == $this->settings['zerospam_general_settings']['bp_support'] ) ) {
			add_action( 'bp_signup_validate', array( &$this, 'bp_signup_validate' ) );
		}

		if ( isset( $this->settings['zerospam_general_settings']['cf7_support'] ) && ( '1' == $this->settings['zerospam_general_settings']['cf7_support'] ) ) {
			add_action( 'wpcf7_validate', array( &$this, 'wpcf7_validate' ) );
		}

		if ( isset( $this->settings['zerospam_general_settings']['wp_generator'] ) && ( '1' == $this->settings['zerospam_general_settings']['wp_generator'] ) ) {
			remove_action( 'wp_head', 'wp_generator' );
		}
	}

	/**
	 * WordPress filters.
	 *
	 * Adds WordPress filters.
	 *
	 * @since 1.1.0
	 * @access private
	 *
	 * @link http://codex.wordpress.org/Function_Reference/add_filter
	 */
	private function _filters() {
		add_filter( 'plugin_row_meta', array( &$this, 'plugin_row_meta' ), 10, 2 );
		if ( is_plugin_active_for_network( plugin_basename( ZEROSPAM_PLUGIN ) ) ) {
			add_filter( 'network_admin_plugin_action_links_' . plugin_basename( ZEROSPAM_PLUGIN ), array( &$this, 'plugin_action_links' ) );
		} else {
			add_filter( 'plugin_action_links_' . plugin_basename( ZEROSPAM_PLUGIN ), array( &$this, 'plugin_action_links' ) );
		}

		if ( isset( $this->settings['zerospam_general_settings']['registration_support'] ) && ( '1' == $this->settings['zerospam_general_settings']['registration_support'] ) ) {
			add_filter( 'registration_errors', array( &$this, 'preprocess_registration' ), 10, 3 );
		}

		// Gravity Forms support.
		add_filter( 'gform_entry_is_spam', array( &$this, 'gform_entry_is_spam' ), 10, 3 );
	}

	/**
	 * Uses wp_ajax_(action).
	 *
	 * Deletes a IP block.
	 *
	 * @since 1.5.0
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
	 */
	public function wp_ajax_trash_ip_block() {
		global $wpdb;
		check_ajax_referer( 'zero-spam', 'security' );

		$ip = $_REQUEST['ip'];
		$this->_delete_blocked_ip( $ip );

		die();
	}

	/**
	 * Uses wp_ajax_(action).
	 *
	 * Get's spam by IP.
	 *
	 * @since 1.5.2
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
	 */
	public function wp_ajax_get_ip_spam() {
		global $wpdb;
		check_ajax_referer( 'zero-spam', 'security' );

		$spam = $this->_get_spam();
		$return = array(
			'by_country' => array(),
			'by_lat_long' => array()
		);

		// API usage limit protection.
		$limit = 100;
		$cnt   = 0;
		foreach ( $spam as $key => $obj ) {
			$cnt++;
			if ( $cnt > 100 ) {
				break;
			}
			$loc = $this->_get_ip_info( $obj->ip );

			if ( $loc ) {
				if ( ! isset( $return['by_country'][ $loc->country_code ] ) ) {
					$return['by_country'][ $loc->country_code ] = array(
						'count' => 0,
						'name' => $loc->country_name
					);
				}
				$return['by_country'][ $loc->country_code ]['count']++;

				if ( ! isset( $return['by_lat_long'][ $obj->ip ] ) ) {
					$return['by_lat_long'][ $obj->ip ] = array(
						'latLng' => array( $loc->latitude, $loc->longitude ),
						'name' => $loc->country_name,
						'count' => 1
					);
				}
			}
		}

		arsort( $return['by_country'] );

		echo json_encode( $return );

		die();
	}

	/**
	 * Uses wp_ajax_(action).
	 *
	 * Get location data from IP.
	 *
	 * @since 1.5.2
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
	 */
	public function wp_ajax_get_location() {
		global $wpdb;
		check_ajax_referer( 'zero-spam', 'security' );

		$ip = $_REQUEST['ip'];
		echo json_encode( $this->_get_ip_info( $ip ) );

		die();
	}

	/**
	 * Uses wp_ajax_(action).
	 *
	 * Resets the spammer log.
	 *
	 * @since 1.5.0
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
	 */
	public function wp_ajax_reset_log() {
		global $wpdb;
		check_ajax_referer( 'zero-spam', 'security' );

		$this->_reset_log();
		die();
	}

	/**
	 * Uses wp_ajax_(action).
	 *
	 * Renders the block IP form.
	 *
	 * @since 1.5.0
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
	 */
	public function wp_ajax_block_ip_form() {
		global $wpdb;
		check_ajax_referer( 'zero-spam', 'security' );

		$ajax_nonce       = wp_create_nonce( 'zero-spam' );

		$date             = new DateTime();
		$end_date         = $date->modify('+1 day');

		$start_date_year  = date( 'Y' );
		$start_date_month = date( 'n' );
		$start_date_day   = date( 'd' );

		$end_date_year    = $end_date->format( 'Y' );
		$end_date_month   = $end_date->format( 'n' );
		$end_date_day     = $end_date->format( 'd' );

		if ( isset( $_REQUEST['ip'] ) ) {
			$ip   = $_REQUEST['ip'];
			$data = $this->_get_blocked_ip( $_REQUEST['ip'] );

			if ( $data ) {
				if ( $data->start_date ) {
					list( $start_date_year, $start_date_month, $start_date_day ) = explode( '-', $data->start_date );
				}
				if ( $data->end_date ) {
					list( $end_date_year, $end_date_month, $end_date_day ) = explode( '-', $data->end_date );
				}
			}
		}

		require_once( ZEROSPAM_ROOT . 'inc/block-ip-form.tpl.php' );

		die();
	}

	/**
	 * Uses wp_ajax_(action).
	 *
	 * Get the blocked IP data.
	 *
	 * @since 1.5.0
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
	 */
	public function wp_ajax_get_blocked_ip() {
		global $wpdb;
		check_ajax_referer( 'zero-spam', 'security' );

		$ajax_nonce = wp_create_nonce( 'zero-spam' );
		$ip         = $_REQUEST['ip'];
		$data       = $this->_get_blocked_ip( $ip );

		if ( $data ) {
			$data->is_blocked     = $this->_is_blocked( $ip );
			$data->start_date_txt = date( 'l, F j, Y', strtotime( $data->start_date ) );
			$data->end_date_txt   = date( 'l, F j, Y', strtotime( $data->end_date ) );

			echo json_encode( (array) $data );
		}

		die();
	}

	/**
	 * Uses wp_ajax_(action).
	 *
	 * AJAX function to block a user's IP address.
	 *
	 * @since 1.5.0
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
	 */
	public function wp_ajax_block_ip() {
		check_ajax_referer( 'zero-spam', 'security' );

		if ( ! $_POST['zerospam-type'] == 'temporary' ) {
			$start_date = false;
			$end_date = false;
		} else {
			$start_date = date( 'Y-m-d G:i:s', strtotime(
				$_POST['zerospam-startdate-year'] . '-' .
				$_POST['zerospam-startdate-month'] . '-' .
				$_POST['zerospam-startdate-day']
			));

			$end_date = date( 'Y-m-d G:i:s', strtotime(
				$_POST['zerospam-enddate-year'] . '-' .
				$_POST['zerospam-enddate-month'] . '-' .
				$_POST['zerospam-enddate-day']
			));
		}

		$reason = isset( $_POST['zerospam-reason'] ) ? $_POST['zerospam-reason'] : NULL;

		// Add/update the blocked IP.
		$this->_block_ip( array(
			'ip' => $_POST['zerospam-ip'],
			'type' => $_POST['zerospam-type'],
			'start_date' => $start_date,
			'end_date' => $end_date,
			'reason' => $reason,
		));

		die();
	}

	/**
	 * Plugin meta links.
	 *
	 * Adds links to the plugins meta.
	 *
	 * @since 1.1.0
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Filter_Reference/preprocess_comment
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( false !== strpos( $file, 'zero-spam.php' ) ) {
			$links = array_merge( $links, array( '<a href="http://www.benmarshall.me/wordpress-zero-spam-plugin/">WordPress Zero Spam</a>' ) );
			$links = array_merge( $links, array( '<a href="https://www.gittip.com/bmarshall511/">Donate</a>' ) );
		}
		return $links;
	}

	/**
	 * Validate Gravity Form submissions.
	 *
	 * @since 1.5.3
	 *
	 * @link https://github.com/bmarshall511/wordpress-zero-spam/issues/101
	 */
	public function gform_entry_is_spam( $is_spam, $form, $entry ) {
		if ( ! isset( $_POST['zerospam_key'] ) || ( $_POST['zerospam_key'] != $this->_get_key() ) ) {

			do_action( 'zero_spam_found_spam_gf_form_submission' );

			$is_spam = true;

			$this->_log_spam( 'gf' );
		}

		return $is_spam;
	}

	/**
	 * Preprocess comment fields.
	 *
	 * An action hook that is applied to the comment data prior to any other processing of the
	 * comment's information when saving a comment data to the database.
	 *
	 * @since 1.0.0
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Filter_Reference/preprocess_comment
	 */
	public function preprocess_comment( $commentdata ) {
		$valid = false;

		if ( is_user_logged_in() && current_user_can( 'moderate_comments' ) ) {
			$valid = true;
		} elseif( isset( $_POST['zerospam_key'] ) ) {
			$valid = true;
		}

		if( ! $valid ) {
			do_action( 'zero_spam_found_spam_comment', $commentdata );

			if ( isset( $this->settings['zerospam_general_settings']['log_spammers'] ) && ( '1' == $this->settings['zerospam_general_settings']['log_spammers'] ) ) {
				$this->_log_spam( 'comment' );
			}

			die( __( $this->settings['zerospam_general_settings']['spammer_msg_comment'], 'zerospam' ) );
		}

		return $commentdata;
	}

	/**
	 * Preprocess comment fields.
	 *
	 * An action hook that is applied to the comment data prior to any other processing of the
	 * comment's information when saving a comment data to the database.
	 *
	 * @since 1.5.2
	 *
	 * @link http://etivite.com/api-hooks/buddypress/trigger/do_action/bp_signup_validate/
	 */
	public function bp_signup_validate() {
		global $bp;

		if ( ! isset( $_POST['zerospam_key'] ) || ( $_POST['zerospam_key'] != $this->_get_key() ) ) {
			do_action( 'zero_spam_found_spam_buddypress_registration' );

			if ( isset( $this->settings['zerospam_general_settings']['log_spammers'] ) && ( '1' == $this->settings['zerospam_general_settings']['log_spammers'] ) ) {
				$this->_log_spam( 'buddypress-registration' );
			}

			die( __( $this->settings['zerospam_general_settings']['buddypress_msg_registration'], 'zerospam' ) );
 		}
	}

	/**
	 * Pre-process registration fields.
	 *
	 * Used to create custom validation rules on user registration. This fires
	 * when the form is submitted but before user information is saved to the
	 * database.
	 *
	 * @since 1.3.0
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/register_post
	 */
	public function preprocess_registration( $errors, $sanitized_user_login, $user_email ) {
		if ( ! isset( $_POST['zerospam_key'] ) || ( $_POST['zerospam_key'] != $this->_get_key() ) ) {
			do_action( 'zero_spam_found_spam_registration', $errors, $sanitized_user_login, $user_email );

			if ( isset( $this->settings['zerospam_general_settings']['log_spammers'] ) && ( '1' == $this->settings['zerospam_general_settings']['log_spammers'] ) ) {
				$this->_log_spam( 'registration' );
			}

			$errors->add( 'spam_error', __( $this->settings['zerospam_general_settings']['spammer_msg_registration'], 'zerospam' ) );
		}

		return $errors;
	}

	 /**
	 * Validate Contact Form 7 form submissions.
	 *
	 * Validates the Contact Form 7 (https://wordpress.org/plugins/contact-form-7/)
	 * form submission, and flags the form submission as invalid if the zero-spam
	 * post data isn't present.
	 *
	 * @since  1.5.0
	 *
	 */
	public function wpcf7_validate( $result ) {
		if ( ! isset( $_POST['zerospam_key'] ) || ( $_POST['zerospam_key'] != $this->_get_key() ) ) {
			do_action( 'zero_spam_found_spam_cf7_form_submission' );

			$result['valid']               = false;
			$result['reason']['zero_spam'] = __( $this->settings['zerospam_general_settings']['spammer_msg_contact_form_7'], 'zerospam' );

			$this->_log_spam( 'cf7' );
		}
		return $result;
	}

	/**
	 * Add plugin scripts.
	 *
	 * Adds the plugins JS files.
	 *
	 * @since 1.0.0
	 *
	 * @link http://codex.wordpress.org/Function_Reference/wp_enqueue_script
	 */
	public function wp_enqueue_scripts() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			wp_register_script( 'zero-spam', plugins_url( '/build/js-dev/zero-spam.js' , ZEROSPAM_PLUGIN ), array( 'jquery' ), '1.1.0', true );
		} else {
			wp_register_script( 'zero-spam', plugins_url( '/build/js/zero-spam.min.js' , ZEROSPAM_PLUGIN ), array( 'jquery' ), '1.1.0', true );
		}
		wp_localize_script( 'zero-spam', 'zerospam', array( 'key' => $this->_get_key() ) );
		wp_enqueue_script( 'zero-spam' );
	}


	/**
	 * Add admin scripts.
	 *
	 * Adds the CSS & JS for the WordPress Zero Spam settings page.
	 *
	 * @since 1.5.2
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Action_Reference/admin_enqueue_scripts
	 *
	 * @param string $hook Used to target a specific admin page.
	 * @return void
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 'settings_page_zerospam' != $hook ) {
        	return;
    	}

    	// Create nonce for AJAX requests.
    	$ajax_nonce = wp_create_nonce( 'zero-spam' );

    	// Register the WordPress Zero Spam admin script.
    	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    		wp_register_script(
    			'zero-spam-admin', plugin_dir_url( ZEROSPAM_PLUGIN ) .
    			'build/js-dev/zero-spam-admin.js'
    		);
    	} else {
    		wp_register_script(
    			'zero-spam-admin',
    			plugin_dir_url( ZEROSPAM_PLUGIN ) .
    			'build/js/zero-spam-admin.min.js'
    		);
    	}

    	// Localize the script with the plugin data.
		$zero_spam_array = array( 'nonce' => $ajax_nonce );
		wp_localize_script( 'zero-spam-admin', 'zero_spam_admin', $zero_spam_array );

		// Enqueue the script.
		wp_enqueue_script( 'zero-spam-admin' );
	}

	/**
	 *  Clears the log table.
	 *
	 * @since 1.5.0
	 */
	private function _reset_log() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'zerospam_log';
		$query      = $wpdb->query( 'TRUNCATE ' . $table_name );

		return $query;
	}

	/**
	 *  Delete a blocked IP.
	 *
	 * @since 1.5.0
	 *
	 * @param $ip string The IP address to block.
	 * @return object
	 */
	private function _delete_blocked_ip( $ip ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'zerospam_blocked_ips';
		$query      = $wpdb->delete( $table_name, array( 'ip' => $ip ), array( '%s' ) );

		return $query;
	}

	/**
	 *  Returns a blocked IP.
	 *
	 * @since 1.5.0
	 *
	 * @param $ip string The IP address to get.
	 * @return object
	 */
	private function _get_blocked_ip( $ip ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'zerospam_blocked_ips';
		$query      = $wpdb->get_row( "SELECT * FROM $table_name WHERE ip = '" . $ip . "'" );

		if ( null == $query ) {
			return false;
		}

		return $query;
	}

	/**
	 *  Returns an array of blocked IPs.
	 *
	 * @since 1.5.0
	 *
	 * @return array An array of blocked IPs from the database.
	 */
	private function _get_blocked_ips( $args = array() ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'zerospam_blocked_ips';

		$order_by   = isset( $args['order_by'] ) ? ' ORDER BY ' . $args['order_by'] : ' ORDER BY zerospam_ip_id DESC';

		$offset     = isset( $args['offset'] ) ? $args['offset'] : false;
		$limit      = isset( $args['limit'] ) ? $args['limit'] : false;
		if ( $offset && $limit ) {
			$limit = ' LIMIT ' . $offset . ', ' . $limit;
		} elseif ( $limit ) {
			$limit = ' LIMIT ' . $limit;
		}

		$query = 'SELECT * FROM ' . $table_name . $order_by . $limit;
		$results = $wpdb->get_results( $query );

		if ( null == $results ) {
			return false;
		}

		return $results;
	}

	/**
	 * Checks if an IP is blocked.
	 *
	 * @since 1.5.0
	 * @access private
	 *
	 * @return boolean True if blocked, false if not.
	 */
	private function _is_blocked( $ip, $time = true ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'zerospam_blocked_ips';
		$check      = $this->_get_blocked_ip( $ip );

		if ( ! $check ) {
			return false;
		}

		// Check block type
		if (
			'temporary' == $check->type &&
			time() >= strtotime( $check->start_date ) &&
			time() <= strtotime( $check->end_date )
			) {
			return true;
		}

		if ( 'permanent' == $check->type ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns number of days since a date.
	 *
	 * @since 1.5.0
	 * @access private
	 *
	 * @return int Number of days since the specified date.
	 */
	private function _num_days( $date ) {
		$datediff = time() - strtotime( $date );

		return floor( $datediff / ( DAY_IN_SECONDS ) );
	}

	/**
	 * Retrieve the key, generating if needed.
	 *
	 * @since 1.5.0
	 * @access private
	 *
	 * @return string The current WordPress Zero Spam key to validate spam against.
	 */
	private function _get_key() {
		if ( ! $key = get_option( 'zerospam_key' ) ) {
			$key = wp_generate_password( 64 );
			update_option( 'zerospam_key', $key );
		}

		return $key;
	}

	/**
	 * Update network settings.
	 *
	 * Used when plugin is network activated to save settings.
	 *
	 * @link http://wordpress.stackexchange.com/questions/64968/settings-api-in-multisite-missing-update-message
	 * @link http://benohead.com/wordpress-network-wide-plugin-settings/
	 */
	public function update_network_setting() {
		update_site_option( 'zerospam_general_settings', $_POST['zerospam_general_settings'] );
		wp_redirect( add_query_arg(
			array(
				'page'    => 'zerospam',
				'updated' => 'true',
				),
			network_admin_url( 'settings.php' )
		) );
		exit;
	}

	/**
	 * Return proper admin_url for settings page.
	 *
	 * @return string|void
	 */
	private function _admin_url() {
		if ( is_plugin_active_for_network( plugin_basename( ZEROSPAM_PLUGIN ) ) ) {
			$settings_url = network_admin_url( $this->settings['page'] );
		} else if ( home_url() != site_url() ) {
			$settings_url = home_url( '/wp-admin/' . $this->settings['page'] );
		} else {
			$settings_url = admin_url( $this->settings['page'] );
		}

		return $settings_url;
	}

}
