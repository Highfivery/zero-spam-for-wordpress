<?php
class ZeroSpam_Scripts {
  public function run() {
    add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
    add_action( 'login_footer', array( $this, 'wp_enqueue_scripts' ) );
  }

  public function wp_enqueue_scripts() {
    $this->register_styles();
    $this->register_scripts();

    $this->enqueue_styles();
    $this->enqueue_scripts();
  }

  public function register_styles() {
    //wp_register_style( 'bam-wedding', get_template_directory_uri() . '/assets/css/style.css' );
  }

  public function register_scripts() {
    wp_register_script( 'zerospam', plugins_url( '/js/zerospam.js' , ZEROSPAM_PLUGIN ), array( 'jquery' ), '2.0.0', true );
  }

  public function enqueue_styles() {
    //wp_enqueue_style( 'bam-wedding' );
  }

  public function enqueue_scripts() {
    wp_localize_script( 'zerospam', 'zerospam', array( 'key' => zerospam_get_key() ) );
    wp_enqueue_script( 'zerospam' );
  }
}