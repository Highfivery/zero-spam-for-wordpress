<?php
/**
 * Ukraine support class
 *
 * As the invasion of Ukraine continues, we at Zero Spam support those fighting
 * to preserve freedom, democracy, and sovereignty in their country. Because of
 * this unlawful invasion by Russia, supported by Belarus, we will no longer
 * provide protection for .ru, .su, and .by domains that have the plugin
 * installed and will display a Ukrainian support banner on those sites.
 *
 * Not a fan, please feel free to deactivate and uninstall the plugin.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Ukrainian support class
 */
class Ukraine {
	/**
	 * Constructor
	 */
	public function __construct() {
		$host = $this->get_site_host();

		if ( $this->supports_putin() ) {
			add_action( 'wp_footer', array( $this, 'output_banner' ), 1 );
		}
	}

	/**
	 * Determines if the domain is .ru or .by
	 */
	public function supports_putin() {
		$host = $this->get_site_host();

		if ( rest_is_ip_address( $host ) || strpos( $host, '.' ) === false ) {
			return false;
		}

		$tld = substr( $host, strrpos( $host, '.' ) + 1 );

		if ( in_array( $tld, array( 'ru', 'by', 'su' ), true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns the site host.
	 */
	public function get_site_host() {
		if ( function_exists( 'get_clean_basedomain' ) ) {
			return get_clean_basedomain();
		}

		return wp_parse_url( get_site_url(), PHP_URL_HOST );
	}

	/**
	 * Displays the Ukrainian support banner.
	 */
	public function output_banner() {
		ob_start();
		?>
		<aside style="display:flex !important;align-items:center !important;position:fixed !important;bottom:40px !important;left:40px !important;z-index:99999999999999 !important;background:#fff !important;padding:15px 20px 15px !important;border-radius:3px !important;box-shadow: 2px 2px 4px rgb(0 0 0 / 10%) !important;visibility:visible !important;opacity:1 !important;">
			<svg width="1200px" height="800px" viewBox="0 0 1200 800" style="width:65px !important;height:auto !important;display:inline-block !important;vertical-align:middle !important;margin-right:10px !important;visibility:visible !important;opacity:1 !important;box-shadow: 1px 1px 2px rgb(0 0 0 / 20%) !important;">
				<title><?php esc_html_e( 'Flag of Ukraine' ); ?></title>
				<g>
					<rect fill="#005BBB" x="0" y="0" width="1200" height="800"></rect>
					<rect fill="#FFD500" x="0" y="400" width="1200" height="400"></rect>
				</g>
			</svg>
			<div style="display:block !important;visibility:visible !important;line-height:1 !important;opacity:1 !important;">
				<strong style="font-weight:bold !important;display:block !important;visibility:visible !important;font-size:14px !important;margin-bottom:5px !important;line-height:1 !important;color:#000 !important;opacity:1 !important;"><?php esc_html_e( 'We stand with the Ukrainian people' ); ?></strong>
				<a href="https://www.highfivery.com/united-with-ukraine/" target="_blank" rel="noreferrer noopener" style="color:#1a0dab !important;text-decoration:underline !important;display:block !important;visibility:visible !important;font-size:13px !important;line-height:1 !important;opacity:1 !important;"><?php esc_html_e( 'Learn how you can support the civilians of Ukraine.' ); ?></a>
			</div>
		</aside>
		<?php
		echo ob_get_clean();
	}
}
