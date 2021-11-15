<?php

namespace OA\Payment;
use OA\{Functions, Response};
use OA\Factory\{User, Medicine, Discount, Order, Option, CacheUpdate};
use GuzzleHttp\Client;

class FosterPayment {

    public static $instance;
	
	public static function instance() {
		if( ! self::$instance instanceof self ) {
			self::$instance = new self;
		}
		return self::$instance;
    }

    function __construct() {
    }

    public function proceed( $order ){

        $txnNo = $order->getMeta( 'TxnNo' );
        if( ! $txnNo ){
            $txnNo = 'Txn' . $order->o_id . \date( 'YmdHis' );
            $order->setMeta( 'TxnNo', $txnNo );
        }
        $address = $order->o_gps_address;
        if( $order->o_gps_address && $order->o_address ){
            $address .= "\n";
        }
        $address .= $order->o_address;

        $items = [];

        $i = 1;
        foreach ( $order->medicines as $medicine ) {
            $items[] = implode( ',', [
                $i++,
                \rtrim( $medicine['name'] . '-' . $medicine['strength'], '-' ),
                Functions::qtyText( $medicine['qty'], $medicine),
                $medicine['d_price'],
            ]);
        }

        $urlparamForHash = http_build_query( array(
            'mcnt_AccessCode' => FP_ACCESS_CODE,
            'mcnt_TxnNo' => $txnNo,
            'mcnt_ShortName' => FP_SHORT_NAME,
            'mcnt_OrderNo' => $order->o_id,
            'mcnt_ShopId' => FP_SHOP_ID,
            'mcnt_Amount' => $order->o_total,
            'mcnt_Currency' => 'BDT'
        ));

        $secret = strtoupper( FP_SECRET_KEY );
        $hashinput = hash_hmac('SHA256',$urlparamForHash,$secret);


        $domain = 'arogga.com'; // or Manually put your domain name
        $ip = $_SERVER["SERVER_ADDR"];//domain ip
        $urlparam =array(
            'mcnt_TxnNo' => $txnNo,
            'mcnt_ShortName' => FP_SHORT_NAME,
            'mcnt_OrderNo' => $order->o_id,
            'mcnt_ShopId' => FP_SHOP_ID,
            'mcnt_Amount' => $order->o_total,
            'mcnt_Currency' => 'BDT',
            'cust_InvoiceTo' => $order->u_name,
            'cust_CustomerServiceName' => 'E-commarce',//must
            'cust_CustomerName' => $order->u_name,//must
            'cust_CustomerEmail' => 'info@arogga.com',//must //we do not track
            'cust_CustomerAddress' => $address,
            'cust_CustomerContact' => $order->u_mobile,//must
            'cust_CustomerGender' => 'N/A', //we do not track
            'cust_CustomerCity' => 'Dhaka',//must
            'cust_CustomerState' => 'Dhaka',
            'cust_CustomerPostcode' => '1000', //we do not track
            'cust_CustomerCountry' => 'Bangladesh',
            'cust_Billingaddress' => $address,//must if not put ‘N/A’
            'cust_ShippingAddress' => $address,
            'cust_orderitems' => implode( '|', $items ),//must
            'GW' => '',//optional
            'CardType' => '', //optional
            'success_url' => $order->signedUrl( '/payment/v1/success/fosterPayment' ),//must
            'cancel_url' => sprintf( '%s/payment/v1/error/%d/%s/?type=cancel', SITE_URL, $order->o_id, 'fosterPayment' ),//must
            'fail_url' => sprintf( '%s/payment/v1/error/%d/%s/?type=fail', SITE_URL, $order->o_id, 'fosterPayment' ),//must
            'emi_amout_per_month' =>'',//optional
            'emi_duration' => '',//optional
            'merchentdomainname' => $domain, // must
            'merchentip' => $ip,
            'mcnt_SecureHashValue' => $hashinput
        );

        $client = new Client();
        $res = $client->post( FP_URL . '/fosterpayments/paymentrequest.php', ['json' => $urlparam ]);
        if( 200 !== $res->getStatusCode() ){
            return $this->output( 'Error', 'Something wrong, Please try again.' );
        }
        $body = Functions::maybeJsonDecode( $res->getBody()->getContents() );
        if( ! $body || ! \is_array( $body ) || 200 !== (int)$body['status'] ){
            return $this->output( 'Error', 'Something wrong, Please try again.' );
        }

        $data = $body['data'];
        //var_dump( $data );
        $redirect_url = $data['redirect_url'];
        $payment_id = $data['payment_id'];
        $url = $redirect_url . "?payment_id=" . $payment_id;

        header("Location:" . $url); 
        exit; 
    }

