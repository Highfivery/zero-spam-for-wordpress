<?php
/**
 * Plugin callout
 *
 * @package WordPressZeroSpam
 * @since 4.6.0
 */
?>

<div class="wpzerospam-callout">
  <div class="wpzerospam-callout-content">
    <h2><?php
    echo sprintf(
      wp_kses(
        __( 'Help support the <a href="%s" target="_blank" rel="noopener noreferrer">WordPress Zero Spam</a> plugin.', 'zero-spam' ),
        [ 'a' => [ 'target' => [], 'href' => [], 'rel' => [] ] ]
      ),
      esc_url( 'https://benmarshall.me/wordpress-zero-spam/?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=donation' )
    );
    ?></h2>
    <p><?php
    echo sprintf(
      wp_kses(
        __( 'Support the continued development of the WPZS by <a href="%s" target="_blank" rel="noopener noreferrer">donating today</a>. Donation goes towards the time it takes to develop new features &amp; updates, but also helps provide pro bono work for nonprofits. <a href="%s" target="_blank" rel="noopener noreferrer">Learn more</a>.', 'zero-spam' ),
        [ 'a' => [ 'target' => [], 'href' => [], 'rel' => [] ] ]
      ),
      esc_url( 'https://benmarshall.me/donate/?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=donation' ),
      esc_url( 'https://benmarshall.me/donate/?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=donation' )
    );
    ?></p>
    <p><?php
    echo sprintf(
      wp_kses(
        __( '<strong>Integrate Zero Spam in any application</strong> with the <a href="%s" target="_blank" rel="noopener noreferrer">Zero Spam Blacklist API</a>.', 'zero-spam' ),
        [ 'strong' => [], 'a' => [ 'target' => [], 'href' => [], 'rel' => [] ] ]
      ),
      esc_url( 'https://zerospam.org/spam-blacklist-api/' )
    );
    ?></p>
  </div>
  <div class="wpzerospam-callout-actions">
    <a href="https://github.com/bmarshall511/wordpress-zero-spam/issues" class="button" target="_blank"><?php _e( 'Submit Bug/Feature Request', 'zero-spam' ); ?></a>
    <a href="https://twitter.com/ZeroSpamOrg" class="button" target="_blank"><?php _e( 'Follow us on Twitter', 'zero-spam' ); ?></a>
    <a href="https://www.facebook.com/zerospamorg/" class="button" target="_blank"><?php _e( 'Like us on Facebook', 'zero-spam' ); ?></a>
    <a href="https://zerospam.org/" class="button" target="_blank"><?php _e( 'Learn more about Zero Spam', 'zero-spam' ); ?></a>
    <a href="https://github.com/bmarshall511/wordpress-zero-spam" class="button" target="_blank"><?php _e( 'Fork on Github', 'zero-spam' ); ?></a>
    <a href="https://benmarshall.me/donate?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=admin" class="button button-primary" target="_blank"><?php _e( 'Show your Support &mdash; Donate', 'zero-spam' ); ?></a>
  </div>
</div>

<p><strong><?php _e( 'Your IP Address:', 'zero-spam' ); ?>:</strong> <code><?php echo wpzerospam_ip(); ?></code></p>
