<?php
/**
 * General settings page template.
 *
 * @since 1.5.0
 */

/**
 * Security Note: Blocks direct access to the plugin PHP files.
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
?>
<div class="zero-spam__row">
  <div class="zero-spam__widget">
  	<div class="zero-spam__inner">
  		<form method="post" action="<?php echo $action; ?>">
  			<?php settings_fields( $tab ); ?>
  			<?php do_settings_sections( $tab ); ?>
  			<?php submit_button(); ?>
  		</form>
  	</div>
  </div>
</div>
