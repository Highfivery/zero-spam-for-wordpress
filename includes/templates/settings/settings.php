<?php
/**
 * Settings: Export/import template
 *
 * @package ZeroSpam
 */

?>

<form action="options.php" method="post">
<?php
// Output security fields for the registered setting "wpzerospam".
settings_fields( 'wpzerospam' );

echo '<div class="zerospam-settings-tabs">';
// Output setting sections and their fields.
do_settings_sections( 'wpzerospam' );

// Output save settings button.
submit_button( 'Save Settings' );
?>
</form>
