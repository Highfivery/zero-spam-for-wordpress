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
    <h2><?php _e( 'Are you a fan of the <a href="https://benmarshall.me/wordpress-zero-spam/?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=admin" target="_blank">WordPress Zero Spam</a> plugin? Show your support.', 'wpzerospam' ); ?></h2>
    <p><?php _e( 'Help support the continued development of the WordPress Zero Spam plugin by <a href="https://benmarshall.me/donate?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=admin" target="_blank">donating today</a>. Your donation goes towards the time it takes to develop new features &amp; updates, but also helps provide pro bono work for nonprofits. <a href="https://benmarshall.me/donate?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=admin" target="_blank">Learn more</a>.', 'wpzerospam' ); ?></p>
    <p><strong><?php _e( 'For the latest updates,', 'wpzerospam' ); ?></strong> <a href="https://twitter.com/ZeroSpamOrg" target="_blank" rel="noopener noreferrer"><?php _e( 'follow us on Twitter', 'wpzerospam' ); ?></a>, <a href="https://www.facebook.com/zerospamorg/" target="_blank" rel="noopener noreferrer"><?php _e( 'Facebook', 'wpzerospam' ); ?></a>, <?php _e( 'or', 'wpzerospam' ); ?> <a href="https://zerospam.org/" target="_blank" rel="noopener noreferrer"><?php _e( 'visit our website', 'wpzerospam' ); ?></a>.</p>
  </div>
  <div class="wpzerospam-callout-actions">
    <a href="https://github.com/bmarshall511/wordpress-zero-spam/issues" class="button" target="_blank"><?php _e( 'Submit Bug/Feature Request' ); ?></a>
    <a href="https://github.com/bmarshall511/wordpress-zero-spam" class="button" target="_blank"><?php _e( 'Fork on Github' ); ?></a>
    <a href="https://benmarshall.me/donate?utm_source=wordpress_zero_spam&utm_medium=settings_page&utm_campaign=admin" class="button button-primary" target="_blank"><?php _e( 'Show your Support &mdash; Donate' ); ?></a>
  </div>
</div>

<p style="font-size: 1rem;"><strong><?php _e( 'Your IP Address:', 'wpzerospam' ); ?>:</strong> <code><?php echo wpzerospam_ip(); ?></code></p>
