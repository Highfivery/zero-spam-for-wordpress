<?php
/**
 * Handles checking submitted Ninja Forms for spam
 *
 * @package WordPressZeroSpam
 * @since 4.3.7
 */

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

        $errors = [
          'form' => [
            'wpzerospam' => $options['blocked_message'],
          ]
        ];

        $response = [
          'errors' => $errors,
        ];
        // @TODO - Find a way to display the error message to the user
        echo wp_json_encode( $response );
        wp_die();
    }

    return $form_data;
  }
}
add_filter( 'ninja_forms_submit_data', 'wpzerospam_ninja_forms_validate' );

if( ! class_exists( 'WordPressZeroSpam_NF_ExtraData' ) ) {
  class WordPressZeroSpam_NF_ExtraData {
    // Stores the form IDs we want to modify
    var $form_ids = [];
    var $script_added = false;

    public function __construct() {
      add_action('ninja_forms_before_form_display', [ $this, 'addHooks' ]);
    }

    public function addHooks( $form_id ) {
      $this->form_ids[] = $form_id;

      //Make sure we only add the script once
      if( ! $this->script_added ) {
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
