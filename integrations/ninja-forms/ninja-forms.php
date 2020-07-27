<?php
/**
 * Handles checking submitted Ninja Forms for spam
 *
 * @package WordPressZeroSpam
 * @since 4.3.7
 */

/**
 * Add the 'ninja_forms' spam type
 */
add_filter( 'wpzerospam_types', function( $types ) {
  $types = array_merge( $types, [ 'ninja_forms' => 'Ninja Forms' ] );
  return $types;
});

/**
 * Validation for Ninja Forms submissions
 */
if ( ! function_exists( 'wpzerospam_ninja_forms_validate' ) ) {
  function wpzerospam_ninja_forms_validate( $form_data ) {
    if ( is_user_logged_in() ) {
      return $form_data;
    }

    if (
      empty( $form_data['extra'] ) ||
      empty( $form_data['extra']['wpzerospam_key'] ) ||
      wpzerospam_get_key() != $form_data['extra']['wpzerospam_key']
      ) {
        $options = wpzerospam_options();

        do_action( 'wpzerospam_ninja_forms_spam' );

        wpzerospam_spam_detected( 'ninja_forms', $form_data, false );

        // @TODO - This is a hacky way to display an error for spam detections,
        // but only way I've found to show an error.
        $form_data['errors']['fields'][1] = $options['spam_message'];
    }

    return $form_data;
  }
}
add_filter( 'ninja_forms_submit_data', 'wpzerospam_ninja_forms_validate' );

if ( ! class_exists( 'WordPressZeroSpam_NF_ExtraData' ) ) {
  class WordPressZeroSpam_NF_ExtraData {
    var $form_ids = [];
    var $script_added = false;

    public function __construct() {
      add_action( 'ninja_forms_before_form_display', [ $this, 'addHooks' ] );
    }

    public function addHooks( $form_id ) {
      $this->form_ids[] = $form_id;

      if ( ! $this->script_added ) {
        add_action( 'wp_footer', [ $this, 'add_extra_to_form' ], 99 );
        $this->script_added = true;
      }
    }

    public function add_extra_to_form() {
      ?>
      <script>
      (function() {
        var form_ids = [ <?php echo join( ", ", $this->form_ids ); ?> ];

        nfRadio.channel( "forms" ).on( "before:submit", function( e ) {
          if( form_ids.indexOf( +e.id ) === -1 ) return;

          var extra = e.get( 'extra' );

          extra.wpzerospam_key = '<?php echo wpzerospam_get_key(); ?>';

          e.set('extra', extra);
        });
      })();
      </script>
      <?php
    }
  }
}

new WordPressZeroSpam_NF_ExtraData();
