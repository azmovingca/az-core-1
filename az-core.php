<?php
/**
 * Plugin Name: A-Z Core
 * Description: Core functionality plugin.
 * Version: 0.2.3
 * Author: Alexander Piskun
 * Author URI: https://www.instagram.com/lovu_volnu/
 * Text Domain: az-moving
 */

if( !defined( 'ABSPATH' ) ){
	exit;
}


define( 'AZ_PLUGIN_VERSION', '0.2.3' );
define( 'AZ_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'AZ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );


/**
 *  Autoload
 */
require_once AZ_PLUGIN_PATH . 'vendor/autoload.php';
require_once AZ_PLUGIN_PATH . 'MyBusiness.php';
require_once AZ_PLUGIN_PATH . 'az-payments.php';
require_once AZ_PLUGIN_PATH . 'az-instagram.php';


/**
 *  Enqueue admin styles
 */
add_action( 'admin_enqueue_scripts', 'az_enqueue_admin_scripts' );
function az_enqueue_admin_scripts(){
    wp_enqueue_style( 'az-admin', AZ_PLUGIN_URL . 'assets/az-admin.css', array(), AZ_PLUGIN_VERSION );
}


/**
 *  Registering Post Types
 */
add_action( 'init', 'az_register_post_types' );
function az_register_post_types(){
    register_post_type( 'review',
        array(
            'label'             => null,
            'labels'            => array(
                'name'                  => __( 'Reviews', 'az-moving' ),
                'signular'              => __( 'Review', 'az-moving' ),
                'add_new'               => __( 'Add review', 'az-moving' ),
                'add_new_item'          => __( 'Adding review', 'az-moving' ),
                'edit_item'             => __( 'Editing review', 'az-moving' ),
                'new_item'              => __( 'New review', 'az-moving' ),
                'view_item'             => __( 'View review', 'az-moving' ),
                'search_items'          => __( 'Search review', 'az-moving' ),
                'not_found'             => __( 'Not found', 'az-moving' ),
                'not_found_in_trash'    => __( 'Not found in trash', 'az-moving' ),
                'menu_name'             => __( 'Reviews', 'az-moving' )
            ),
            'public'            => true,
            'menu_position'     => 20,
            'menu_icon'         => 'dashicons-star-half',
            'supports'          => array( 'title', 'editor', 'thumbnail' )
        )
    );

    register_post_type( 'vacancie',
        array(
            'label'             => null,
            'labels'            => array(
                'name'                  => __( 'Vacancies', 'az-moving' ),
                'signular'              => __( 'Vacancy', 'az-moving' ),
                'add_new'               => __( 'Add vacancy', 'az-moving' ),
                'add_new_item'          => __( 'Adding vacancy', 'az-moving' ),
                'edit_item'             => __( 'Editing vacancy', 'az-moving' ),
                'new_item'              => __( 'New vacancy', 'az-moving' ),
                'view_item'             => __( 'View vacancy', 'az-moving' ),
                'search_items'          => __( 'Search vacancy', 'az-moving' ),
                'not_found'             => __( 'Not found', 'az-moving' ),
                'not_found_in_trash'    => __( 'Not found in trash', 'az-moving' ),
                'menu_name'             => __( 'Vacancies', 'az-moving' )
            ),
            'public'            => true,
            'menu_position'     => 21,
            'menu_icon'         => 'dashicons-hammer',
            'supports'          => array( 'title', 'editor', 'thumbnail' )
        )
    );
}


/**
 *  Function Helpers
 */
function az_get_google_service_account_credentials(){
    $creds = AZ_PLUGIN_PATH . 'service-account-credentials.json';

    return file_exists( $creds ) ? $creds : false;
}

