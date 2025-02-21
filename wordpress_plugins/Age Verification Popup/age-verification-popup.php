<?php
/*
Plugin Name: Age Verification Popup
Description: A plugin to show an age verification popup before accessing the site.
Version: 1.0
Author: Mahendrakumar Ribadiya 
*/

// Enqueue Scripts and Styles
function avp_enqueue_scripts() {
    wp_enqueue_style( 'avp-style', plugin_dir_url( __FILE__ ) . 'assets/css/style.css' );
    wp_enqueue_script( 'avp-script', plugin_dir_url( __FILE__ ) . 'assets/js/script.js', array( 'jquery' ), false, true );
}
add_action( 'wp_enqueue_scripts', 'avp_enqueue_scripts' );

// Add the Age Verification Popup HTML
function avp_display_popup() {
    if ( get_option( 'avp_enable_popup' ) === 'yes' && ! isset( $_COOKIE['avp_accepted'] ) ) {
        $title = get_option( 'avp_popup_title', 'Age Verification' );
        $subtitle = get_option( 'avp_popup_subtitle', 'You must be 18 or older to access this website.' );
        $description = get_option( 'avp_popup_description', 'Please verify your age to continue.' );
        $button_text = get_option( 'avp_popup_button_text', 'I am 18 or older' );
        
        ?>
        <div id="avp-popup">
            <div id="avp-popup-content">
                <h1><?php echo esc_html( $title ); ?></h1>
                <h3><?php echo esc_html( $subtitle ); ?></h3>
                <p><?php echo esc_html( $description ); ?></p>
                <button id="avp-close-btn"><?php echo esc_html( $button_text ); ?></button>
            </div>
        </div>
        <?php
    }
}
add_action( 'wp_footer', 'avp_display_popup', 100 );

// Set Cookie When User Accepts Age Verification
function avp_set_cookie() {
    if ( isset( $_POST['avp_accept'] ) ) {
        setcookie( 'avp_accepted', '1', time() + ( 5 * DAY_IN_SECONDS ), '/' ); // Cookie for 5 days
        wp_redirect( $_SERVER['REQUEST_URI'] ); // Reload the page to hide the popup
        exit;
    }
}
add_action( 'template_redirect', 'avp_set_cookie' );

// Register Customizer Settings
require_once plugin_dir_path( __FILE__ ) . 'includes/customizer.php';

