<?php

namespace OA;
use OA\Factory\{User, Medicine, Discount, Order, Option, CacheUpdate};
use OA\Payment\{FosterPayment, Nagad, Bkash};
use GuzzleHttp\Client;

class PaymentResponse {

    function __construct() {
    }

    public function home($o_id, $o_token, $method = '' ){
        if( ! MAIN ){
            //$this->output( 'Error', 'This is a test site, can not pay here.' );
        }

        //$this->output( 'Error', 'Due to technical difficulty we cannot accept your online payment now. Please try again later or pay Cash On Delivery to our delivery man.' );

        //$_GET['method'] = 'fosterPayment';
        //$this->proceed( $o_id, $o_token, 'fosterPayment' );
        //exit;

        if( ! $o_id || ! $o_token ){
            $this->output( 'Error', 'No id or secret provided' );
        }
        $order = Order::getOrder( $o_id );
        if( ! $order || ! $order->validateToken( $o_token ) ){
            $this->output( 'Error', 'No Order found' );
        }
        if( ! \in_array( $order->o_status, [ 'confirmed', 'delivering', 'delivered' ] ) ){
            $this->output( 'Error', 'You cannot pay for this order.' );
        }
        if( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ){
            $this->output( 'Success', 'You have already paid for this order.' );
        }
        /*
        if( ! in_array( $order->u_id, [ 1, 6140, 33210, 37307 ] ) ){
            $this->proceed( $o_id, $o_token, 'fosterPayment' );
            exit;
        }
        */
        
        if( $method ){
            $this->proceed( $o_id, $o_token, $method );
        }
        ob_start();
        ?>
        <div style="margin-top: 10%;">
            <div>
                <div><a title="bKash" href="<?php echo $order->signedUrl( '/payment/v1', '/bKash' ); ?>"><img src="<?php echo Functions::getS3Url( 'misc/bKash-logo.jpg', 450, 150 ); ?>" alt="" style="border:1px solid black; width: 90%; max-width: 300px" /></a></div>
                <div><a title="Nagad" href="<?php echo $order->signedUrl( '/payment/v1', '/nagad' ); ?>"><img src="<?php echo Functions::getS3Url( 'misc/nagad-logo.jpg', 450, 150 ); ?>" alt="" style="border:1px solid black; width: 90%; max-width: 300px" /></a></div>
                <div><a title="fosterPayment" href="<?php echo $order->signedUrl( '/payment/v1', '/fosterPayment' ); ?>"><img src="<?php echo Functions::getS3Url( 'misc/visa-master-logo.jpg', 450, 150 ); ?>" alt="" style="border:1px solid black; width: 90%; max-width: 300px" /></a></div>
            </div>
        </div>
        <?php

        $this->output( 'Payment', '', ob_get_clean() );

    }

    public function proceed( $o_id, $o_token, $method ){
        if( ! $o_id || ! $o_token || ! $method ){
            $this->output( 'Error', 'No id or secret provided' );
        }
        $order = Order::getOrder( $o_id );
        if( ! $order || ! $order->validateToken( $o_token ) ){
            $this->output( 'Error', 'No Order found' );
        }
        if( ! \in_array( $order->o_status, [ 'confirmed', 'delivering', 'delivered' ] ) ){
            $this->output( 'Error', 'You cannot pay for this order.' );
        }
        if( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ){
            $this->output( 'Success', 'You have already paid for this order.' );
        }
        $output = [];
        switch ($method) {
            case 'fosterPayment':
                $output = FosterPayment::instance()->proceed( $order );
                break;
            case 'nagad':
                $output = Nagad::instance()->proceed( $order );
                break;
            case 'bKash':
                $output = Bkash::instance()->proceed( $order );
                break;
            default:
                # code...
                break;
        }
        $this->output( $output['title']??'', $output['heading']??'', $output['body']??'' );
    }

    public function success( $method, $o_id, $o_token ){
        if( ! $o_id || ! $method ){
            $this->output( 'Error', 'No id provided' );
        }
        $order = Order::getOrder( $o_id );
        if( ! $order || ! $order->validateToken( $o_token ) ){
            $this->output( 'Error', 'No Order found' );
        }

        if( ! \in_array( $order->o_status, [ 'confirmed', 'delivering', 'delivered' ] ) ){
            $this->output( 'Error', 'You cannot pay for this order.' );
        }
        if( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ){
            $this->output( 'Success', 'You have already paid for this order.' );
        }

        $output = [];
        switch ($method) {
            case 'fosterPayment':
                $output = FosterPayment::instance()->success( $order );
                break;
            case 'nagad':
                $output = Nagad::instance()->success( $order );
                break;
            case 'bKash':
                $output = Bkash::instance()->success( $order );
                break;
            default:
                # code...
                break;
        }
        $this->output( $output['title']??'', $output['heading']??'', $output['body']??'' );
    }

    public function error( $o_id, $method ){
        if( ! $o_id ){
            $this->output( 'Error', 'No id provided' );
        }
        $order = Order::getOrder( $o_id );
        if( ! $order ){
            $this->output( 'Error', 'No Order found' );
        }

        if( ! \in_array( $order->o_status, [ 'confirmed', 'delivering', 'delivered' ] ) ){
            $this->output( 'Error', 'You cannot pay for this order.' );
        }
        if( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ){
            $this->output( 'Success', 'You have already paid for this order.' );
        }
        $output = [];
        switch ($method) {
            case 'fosterPayment':
                $output = FosterPayment::instance()->error( $order );
                break;
            default:
                # code...
                break;
        }
        $this->output( $output['title']??'', $output['heading']??'', $output['body']??'' );
    }

    public function ipn( $method = '' ){
        $output = [];
        switch ($method) {
            case 'bKash':
                $output = Bkash::instance()->ipn();
                break;
            case 'fosterPayment':
            default:
                $output = FosterPayment::instance()->ipn();
                break;
        }
        $this->output( $output['title']??'', $output['heading']??'', $output['body']??'' );
    }

    public function callback( $method ){
        $order_id = $_GET['order_id'] ?? '';
        $o_id = (int)$order_id;

        if( ! $method || ! $o_id ){
            $this->output( 'Error', 'No id provided' );
        }
        $order = Order::getOrder( $o_id );
        if( ! $order ){
            $this->output( 'Error', 'No Order found' );
        }
        if( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ){
            $this->output( 'Success', 'You have already paid for this order.' );
        }
        $output = [];
        switch ($method) {
            case 'nagad':
                $output = Nagad::instance()->callback( $order );
                break;
            default:
                # code...
                break;
        }
        $this->output( $output['title']??'', $output['heading']??'', $output['body']??'' );
    }


    public function output( $title, $heading, $body = '' ){
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en-US" xml:lang="en-US">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex, nofollow">
            <title><?php echo $title . ' - Arogga'; ?></title>
        </head>
        <body>
            <div style="text-align: center;">
                <h3 id="heading"><?php echo $heading; ?></h3>
                <?php if( $body ){
                    echo "<div id='body'>$body</div>";
                } ?>
                <?php if( 'Success' == $title ){ ?>
                    <script>
                        setTimeout(function(){
                            window.location.href = "https://www.arogga.com/account#orders";
                        }, 5000);
                    </script>
                <?php } ?>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

}