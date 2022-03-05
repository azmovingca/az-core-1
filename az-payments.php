<?php

use Square\SquareClient;
use Square\Models\CreatePaymentRequest;
use Square\Models\Money;
use Square\Environment;

add_action( 'wp_enqueue_scripts', 'az_enqueue_payment_scripts' );
function az_enqueue_payment_scripts(){
    wp_enqueue_script( 'square', 'https://js.squareup.com/v2/paymentform', array(), AZ_PLUGIN_VERSION );
}

function az_payment_request( $nonce = false ){
    $square_token = get_option( '_square_token' );
    $price = get_option( '_reservation_price' );
    $currency = get_option( '_reservation_currency' );

    if( $square_token && $price && $currency && $nonce ){
        $client = new SquareClient( array(
            'accessToken' => $square_token,
            'environment' => Environment::PRODUCTION
        ) );

        $idempotency_key = uniqid( 'azmoving-' );

        $amount_money = new Money();
        $amount_money->setAmount( $price * 100 );
        $amount_money->setCurrency( $currency );
        
        $body = new CreatePaymentRequest(
            $nonce,
            $idempotency_key,
            $amount_money
        );

        $api_response = $client->getPaymentsApi()->createPayment( $body );

        if( $api_response->isSuccess() ){
            $result = $api_response->getResult();

            return true;
        }

        return $api_response->getErrors();
    }

    return false;
}