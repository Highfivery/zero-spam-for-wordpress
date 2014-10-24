<?php
/**
 * Admin Sidebar Template
 *
 * Content for the plugin settings page right sidebar.
 *
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
		<b>Version:</b> <?php echo $plugin['Version']; ?> | <b><?php echo __( 'Author', 'zerospam' ); ?></b> <?php echo $plugin['Author']; ?></p>
		<p><?php echo $plugin['Description']; ?></p>
		<p><small>If you have suggestions for a new add-on, feel free to email me at <a href="mailto:me@benmarshall.me">me@benmarshall.me</a>. Want regular updates? Follow me on <a href="https://twitter.com/bmarshall0511" target="_blank">Twitter</a> or <a href="http://www.benmarshall.me/" target="_blank">visit my blog</a>.</small></p>
		<p>
			<a href="https://www.gittip.com/bmarshall511/" class="zero-spam__button" target="_blank"><?php echo __( 'Show Support &mdash; Donate!', 'zerospam' ); ?></a>
			<a href="https://wordpress.org/support/view/plugin-reviews/zero-spam" class="zero-spam__button" target="_blank"><?php echo __( 'Spread the Love &mdash; Rate!', 'zerospam' ); ?></a>
		</p>
	</div>
</div>

<div class="zero-spam__widget">
	<div class="zero-spam__inner">
		<h3><?php echo __( 'Are you a WordPress developer?', 'zerospam' ); ?></h3>

		<p><?php echo __( 'Help grow this plugin, integrate into your own or add new features by contributing.', 'zerospam' ); ?></p>
		<p><a href="https://github.com/bmarshall511/wordpress-zero-spam/fork" target="_blank" class="button button-large button-primary"><?php echo __( 'Fork it on GitHub!', 'zerospam' ); ?></a></p>
	</div>
</div>

<div class="zero-spam__widget">
	<div class="zero-spam__inner">
		<h3><?php echo __( 'Follow WordPress Zero Spam on Twitter', 'zerospam' ); ?></h3>
		<a class="twitter-timeline" href="https://twitter.com/bmarshall0511/lists/wordpress-zero-spam" data-widget-id="525626580693815297" data-chrome="noborders noheader">Tweets from https://twitter.com/bmarshall0511/lists/wordpress-zero-spam</a>
<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+"://platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
	</div>
</div>
