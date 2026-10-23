<?php
/**
 * Admin header
 *
 * The status line reads the license status from License_Status, like the
 * dashboard widget and the notices.
 *
 * @package ZeroSpam
 */

use ZeroSpam\Core\License_Status;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

$zerospam_header_img      = plugin_dir_url( ZEROSPAM ) . 'assets/img/';
$zerospam_header_settings = admin_url( 'options-general.php?page=wordpress-zero-spam-settings&subview=zerospam' );
$zerospam_header_kses     = array(
	'a'      => array(
		'href'   => array(),
		'class'  => array(),
		'rel'    => array(),
		'target' => array(),
	),
	'strong' => array(),
);
$zerospam_header_license  = License_Status::is_enabled() ? License_Status::get() : null;
$zerospam_header_status   = null === $zerospam_header_license ? License_Status::NONE : $zerospam_header_license['status'];
?>

<header class="zerospam-header">
	<img src="<?php echo esc_url( $zerospam_header_img . 'text-zero-spam.svg' ); ?>" width="250" />
	<div class="zerospam-header__status">
		<?php
		if ( License_Status::UNREACHABLE === $zerospam_header_status ) :
			// The API couldn't be reached, which says nothing about the key itself.
			echo '<img src="' . esc_url( $zerospam_header_img . 'icon-info.svg' ) . '" class="zerospam-small-icon" />';
			echo '<strong>' . esc_html__( 'Couldn\'t verify your license right now (Zero Spam API unreachable). Protection stays enabled.', 'zero-spam' ) . '</strong>';
		elseif ( License_Status::INVALID === $zerospam_header_status ) :
			echo '<img src="' . esc_url( $zerospam_header_img . 'icon-error.svg' ) . '" class="zerospam-small-icon" />';
			printf(
				wp_kses(
					/* translators: %1$s: Replaced with the Zero Spam settings URL */
					__( '<strong>Enhanced Protection is <a href="%1$s">DISABLED</a> (invalid license).</strong>', 'zero-spam' ), // phpcs:ignore WordPress.WP.I18n.NoHtmlWrappedStrings -- Existing msgid kept so translations keep working; this legacy header is replaced in 6.0.
					$zerospam_header_kses
				),
				esc_url( $zerospam_header_settings )
			);
		elseif ( License_Status::QUOTA_EXCEEDED === $zerospam_header_status ) :
			echo '<img src="' . esc_url( $zerospam_header_img . 'icon-error.svg' ) . '" class="zerospam-small-icon" />';
			printf(
				wp_kses(
					/* translators: %1$s: Replaced with the Zero Spam pricing URL */
					__( '<strong>License query limit reached.</strong> <a href="%1$s" target="_blank" rel="noopener noreferrer">Upgrade your plan</a> to keep Enhanced Protection running.', 'zero-spam' ),
					$zerospam_header_kses
				),
				esc_url( ZEROSPAM_URL . 'pricing/' )
			);
		elseif ( License_Status::VALID === $zerospam_header_status ) :
			echo '<img src="' . esc_url( $zerospam_header_img . 'icon-success.svg' ) . '" class="zerospam-small-icon" />';
			printf(
				wp_kses(
					/* translators: %1s: Number of queries left on the license */
					__( '<strong>%1s queries available</strong>', 'zero-spam' ), // phpcs:ignore WordPress.WP.I18n.NoHtmlWrappedStrings -- Existing msgid kept so translations keep working; this legacy header is replaced in 6.0.
					$zerospam_header_kses
				),
				esc_html( number_format( (int) ( $zerospam_header_license['queries']['remaining'] ?? 0 ), 0 ) )
			);
		else :
			// Enhanced Protection is off, or on without a license key.
			echo '<img src="' . esc_url( $zerospam_header_img . 'icon-error.svg' ) . '" class="zerospam-small-icon" />';
			printf(
				wp_kses(
					/* translators: %1$s: Replaced with the Zero Spam settings URL */
					__( '<strong>Enhanced Protection is <a href="%1$s">DISABLED</a>.</strong>', 'zero-spam' ), // phpcs:ignore WordPress.WP.I18n.NoHtmlWrappedStrings -- Existing msgid kept so translations keep working; this legacy header is replaced in 6.0.
					$zerospam_header_kses
				),
				esc_url( $zerospam_header_settings )
			);
		endif;
		?>
	</div>
</header>
