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
	}

	/**
	 * Admin menu.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function admin_menu() {
		add_submenu_page(
			'options-general.php',
			__( 'WordPress Zero Spam Settings', 'zerospam' ),
			__( 'Zero Spam', 'zerospam' ),
			'manage_options',
			'wordpress-zero-spam-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function register_settings() {
		register_setting( 'wpzerospam', 'wpzerospam' );

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

			if ( ! empty( $setting['field_class'] ) ) {
				$options['field_class'] = $setting['field_class'];
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
	 * Settings section.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function settings_section( $arg ) {
	}

	/**
	 * Settings field.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function settings_field( $args ) {
		switch ( $args['type'] ) {
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
						/> <?php echo $label; ?>
					</label><br />
				<?php
				}
				break;
		}

		if ( ! empty( $args['suffix'] ) ) {
			echo $args['suffix'];
		}

		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . $args['desc'] . '</p>';
		}
	}

	/**
	 * Settings page.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php require ZEROSPAM_PATH . 'includes/templates/admin-callout.php'; ?>

			<form action="options.php" method="post">
			<?php
			// Output security fields for the registered setting "wpzerospam".
			settings_fields( 'wpzerospam' );

			echo '<div class="zerospam-settings-tabs">';
			// Output setting sections and their fields.
			do_settings_sections( 'wpzerospam' );

			// Output save settings button.
			submit_button( 'Save Settings' );
			echo '</div>';
			?>
			</form>
		</div>
		<?php
	}
}