function az_set_post_thumbnail_from_url( $post_id, $img_url, $img_name ){
    $upload_dir = wp_upload_dir();
    $img_data = file_get_contents( $img_url );
    $unique_file_name = wp_unique_filename( $upload_dir['path'], $img_name );
    $filename = basename( $unique_file_name );

    if( wp_mkdir_p( $upload_dir['path'] ) ) {
        $file = $upload_dir['path'] . '/' . $filename;
    }
    else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }

    file_put_contents( $file, $img_data );

    $wp_filetype = wp_check_filetype( $filename, null );

    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title'     => sanitize_file_name( $filename ),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );

    $attach_id = wp_insert_attachment( $attachment, $file, $post_id );

    require_once( ABSPATH . 'wp-admin/includes/image.php' );

    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
    wp_update_attachment_metadata( $attach_id, $attach_data );
    set_post_thumbnail( $post_id, $attach_id );
}


/**
 *  Google API Client
 */
function az_get_google_client(){
    $client_id = carbon_get_theme_option( 'google_client_id' );
    $client_secret = carbon_get_theme_option( 'google_client_secret' );

    if( $client_id && $client_secret ){
        $client = new Google\Client();
        $client->setClientId( $client_id );
        $client->setClientSecret( $client_secret );
        $client->addScope( 'https://www.googleapis.com/auth/plus.business.manage' );
        $client->setAccessType( 'offline' );
        $client->setPrompt( 'select_account' );

        $auth_code = isset( $_GET['code'] ) ? $_GET['code'] : false;

        $redirect_uri = get_admin_url( null, 'admin.php?page=az-google-reviews-sync' );
        $client->setRedirectUri( $redirect_uri );

        $token = get_option( 'google_token' );

        if( $token ){
            $client->setAccessToken( $token );
        }

        if( $client->isAccessTokenExpired() ){
            $new_token = $client->getRefreshToken();

            if( $new_token ){
                $client->fetchAccessTokenWithRefreshToken( $new_token );
            }
            else{
                $auth_url = $client->createAuthUrl();

                if( !$auth_code ){
                    echo '<div class="az-infobox"><div class="az-infobox__message">You need to login in your Google account for synching reviews.</div><div class="az-infobox__action"><a href="' . $auth_url . '" class="az-btn">' . __( 'Login to Google', 'az-moving' ) . '</a></div></div>';
                }
                else{
                    $client->setAccessToken( json_encode( $client->fetchAccessTokenWithAuthCode( $auth_code ) ) );
                }
            }

            update_option( 'google_token', $client->getAccessToken() );
        }

        return $client;
    }

    return false;
}

function az_fetch_reviews( $client ){
    $my_business_service = new Google_Service_Mybusiness( $client );

    $accounts = $my_business_service->accounts;
    $accounts_list = $accounts->listAccounts()->getAccounts();
    $account = $accounts_list[0];

    $locations = $my_business_service->accounts_locations;
    $locations_list = $locations->listAccountsLocations( $account->name )->getLocations();
    $location = $locations_list[0];
    
    $reviews = $my_business_service->accounts_locations_reviews;
    $list_reviews_response = $reviews->listAccountsLocationsReviews( $location->name );
    $reviews_list = $list_reviews_response->getReviews();

    return $reviews_list;
}

function az_parse_reviews( $reviews = array() ){
    $output = [];

    foreach( $reviews as $review ){
        $reviewer = $review->getReviewer();
        $date = new DateTime( $review->getCreateTime() );

        $output[] = array(
            'name'      => $reviewer->getDisplayName(),
            'photo'     => $reviewer->getProfilePhotoUrl(),
            'date'      => $date->format( 'Y-m-d H:i:s' ),
            'rating'    => $review->getStarRating(),
            'comment'   => $review->getComment()
        );
    }

    return $output;
}

function az_create_review( $review ){
    if( !get_page_by_title( $review['name'], 'OBJECT', 'review' ) ){
        $post_id = wp_insert_post( wp_slash( array(
            'post_status'   => 'publish',
            'post_type'     => 'review',
            'post_author'   => 1,
            'post_title'    => $review['name'],
            'post_date'     => $review['date'],
            'post_content'  => $review['comment']
        ) ) );

        if( is_wp_error( $post_id ) ){
            echo $post_id->get_error_message();
            return false;
        }
        else{
            az_set_post_thumbnail_from_url( $post_id, $review['photo'], $review['name'] . '.png' );
            update_post_meta( $post_id, 'rating_stars', $review['rating'] );

            return true;
        }
    }
}