    public function success( $order ){

        $fp_MerchantTxnNo = isset( $_POST['MerchantTxnNo'] ) ? $_POST['MerchantTxnNo'] : '';
        $fp_OrderNo = isset( $_POST['OrderNo'] ) ? (int)$_POST['OrderNo'] : 0;
        $fp_hashkey = isset( $_POST['hashkey'] ) ? $_POST['hashkey'] : '';
        $fp_TxnResponse = isset( $_POST['TxnResponse'] ) ? (int)$_POST['TxnResponse'] : 0;
        $fp_TxnAmount = isset( $_POST['TxnAmount'] ) ? (int)$_POST['TxnAmount'] : 0;
        $fp_Currency = isset( $_POST['Currency'] ) ? $_POST['Currency'] : '';

        if( $fp_OrderNo !== $order->o_id || $fp_MerchantTxnNo !== $order->getMeta( 'TxnNo' ) || $fp_hashkey  !== md5( $fp_TxnResponse . $fp_MerchantTxnNo . FP_SECRET_KEY ) ){
            return $this->output( 'Error', 'No Orders found' );
        }

        $meta = $order->getMeta( 'paymentResponse' );
        if( ! is_array( $meta ) ){
            $meta = [];
        }
        $post = $_POST;
        $post['log_time'] = \date( 'Y-m-d H:i:s' );
        $post['log_type'] = 'success';
        $meta[] = $post;
        $order->setMeta( 'paymentResponse', $meta );

        if( 2 !== $fp_TxnResponse ){
            return $this->output( 'Error', "Payment didn't succeed. Please Contact our support." );
        }

        if( 'BDT' !== $fp_Currency || round( $fp_TxnAmount ) !== round( $order->o_total ) ){
            return $this->output( 'Error', "Payment amount mismatched." );
        }

        if( 'paid' === $order->getMeta( 'paymentStatus' ) ){
            return $this->output( 'Success', 'Payment success', 'You can close this window now.' );
        }

        if( $this->fosterpaymentsStatus( $order ) ){
            $order->addTimeline( 'payment', [
                'body' => sprintf('%s Taka payment received by arogga.', $order->o_total ),
            ] );
            $order->addHistory( 'Payment', sprintf('%s Taka payment received through Foster Payment.', $order->o_total ) );
        }

        return $this->output( 'Success', 'Payment success', 'You can close this window now.' );
    }

    public function error( $order ){
        $type = $_GET['type'] ?? '';

        $try_again = '<a href="' . $order->signedUrl( '/payment/v1' ) . '/">Try Again</a>';

        $fp_MerchantTxnNo = isset( $_POST['MerchantTxnNo'] ) ? $_POST['MerchantTxnNo'] : '';
        $fp_OrderNo = isset( $_POST['OrderNo'] ) ? (int)$_POST['OrderNo'] : 0;
        $fp_hashkey = isset( $_POST['hashkey'] ) ? $_POST['hashkey'] : '';
        $fp_TxnResponse = isset( $_POST['TxnResponse'] ) ? (int)$_POST['TxnResponse'] : 0;

        if( $fp_OrderNo !== $order->o_id || $fp_MerchantTxnNo !== $order->getMeta( 'TxnNo' ) || $fp_hashkey  !== md5( $fp_TxnResponse . $fp_MerchantTxnNo . FP_SECRET_KEY ) ){
            return $this->output( 'Error', 'No Orders found' );
        }

        $meta = $order->getMeta( 'paymentResponse' );
        if( ! is_array( $meta ) ){
            $meta = [];
        }
        $post = $_POST;
        $post['log_time'] = \date( 'Y-m-d H:i:s' );
        $post['log_type'] = $type;
        $meta[] = $post;
        $order->setMeta( 'paymentResponse', $meta );

        if( 'fail' == $type ){
            return $this->output( 'Failed', 'Payment failed', $try_again );
        } elseif( 'cancel' == $type ){
            return $this->output( 'Cancel', 'Payment cancelled', $try_again );
        }
        return $this->output( 'Error', 'Something wrong.', $try_again );
    }

