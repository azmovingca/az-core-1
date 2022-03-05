<?php

/**
 *  Instagram API Client
 */
function az_instagram_api_get_login_url(){
    $app_id = get_option( '_instagram_app_id' );

    if( $app_id ){
        $redirect_url = admin_url( '', 'https' );

        $api_base_url = 'https://api.instagram.com/';

        $auth_vars = array(
            'app_id'        => $app_id,
            'redirect_uri'  => $redirect_url,
            'scope'         => 'user_profile,user_media',
            'response_type' => 'code',
            'state'         => 'az_instagram'
        );

        return $api_base_url . 'oauth/authorize?' . http_build_query( $auth_vars );
    }

    return;
}


function az_instagram_make_api_call( $params ){
    $ch = curl_init();

    $endpoint = $params['endpoint_url'];

    if( 'POST' == $params['type'] ){
        curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $params['url_params'] ) );
        curl_setopt( $ch, CURLOPT_POST, 1 );
    }
    else if( 'GET' == $params['type'] ){
        $endpoint .= '?' . http_build_query( $params['url_params'] );
    }

    curl_setopt( $ch, CURLOPT_URL, $endpoint );

    curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

    $response = curl_exec( $ch );

    curl_close( $ch );

    $response_array = json_decode( $response, true );

    if( isset( $response_array['error_type'] ) ){
        var_dump( $response_array );
        die();
    }
    else{
        return $response_array;
    }
}


add_action( 'admin_head', 'az_instagram_fetch_long_access_token' );
function az_instagram_fetch_long_access_token(){
    if( !defined( 'DOING_AJAX' ) || !DOING_AJAX ){
        if( isset( $_GET['code'] ) && isset( $_GET['state'] ) && $_GET['state'] == 'az_instagram' ){
            $app_id = get_option( '_instagram_app_id' );
            $app_secret = get_option( '_instagram_app_secret' );

            if( $app_id && $app_secret ){
                $redirect_url = admin_url( '', 'https' );

                $api_base_url = 'https://api.instagram.com/';
                $graph_base_url = 'https://graph.instagram.com/';

                $short_access_token = array(
                    'endpoint_url'  => $api_base_url . 'oauth/access_token',
                    'type'          => 'POST',
                    'url_params'    => array(
                        'app_id'        => $app_id,
                        'app_secret'    => $app_secret,
                        'grant_type'    => 'authorization_code',
                        'redirect_uri'  => $redirect_url,
                        'code'          => $_GET['code']
                    )
                );

                $long_access_token = array(
                    'endpoint_url'  => $graph_base_url . 'access_token',
                    'type'          => 'GET',
                    'url_params'    => array(
                        'client_secret' => $app_secret,
                        'grant_type'    => 'ig_exchange_token'
                    )
                );

                $short_access_token_response = az_instagram_make_api_call( $short_access_token );

                $short_token = $short_access_token_response['access_token'];

                $long_access_token['url_params']['access_token'] = $short_token;
                $long_access_token_response = az_instagram_make_api_call( $long_access_token );

                if( isset( $long_access_token_response['access_token'] ) ){
                    update_option( 'instagram_token', $long_access_token_response['access_token'] );
                    update_option( 'instagram_token_expires_in', time() + $long_access_token_response['expires_in'] );

                    wp_redirect( admin_url( 'admin.php?page=az-instagram-sync', 'https' ) );
                    exit();
                }
            }
        }
    }
}


function az_instagram_is_token_expired(){
    $token_expires_in = get_option( 'instagram_token_expires_in' );

    return $token_expires_in > time();
}


function az_instagram_get_user_profile( $fields = array( 'username' ) ){
    $access_token = get_option( 'instagram_token' );

    if( $access_token ){
        $fields = implode( ',', $fields );

        $instagram_connection = curl_init();

        curl_setopt( $instagram_connection, CURLOPT_URL, "https://graph.instagram.com/me?fields={$fields}&access_token={$access_token}" );
        curl_setopt( $instagram_connection, CURLOPT_RETURNTRANSFER, 1 );

        curl_setopt( $instagram_connection, CURLOPT_TIMEOUT, 9 );
        curl_setopt( $instagram_connection, CURLOPT_CONNECTTIMEOUT, 9 );

        $response = json_decode( curl_exec( $instagram_connection ), true );

        curl_close( $instagram_connection );

        return $response;
    }

    return;
}


function az_instagram_get_user_media( $fields = array( 'username', 'media_url', 'media_type', 'permalink' ) ){
    $access_token = get_option( 'instagram_token' );

    if( $access_token ){
        $fields = implode( ',', $fields );

        $instagram_connection = curl_init();

        curl_setopt( $instagram_connection, CURLOPT_URL, "https://graph.instagram.com/me/media?fields={$fields}&access_token={$access_token}" );
        curl_setopt( $instagram_connection, CURLOPT_RETURNTRANSFER, 1 );

        curl_setopt( $instagram_connection, CURLOPT_TIMEOUT, 9 );
        curl_setopt( $instagram_connection, CURLOPT_CONNECTTIMEOUT, 9 );

        $response = json_decode( curl_exec( $instagram_connection ), true );

        curl_close( $instagram_connection );

        return $response;
    }

    return;
}


/**
 *  Helpers
 */
function az_get_instagram_feed_profile_url(){
    $profile = az_instagram_get_user_profile();
    return 'https://instagram.com/' . $profile['username'];
}