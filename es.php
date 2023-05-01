<?php

/*
Plugin Name: Email Subscription
Description: This is a Plugin for mailing daily latest post to the Subscribers daily
Version: 1.0.0
Author: Arkaprava
Text Domain:   es
Domain Path:   /lang
*/

if(!defined('ABSPATH')){
    die();
}

function es_enqueue_scripts(){
    wp_enqueue_script( 'ajax-script', plugins_url( 'assets/js/ajax-script.js', __FILE__ ), array('jquery'), null, true );
    wp_localize_script( 'ajax-script', 'js_config', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
    ) );
}
add_action('wp_enqueue_scripts', 'es_enqueue_scripts');

function es_activation(){
    global $wpdb, $table_prefix;
    $table_name = $table_prefix.'subscription_emails';
    $q = "CREATE TABLE IF NOT EXISTS `$table_name` (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(50) NOT NULL UNIQUE,
        PRIMARY KEY  (id)
    )";
    $wpdb->query($q);
}
register_activation_hook( __FILE__, 'es_activation' );

function es_deactivation(){
    global $wpdb, $table_prefix;
    $table_name = $table_prefix."subscription_emails";
    $q = "TRUNCATE TABLE `$table_name`;";
    $wpdb->query($q);
}
register_deactivation_hook( __FILE__, 'es_deactivation' );

function es_shortcode() {
    ob_start();
    ?>
    <form method="post" id="subscription_form">
        <h1>Email Subscription</h1>
        <input type="email" name="email" id="email" placeholder="Enter Your Email" />
        <button type="submit" name="submit">Subscribe</button>
    </form>
    <?php
    $html = ob_get_clean();
    return $html;
}
add_shortcode( 'es', 'es_shortcode' );

add_action('wp_ajax_subscribe', 'ajax_subscribe');
function ajax_subscribe(){
    $email = [];
    wp_parse_str($_POST['subscribe'],$email);
    if(is_email($email[email])){
        $data = sanitize_email($email[email]);
        global $wpdb, $table_prefix;
        $table_name = $table_prefix."subscription_emails";
        $q = $wpdb->prepare(
            "SELECT ID FROM `$table_name` WHERE email = '%s';",
            array($data)
        );
        $results = $wpdb->get_results($q);
        if(empty($results)==1){
            $q = $wpdb->prepare(
                "INSERT INTO `$table_name` (`email`) VALUES ('%s');",
                array($data)
            );
            $wpdb->query($q);
            $to = $data;
            $subject = 'Subscription Mail';
            $message = 'You have successfully subscribed to our site';
            $headers = '';
            wp_mail($to,$subject,$message,$headers);
            wp_send_json_success( 'Successfully Register', '200' );
        }
        else{
            wp_send_json_error( 'Already Registered', '403' );
        }
    }
    else{
        wp_send_json_error( 'Invalid Email', '403' );
    }
}