/**
 *  Adding admin page
 */
add_action( 'admin_menu', 'az_register_admin_pages', 11 );
function az_register_admin_pages(){
    add_submenu_page(
        'crb_carbon_fields_container_a-z_moving.php',
        'Google Reviews',
		'Google Reviews',
		'manage_options',
		'az-google-reviews-sync',
		'az_get_google_reviews_admin_page'
    );

    add_submenu_page(
        'crb_carbon_fields_container_a-z_moving.php',
        'Instagram',
		'Instagram',
		'manage_options',
		'az-instagram-sync',
		'az_get_instagram_admin_page'
    );
}

function az_get_google_reviews_admin_page(){ ?>
        <div class="az-wrapper">
            <section class="az-section">
                <header class="az-header">
                    <h1>Google Reviews Synchronization</h1>
                </header>
                <div class="az-body">
                    <?php
                    $google_client = az_get_google_client();

                    if( !$google_client->isAccessTokenExpired() ) : ?>
                        <div class="az-text">
                            Latest reviews update: <?=get_option( '_last_reviews_update' ); ?>
                        </div>
                        <button class="az-btn" data-ajax="sync_google_reviews">Sync reviews</button>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <script>
        jQuery(document).ready(function($){
            $('[data-ajax]').on('click', function(){
                let $self = $(this);

                $self.prop('disabled', true);
                $self.addClass('loading');

                $.post(ajaxurl, { action: $self.data('ajax') }, function(resp){
                    $self.prop('disabled', false);
                    $self.removeClass('loading');
                    console.log(resp);
                });
            });
        });
        </script>
    <?php
}

function az_get_instagram_admin_page(){ ?>
    <div class="az-wrapper">
        <section class="az-section">
            <header class="az-header">
                <h1>Instagram Feed</h1>
            </header>
            <div class="az-body">
                <?php if( !az_instagram_is_token_expired() ) : ?>
                    <a href="<?=az_instagram_api_get_login_url(); ?>" class="az-btn">Login to Instagram</a>
                <?php else :
                    $profile = az_instagram_get_user_profile(); ?>
                    <div class="az-text">
                        You are linked to <a href="https://instagram.com/<?=$profile['username']; ?>" target="_blank"><?=$profile['username']; ?></a> account.
                    </div>
                    <button class="az-btn" data-ajax="unlink_instagram_account">Unlink</button>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <script>
        jQuery(document).ready(function($){
            $('[data-ajax]').on('click', function(){
                let $self = $(this);

                $self.prop('disabled', true);
                $self.addClass('loading');

                $.post(ajaxurl, { action: $self.data('ajax') }, function(resp){
                    $self.prop('disabled', false);
                    $self.removeClass('loading');

                    location.reload();
                });
            });
        });
    </script>
    <?php
}


/**
 *  AJAX Actions
 */
add_action( 'wp_ajax_sync_google_reviews', 'az_sync_google_reviews' );
function az_sync_google_reviews(){
    $counter = 0;

    $reviews = az_parse_reviews( az_fetch_reviews( az_get_google_client() ) );
    foreach( $reviews as $review ){
        if( az_create_review( $review ) ){
            $counter++;
        }
    }

    update_option( '_last_reviews_update', current_time( 'Y-m-d H:i' ) );

    wp_die( $counter . ' reviews added' );
}

add_action( 'wp_ajax_unlink_instagram_account', 'az_unlink_instagram_account' );
function az_unlink_instagram_account(){
    delete_option( 'instagram_token' );
    delete_option( 'instagram_token_expires_in' );
    wp_die();
}

