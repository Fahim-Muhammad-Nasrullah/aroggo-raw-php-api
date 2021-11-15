<?php

namespace OA\Payment;
use OA\{Functions, Response};
use OA\Factory\{User, Medicine, Discount, Order, Option, CacheUpdate};
use GuzzleHttp\Client;

class Nagad {

    public static $instance;
    private $client;

    function __construct() {
        $this->client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'X-KM-Api-Version' => 'v-0.2.0',
                'X-KM-IP-V4' => $_SERVER["SERVER_ADDR"],
                'X-KM-Client-Type' => 'PC_WEB'
            ],
        ]);
    }

    public static function instance() {
		if( ! self::$instance instanceof self ) {
			self::$instance = new self;
		}
		return self::$instance;
    }


    public function proceed( $order ){
        $paymentAttempt = $order->getMeta( 'paymentAttempt' );
        $order->setMeta('paymentAttempt', $paymentAttempt+1);
        $char = '';
        if( $paymentAttempt ){
            $i = $paymentAttempt;
            $char = 'A';
            while($i--){
                ++$char;
            }
        }
        $address = $order->o_gps_address;
        if( $order->o_gps_address && $order->o_address ){
            $address .= "\n";
        }
        $address .= $order->o_address;

        $MerchantID = NAGAD_MERCHANT_ID;
        $DateTime = Date('YmdHis');
        $OrderId = $order->o_id.$char;
    
        $SensitiveData = array(
            'merchantId' => $MerchantID,
            'datetime' => $DateTime,
            'orderId' => $OrderId,
            'challenge' => Functions::randToken( 'hexdec', 40 ),
        );
        $PostData = array(
            'accountNumber' => NAGAD_MERCHANT_NO,
            'dateTime' => $DateTime,
            'sensitiveData' => $this->EncryptDataWithPublicKey(json_encode($SensitiveData)),
            'signature' => $this->SignatureGenerate(json_encode($SensitiveData))
        );
        $try_again = '<a href="' . $order->signedUrl( '/payment/v1' ) . '/">Try Again</a>';

        try {
            $res = $this->client->post( NAGAD_URL . "/api/dfs/check-out/initialize/$MerchantID/$OrderId", ['json'=> $PostData] );
        } catch (\Exception $e) {
            return $this->output( 'Error', 'Something wrong, Please try again.', $try_again );
        }

        if( 200 !== $res->getStatusCode() ){
            return $this->output( 'Error', 'Something wrong, Please try again.', $try_again );
        }
        $Result_Data = Functions::maybeJsonDecode( $res->getBody()->getContents() );
        if( ! $Result_Data || ! \is_array( $Result_Data ) || empty($Result_Data['sensitiveData']) || empty($Result_Data['signature']) ){
            return $this->output( 'Error', 'Something wrong, Please try again.', $try_again );
        }
        
        $PlainResponse = Functions::maybeJsonDecode( $this->DecryptDataWithPrivateKey($Result_Data['sensitiveData']) );

        if ( empty($PlainResponse['paymentReferenceId']) || empty($PlainResponse['challenge']) ) {
            return $this->output('Failed', 'Nagad Payment Failed', $try_again );
        }

        $SensitiveDataOrder = array(
            'merchantId' => $MerchantID,
            'orderId' => $OrderId,
            'currencyCode' => '050',
            'amount' => $order->o_total,
            'challenge' => $PlainResponse['challenge']
        );
        $PostDataOrder = array(
            'sensitiveData' => $this->EncryptDataWithPublicKey(json_encode($SensitiveDataOrder)),
            'signature' => $this->SignatureGenerate(json_encode($SensitiveDataOrder)),
            'merchantCallbackURL' => sprintf( '%s/payment/v1/callback/%s', SITE_URL, 'nagad' ),
            'additionalMerchantInfo' => ['Service Name' => 'AROGGA LIMITED'],
        );
        $res = $this->client->post( NAGAD_URL . '/api/dfs/check-out/complete/' . $PlainResponse['paymentReferenceId'], ['json'=> $PostDataOrder] );
        if( 200 !== $res->getStatusCode() ){
            return $this->output( 'Error', 'Something wrong, Please try again.', $try_again );
        }
        $Result_Data_Order = Functions::maybeJsonDecode( $res->getBody()->getContents() );
        if( ! $Result_Data_Order || ! \is_array( $Result_Data_Order ) || empty($Result_Data_Order['status']) ){
            return $this->output( 'Error', 'Something wrong, Please try again.', $try_again );
        }
        if ($Result_Data_Order['status'] === "Success") {
            $url = json_encode($Result_Data_Order['callBackUrl']);
            return $this->output('Success', 'Payment with Nagad', "<script>window.open($url, '_self')</script>");                      
        } else {
            return $this->output('Failed', 'Nagad Payment Failed', $try_again );
        }
    }

    public function callback( $order ){
        $payment_ref_id = $_GET['payment_ref_id'] ?? '';
        $status = $_GET['status'] ?? '';
        if( $status === 'Success' ){
            header("Location:" . $order->signedUrl( '/payment/v1/success/nagad', '/?payment_ref_id=' . $payment_ref_id ) ); 
            exit; 
        }
        $meta = $order->getMeta( 'paymentResponse' );
        if( ! is_array( $meta ) ){
            $meta = [];
        }
        $post = $_GET;
        $post['log_time'] = \date( 'Y-m-d H:i:s' );
        $post['log_type'] = 'fail';
        $meta[] = $post;
        $order->setMeta( 'paymentResponse', $meta );

        $try_again = sprintf( '<a href="%s">Try Again</a>', SITE_URL, $order->signedUrl( '/payment/v1' ) );

        return $this->output( 'Failed', 'Payment failed', $try_again);
    }

    public function success( $order ){

        $payment_ref_id = $_GET['payment_ref_id'] ?? '';
        $res = $this->client->get( NAGAD_URL . "/api/dfs/verify/payment/$payment_ref_id" );

        if( 200 !== $res->getStatusCode() ){
            return $this->output( 'Error', 'Something wrong, Please contact customer care.' );
        }
        $result = Functions::maybeJsonDecode( $res->getBody()->getContents() );
        if( ! $result || ! \is_array( $result ) ){
            return $this->output( 'Error', 'Something wrong, Please contact customer care.' );
        }

        $meta = $order->getMeta( 'paymentResponse' );
        if( ! is_array( $meta ) ){
            $meta = [];
        }
        $post = $result;
        $post['log_time'] = \date( 'Y-m-d H:i:s' );
        $post['log_type'] = 'success';
        $meta[] = $post;
        $order->setMeta( 'paymentResponse', $meta );

        $try_again = sprintf( '<a href="%s">Try Again</a>', SITE_URL, $order->signedUrl( '/payment/v1' ) );

        if($result['status'] !== "Success"){
            return $this->output( 'Failed', 'Payment failed', $try_again);
        }

        if( $result['merchantId'] !== NAGAD_MERCHANT_ID ||  0 !== strpos( $result['orderId'], (string)$order->o_id ) || round( $result['amount'] ) !== round( $order->o_total ) ){
            return $this->output( 'Error', "Payment mismatched. Contact customer support." );
        }
        
        $updateData = [
            'o_payment_method' => 'nagad',
        ];
        if( 'delivered' == $order->o_status && 'confirmed' == $order->o_i_status ){
            $updateData['o_i_status'] = 'paid';
        }
        $order->update( $updateData );
        $order->setMeta( 'paymentStatus', 'paid' );
        $order->setMeta( 'paymentAmount', $order->o_total );
        //$order->setMeta( 'paymentGatewayFee', 0 );

        $order->addTimeline( 'payment', [
            'body' => sprintf('%s Taka payment received by arogga through Nagad.', $order->o_total ),
        ] );
        $order->addHistory( 'Payment', sprintf('%s Taka payment received through Nagad', $order->o_total ) );

        return $this->output( 'Success', 'Payment success', 'You can close this window now.' );
    }

    function EncryptDataWithPublicKey($data){
        $public_key = "-----BEGIN PUBLIC KEY-----\n" . NAGAD_PG_PUBLIC_KEY . "\n-----END PUBLIC KEY-----";
        $key_resource = openssl_get_publickey($public_key);
        openssl_public_encrypt($data, $cryptText, $key_resource);
        return base64_encode($cryptText);
    }

    function SignatureGenerate($data){
        $private_key = "-----BEGIN RSA PRIVATE KEY-----\n" . NAGAD_PRIVATE_KEY . "\n-----END RSA PRIVATE KEY-----";
        openssl_sign($data, $signature, $private_key, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    function DecryptDataWithPrivateKey($cryptText){
        $private_key = "-----BEGIN RSA PRIVATE KEY-----\n" . NAGAD_PRIVATE_KEY . "\n-----END RSA PRIVATE KEY-----";
        openssl_private_decrypt(base64_decode($cryptText), $plain_text, $private_key);
        return $plain_text;
    }

    public function output( $title, $heading, $body = '' ){
        return compact( 'title', 'heading', 'body' );
    }

}