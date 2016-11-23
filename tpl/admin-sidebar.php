<?php
/**
 * Admin sidebar template
 *
 * Outputs the admin sidebar HTML.
 *
 * @package WordPress Zero Spam
 * @subpackage ZeroSpam_Plugin
 * @since 1.5.0
 */

/**
 * Security Note: Blocks direct access to the plugin PHP files.
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
?>
<div class="zero-spam__widget">
  <div class="zero-spam__inner">
    <h2><a href="<?php echo esc_url( $plugin['PluginURI'] ); ?>" target="_blank"><?php echo __( $plugin['Name'], 'zerospam' ); ?></a></h2>
    <p class="zero-spam__description"><b><?php echo __( 'Rate', 'zerospam' ); ?>:</b> <a href="https://wordpress.org/support/view/plugin-reviews/zero-spam" target="_blank"><i class="fa fa-star"></i>
    <i class="fa fa-star"></i>
    <i class="fa fa-star"></i>
    <i class="fa fa-star"></i>
    <i class="fa fa-star"></i></a> |

    <?php
    echo sprintf(
      wp_kses( __( '<b>Version:</b> %s | <b>Author</b> %s', 'zerospam' ), array( 'b' => array() ) ),
      $plugin['Version'],
      $plugin['Author']
    );
    ?>
    <p><?php echo wp_kses(
      __( $plugin['Description'], 'zerospam' ),
      array( 'a' => array( 'href' => array() )  )
    ); ?></p>
    <p><?php
    echo sprintf(
      wp_kses(
        __( '<small>If you have suggestions for a new add-on, feel free to email me at <a href="%s">me@benmarshall.me</a>. Want regular updates? Follow me on <a href="%s" target="_blank">Twitter</a> or <a href="%s" target="_blank">visit my blog</a>.</small>', 'zero-spam' ),
        array(
          'a' => array(
            'href' => array()
          ),
          'small' => array()
        )
      ),
      esc_url( 'mailto:me@benmarshall.me' ),
      esc_url( 'https://twitter.com/bmarshall0511' ),
      esc_url( 'https://benmarshall.me/' ) );
    ?></p>
    <p>
      <a href="https://www.gittip.com/bmarshall511/" class="zero-spam__button" target="_blank"><?php _e( 'Show Support &mdash; Donate!', 'zerospam' ); ?></a>
      <a href="https://wordpress.org/support/view/plugin-reviews/zero-spam" class="zero-spam__button" target="_blank"><?php _e( 'Spread the Love &mdash; Rate!', 'zerospam' ); ?></a>
    </p>
  </div>
</div>

<div class="zero-spam__widget">
  <div class="zero-spam__inner">
    <h3><?php _e( 'Are you a WordPress developer?', 'zerospam' ); ?></h3>
    <p><?php _e( 'Help grow this plugin, integrate into your own or add new features by contributing.', 'zerospam' ); ?></p>
    <p><a href="https://github.com/bmarshall511/wordpress-zero-spam" target="_blank" class="button button-large button-primary"><?php _e( 'Fork it on GitHub!', 'zerospam' ); ?></a></p>
  </div>
</div>
