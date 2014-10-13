<?php
/**
 * Block IP form template.
 *
 * @since 1.5.0
 */

/**
 * Security Note: Blocks direct access to the plugin PHP files.
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

$ajax_nonce = wp_create_nonce( 'zero-spam' );
?>
<h2><?php echo __( 'Block', 'zerospam' ); ?> <?php echo $ip; ?></h2>
<form method="post" action="options.php" id="zero-spam__block-ip-form">
<table class="form-table">
  <tr>
    <th><label for="zerospam-ip">IP:</th>
    <td><input type="text" name="zerospam-ip" id="zerospam-ip" value="<?php echo esc_attr( $ip ); ?>" disabled="disabled" class="regular-text"></td>
  </tr>
  <tr>
    <th><label for="zerospam-type"><?php echo __( 'Type', 'zerospam' ); ?>:</th>
    <td>
      <select name="zerospam-type" id="zerospam-type">
        <option value="temporary"><?php echo __( 'Temporary', 'zerospam' ); ?></option>
        <option value="permanent"><?php echo __( 'Permanent', 'zerospam' ); ?></option>
      </select>
    </td>
  </tr>
  <tr>
    <th><label for="zerospam-startdate"><?php echo __( 'Start Date', 'zerospam' ); ?>:</th>
    <td>
      <select name="zerospam-startdate-month" id="zerospam-startdate">
        <option value="1"><?php echo __( 'January', 'zerospam' ); ?></option>
        <option value="2"><?php echo __( 'February', 'zerospam' ); ?></option>
        <option value="3"><?php echo __( 'March', 'zerospam' ); ?></option>
        <option value="4"><?php echo __( 'April', 'zerospam' ); ?></option>
        <option value="5"><?php echo __( 'May', 'zerospam' ); ?></option>
        <option value="6"><?php echo __( 'June', 'zerospam' ); ?></option>
        <option value="7"><?php echo __( 'July', 'zerospam' ); ?></option>
        <option value="8"><?php echo __( 'August', 'zerospam' ); ?></option>
        <option value="9"><?php echo __( 'September', 'zerospam' ); ?></option>
        <option value="10"><?php echo __( 'October', 'zerospam' ); ?></option>
        <option value="11"><?php echo __( 'November', 'zerospam' ); ?></option>
        <option value="12"><?php echo __( 'December', 'zerospam' ); ?></option>
      </select>

      <select name="zerospam-startdate-day">
        <?php for ($i = 1; $i <= 31; $i++): ?>
          <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
        <?php endfor; ?>
      </select>

      <select name="zerospam-startdate-year">
        <?php for ( $i = date( 'Y' ); $i <= ( date( 'Y' ) + 50 ); $i++ ): ?>
          <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
        <?php endfor; ?>
      </select>
    </td>
  </tr>
  <tr>
    <th><label for="zerospam-enddate"><?php echo __( 'End Date', 'zerospam' ); ?>:</th>
    <td>
      <select name="zerospam-enddate-month" id="zerospam-enddate">
        <option value="1"><?php echo __( 'January', 'zerospam' ); ?></option>
        <option value="2"><?php echo __( 'February', 'zerospam' ); ?></option>
        <option value="3"><?php echo __( 'March', 'zerospam' ); ?></option>
        <option value="4"><?php echo __( 'April', 'zerospam' ); ?></option>
        <option value="5"><?php echo __( 'May', 'zerospam' ); ?></option>
        <option value="6"><?php echo __( 'June', 'zerospam' ); ?></option>
        <option value="7"><?php echo __( 'July', 'zerospam' ); ?></option>
        <option value="8"><?php echo __( 'August', 'zerospam' ); ?></option>
        <option value="9"><?php echo __( 'September', 'zerospam' ); ?></option>
        <option value="10"><?php echo __( 'October', 'zerospam' ); ?></option>
        <option value="11"><?php echo __( 'November', 'zerospam' ); ?></option>
        <option value="12"><?php echo __( 'December', 'zerospam' ); ?></option>
      </select>

      <select name="zerospam-enddate-day">
        <?php for ($i = 1; $i <= 31; $i++): ?>
          <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
        <?php endfor; ?>
      </select>

      <select name="zerospam-enddate-year">
        <?php for ( $i = date( 'Y' ); $i <= ( date( 'Y' ) + 50 ); $i++ ): ?>
          <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
        <?php endfor; ?>
      </select>
    </td>
  </tr>
  <tr>
    <th><label for="zerospam-reason"><?php echo __( 'Reason', 'zerospam' ); ?>:</th>
    <td><input type="text" name="zerospam-reason" id="zerospam-reason" class="large-text"></td>
  </tr>
</table>
<p class="submit">
    <input type="submit" value="<?php echo __( 'Save Changes', 'zerospam' ); ?>" class="button button-primary button-large">
    <? if ( $ip ): ?><a href="javascript: closeForms();" class="button button-large"><?php echo __( 'Cancel', 'zerospam' ); ?></a><?php endif; ?>
</p>
</form>
<script>
jQuery( document ).ready( function( $ ) {
    $( "#zero-spam__block-ip-form" ).submit( function( e ) {
        e.preventDefault();

        var data = $( "#zero-spam__block-ip-form" ).serialize();
        data += '&security=<?php echo $ajax_nonce; ?>';
        data += '&action=block_ip';

        $.post( ajaxurl, data, function( d ) {
          console.log( d );
        });
    });
});
</script>
