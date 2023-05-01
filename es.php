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
    if ( !wp_next_scheduled( 'email_latest_posts_daily_to_subscribers' ) ) {
        wp_schedule_event( time(), 'daily', 'email_latest_posts_daily_to_subscribers' );
    }
}
register_activation_hook( __FILE__, 'es_activation' );

function es_deactivation(){
    global $wpdb, $table_prefix;
    $table_name = $table_prefix."subscription_emails";
    $q = "TRUNCATE TABLE `$table_name`;";
    // $wpdb->query($q);
    $timestamp = wp_next_scheduled( 'email_latest_posts_daily_to_subscribers' );
    wp_unschedule_event( $timestamp, 'email_latest_posts_daily_to_subscribers' );
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
function es_admin_menu(){
    add_menu_page(
        __('Email Subscription','es'),
        'Email Subscription',
        'manage_options',
        'email-subscription',
		'es_admin_menu_content',
        '',
        100
    );
}
add_action( 'admin_menu', 'es_admin_menu' );
function es_admin_menu_content(){
    
}
add_action('email_latest_posts_daily_to_subscribers','es_mail');
function es_mail(){
    $message = es_data_loader();
    $subject = 'Recent Post Notification';
    $headers = '';
    $to_list = es_get_subscription_emails();
    foreach($to_list as $to){
        $content_type = function() { return 'text/html'; };
        add_filter( 'wp_mail_content_type', $content_type );
        wp_mail( $to, $subject, $message, $headers );
        remove_filter( 'wp_mail_content_type', $content_type );
    }
}
function es_data_loader(){
    $news = array(
        'post_type'=>'news',
        'post_status'=>'publish',
        'date_query'    => array(
            'column'  => 'post_date',
            'after'   => '- 1 days'
        ),
        'posts_per_page' => '3'
    );
    $data = array();
    $query = new WP_query($news);
    if($query->have_posts()){
        while($query->have_posts()){
            $query->the_post();
            $title = '<strong>'.get_the_title().'</strong>';
            $link = '<a href="'.get_the_permalink().'">'.get_the_permalink().'</a>' ;
            $post = array(
                'title' => $title,
                'link' => $link
            );
            array_push($data, $post);
        }
        ob_start();
        ?>
        <table id="elements">
            <thead>
                <th>News Title</th>
                <th>link</th>
            </thead>
            <tbody>
                <?php
                foreach($data as $dat){
                    ?>
                    <tr>
                        <td><?php echo $dat['title']; ?></td>
                        <td><?php echo $dat['link']; ?></td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
        <style>
            #elements {
                border-collapse: collapse;
                width: 900px;
                margin: 20px 50px;
            }
            #elements th, #elements td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: center;
            }

            #elements tr:nth-child(even){background-color: #f3f3f3;}

            #elements tr:hover {background-color: #fafafa;}

            #elements th {
                padding-top: 12px;
                padding-bottom: 12px;
                background-color: #ffffff;
                color: #black;
            }
        </style>
        <?php
        $html = ob_get_clean();
        return $html;
    }
    else{
        return "<h1>no recent post<h1>";
    }
}
function es_get_subscription_emails(){
    global $wpdb, $table_prefix;
    $table_name = $table_prefix."subscription_emails";
    $q = "SELECT email FROM `$table_name`;";
    $results = $wpdb->get_results($q);
    $mail = array();
    foreach($results as $result){
        array_push($mail,$result->email);
    }
    return $mail;
}