    public function ipn(){

        $fp_MerchantTxnNo = isset( $_POST['MerchantTxnNo'] ) ? $_POST['MerchantTxnNo'] : '';
        $fp_OrderNo = isset( $_POST['OrderNo'] ) ? (int)$_POST['OrderNo'] : 0;
        $fp_hashkey = isset( $_POST['hashkey'] ) ? strtolower( $_POST['hashkey'] ) : '';

        $fp_TxnResponse = isset( $_POST['TxnResponse'] ) ? (int)$_POST['TxnResponse'] : '';
        $fp_TxnAmount = isset( $_POST['TxnAmount'] ) ? (int)$_POST['TxnAmount'] : 0;
        $fp_Currency = isset( $_POST['Currency'] ) ? $_POST['Currency'] : '';

        $order = Order::getOrder( $fp_OrderNo );
        if( ! $order ){
            Response::instance()->sendMessage( 'No orders found.' );
        }

        if( $fp_OrderNo !== $order->o_id || $fp_MerchantTxnNo !== $order->getMeta( 'TxnNo' ) || $fp_hashkey  !== md5( $fp_TxnResponse . $fp_MerchantTxnNo . FP_SECRET_KEY ) ){
            Response::instance()->sendMessage( 'Order id or Txn id or hash mismatched.' );
        }

        if( 2 !== $fp_TxnResponse || 'BDT' !== $fp_Currency || round( $fp_TxnAmount ) !== round( $order->o_total ) ){
            Response::instance()->sendMessage( 'response code or amount mismatched.' );
        }
        if( 'paid' === $order->getMeta( 'paymentStatus' ) ){
            Response::instance()->sendMessage( 'Already updated.', 'success' );
        }

        $meta = $order->getMeta( 'paymentResponse' );
        if( ! is_array( $meta ) ){
            $meta = [];
        }
        $post = $_POST;
        $post['log_time'] = \date( 'Y-m-d H:i:s' );
        $post['log_type'] = 'ipn';
        $meta[] = $post;
        $order->setMeta( 'paymentResponse', $meta );

        if( $this->fosterpaymentsStatus( $order ) ){
            $order->addTimeline( 'payment', [
                'body' => sprintf('%s Taka payment received by arogga.', $order->o_total ),
            ] );
            $order->addHistory( 'Payment', sprintf('%s Taka payment received through Foster Payment (ipn).', $order->o_total ) );
        }

        Response::instance()->sendMessage( 'IPN received and updated.', 'success' );
    }


    public function output( $title, $heading, $body = '' ){
        return compact( 'title', 'heading', 'body' );
    }

    public function fosterpaymentsStatus( $order ){
        $fp_MerchantTxnNo = $order->getMeta( 'TxnNo' );
        if( ! $fp_MerchantTxnNo ){
            return false;
        }
        if( 'paid' === $order->getMeta( 'paymentStatus' ) ){
            return false;
        }

        $client = new Client();
        $res = $client->get( FP_URL . '/fosterpayments/TransactionStatus/transactionStatusApi1.2.php?mcnt_TxnNo=' . $fp_MerchantTxnNo . '&mcnt_SecureHashValue=' . md5( FP_SECRET_KEY . $fp_MerchantTxnNo ) );
        if( 200 !== $res->getStatusCode() ){
            Response::instance()->sendMessage( 'Something wrong, Please try again.' );
        }
        $body = Functions::maybeJsonDecode( $res->getBody()->getContents() );
        if( ! $body || ! \is_array( $body ) ){
            return false;
        }
        $_body = reset( $body );
        $fp_st_MerchantTxnNo = isset( $_body['merchantTxnNo'] ) ? $_body['merchantTxnNo'] : '';
        $fp_st_OrderNo = isset( $_body['orderNo'] ) ? (int)$_body['orderNo'] : 0;
        $fp_st_hashkey = isset( $_body['haskey'] ) ? $_body['haskey'] : '';
        $fp_gateway_fee = isset( $_body['serviceCharge'] ) ? $_body['serviceCharge'] : 0;

        $fp_TxnResponse = isset( $_body['txnResponse'] ) ? (int)$_body['txnResponse'] : '';
        $fp_TxnAmount = isset( $_body['txnAmount'] ) ? $_body['txnAmount'] : 0;
        $fp_Currency = isset( $_body['currency'] ) ? $_body['currency'] : '';

        if( $fp_st_MerchantTxnNo !== $fp_MerchantTxnNo || $fp_st_OrderNo !== $order->o_id ){
            return false;
        }
        if( 2 !== $fp_TxnResponse || 'BDT' !== $fp_Currency || round( $fp_TxnAmount ) !== round( $order->o_total ) ){
            return false;
        }
        if( 'paid' === $order->getMeta( 'paymentStatus' ) ){
            return false;
        }
        $updateData = [];
        if( 'fosterPayment' != $order->o_payment_method  ){
            $updateData['o_payment_method'] = 'fosterPayment';
        }
        if( 'delivered' == $order->o_status && 'confirmed' == $order->o_i_status ){
            $updateData['o_i_status'] = 'paid';
        }
        if( $updateData ){
            $order->update( $updateData );
        }
        $order->setMeta( 'paymentStatus', 'paid' );
        $order->setMeta( 'paymentAmount', $order->o_total );
        $order->setMeta( 'paymentGatewayFee', $fp_gateway_fee );

        $meta = $order->getMeta( 'paymentResponse' );
        if( ! is_array( $meta ) ){
            $meta = [];
        }
        $post = $body;
        $post['log_time'] = \date( 'Y-m-d H:i:s' );
        $post['log_type'] = 'status';
        $meta[] = $post;
        $order->setMeta( 'paymentResponse', $meta );

        return true;
    }
}