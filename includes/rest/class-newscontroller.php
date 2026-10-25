<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps News_Controller to this file name.
/**
 * REST route for news and security notices from zerospam.org.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Includes\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use ZeroSpam\Includes\News_Feed;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * `zero-spam/v1/news`: the latest articles from zerospam.org (News_Feed).
 *
 * Returns a list of articles (`title`, `excerpt`, `date`, `link`,
 * `categories`, `security_notice`), newest first, all plain text. The
 * `X-ZeroSpam-News-Fetched` header gives when they were downloaded (ISO 8601,
 * UTC) and `X-ZeroSpam-News-Stale` is `1` when a failed download made the
 * route serve an older copy. 404 `zerospam_news_disabled` when the news is
 * turned off (nothing is downloaded then), 503 `zerospam_news_unavailable`
 * when no copy can be had.
 *
 * @since 6.0.0
 */
class News_Controller extends Base_Controller {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'zero-spam/v1';

	/**
	 * REST API base route.
	 *
	 * @var string
	 */
	protected $rest_base = 'news';

	/**
	 * Registers the route.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'site_permissions_check' ),
					'args'                => array(
						'limit' => array(
							'description' => __( 'Number of articles, from 1 to 8.', 'zero-spam' ),
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => News_Feed::MAX_ITEMS,
							'default'     => News_Feed::MAX_ITEMS,
						),
					),
				),
			)
		);
	}

	/**
	 * The latest articles.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		if ( ! News_Feed::is_enabled() ) {
			return new WP_Error(
				'zerospam_news_disabled',
				__( 'Zero Spam News is turned off.', 'zero-spam' ),
				array( 'status' => 404 )
			);
		}

		$news = News_Feed::get();

		if ( null === $news ) {
			return new WP_Error(
				'zerospam_news_unavailable',
				__( 'The news from zerospam.org could not be loaded. Please try again later.', 'zero-spam' ),
				array( 'status' => 503 )
			);
		}

		$limit = max( 1, min( News_Feed::MAX_ITEMS, (int) $request->get_param( 'limit' ) ) );
		$items = array();

		foreach ( array_slice( $news['items'], 0, $limit ) as $item ) {
			$items[] = $this->filter_fields( $item, $request );
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-ZeroSpam-News-Fetched', gmdate( 'Y-m-d\TH:i:s\Z', $news['fetched_at'] ) );
		$response->header( 'X-ZeroSpam-News-Stale', $news['stale'] ? '1' : '0' );

		return $response;
	}
}