add_action( 'wp_ajax_quote_submit', 'az_quote_submit_callback' );
add_action( 'wp_ajax_nopriv_quote_submit', 'az_quote_submit_callback' );
function az_quote_submit_callback(){
    $data = array(
        'first_name'    => $_POST['first_name'],
        'last_name'     => $_POST['last_name'],
        'phone'         => $_POST['phone'],
        'email'         => $_POST['email'],
        'address_from'  => $_POST['address_from'],
        'address_to'    => $_POST['address_to'],
        'date'          => $_POST['date'],
        'message'       => $_POST['message'],
        'address_type'  => ucfirst( $_POST['address_type'] ),
        'address_size'  => $_POST['address_type_' . $_POST['address_type']],
        'options'       => array(
            'on_site_estimate'          => $_POST['on_site_estimate'],
            'packaging'                 => $_POST['packaging'],
            'unloading'                 => $_POST['unloading'],
            'disassembling_services'    => $_POST['disassembling_services'],
        ),
        'supplies'      => array(
            'standart_boxes'    => $_POST['standart_boxes'],
            'mattress_cover'    => $_POST['mattress_cover'],
            'tv_boxes'          => $_POST['tv_boxes'],
            'bubble_wrap'       => $_POST['bubble_wrap']
        )
    );

    $message_html = "
        <h1>Quote Request</h1>
        <p>
            {$data['first_name']} {$data['last_name']}<br>
            <a href='tel:{$data['phone']}'>{$data['phone']}</a><br>
            <a href='mailto:{$data['email']}'>{$data['email']}</a>
        </p>
        <h2>Moving details</h2>
        <p>
            <b>From:</b> {$data['address_from']}<br>
            <b>Address type:</b> {$data['address_type']}, {$data['address_size']}<br>
            <b>To:</b> {$data['address_to']}<br>
            <b>Date:</b> {$data['date']}<br>
            <b>Message:</b> {$data['message']}
        </p>
        <h2>Options</h2>
        <p>
            <b>On-site estimate:</b> {$data['options']['on_site_estimate']}<br>
            <b>Packing services:</b> {$data['options']['packaging']}<br>
            <b>Unloading:</b> {$data['options']['unloading']}<br>
            <b>Disassembling services:</b> {$data['options']['disassembling_services']}<br>
        </p>
        <h2>Additional supplies</h2>
        <p>
            <b>Standard size boxes:</b> {$data['supplies']['standart_boxes']}<br>
            <b>Mattress cover:</b> {$data['supplies']['mattress_cover']}<br>
            <b>TV box:</b> {$data['supplies']['tv_boxes']}<br>
            <b>Bubble wrap:</b> {$data['supplies']['bubble_wrap']}<br>
        </p>
    ";

    $is_sended = wp_mail(
        array(
            'contactazmoving@gmail.com',
            'djalexmurcer@gmail.com'
        ),
        __( 'Quote Request', 'az-moving' ),
        $message_html,
        array(
            'content-type: text/html'
        )
    );

    if( $is_sended ){
        wp_die( json_encode( array( 'url' => get_permalink( carbon_get_theme_option( 'thank_you_page' )['0']['id'] ) ) ) );
    }

    wp_die();
}

