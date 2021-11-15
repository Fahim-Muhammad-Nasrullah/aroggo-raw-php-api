<?php

namespace OA\Payment;
use OA\{Functions, Response, Auth};
use OA\Factory\{User, Medicine, Discount, Order, Option, CacheUpdate};
use GuzzleHttp\Client;

class Bkash {

    public static $instance;
    private $client;
    private $cl_data = [];
    // bKash Merchant API Information

    public static function instance() {
        if( ! self::$instance instanceof self ) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    function __construct() {
    }

    private function client(){
        $this->cl_data['Request Body']['headers'] = [
            'Content-Type' => 'application/json',
            'authorization' => Option::get( 'bKash_token' ),
            'x-app-key' => BKASH_APP_KEY,
        ];
        return new Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'authorization' => Option::get( 'bKash_token' ),
                'x-app-key' => BKASH_APP_KEY,
            ],
        ]);
    }

    public function proceed( $order ){
        ob_start();
        ?>
        <div id="pls_wait">Please Wait...</div>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
        <script id="myScript" src="<?PHP echo BKASH_SCRIPT_URL; ?>"></script>
        <button id="bKash_button" style="display:none;">Pay With bKash</button>

        <script type="text/javascript">
            $(document).ready(function(){

                var paymentConfig={
                    createCheckoutURL:"<?php echo $order->signedUrl( '/payment/v1/bKash/create' ); ?>",
                    executeCheckoutURL:"<?php echo $order->signedUrl( '/payment/v1/bKash/execute' ); ?>",
                    tryAgainURL:"<?php echo $order->signedUrl( '/payment/v1' ); ?>",
                    successUrl:"<?php echo $order->signedUrl( '/payment/v1/success/bKash' ); ?>",
                };

                var paymentRequest;
                var paymentID;
                paymentRequest = { amount:'<?php echo $order->o_total;?>',intent:'sale'};
                bKash.init({
                    paymentMode: 'checkout',
                    paymentRequest: paymentRequest,
                    createRequest: function(request){
                        console.log('=> createRequest');
                        console.log(request);
                        $.ajax({
                            url: paymentConfig.createCheckoutURL+"?amount="+paymentRequest.amount,
                            type:'GET',
                            contentType: 'application/json',
                            success: function(data) {
                                if(!!data && !!data.data && data.data.paymentID != null){
                                    paymentID = data.data.paymentID;
                                    bKash.create().onSuccess(data.data);
                                }
                                else {
                                    console.log('error1');
                                    bKash.create().onError();
                                    if( data.message ){
                                        jqAlert( data.message )
                                    } else if( data.data.errorMessage ){
                                        jqAlert( data.data.errorMessage )
                                    }
                                }
                            },
                            error: function(){
                                console.log('error2');
                                bKash.create().onError();
                                jqAlert( "Close this window and try again" )
                            },
                            complete: function(){
                                console.log('complete');
                                $("#pls_wait").hide();
                            }
                        });
                    },

                    executeRequestOnAuthorization: function(request){
                        console.log('=> executeRequestOnAuthorization');
                        $.ajax({
                            url: paymentConfig.executeCheckoutURL+"?paymentID="+paymentID,
                            type: 'GET',
                            contentType:'application/json',
                            success: function(data){
                                if(!!data && !!data.data && data.data.paymentID != null){
                                    window.location.href = paymentConfig.successUrl+"?paymentID="+data.data.paymentID;
                                } else {
                                    console.log('error3');
                                    bKash.execute().onError();
                                    if( data.message ){
                                        jqAlert( data.message )
                                    } else if( data.data.errorMessage ){
                                        jqAlert( data.data.errorMessage )
                                    }
                                }
                            },
                            error: function(){
                                console.log('error4');
                                bKash.execute().onError();
                                jqAlert( "Close this window and try again" )
                            }
                        });
                    },
                    onClose: function () {
                        console.log('User has clicked the close button');
                        window.location.href = paymentConfig.tryAgainURL;
                    }
                });
                clickPayButton();

                function callReconfigure(val){
                    bKash.reconfigure(val);
                }

                function clickPayButton(){
                    $("#bKash_button").trigger('click');
                }
                function jqAlert(outputMsg, titleMsg = 'Payment Failed') {
                    if (!outputMsg) return;
                    
                    var div=$('<div></div>');
                    div.html(outputMsg).dialog({
                        title: titleMsg,
                        resizable: false,
                        modal: true,
                        buttons: {
                            "Try Again": function () {
                                $(this).dialog("close");
                            }
                        },
                        close: function () {
                            window.location.href = paymentConfig.tryAgainURL;
                        }
                    });
                    if (!titleMsg) div.siblings('.ui-dialog-titlebar').hide();
                }
            });
        </script>
        <link rel="stylesheet" type="text/css" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.min.css" />
        <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" integrity="sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU=" crossorigin="anonymous"></script>
        <?php
        return $this->output('Payment with bKash', '', ob_get_clean());
    }

    function token(){

        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'username' => BKASH_USERNAME,
                'password' => BKASH_PASSWORD,
            ],
        ]);
        $PostData = [
            'app_key' => BKASH_APP_KEY,
            'app_secret' => BKASH_APP_SECRET
        ];
        $this->cl_data['API Title'] = "Grant Token";
        $this->cl_data['API URL'] = BKASH_BASE_URL . "/checkout/token/grant";
        $this->cl_data['Request Body']['headers'] = [
            'Content-Type' => 'application/json',
            'username' => BKASH_USERNAME,
            'password' => BKASH_PASSWORD,
        ];
        $this->cl_data['Request Body']['body params'] = [
            'app_key' => BKASH_APP_KEY,
            'app_secret' => BKASH_APP_SECRET
        ];

        $res = $client->post( BKASH_BASE_URL . "/checkout/token/grant", ['json'=> $PostData] );
        if( 200 !== $res->getStatusCode() ){
            Response::instance()->sendMessage( 'Something wrong, Please try again.' );
        }
        $Result_Data = Functions::maybeJsonDecode( $res->getBody()->getContents() );
        if( ! $Result_Data || ! \is_array( $Result_Data ) || empty( $Result_Data['id_token'] ) ){
            Response::instance()->sendMessage( 'Something wrong, Please try again.' );
        }
        $this->cl_data['API Response'] = $Result_Data;

        file_put_contents( STATIC_DIR . '/bkash.json', Functions::maybeJsonEncode( $this->cl_data ), FILE_APPEND | LOCK_EX);

        Option::set( 'bKash_token', $Result_Data['id_token'] );

        return $Result_Data['id_token'];
    }

    public function create( $o_id, $o_token ){
        if( ! $o_id || ! $o_token ){
            Response::instance()->sendMessage( 'No id or secret provided' );
        }
        $order = Order::getOrder( $o_id );
        if( ! $order || ! $order->validateToken( $o_token )){
            Response::instance()->sendMessage( 'No Order found' );
        }
        if( ! \in_array( $order->o_status, [ 'confirmed', 'delivering', 'delivered' ] ) ){
            Response::instance()->sendMessage( 'You cannot pay for this order.' );
        }
        if( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ){
            Response::instance()->sendMessage( 'You have already paid for this order.' );
        }

        $this->token();

        $this->cl_data['API Title'] = "Create Payment";
        $this->cl_data['API URL'] = BKASH_BASE_URL . "/checkout/payment/create";

        $PostData = [
            "intent" => "sale",
            "currency" => "BDT",
            "amount" => $order->o_total,
            "merchantInvoiceNumber" => $order->o_id,
        ];

        $res = $this->client()->post( BKASH_BASE_URL . "/checkout/payment/create", ['json'=> $PostData] );
        if( 200 !== $res->getStatusCode() ){
            Response::instance()->sendMessage( 'Something wrong, Please try again.' );
        }
        $Result_Data = Functions::maybeJsonDecode( $res->getBody()->getContents() );
        if( ! $Result_Data || ! \is_array( $Result_Data ) ){
            Response::instance()->sendMessage( 'Something wrong, Please try again.' );
        }
        $this->cl_data['Request Body']['body params'] = $PostData;
        $this->cl_data['API Response'] = $Result_Data;
        file_put_contents( STATIC_DIR . '/bkash.json', Functions::maybeJsonEncode( $this->cl_data ), FILE_APPEND | LOCK_EX);

        Response::instance()->sendData( $Result_Data, 'success' );
    }

    public function execute( $o_id, $o_token ){
        if( ! $o_id || ! $o_token ){
            Response::instance()->sendMessage( 'No id or secret provided' );
        }
        $order = Order::getOrder( $o_id );
        if( ! $order || ! $order->validateToken( $o_token ) ){
            Response::instance()->sendMessage( 'No Order found' );
        }
        if( ! \in_array( $order->o_status, [ 'confirmed', 'delivering', 'delivered' ] ) ){
            Response::instance()->sendMessage( 'You cannot pay for this order.' );
        }
        if( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ){
            Response::instance()->sendMessage( 'You have already paid for this order.' );
        }
        $paymentID = $_GET['paymentID']?? '';

        $this->cl_data['API Title'] = "Execute Payment";
        $this->cl_data['API URL'] = BKASH_BASE_URL . "/checkout/payment/execute/" . $paymentID;

        try {
            $res = $this->client()->post( BKASH_BASE_URL . "/checkout/payment/execute/" . $paymentID, ['json'=> []] );
            if( 200 !== $res->getStatusCode() ){
                Response::instance()->sendMessage( 'Something wrong, Please try again.' );
            }
            $Result_Data = Functions::maybeJsonDecode( $res->getBody()->getContents() );
            if( ! $Result_Data || ! \is_array( $Result_Data ) ){
                Response::instance()->sendMessage( 'Something wrong, Please try again.' );
            }
        } catch (\Exception $e) {
            $res = $this->client()->get( BKASH_BASE_URL . "/checkout/payment/query/".$paymentID, ['json'=> []] );
            if( 200 !== $res->getStatusCode() ){
                Response::instance()->sendMessage( 'Something wrong, Please try again.' );
            }
            $Result_Data = Functions::maybeJsonDecode( $res->getBody()->getContents() );
            if( ! $Result_Data || ! \is_array( $Result_Data ) || 'Completed' !== $Result_Data['transactionStatus'] ){
                Response::instance()->sendMessage( 'Something wrong, Please try again.' );
            }
        }

        //$this->cl_data['Request Body']['body params'] = $PostData;
        $this->cl_data['API Response'] = $Result_Data;
        file_put_contents( STATIC_DIR . '/bkash.json', Functions::maybeJsonEncode( $this->cl_data ), FILE_APPEND | LOCK_EX);

        $order->setMeta( 'bkash_trxID', $Result_Data['trxID'] ?? '' );
        $order->setMeta( 'bkash_paymentID', $paymentID );

        Response::instance()->sendData( $Result_Data, 'success' );
    }

    public function success( $order ){
        $paymentID = $_GET['paymentID']?? '';

        $try_again = '<a href="' . $order->signedUrl( '/payment/v1' ) . '/">Try Again</a>';

        $this->cl_data['API Title'] = "Query Payment";
        $this->cl_data['API URL'] = BKASH_BASE_URL . "/checkout/payment/query/".$paymentID;

        $res = $this->client()->get( BKASH_BASE_URL . "/checkout/payment/query/".$paymentID, ['json'=> []] );
        if( 200 !== $res->getStatusCode() ){
            return $this->output( 'Error', 'Something wrong, Please try again.', $try_again );
        }
        $Result_Data = Functions::maybeJsonDecode( $res->getBody()->getContents() );
        if( ! $Result_Data || ! \is_array( $Result_Data ) ){
            return $this->output( 'Error', 'Something wrong, Please try again.', $try_again );
        }

        //$this->cl_data['Request Body']['body params'] = $PostData;
        $this->cl_data['API Response'] = $Result_Data;
        file_put_contents( STATIC_DIR . '/bkash.json', Functions::maybeJsonEncode( $this->cl_data ), FILE_APPEND | LOCK_EX);

        $meta = $order->getMeta( 'paymentResponse' );
        if( ! is_array( $meta ) ){
            $meta = [];
        }
        $post = $Result_Data;
        $post['log_time'] = \date( 'Y-m-d H:i:s' );
        $post['log_type'] = 'success';
        $meta[] = $post;
        $order->setMeta( 'paymentResponse', $meta );

        if( $Result_Data['merchantInvoiceNumber'] !== (string)$order->o_id || round( $Result_Data['amount'] ) !== round( $order->o_total ) || $order->getMeta('bkash_trxID') !== $Result_Data['trxID'] || 'Completed' !== $Result_Data['transactionStatus'] ){
            return $this->output( 'Error', "Payment mismatched. Contact customer support." );
        }
        $updateData = [
            'o_payment_method' => 'bKash',
        ];
        if( 'delivered' == $order->o_status && 'confirmed' == $order->o_i_status ){
            $updateData['o_i_status'] = 'paid';
        }
        $order->update( $updateData );
        $order->setMeta( 'paymentStatus', 'paid' );
        $order->setMeta( 'paymentAmount', $order->o_total );
        //$order->setMeta( 'paymentGatewayFee', 0 );

        $order->addTimeline( 'payment', [
            'body' => sprintf('%s Taka payment received by arogga through bKash.', $order->o_total ),
        ] );
        $order->addHistory( 'Payment', sprintf('%s Taka payment received through bKash', $order->o_total ) );

        return $this->output( 'Success', 'Payment success', 'You can close this window now.' );
    }

    public function manualSuccess( $o_id ){
        $trxID = isset($_GET['trxID']) ? $_GET['trxID'] : '';
        $customerMobile = isset($_GET['customerMobile']) ? $_GET['customerMobile'] : '';

        $user = User::getUser( Auth::id() );
        if ( ! $user || 'administrator' !== $user->u_role ) {
            Response::instance()->sendMessage( 'Your account does not have permission to do this.' );
        }

        $order = Order::getOrder( $o_id );
        if( ! $order ){
            Response::instance()->sendMessage( 'No Orders found' );
        }

        if( ! \in_array( $order->o_status, [ 'confirmed', 'delivering', 'delivered' ] ) ){
            Response::instance()->sendMessage( 'You cannot pay for this order.' );
        }
        if( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ){
            //Response::instance()->sendMessage( 'You have already paid for this order.' );
        }

        $this->cl_data['API Title'] = "Search Transaction Details";
        $this->cl_data['API URL'] = BKASH_BASE_URL . "/checkout/payment/search/".$trxID;

        $res = $this->client()->get( BKASH_BASE_URL . "/checkout/payment/search/".$trxID, ['json'=> []] );
        if( 200 !== $res->getStatusCode() ){
            Response::instance()->sendMessage( 'Something wrong, Please try again.' );
        }
        $Result_Data = Functions::maybeJsonDecode( $res->getBody()->getContents() );
        if( ! $Result_Data || ! \is_array( $Result_Data ) ){
            Response::instance()->sendMessage( 'Something wrong, Please try again.' );
        }

        $this->cl_data['API Response'] = $Result_Data;
        file_put_contents( STATIC_DIR . '/bkash.json', Functions::maybeJsonEncode( $this->cl_data ), FILE_APPEND | LOCK_EX);

        
        $meta = $order->getMeta( 'paymentResponse' );
        if( ! is_array( $meta ) ){
            $meta = [];
        }
        $post = $Result_Data;
        $post['log_time'] = \date( 'Y-m-d H:i:s' );
        $post['log_type'] = 'manualSuccess';
        $meta[] = $post;
        $order->setMeta( 'paymentResponse', $meta );

        if( false === strpos( $Result_Data['customerMsisdn'] ?? '', $customerMobile ) || round( $Result_Data['amount'] ?? 0 ) !== round( $order->o_total ) || 'Completed' !== $Result_Data['transactionStatus'] ?? '' ){
            Response::instance()->sendMessage( 'Payment mismatched. Contact customer support.' );
        }
        $updateData = [
            'o_payment_method' => 'bKash',
        ];
        if( 'delivered' == $order->o_status && 'confirmed' == $order->o_i_status ){
            $updateData['o_i_status'] = 'paid';
        }
        $order->update( $updateData );
        $order->setMeta( 'paymentStatus', 'paid' );
        $order->setMeta( 'paymentAmount', $order->o_total );
        $order->setMeta('bkash_trxID', $Result_Data['trxID']);
        //$order->setMeta( 'paymentGatewayFee', 0 );

        $order->addTimeline( 'payment', [
            'body' => sprintf('%s Taka payment received by arogga.', $order->o_total ),
        ]);
        $order->addHistory( 'Payment', sprintf('%s Taka payment received through bKash (Manual)', $order->o_total ) );

        Response::instance()->sendMessage( 'Payment success', 'success' );
    }

    public function refund( $o_id ){
        if( ! $o_id ){
            Response::instance()->sendMessage( 'No id or secret provided' );
        }
        $user = User::getUser( Auth::id() );
        if ( ! $user || 'administrator' !== $user->u_role ) {
            Response::instance()->sendMessage( 'Your account does not have permission to do this.' );
        }
        $order = Order::getOrder( $o_id );
        if( ! $order ){
            Response::instance()->sendMessage( 'No Order found' );
        }

        if( 'paid' !== $order->o_i_status && 'paid' !== $order->getMeta( 'paymentStatus' ) ){
            Response::instance()->sendMessage( 'You did not paid for this order.' );
        }
        $amount = $_POST['amount'] ?? '0.00';
        $reason = $_POST['reason'] ?? '';

        $this->cl_data['API Title'] = "Refund API";
        $this->cl_data['API URL'] = BKASH_BASE_URL . "/checkout/payment/refund";

        $PostData = [
            "paymentID" => $order->getMeta('bkash_paymentID'),
            "trxID" => $order->getMeta('bkash_trxID'),
            "amount" => $amount,
            "reason" => $reason,
            "sku" => 'SKU-' . $order->o_id,
        ];

        $res = $this->client()->post( BKASH_BASE_URL . "/checkout/payment/refund", ['json'=> $PostData] );
        if( 200 !== $res->getStatusCode() ){
            Response::instance()->sendMessage( 'Something wrong, Please try again.' );
        }
        $Result_Data = Functions::maybeJsonDecode( $res->getBody()->getContents() );
        if( ! $Result_Data || ! \is_array( $Result_Data ) ){
            Response::instance()->sendMessage( 'Something wrong, Please try again.' );
        }
        $this->cl_data['Request Body']['body params'] = $PostData;
        $this->cl_data['API Response'] = $Result_Data;
        file_put_contents( STATIC_DIR . '/bkash.json', Functions::maybeJsonEncode( $this->cl_data ), FILE_APPEND | LOCK_EX);

        Response::instance()->sendData( $Result_Data, 'success' );
    }

    public function refundStatus( $o_id ){
        if( ! $o_id ){
            Response::instance()->sendMessage( 'No id or secret provided' );
        }
        $order = Order::getOrder( $o_id );
        if( ! $order ){
            Response::instance()->sendMessage( 'No Order found' );
        }

        if( 'paid' !== $order->o_i_status && 'paid' !== $order->getMeta( 'paymentStatus' ) ){
            Response::instance()->sendMessage( 'You did not paid for this order.' );
        }
        $amount = $_POST['amount'] ?? 0;
        $reason = $_POST['reason'] ?? '';

        $this->cl_data['API Title'] = "Refund Status API";
        $this->cl_data['API URL'] = BKASH_BASE_URL . "/checkout/payment/refund";

        $PostData = [
            "paymentID" => $order->getMeta('bkash_paymentID'),
            "trxID" => $order->getMeta('bkash_trxID'),
        ];

        $res = $this->client()->post( BKASH_BASE_URL . "/checkout/payment/refund", ['json'=> $PostData] );
        if( 200 !== $res->getStatusCode() ){
            Response::instance()->sendMessage( 'Something wrong, Please try again.' );
        }
        $Result_Data = Functions::maybeJsonDecode( $res->getBody()->getContents() );
        if( ! $Result_Data || ! \is_array( $Result_Data ) ){
            Response::instance()->sendMessage( 'Something wrong, Please try again.' );
        }
        $this->cl_data['Request Body']['body params'] = $PostData;
        $this->cl_data['API Response'] = $Result_Data;
        file_put_contents( STATIC_DIR . '/bkash.json', Functions::maybeJsonEncode( $this->cl_data ), FILE_APPEND | LOCK_EX);

        Response::instance()->sendData( $Result_Data, 'success' );
    }

    public function ipn(){

        $data = [];

        //$payload = $_POST;

        $data['payload_post'] = $_POST;
        $payload  = file_get_contents('php://input');
        $data['payload_raw'] = $payload;

        $payload = Functions::maybeJsonDecode( $payload );

        $data['payload_decoded'] = $payload;

        file_put_contents( STATIC_DIR . '/bkash.json', Functions::maybeJsonEncode( $data ), FILE_APPEND | LOCK_EX);

        $data['SNS_MESSAGE_TYPE'] = $_SERVER['HTTP_X_AMZ_SNS_MESSAGE_TYPE'];
        // headers
        $messageType = $payload['Type'] ?? '';
        $signingCertURL = $payload['SigningCertURL'] ?? '';
        $certUrlValidation = $this->validateUrl($signingCertURL);

        $data['certUrlValidation'] = $certUrlValidation;
        
        if( $certUrlValidation ){
            $pubCert = $this->get_content($signingCertURL);
            $signatureDecoded = base64_decode( $payload['Signature'] ?? '' );

            $content = $this->getStringToSign($payload);
            $data['content'] = $content;

            if( $content ){
                $verified = openssl_verify( $content, $signatureDecoded, $pubCert, OPENSSL_ALGO_SHA1 );
                $data['verified'] = $verified;

                if( $verified ){
                    if( $messageType == "SubscriptionConfirmation" ){
                        try{
                            $res = $this->client()->get( $payload['SubscribeURL'] );
                            $data['statusCode'] = $res->getStatusCode();
                            $data['callContent'] = Functions::maybeJsonDecode( $res->getBody()->getContents() );
                        } catch (\Exception $e) {
                            $data['SubscribeURLcallError'] = $e->getMessage();
                        }
                        file_put_contents( STATIC_DIR . '/bkash.json', Functions::maybeJsonEncode( $data ), FILE_APPEND | LOCK_EX);

                        if( 200 !== $res->getStatusCode() ){
                            Response::instance()->sendMessage( 'Something wrong, Please try again.' );
                        } else {
                            Response::instance()->sendMessage( 'SubscriptionConfirmation called successfully.', 'success' );
                        }
                    } else if( $messageType == "Notification" ){
                        file_put_contents( STATIC_DIR . '/bkash.json', Functions::maybeJsonEncode( $data ), FILE_APPEND | LOCK_EX);
                        $this->ipnMessageHandler( $payload['Message'] );
                    }
                }
            }
        }
        file_put_contents( STATIC_DIR . '/bkash.json', Functions::maybeJsonEncode( $data ), FILE_APPEND | LOCK_EX);
    }

    private function ipnMessageHandler( $ipnMessage ){

        $bkash_trxID = isset( $ipnMessage['trxID'] ) ? $ipnMessage['trxID'] : '';
        $customerPhone = isset( $ipnMessage['debitMSISDN'] ) ? strtolower( $ipnMessage['debitMSISDN'] ) : '';

        $transactionStatus = isset( $ipnMessage['transactionStatus'] ) ? $ipnMessage['transactionStatus'] : '';
        $amount = isset( $ipnMessage['amount'] ) ? $ipnMessage['amount'] : 0;
        $currency = isset( $ipnMessage['currency'] ) ? $ipnMessage['currency'] : '';

        $orderID = isset( $ipnMessage['merchantInvoiceNumber'] ) ? $ipnMessage['merchantInvoiceNumber'] : 0;
        if( ! $orderID ){
            $orderID = isset( $ipnMessage['transactionReference'] ) ? trim( $ipnMessage['transactionReference'] ) : 0;
        }

        $order = Order::getOrder( $orderID );
        if( ! $order ){
            Response::instance()->sendMessage( 'No orders found.' );
        }

        if( 'paid' === $order->getMeta( 'paymentStatus' ) ){
            Response::instance()->sendMessage( 'Already updated.', 'success' );
        }

        if( round( $amount ) !== round( $order->o_total ) || 'Completed' !== $transactionStatus ){
            Response::instance()->sendMessage( 'Payment mismatched. Contact customer support.' );
        }

        $meta = $order->getMeta( 'paymentResponse' );
        if( ! is_array( $meta ) ){
            $meta = [];
        }
        $post = $ipnMessage;
        $post['log_time'] = \date( 'Y-m-d H:i:s' );
        $post['log_type'] = 'ipn';
        $meta[] = $post;
        $order->setMeta( 'paymentResponse', $meta );

        $updateData = [
            'o_payment_method' => 'bKash',
        ];
        if( 'delivered' == $order->o_status && 'confirmed' == $order->o_i_status ){
            $updateData['o_i_status'] = 'paid';
        }
        $order->update( $updateData );
        $order->setMeta( 'paymentStatus', 'paid' );
        $order->setMeta( 'paymentAmount', $order->o_total );
        //$order->setMeta( 'paymentGatewayFee', 0 );

        $order->addTimeline( 'payment', [
            'body' => sprintf('%s Taka payment received by arogga.', $order->o_total ),
        ] );

        Response::instance()->sendMessage( 'IPN received and updated.', 'success' );
    }

    private function validateUrl($url){
        $defaultHostPattern = '/^sns\.[a-zA-Z0-9\-]{3,}\.amazonaws\.com(\.cn)?$/';
        $parsed = parse_url($url);

        if (empty($parsed['scheme']) || empty($parsed['host']) || $parsed['scheme'] !== 'https' || substr($url, -4) !== '.pem' || !preg_match($defaultHostPattern, $parsed['host']) ) {
            return false;
        } else {
            return true;
        }
    }

    private function get_content( $url ){
        $client = new Client();
        $res = $client->get( $url );
        if( 200 !== $res->getStatusCode() ){
            Response::instance()->sendMessage( 'Something wrong, Please try again.' );
        }
        $data = $res->getBody()->getContents();

        return $data;
    }

    private function getStringToSign($message){
        $signableKeys = [
            'Message',
            'MessageId',
            'Subject',
            'SubscribeURL',
            'Timestamp',
            'Token',
            'TopicArn',
            'Type'
        ];

        $stringToSign = '';

        if ($message['SignatureVersion'] !== '1') {
            $errorLog =  "The SignatureVersion \"{$message['SignatureVersion']}\" is not supported.";
        } else{
            foreach ($signableKeys as $key) {
                if (isset($message[$key])) {
                    $stringToSign .= "{$key}\n{$message[$key]}\n";
                }
            }
        }
        return $stringToSign;
    }

    public function output( $title, $heading, $body = '' ){
        return compact( 'title', 'heading', 'body' );
    }

}