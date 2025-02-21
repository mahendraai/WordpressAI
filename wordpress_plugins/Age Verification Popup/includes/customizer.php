<?php
function avp_customize_register( $wp_customize ) {
    // Add Section for Age Verification Popup Settings
    $wp_customize->add_section( 'avp_section', array(
        'title' => 'Age Verification Popup',
        'priority' => 30,
    ) );

    // Enable/Disable Popup
    $wp_customize->add_setting( 'avp_enable_popup', array(
        'default' => 'yes',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'avp_enable_popup', array(
        'label' => 'Enable Age Verification Popup',
        'section' => 'avp_section',
        'type' => 'checkbox',
    ) );

    // Popup Title
    $wp_customize->add_setting( 'avp_popup_title', array(
        'default' => 'Age Verification',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'avp_popup_title', array(
        'label' => 'Popup Title',
        'section' => 'avp_section',
        'type' => 'text',
    ) );

    // Popup Subtitle
    $wp_customize->add_setting( 'avp_popup_subtitle', array(
        'default' => 'You must be 18 or older to access this website.',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'avp_popup_subtitle', array(
        'label' => 'Popup Subtitle',
        'section' => 'avp_section',
        'type' => 'text',
    ) );

    // Popup Description
    $wp_customize->add_setting( 'avp_popup_description', array(
        'default' => 'Please verify your age to continue.',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'avp_popup_description', array(
        'label' => 'Popup Description',
        'section' => 'avp_section',
        'type' => 'textarea',
    ) );

    // Popup Button Text
    $wp_customize->add_setting( 'avp_popup_button_text', array(
        'default' => 'I am 18 or older',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'avp_popup_button_text', array(
        'label' => 'Popup Button Text',
        'section' => 'avp_section',
        'type' => 'text',
    ) );
}
add_action( 'customize_register', 'avp_customize_register' );

