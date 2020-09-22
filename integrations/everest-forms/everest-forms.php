<?php
/**
 * Handles checking submitted Everest Forms for spam
 *
 * @package WordPressZeroSpam
 * @since 4.11.0
 */

/**
 * Add the 'everest_forms' spam type
 */
add_filter(
	'wpzerospam_types',
	function( $types ) {
		$types = array_merge( $types, array( 'everest_forms' => __( 'Everest Forms', 'zero-spam' ) ) );
		return $types;
	}
);

if ( ! function_exists( 'wpzerospam_everest_forms_process_before' ) ) {

	/**
	 * Validation for Everest Forms submissions.
	 *
	 * @param array $entry     Entry data.
	 * @param array $form_data Submitted form data.
	 */
	function wpzerospam_everest_forms_process_before( $entry, $form_data ) {
		if ( is_user_logged_in() || wpzerospam_key_check() ) {
			return;
		}

		do_action( 'wpzerospam_everest_forms_spam' );

		$data = array(
			'entry'     => $entry,
			'form_data' => $form_data,
		);
		wpzerospam_spam_detected( 'everest_forms', $data );
	}
}
add_action( 'everest_forms_process_before', 'wpzerospam_everest_forms_process_before', 10, 2 );

if ( ! function_exists( 'wpzerospam_everest_forms' ) ) {

	/**
	 * Enqueue the Everest Forms JS.
	 */
	function wpzerospam_everest_forms() {
		wp_enqueue_script(
			'wpzerospam-integration-everest-forms',
			plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
			'integrations/everest-forms/js/everest-forms.js',
			array( 'wpzerospam' ),
			WORDPRESS_ZERO_SPAM_VERSION,
			true
		);
	}
}
add_action( 'everest_forms_frontend_output_before', 'wpzerospam_everest_forms' );