add_action( 'wp_ajax_create_reservation', 'az_create_reservation_callback' );
add_action( 'wp_ajax_nopriv_create_reservation', 'az_create_reservation_callback' );
function az_create_reservation_callback(){
    $is_payment_successful = az_payment_request( $_POST['nonce'] );

    if( $is_payment_successful ){
        $data = array(
            'first_name'                    => $_POST['first_name'],
            'last_name'                     => $_POST['last_name'],
            'phone'                         => $_POST['phone'],
            'email'                         => $_POST['email'],
            'pickup_date'                   => $_POST['pickup_date'],
            'pickup_hours'                  => $_POST['pickup_hours'],
            'pickup_minutes'                => $_POST['pickup_minutes'],
            'pickup_address'                => $_POST['pickup_address'],
            'pickup_movers'                 => $_POST['pickup_movers'],
            'pickup_trucks'                 => $_POST['pickup_trucks'],
            'pickup_address_type'           => $_POST['pickup_address_type'],
            'pickup_address_size'           => $_POST['pickup_address_size'],
            'pickup_location_parking'       => $_POST['pickup_location_parking'],
            'delivery_date'                 => $_POST['delivery_date'],
            'delivery_hours'                => $_POST['delivery_hours'],
            'delivery_minutes'              => $_POST['delivery_minutes'],
            'delivery_address'              => $_POST['delivery_address'],
            'additional_delivery_date'      => $_POST['additional_delivery_date'],
            'additional_delivery_hours'     => $_POST['additional_delivery_hours'],
            'additional_delivery_minutes'   => $_POST['additional_delivery_minutes'],
            'delivery_address_type'         => $_POST['delivery_address_type'],
            'delivery_address_size'         => $_POST['delivery_address_size'],
            'delivery_location_parking'     => $_POST['delivery_location_parking'],
            'inventory_list'                => $_POST['inventory_list'],
            'comments'                      => $_POST['comments']
        );

        $data['standard_services'] = implode( '<br>', $_POST['standard_services'] );
        $data['packing_supplies'] = implode( '<br>', $_POST['packing_supplies'] );
        $data['extra_services'] = implode( '<br>', $_POST['extra_services'] );

        $message_html = "
            <h1>Reservation</h1>
            <p>
                {$data['first_name']} {$data['last_name']}<br>
                <a href='tel:{$data['phone']}'>{$data['phone']}</a><br>
                <a href='mailto:{$data['email']}'>{$data['email']}</a>
            </p>
            <h2>Moving details</h2>
            <p>
                <b>From:</b><br>
                {$data['pickup_address']}<br>
                {$data['pickup_date']} {$data['pickup_hours']}:{$data['pickup_minutes']}<br>
                {$data['pickup_address_type']}<br>
                {$data['pickup_address_size']}<br>
                {$data['pickup_location_parking']}<br><br>
                <b>To:</b><br>
                {$data['delivery_address']}<br>
                {$data['delivery_date']} {$data['delivery_hours']}:{$data['delivery_minutes']}<br>
                {$data['delivery_address_type']}<br>
                {$data['delivery_address_size']}<br>
                {$data['delivery_location_parking']}<br><br>
                <b>Movers:</b> {$data['pickup_movers']}<br>
                <b>Trucks:</b> {$data['pickup_trucks']}<br><br>
                <b>Comments:</b><br>
                {$data['comments']}<br><br>
                <b>Inventory list:</b><br>
                {$data['inventory_list']}
            </p>
            <h2>Standard services</h2>
            <p>
                {$data['standard_services']}
            </p>
            <h2>Packing supplies</h2>
            <p>
                {$data['packing_supplies']}
            </p>
            <h2>Extra services</h2>
            <p>
                {$data['extra_services']}
            </p>
        ";
        
        $is_sended = wp_mail(
            array(
                'contactazmoving@gmail.com',
                'djalexmurcer@gmail.com'
            ),
            __( 'Reservation', 'az-moving' ),
            $message_html,
            array(
                'content-type: text/html'
            )
        );
        
        if( $is_sended ){
            wp_die( json_encode( array( 'url' => get_permalink( carbon_get_theme_option( 'thank_you_page' )['0']['id'] ) ) ) );
        }
    }

    wp_die();
}


/**
 *  CRON
 */
register_activation_hook( __FILE__, 'az_activate_reviews_daily_sync' );
function az_activate_reviews_daily_sync(){
    wp_clear_scheduled_hook( 'az_reviews_daily_sync' );
    wp_schedule_event( strtotime( '00:00:00' ), 'daily', 'az_reviews_daily_sync' );
}

add_action( 'az_reviews_daily_sync', 'az_sync_google_reviews' );

register_deactivation_hook( __FILE__, 'az_deactivate_reviews_daily_sync' );
function az_deactivate_reviews_daily_sync(){
	wp_clear_scheduled_hook( 'az_reviews_daily_sync' );
}