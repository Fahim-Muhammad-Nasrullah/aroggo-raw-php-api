<?php

namespace OA;
use OA\Factory\{User, Medicine, Discount, Order, Option, CacheUpdate, Inventory, Meta, Redx, Location, Bag};
use GuzzleHttp\Client;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Firebase\JWT\JWT;

class Functions {

    function __construct() {
    }

    public static function randToken( $type, $length ){
        switch ( $type ) {
            case 'alpha':
                $string = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'hexdec':
                $string = '0123456789abcdef';
                break;
            case 'numeric':
                $string = '0123456789';
                break;
            case 'distinct':
                $string = '2345679ACDEFHJKLMNPRSTUVWXYZ';
                break;
            case 'alnumlc':
                $string = '0123456789abcdefghijklmnopqrstuvwxyz';
                break;
            case 'alnum':
            default:
                $string = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
        }
        $max = strlen($string);
        $token = '';
        for ($i=0; $i < $length; $i++) {
            $token .= $string[random_int(0, $max-1)];
        }
    
        return $token;
    }

    public static function jwtEncode( $payload, $key = JWT_TOKEN_KEY ){
        return JWT::encode( $payload, $key );
    }

    public static function jwtDecode( $token, $key = JWT_TOKEN_KEY ){
        try {
            $payload = (array)JWT::decode( $token, $key, array('HS256'));

            return $payload;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function sendOTPSMS( $mobile, $otp, $count = 1 ) {
        if( !$mobile || !$otp ) {
            return false;
        }
        if( 0 === \strpos( $mobile, '+880100000000') ) {
            return false;
        }

        if( !MAIN ) {
            return false;
        }

        $message = "Your Arogga OTP is: {$otp}\nUID:7UvkiTFw3Ha";

        static::sendSMS( $mobile, $message, $count );
    }

    public static function sendSMS( $mobile, $message, $count = 0 ){
        if( !MAIN ) {
            return false;
        }
        if( ! $mobile || ! $message ) {
            return false;
        }
        $gateway = ACTIVE_SMS_GATEWAYS[ (int)$count % count(ACTIVE_SMS_GATEWAYS) ];

        $mobile = is_array( $mobile ) ? implode( ",", $mobile ) : $mobile;

        $url = '';
        $data = [];

        switch ( $gateway ) {
            case 'ALPHA':
                $data = [
                    'u' => 'arogga',
                    'h' => ALPHA_SMS_KEY,
                    'op' => 'pv',
                    'to' => $mobile,
                    'msg' => $message
                ];
                $url = 'https://alphasms.biz/index.php?app=ws';
                break;
            case 'GREENWEB':
                $data = [
                    'token' => GREENWEB_SMS_KEY,
                    'to' => $mobile,
                    'message' => $message
                ];
                $url = 'http://api.greenweb.com.bd/api.php';
                break;
            case 'BULK71':
                $data = [
                    'api_key' => BULK71_SMS_KEY,
                    'mobile_no' => $mobile,
                    'message' => $message,
                    'User_Email' => 'testshamimhasan@gmail.com',
                    'sender_id' => '47',
                ];
                $url = 'https://71bulksms.com/sms_api/bulk_sms_sender.php';
                break;
            case 'MDL':
                $data = [
                    'api_key' => MDL_SMS_KEY,
                    'senderid' => MDL_SENDER_ID,
                    'label' => 'transactional',
                    'type' => 'text',
                    'contacts' => $mobile,
                    'msg' => $message
                ];
                $url = 'http://premium.mdlsms.com/smsapi';
                break;

            default:
                return false;
                break;
        }
        if( ! $url || ! $data || ! \is_array( $data ) ){
            return false;
        }
        try {
            $client = new Client(['verify' => false, 'http_errors' => false]);
            $client->post( $url, [
                'form_params' => $data,
            ]);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    public static function sendNotification( $fcm_token, $title, $message, $extraData = [] ) {
        if( !$fcm_token || ! $title || ! $message ) {
            return false;
        }
        if( !MAIN ) {
            return false;
        }
        try {
            $body = [
                'notification' => [
                    'title' => $title,
                    'body' => $message,
                    'sound' => 'default',
                    'badge' => '1',
                    //'icon' => 'https://api.arogga.com/static/icon.png',
                    //'image' => 'https://api.arogga.com/static/logo.png',
                ],
                'data' => [
                    'title' => $title,
                    'body' => $message,
                    'extraData' => $extraData,
                ]
            ];
            if( is_array( $fcm_token ) ){
                $body['registration_ids'] = $fcm_token;
            } else {
                $body['to'] = $fcm_token;
            }

            $client = new Client([
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'key='. FCM_SERVER_KEY,
                ],
                'http_errors' => false,
            ]);
            $client->post('https://fcm.googleapis.com/fcm/send',
                ['body' => json_encode($body)]
            );
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    public static function sendAsyncNotification( $client, $fcm_token, $title, $message, $extraData = [] ) {
        if( !$fcm_token || ! $title || ! $message ) {
            return false;
        }
        if( !MAIN ) {
            return false;
        }
        try {
            $promise = $client->postAsync('https://fcm.googleapis.com/fcm/send',
                ['body' => json_encode([
                    'notification' => [
                        'title' => $title,
                        'body' => $message,
                        'sound' => 'default',
                        'badge' => '1',
                        //'icon' => 'https://api.arogga.com/static/icon.png',
                        //'image' => 'https://api.arogga.com/static/logo.png',
                    ],
                    'data' => [
                        'title' => $title,
                        'body' => $message,
                        'extraData' => $extraData,
                    ],
                    'to' => $fcm_token
                ])]
            );
            return $promise;
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    public static function sendOrderStatusChangeNotification( $prev_order, $order ) {
        if( ! $prev_order || !$order || $prev_order->o_status == $order->o_status ) {
            return false;
        }
        $user = User::getUser( $order->u_id );
        if( $user && $user->fcm_token ) {
            $title = '';
            $message = '';
            if( 'confirmed' == $order->o_status && 'online' !== $order->o_payment_method ) {
                $title = 'Order Confirmed';
                $message = "Hello {$order->u_name}\nYour order #{$order->o_id} is confirmed.";
            } elseif('delivering' == $order->o_status ) {
                if( $order->city() == 'Dhaka City' ){
                    $deliveryman = User::getUser( $order->o_de_id );
                    
                    $title = 'Order Delivering';
                    $message = sprintf("Hello %s\nWe have handed over your order #%d to deliveryman (%s: %s). Please be ready to receive it shortly. Deliveryman will call you once he reaches your address. Our delivery continues from 10am to 10pm everyday.", $order->u_name, $order->o_id, $deliveryman ? $deliveryman->u_name : '', $deliveryman ? $deliveryman->u_mobile : '' );
                }
            } elseif('delivered' == $order->o_status ) {
                $title = 'Order Delivered';
                $message = "Hello {$order->u_name}\nYour order #{$order->o_id} is delivered. Thank you for your purchase.";
            } elseif('cancelled' == $order->o_status ) {
                $title = 'Order Cancelled';
                $message = "Hello {$order->u_name}\nYour order #{$order->o_id} is cancelled.";
            }
            /*
             elseif('cancelled' == $order->o_status ) {
                $title = 'Order Cancelled';
                $message = "Hello {$order->u_name}\nDue to coronavirus outbreak we found unexpected spike in order volume and shortage of delivery personnel both at the same time.\nFor that reason, we are unable to fulfil your order #{$order->o_id}.We had to cancel the order and reorganize our delivery channel for this pandemic situation.\nWe are reshaping our delivery channel so that we can serve you in future with faster delivery service.\nThanks for your understanding.";
            }
            */
            if( $title && $message ) {
                return static::sendNotification( $user->fcm_token, $title, $message, ['screen' => 'Orders', 'btnScreen' => 'SingleOrder', 'btnScreenParams' => ['o_id' => $order->o_id], 'btnLabel' => 'View Order'] );
            }
        }
        return false;
    }

    public static function sendInternalOrderStatusChangeNotification( $prev_order, $order ) {
        if( ! $prev_order || !$order || $prev_order->o_i_status == $order->o_i_status ) {
            return false;
        }
        $user = User::getUser( $order->u_id );
        if( $user && $user->fcm_token ) {
            $title = '';
            $message = '';
            if( 'later' == $order->o_i_status ) {
                //$title2 = "Your order #{$order->o_id} may delay";
                //$message2 = "Hello {$user2->u_name}\nOne or more medicines of your order #{$order->o_id} is not available in our stock right now. We need extra few hours to make this availabe for you.\nYour estimated delivery time is 48 hours from now.\nIf you want to cancel this order for this extra delay, please call customer care 09606999688, we will cancel it for you.";
            }
            if( 'confirmed' == $order->o_status && 'packing' == $order->o_i_status && 'online' == $order->o_payment_method ){
                $title = 'Order Confirmed';
                $message = "Hello {$order->u_name}\nYour order #{$order->o_id} is packed and ready to be delivered.\nYou can now pay using your bKash/Nagad/Debit/Credit card.";
                return static::sendNotification( $user->fcm_token, $title, $message, ['screen' => 'Orders', 'btnScreen' => 'AroggaWebView', 'btnScreenParams' => ['uri' => $order->signedUrl( '/payment/v1', '', time() + 60*60*24*3 )], 'btnLabel' => 'Pay Online'] );
            }
            if( $title && $message ) {
                return static::sendNotification( $user->fcm_token, $title, $message );
            }
        }
        return false;
    }

    public static function calculateCash( $prev_order, $order ) {
        if( ! $prev_order || ! $order ) {
            return false;
        }
        
        $ph_user = User::getUser( $order->o_ph_id );

        if( $ph_user && $prev_order->o_total != $order->o_total && 'delivering' == $prev_order->o_status && 'delivering' == $order->o_status ) {
            $ph_user->pCashUpdate( $order->o_total - $prev_order->o_total );
        }

        if( $prev_order->o_status == $order->o_status ) {
            return false;
        }

        if( ! \in_array( $order->o_status, [ 'delivering', 'delivered', 'cancelled', 'damaged', 'returned' ] ) ) {
            return false;
        }

        if( $order->o_total && $ph_user ){
            if ( 'delivering' == $order->o_status ) {
                $ph_user->u_p_cash = $ph_user->u_p_cash + $order->o_total;
            } elseif ( \in_array( $order->o_status, [ 'delivered', 'damaged' ] ) && 'delivering' == $prev_order->o_status ) {
                $ph_user->u_p_cash = $ph_user->u_p_cash - $order->o_total;
                $ph_user->u_cash = $ph_user->u_cash + $order->o_total;
            } elseif ( 'delivering' == $prev_order->o_status && 'cancelled' == $order->o_status ) {
                $ph_user->u_p_cash = $ph_user->u_p_cash - $order->o_total;
            } elseif( 'delivered' == $prev_order->o_status && 'returned' == $order->o_status ) {
                $ph_user->u_cash = $ph_user->u_cash - $order->o_total;
            }
            $ph_user->update();
        }

        $user = User::getUser( $order->u_id );
        if( $user ){
            if ( \in_array( $order->o_status, [ 'cancelled', 'returned' ] ) && ( 'paid' == $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ) ) {
                $user->u_cash = $user->u_cash + $order->o_total;

                $order->appendMeta( 'o_admin_note', sprintf( '%s: %s TK refunded on cancellation', \date( 'Y-m-d H:i:s' ), $order->o_total ) );
                $prev_refund = $order->getMeta( 'refund' );
                if( ! is_numeric( $prev_refund ) ){
                    $prev_refund = 0;
                }
                $order->setMeta( 'refund', $prev_refund + $order->o_total );
                $order->addHistory( 'Refund', $prev_refund, $prev_refund + $order->o_total );
            }

            if ( \in_array( $order->o_status, [ 'cancelled' ] ) ) {
                $o_data = (array)$order->getMeta( 'o_data' );
                if( isset($o_data['deductions']) && isset($o_data['deductions']['cash']) ) {
                    $user->u_cash = $user->u_cash + $o_data['deductions']['cash']['amount'];
                    $order->addHistory( 'Arogga Cash Refund', 0, $o_data['deductions']['cash']['amount'] );
                }
            }
        }

        $cash_back = $order->cashBackAmount();

        if( $cash_back && $user ) {
            if( in_array( $order->o_status, [ 'delivered', 'cancelled', 'damaged' ] ) ){
                $user->u_p_cash = $user->u_p_cash - $cash_back;
            }
            if ( \in_array( $order->o_status, [ 'delivered' ] ) ) {
                $user->u_cash = $user->u_cash + $cash_back;
            }
            if ( 'delivered' == $prev_order->o_status && 'returned' == $order->o_status ) {
                $user->u_cash = $user->u_cash - $cash_back;
            }
        }
        if( $user ){
            $user->update();
        }
        return true;
    }

    public static function referralCash( $prev_order, $order ) {
        if( ! $prev_order || !$order || $prev_order->o_status == $order->o_status ) {
            return false;
        }

        if( ! \in_array( $order->o_status, [ 'delivered' ] ) ) {
            return false;
        }
        $user = User::getUser( $order->u_id );
        if( ! $user || ! $user->u_r_uid ) {
            return false;
        }

        $query = DB::db()->prepare( 'SELECT o_id FROM t_orders WHERE u_id = ? AND o_status = ? LIMIT 2' );
        $query->execute( [ $user->u_id, 'delivered' ] );
        $result = $query->fetchAll();

        if( \count( $result ) === 1 ){
            $r_user = User::getUser( $user->u_r_uid );
            $cashback_amount = Functions::changableData('refBonus');
            if( $r_user && $r_user->cashUpdate( $cashback_amount ) ) {
                if( $r_user->fcm_token ){
                    $title = "Congrats!";
                    $message = "Congrats the order of {$user->u_name} {$user->u_mobile} is delivered. Your {$cashback_amount} Taka referral bonus is usable now.";
                    static::sendNotification( $r_user->fcm_token, $title, $message );
                }
                return true;
            }
        }
        return false;
    }

    public static function miscOrderUpdate( $prev_order, $order ) {
        if( !$prev_order || !$order ) {
            return false;
        }

		if( $prev_order->o_status != $order->o_status ) {
			$order->addHistory( 'Status', $prev_order->o_status, $order->o_status );
		}
		if( $prev_order->o_i_status !== $order->o_i_status ) {
			$order->addHistory( 'Internal Status', $prev_order->o_i_status, $order->o_i_status );
		}
		if( $prev_order->o_is_status !== $order->o_is_status ) {
			$order->addHistory( 'Issue Status', $prev_order->o_is_status, $order->o_is_status );
		}
		if( $prev_order->o_de_id !== $order->o_de_id ) {
			$order->addHistory( 'Deliveryman Change', User::getName( $prev_order->o_de_id ), User::getName( $order->o_de_id ) );
		}

        if( $prev_order->o_status != $order->o_status ) {
            if( in_array( $order->o_status, [ 'confirmed', 'cancelled', 'delivering', 'delivered' ] ) ){
                $order->addTimeline( $order->o_status, [] );
            }
            if( in_array( $order->o_status, [ 'confirmed' ] ) ){
                $order->addTimeline( 'packing', [] );
            }
        }
        if( $prev_order->o_i_status != $order->o_i_status && 'packing' == $order->o_i_status ) {
            $order->addTimeline( 'packed', [] );
        }

        if( ( $prev_order->o_status != $order->o_status || $prev_order->o_i_status != $order->o_i_status ) && 'confirmed' == $order->o_status && 'packing' == $order->o_i_status && 'online' == $order->o_payment_method ) {
            //$order->addTimeline( 'packed', [] );
            $order->addTimeline( 'payment', [
                'title' => 'Payment',
                'body' => '',
                'done' => false,
            ] );
            $order->setMeta( 'paymentEligibleTime', \date( 'Y-m-d H:i:s' ) );
        }

        if( ( $prev_order->o_status != $order->o_status && \in_array( $order->o_status, ['confirmed', 'delivering', 'delivered'] ) ) || ( $prev_order->o_i_status != $order->o_i_status && \in_array( $order->o_i_status, ['ph_fb', 'confirmed'] ) ) ) {
            foreach ( $order->medicineQty as $id_qty ) {
                $m_id = isset($id_qty['m_id']) ? (int)$id_qty['m_id'] : 0;
                $quantity = isset($id_qty['qty']) ? (int)$id_qty['qty'] : 0;
                $om_status = isset( $id_qty['om_status'] ) ? $id_qty['om_status']: '';
    
                if( \in_array( $om_status, ['pending', 'later'] ) ){
                    $inventory = Inventory::getByPhMid( $order->o_ph_id, $m_id );

                    if( $inventory && $inventory->i_qty >= $quantity ){
                        $inventory->i_qty = $inventory->i_qty - $quantity;
                        $inventory->update();
                        DB::instance()->update( 't_o_medicines', ['om_status' => 'available', 's_price' => $inventory->i_price ], [ 'o_id' => $order->o_id, 'm_id' => $m_id ] );
                    } else {
                        if( 'pending' == $om_status ){
                            DB::instance()->update( 't_o_medicines', ['om_status' => 'later' ], [ 'o_id' => $order->o_id, 'm_id' => $m_id ] );
                        }
                    }
                }
            }
        }

        if( $order->o_priority && $prev_order->o_priority != $order->o_priority && 'delivering' == $order->o_status ) {
            if( $de_user = User::getUser( $order->o_de_id ) ){
                $mobile = '';
                $s_address = $order->getMeta('s_address');
                if( is_array( $s_address ) && ! empty( $s_address['mobile'] ) ){
                    $mobile = Functions::checkMobile( $s_address['mobile'] );
                }
                $mobile = $mobile ?: $order->u_mobile;
                $title = "আর্জেন্ট ডেলিভারি";
                $message = "#{$order->o_id} এই অর্ডারটি আর্জেন্টলি ডেলিভারি করুন। কাস্টমারের ফোন নাম্বার {$mobile}";
                static::sendNotification( $de_user->fcm_token, $title, $message );
            }
        }

        if( $prev_order->o_status != $order->o_status && 'delivering' == $order->o_status ) {
            DB::instance()->update( 't_o_medicines', ['om_status' => 'available'], [ 'o_id' => $order->o_id, 'om_status' => 'packed' ] );
        }
        if( $prev_order->o_status != $order->o_status && in_array( $order->o_status, ['cancelled', 'returned'] ) ) {
            $ph_m_ids = [];
            $iv_insert = [];
            foreach ( $order->medicineQty as $id_qty ) {
                $m_id = isset($id_qty['m_id']) ? (int)$id_qty['m_id'] : 0;
                $quantity = isset($id_qty['qty']) ? (int)$id_qty['qty'] : 0;
                $om_status = isset( $id_qty['om_status'] ) ? $id_qty['om_status']: '';
                $s_price = isset( $id_qty['s_price'] ) ? $id_qty['s_price']: '';
    
                if( \in_array( $om_status, ['packed', 'available'] ) ){
                    $ph_m_ids[ $order->o_ph_id ][] = $m_id;
                    if( $inventory = Inventory::getByPhMid( $order->o_ph_id, $m_id ) ){
                        if(($inventory->i_qty + $quantity)){
                            $inventory->i_price = ( ($inventory->i_price * $inventory->i_qty ) + ($s_price * $quantity ) ) / ($inventory->i_qty + $quantity);
                        } else {
                            $inventory->i_price = '0.00';
                        }
                        $inventory->i_qty = $inventory->i_qty + $quantity;
                        $inventory->update();
                    } else {
                        $iv_insert[] = [
                            'i_ph_id' => $order->o_ph_id,
                            'i_m_id' => $m_id,
                            'i_price' => $s_price,
                            'i_qty' => $quantity,
                        ];
                    }
                }
            }
            if( $iv_insert ){
                DB::instance()->insertMultiple( 't_inventory', $iv_insert );
            }
            DB::instance()->update( 't_o_medicines', ['om_status' => 'pending'], ['o_id' => $order->o_id] );

            if( $ph_m_ids ){
                Functions::checkOrdersForInventory( $ph_m_ids );
                Functions::checkOrdersForPacking();
            }
        }
        /*
        //Done after modify order medicines
        if( $prev_order->o_i_status != $order->o_i_status && 'ph_fb' == $order->o_i_status ){
            $query = DB::db()->prepare( 'SELECT DISTINCT om_status FROM t_o_medicines WHERE o_id = ? AND m_qty > ?' );
            $query->execute( [ $order->o_id, 0 ] );

            $i_status_confirmed = true;
            while( $om = $query->fetch() ){
                if( 'available' !== $om['om_status'] ){
                    $i_status_confirmed = false;
                    break;
                }
            }
            if( $i_status_confirmed ){
                $order->update( [ 'o_i_status' => 'packing' ] );
            }
        }
        */

        $district = Location::getValueByLocationId( $order->o_l_id, 'district' );

        if( $prev_order->o_status != $order->o_status && 'delivered' == $order->o_status ) {
            $query = DB::db()->prepare( "SELECT SUM(s_price*m_qty) FROM t_o_medicines WHERE o_id = ? AND om_status = ?" );
            $query->execute( [ $order->o_id, 'available' ] );
            $order->setMeta( 'supplierPrice', round( $query->fetchColumn(), 2 ) );

            if( 'paid' === $order->getMeta( 'paymentStatus' ) ){
                $order->update( [ 'o_i_status' => 'paid' ] );
            }

            if ( 'Dhaka City' !== $district && $order->isPaid() ){
                if( $b_id = $order->getMeta( 'bag' ) ){
                    $bag = Bag::getBag( $b_id );
                    $bag->removeOrder( $order->o_id );
                    $order->deleteMeta( 'bag' );
                }
            }
        }

        if ( ( $order->o_status != $prev_order->o_status || $order->o_i_status != $prev_order->o_i_status || $order->o_payment_method != $prev_order->o_payment_method ) && 'confirmed' === $order->o_status && in_array( $order->o_i_status, [ 'packing', 'confirmed' ] ) ){
            if ( 'Dhaka City' !== $district && $order->isPaid() && ! $order->getMeta( 'redx_tracking_id' ) ){
                Redx::instance()->createRedxParcel( $order->o_id );
            }
        }
    }

    public static function orderMetaUpdated( $key, $prev_value, $value, $order ){
        if( !$key || !$order || !$order->exist() ){
            return false;
        }
        if( 'o_i_note' === $key ){
            $order->addHistory( 'Internal Note', $prev_value, $value );
        }
    }

    public static function checkOrdersForInventory( $ph_m_ids ){
        DB::db()->beginTransaction();
        try {
            if( $ph_m_ids ){
                foreach ( $ph_m_ids as $ph_id => $m_ids ) {
                    $db = new DB;
                    $db->add( 'SELECT tom.om_id, tom.m_id, tom.m_qty, tr.o_id FROM t_o_medicines tom INNER JOIN t_orders tr ON tom.o_id = tr.o_id WHERE 1=1' );
                    $in  = str_repeat('?,', count($m_ids) - 1) . '?';
                    $db->add( " AND tom.m_id IN ($in)", ...$m_ids );
                    $db->add( ' AND tom.om_status IN (?,?)', 'pending', 'later' );
                    $db->add( ' AND tr.o_ph_id = ?', $ph_id );
                    $db->add( ' AND tr.o_status IN (?,?)', 'confirmed', 'delivering' );
                    $db->add( ' AND tr.o_i_status IN (?,?)', 'ph_fb', 'confirmed' );
                    $db->add( ' ORDER BY tr.o_id ASC' );
                    $query = $db->execute();
        
                    while( $om = $query->fetch() ) {
                        if( ( $inventory = Inventory::getByPhMid( $ph_id, $om['m_id'] ) ) && $inventory->i_qty >= $om['m_qty'] ){
                            $inventory->i_qty = $inventory->i_qty - $om['m_qty'];
                            $inventory->update();
                            DB::instance()->update( 't_o_medicines', ['om_status' => 'available', 's_price' => $inventory->i_price ], [ 'om_id' => $om['om_id'] ] );
                        }
                    }
                }
            }

            DB::db()->commit(); 
        } catch(\PDOException $e) {
            DB::db()->rollBack();
            \error_log( $e->getMessage() );
            //Response::instance()->sendMessage( 'Something wrong, Please try again.' );
        }
    }

    public static function checkOrdersForPacking(){
        $query = DB::db()->prepare( 'SELECT DISTINCT tom.o_id, tom.om_status FROM t_o_medicines tom INNER JOIN t_orders tr ON tom.o_id = tr.o_id WHERE tr.o_status = ? AND tr.o_i_status = ? AND tom.m_qty > ?' );
        $query->execute( [ 'confirmed', 'ph_fb', 0 ]);
        $oid_statuses = [];
        while( $om = $query->fetch() ){
            $oid_statuses[ $om['o_id'] ][] = $om['om_status'];
        }
        $confirmed_orders = [];
        foreach ( $oid_statuses as $o_id => $statuses ) {
            if( count( $statuses ) === 1 && 'available' == $statuses[0] ){
                $confirmed_orders[] = $o_id;
            }
        }
        $confirmed_orders = array_filter( array_unique( $confirmed_orders ) );

        if( count( $confirmed_orders ) > 1000 ){
            $confirmed_orders = array_slice( $confirmed_orders, 0, 1000 );
        }
        foreach ( $confirmed_orders as $o_id ) {
            if( $order = Order::getOrder( $o_id ) ){
                $order->update( [ 'o_i_status' => 'packing' ] );
            }
        }
    }

    public static function setInternalOrderStatus( $order ){
        if( ! $order || 'confirmed' != $order->o_status ||  ! in_array( $order->o_i_status, [ 'ph_fb', 'packing' ] ) ){
            return false;
        }
        $query = DB::db()->prepare( 'SELECT DISTINCT om_status FROM t_o_medicines WHERE o_id = ? AND m_qty > ?' );
        $query->execute( [ $order->o_id, 0 ] );
        $statuses = $query->fetchAll( \PDO::FETCH_COLUMN );

        if( 'ph_fb' == $order->o_i_status ){
            if( count( $statuses ) === 1 && 'available' == $statuses[0] ){
                $order->update( [ 'o_i_status' => 'packing' ] );
            }
        } elseif( 'packing' == $order->o_i_status ){
            if( count( $statuses ) > 1 || 'available' != $statuses[0] ){
                $order->update( [ 'o_i_status' => 'ph_fb' ] );
            }
        }
    }

    public static function orderCreated( $order ){
        if( ! $order || ! $order->exist() ){
            return false;
        }

        if( $order->u_id && ( $user = User::getUser( $order->u_id ) ) ){
            $user->update( [ 'u_o_count' => $user->u_o_count + 1 ] );

            if( $user->u_r_uid && ( $r_user = User::getUser( $user->u_r_uid ) ) && $r_user->fcm_token ){
                $query = DB::db()->prepare( 'SELECT o_id FROM t_orders WHERE u_id = ? AND o_status = ? LIMIT 2' );
                $query->execute( [ $user->u_id, 'delivered' ] );
                $result = $query->fetchAll();
                if( \count( $result ) === 1 ){
                    $title = "Congrats!";
                    $message = "Mr./Mrs {$user->u_name} {$user->u_mobile}) has placed first order with Arogga whom you have referred. Once this order is delivered, your {$refBonus} Taka cashback will be usable.";
                    static::sendNotification( $r_user->fcm_token, $title, $message );
                }
            }
        }
        $timeline = [
            'placed' => [],
        ];
        $order->setMeta( 'timeline', $timeline );
    }

    public static function orderDeleted( $order ){
        if( ! $order || ! $order->exist() ){
            return false;
        }

        if( $order->u_id && ( $user = User::getUser( $order->u_id ) ) ){
            $user->update( [ 'u_o_count' => $user->u_o_count - 1 ] );
        }
    }

    public static function cartData( $user, $medicines, $d_code = '', $order = null, $offline = false, $args = [] ) {
        $subtotal = '0.00';
        $total = '0.00';
        $saving = 0;
        $d_amount = '0.00';
        $a_amount = '0.00';
        $rx_req = false;
        $cold = false;
        $m_return = [];
        $additions = [];
        $deductions = [];
        $free_delivery = false;
		$prev_applied_cash = 0;
		$o_status = '';

        $u_id = $user ? $user->u_id : 0;

		if( $order ){
			$o_status = $order->o_status;
			$o_data = (array)$order->getMeta( 'o_data' );

			if( isset($o_data['deductions']['cash']) ) {
				$prev_applied_cash = $o_data['deductions']['cash']['amount'];
			}
		}

        if( empty( $args['s_address'] ) || empty( $args['s_address']['district'] ) ){
            $args['s_address']['district'] = 'Dhaka City';
        }

        if( ! is_array( $medicines ) ){
            $medicines = [];
        }

        foreach ( $medicines as $key => $value ) {
            if( is_array( $value ) ) {
                $m_id = isset($value['m_id']) ? (int)$value['m_id'] : 0;
                $quantity = isset($value['qty']) ? (int)$value['qty'] : 0;
            } else {
                $m_id = (int) $key;
                $quantity = (int) $value;
            }

            if( ! ( $medicine = Medicine::getMedicine( $m_id ) ) ) {
                continue;
            }
            if( isset( $m_return[ $m_id ] ) ) {
                continue;
            }
            if( ! $medicine->m_rob && ! $o_status ) {
                $quantity = 0;
            }
            if ( (bool)$medicine->m_rx_req ) {
                $rx_req = true;
            }
            $isCold = (bool)$medicine->m_cold;
            if( $isCold ){
                $cold = true;
            }

            $price = $medicine->m_price ? \round( $medicine->m_price * $quantity, 2 ) : 0;
            $d_price = $medicine->m_d_price ? \round( $medicine->m_d_price * $quantity, 2 ) : 0;
            $d_price = $offline ? $price : $d_price;

            if( $isCold && $args['s_address']['district'] != 'Dhaka City' ){
                $quantity = 0;
                $price = 0;
                $d_price = 0;
            }

            $m_return[ $m_id ] = [
                'qty' => $quantity,
                'm_id' => $m_id,
                'name' => $medicine->m_name,
                'strength' => $medicine->m_strength,
                'form' => $medicine->m_form,
                'unit' => $medicine->m_unit,
                'price' => $price,
                'd_price' => $d_price,
                'rx_req' => $medicine->m_rx_req,
                'pic_url' => $medicine->m_pic_url,
                'cold' => $isCold,
                'min' => $medicine->m_min,
				'max' => $medicine->m_max,
            ];
            $subtotal += $price;
            $saving   += ( $price - $d_price );
        }
        if( $saving ) {
            $d_amount    += $saving;
            $deductions['saving'] = [
                'amount' => \round( $saving, 2 ),
                'text' => 'Discount applied',
                'info' => '',
            ];
        }
        $discount = Discount::getDiscount( $d_code );

        if( $discount && $discount->canUserUse( $u_id ) ) {
            if ( 'percent' === $discount->d_type || 'firstPercent' === $discount->d_type ) {
                $amount = ( ( $subtotal - $d_amount ) / 100 ) * $discount->d_amount;
                if ( ! empty( $discount->d_max ) ) {
                    $amount = min( $discount->d_max, $amount );
                }
            } elseif( 'fixed' === $discount->d_type || 'firstFixed' === $discount->d_type ) {
                $amount = $discount->d_amount;
            } elseif( 'free_delivery' === $discount->d_type ) {
                $amount = 0; //For free delivery we will calculate later
                $free_delivery = true;
            } else {
                $amount = 0;
                //$free_delivery = false;
            }
            $d_amount    += \round( $amount, 2 );

            $deductions['discount'] = [
                'amount' => \round( $amount, 2 ),
                'text' => "Coupon applied ($discount->d_code)",
                'info' => ! empty( $free_delivery ) ? 'Free Delivery' : '',
            ];
        }
        if( $user && ( $user->u_cash || $prev_applied_cash ) ) {
            if ( ( $subtotal - $d_amount ) > 0 ) {
                $amount = \round( $subtotal - $d_amount, 2 );
                $amount = \round( \min( $amount, $user->u_cash + $prev_applied_cash ), 2);
                $d_amount    += $amount;

                $deductions['cash'] = [
                    'amount' => $amount,
                    'text' => 'arogga cash applied',
                    'info' => '',
                ];
            } else {
                /*
                $deductions['cash'] = [
                    'amount' => '0.00',
                    'text' => 'arogga cash',
                    'info' => 'To use arogga cash order more than ৳499',
                ];
                */
            }
            
        }
        if ( ! empty( $args['man_discount'] ) ) {
            $amount = \round( $args['man_discount'], 2 );
            $d_amount    += $amount;

            $deductions['man_discount'] = [
                'amount' => $amount,
                'text' => 'Manual discount applied',
                'info' => '',
            ];
        }

        if( ! $offline && ! $free_delivery ){
            if( 'Dhaka City' == $args['s_address']['district'] ){
                $trigger_delivery_fee = 999;
                $delivery_fee = 39;
            } else {
                //Other districts
                $trigger_delivery_fee = 1999;
                $delivery_fee = 99;
            }
            if ( ( $subtotal - $d_amount ) < $trigger_delivery_fee ) {
                $additions['delivery'] = [
                    'amount' => $delivery_fee,
                    'text' => sprintf( 'Delivery charge (%s)', 'Dhaka City' == $args['s_address']['district'] ? 'Inside Dhaka' : 'Outside Dhaka' ),
                    'info' => sprintf( 'To get free delivery order more than ৳%d', $trigger_delivery_fee ),
                ];
                $a_amount += $delivery_fee;
            } else {
                $additions['delivery'] = [
                    'amount' => '00',
                    'text' => 'Delivery charge',
                    'info' => '',
                ];
            }
        }

        if ( ! empty( $args['man_addition'] ) ) {
            $amount = \round( $args['man_addition'], 2 );
            $a_amount    += $amount;

            $deductions['man_addition'] = [
                'amount' => $amount,
                'text' => 'Manual addition applied',
                'info' => '',
            ];
        }
        $subtotal = \round( $subtotal, 2 );
        $total = \round( $subtotal - $d_amount + $a_amount, 2);
        $total_floor = \floor($total);
        if( $total_floor < $total ) {
            $deductions['rounding'] = [
                'amount' => \round( $total - $total_floor, 2 ),
                'text' => 'Rounding Off',
                'info' => '',
            ];
            $d_amount    += \round( $total - $total_floor, 2 );
            $total = $total_floor;
        }

        if ( $total > 4999 ) {
            $cash_back = 100;
        } elseif( $total > 3999 ) {
            $cash_back = 80;
        } elseif( $total > 2999 ) {
            $cash_back = 60;
        } elseif( $total > 1999 ) {
            $cash_back = 40;
        } elseif( $total > 999 ) {
            $cash_back = 20;
        } else {
            $cash_back = 0;
        }

        if ( $offline ) {
            $cash_back = 0;
        }
        
        $data = [
            'medicines'   => $m_return,
            'deductions'  => $deductions,
            'additions'   => $additions,
            'subtotal'    => \round( $subtotal, 2 ),
            'total'       => \round( $total, 2),
            'a_amount'    => \round( $a_amount, 2),
            'd_amount'    => \round( $d_amount, 2),
            'total_items' => count( $m_return ),
            'rx_req'      => $rx_req,
            //'rx_req'      => false,
            'cold'        => $cold,
            'cash_back'   => $cash_back,
            'a_message'   => '', //additional message, Show just above purchase button
        ];

        return $data;
    }

    public static function reOrder( $o_id ){
        if ( ! $o_id || !($order = Order::getOrder( $o_id )) ) {
            return false;
        }
        if( !$order->u_id || !($user = User::getUser( $order->u_id )) ){
            return false;
        }

        $d_code = (string)$order->getMeta( 'd_code' );
        $s_address = $order->getMeta('s_address')?:[];
        $prescriptions = $order->getMeta( 'prescriptions' );
        $medicineQty = $order->medicineQty;

        $discount = Discount::getDiscount( $d_code );

        if( ! $discount || ! $discount->canUserUse( $user->u_id ) ) {
            $d_code = '';
        }

        $cart_data = Functions::cartData( $user, $medicineQty, $d_code, null, false, ['s_address' => $s_address] );

        if( isset($cart_data['deductions']['cash']) && !empty($cart_data['deductions']['cash']['info'])) {
            $cart_data['deductions']['cash']['info'] = "Didn't apply because the order value was less than ৳499.";
        }
        if( isset($cart_data['additions']['delivery']) && !empty($cart_data['additions']['delivery']['info'])) {
            $cart_data['additions']['delivery']['info'] = str_replace('To get free delivery order more than', 'Because the order value was less than', $cart_data['additions']['delivery']['info']);
        }
        $c_medicines = $cart_data['medicines'];
        unset( $cart_data['medicines'] );

        $o_data = $order->toArray();
        $o_data['o_subtotal'] = $cart_data['subtotal'];
        $o_data['o_addition'] = $cart_data['a_amount'];
        $o_data['o_deduction'] = $cart_data['d_amount'];
        $o_data['o_total'] = $cart_data['total'];
        $o_data['o_delivered'] = '0000-00-00 00:00:00';
        $o_data['o_status'] = 'processing';
        $o_data['o_i_status'] = 'processing';
		$o_data['o_is_status'] = '';
        $o_data['o_priority'] = false;
        if( ! in_array( $o_data['o_payment_method'], [ 'cod', 'online' ] ) ){
            $o_data['o_payment_method'] = 'online';
        }

        $newOrder = new Order;
        $newOrder->insert( $o_data  );
        Functions::ModifyOrderMedicines( $newOrder, $c_medicines );
        $meta = [
            'o_data' => $cart_data,
            'o_secret' => Functions::randToken( 'alnumlc', 16 ),
            's_address' => $s_address,
            'd_code' => $d_code,
        ];

        $imgArray = [];
        if( $prescriptions && is_array( $prescriptions ) ){
            $i = 1;
            foreach( $prescriptions as $prescription_s3key ){
                $array = explode('.', $prescription_s3key );
                $ext = end($array);
                $fileName = \sprintf( '%s-%s.%s', $newOrder->o_id, $i++ . Functions::randToken( 'alnumlc', 12 ), $ext );

                $s3key = Functions::uploadToS3( $newOrder->o_id, '', 'order', $fileName, '', $prescription_s3key );
                if ( $s3key ){
                    array_push( $imgArray, $s3key );
                }
            }
        }
        if ( count($imgArray) ){
            $meta['prescriptions'] = $imgArray ;
        }
        $newOrder->insertMetas( $meta );
		$newOrder->addHistory( 'Created', 'Created through re-order' );

        $cash_back = $newOrder->cashBackAmount();

        //again get user. User data may changed.
        $user = User::getUser( $newOrder->u_id );
        
        if ( $cash_back ) {
            $user->u_p_cash = $user->u_p_cash + $cash_back;
        }
        if( isset($cart_data['deductions']['cash']) ){
            $user->u_cash = $user->u_cash - $cart_data['deductions']['cash']['amount'];
        }
        $user->update();

        return $newOrder;
    }

    public static function ModifyOrderMedicines( $order, $medicineQty, $prev_order_data = null ) {
        if( ! $order || ! $order->o_id) {
            return false;
        }

        $new_ids = [];
        $insert = [];
        $old_data = [];
		$o_is_status = '';

        $query = DB::instance()->select( 't_o_medicines', [ 'o_id' => $order->o_id ], 'm_id, m_qty, m_price, m_d_price, s_price, om_status' );
        while ( $old = $query->fetch() ) {
            $old_data[ $old['m_id'] ] = $old;
        }

        foreach ( $medicineQty as $id_qty ) {
            $m_id = isset($id_qty['m_id']) ? (int)$id_qty['m_id'] : 0;
            $quantity = isset($id_qty['qty']) ? (int)$id_qty['qty'] : 0;

            if( ! ( $medicine = Medicine::getMedicine( $m_id ) ) ) {
                continue;
            }
            $new_ids[] = $medicine->m_id;

            if( isset( $old_data[ $medicine->m_id ] ) ) {
                $change = [];
                if( $quantity != $old_data[ $medicine->m_id ]['m_qty'] ) {
                    $change['m_qty'] = $quantity;
					$order->addHistory( 'Medicine Qty Change', sprintf( '%s %s - %s', $medicine->m_name, $medicine->m_strength, Functions::qtyTextClass( $old_data[ $medicine->m_id ]['m_qty'], $medicine ) ), sprintf( '%s %s - %s', $medicine->m_name, $medicine->m_strength, Functions::qtyTextClass( $quantity, $medicine ) ) );
                    if( $prev_order_data && $order->o_ph_id ){
                        //update inventory
                        if( 'available' == $old_data[ $medicine->m_id ]['om_status'] ){
                            if( $old_data[ $medicine->m_id ]['m_qty'] >= $quantity ){
                                Inventory::qtyUpdateByPhMid( $order->o_ph_id, $medicine->m_id, $old_data[ $medicine->m_id ]['m_qty'] - $quantity );
                                //medicine quantity removed note
                                if( 'delivering' === $order->o_status ){
                                    $o_i_note = sprintf( '%s %s - %s removed during delivering', $medicine->m_name, $medicine->m_strength, Functions::qtyTextClass( ($old_data[ $medicine->m_id ]['m_qty'] - $quantity), $medicine ) );
                                    $order->appendMeta( 'o_i_note', $o_i_note );
									if( ! $o_is_status ){
										$o_is_status = 'delivered';
									}
                                }
                            } else {
                                $inventory = Inventory::getByPhMid( $order->o_ph_id, $medicine->m_id );
                                if( ( $inventory->i_qty + $old_data[ $medicine->m_id ]['m_qty'] ) >= $quantity ){
                                    Inventory::qtyUpdateByPhMid( $order->o_ph_id, $medicine->m_id, $old_data[ $medicine->m_id ]['m_qty'] - $quantity );
                                } else {
                                    Inventory::qtyUpdateByPhMid( $order->o_ph_id, $medicine->m_id, $old_data[ $medicine->m_id ]['m_qty'] );
                                    $change['om_status'] = 'later';
                                }
                                //medicine quantity added note
                                if( 'delivering' === $order->o_status ){
                                    $o_i_note = sprintf( '%s %s - %s added during delivering', $medicine->m_name, $medicine->m_strength, Functions::qtyTextClass( ($quantity - $old_data[ $medicine->m_id ]['m_qty']), $medicine ) );
                                    $order->appendMeta( 'o_i_note', $o_i_note );
									if( 'packing' !== $o_is_status ){
										$o_is_status = 'packing';
									}
                                }
                            }
                        } elseif( 'later' == $old_data[ $medicine->m_id ]['om_status'] ){
                            $inventory = Inventory::getByPhMid( $order->o_ph_id, $medicine->m_id );
                            if( $inventory && $inventory->i_qty >= $quantity ){
                                $inventory->i_qty = $inventory->i_qty - $quantity;
                                $inventory->update();
                                $change['s_price'] = $inventory->i_price;
                                $change['om_status'] = 'available';
                            }
                        }
                    }
                }
                if( $medicine->m_price != $old_data[ $medicine->m_id ]['m_price'] ) {
                    $change['m_price'] = $medicine->m_price;
                }
                if( $medicine->m_d_price != $old_data[ $medicine->m_id ]['m_d_price'] ) {
                    $change['m_d_price'] = $medicine->m_d_price;
                }
                if( $change ) {
                    DB::instance()->update( 't_o_medicines', $change, [ 'o_id' => $order->o_id, 'm_id' => $medicine->m_id ] );
                }
            } else {
                $insert_data = [
                    'o_id' => $order->o_id,
                    'm_id' => $medicine->m_id,
                    'm_unit' => $medicine->m_unit,
                    'm_price' => $medicine->m_price ?: 0,
                    'm_d_price' => $medicine->m_d_price ?: 0,
                    'm_qty' => $quantity,
                ];
                
                if( $prev_order_data ){
                    $inventory = Inventory::getByPhMid( $order->o_ph_id, $medicine->m_id );
                    if( $inventory && $inventory->i_qty >= $quantity ){
                        $inventory->i_qty = $inventory->i_qty - $quantity;
                        $inventory->update();
                        $insert_data['s_price'] = $inventory->i_price;
                        $insert_data['om_status'] = 'available';
                    } else {
                        $insert_data['s_price'] = 0;
                        $insert_data['om_status'] = 'later';
                    }
					if( 'delivering' === $order->o_status ){
						$o_i_note = sprintf( '%s %s - %s added during delivering', $medicine->m_name, $medicine->m_strength, Functions::qtyTextClass( $quantity, $medicine ) );
						$order->appendMeta( 'o_i_note', $o_i_note );
						if( 'packing' != $o_is_status ){
							$o_is_status = 'packing';
						}
					}
					$order->addHistory( 'Medicine Add', '', sprintf( '%s %s - %s', $medicine->m_name, $medicine->m_strength, Functions::qtyTextClass( $quantity, $medicine ) ) );
                }
                $insert[] = $insert_data;
            }
        }
        $d_ids = \array_diff( \array_keys( $old_data ), $new_ids );

        if( $d_ids ) {
            $in  = str_repeat('?,', count($d_ids) - 1) . '?';

            $query = DB::db()->prepare( "DELETE FROM t_o_medicines WHERE o_id = ? AND m_id IN ($in)" );
            $query->execute( \array_merge([$order->o_id], $d_ids) );
            if( $prev_order_data && $order->o_ph_id ){
                $o_i_note = '';
                foreach ( $d_ids as $d_id ) {
                    if( 'available' == $old_data[ $d_id ]['om_status'] ){
                        //update inventory
                        Inventory::qtyUpdateByPhMid( $order->o_ph_id, $d_id, $old_data[ $d_id ]['m_qty'] );

                        if('delivering' === $order->o_status){
                            if( ! ( $d_medicine = Medicine::getMedicine( $d_id ) ) ) {
                                continue;
                            }
                            $o_i_note = sprintf( '%s %s - %s removed during delivering', $d_medicine->m_name, $d_medicine->m_strength, Functions::qtyTextClass( $old_data[ $d_id ]['m_qty'], $d_medicine ) );
                            $order->appendMeta( 'o_i_note', $o_i_note );
							if( ! $o_is_status ){
								$o_is_status = 'delivered';
							}
                        }
                    }
					if( $del_medicine = Medicine::getMedicine( $d_id ) ) {
						$order->addHistory( 'Medicine Remove', sprintf( '%s %s - %s', $del_medicine->m_name, $del_medicine->m_strength, Functions::qtyTextClass( $old_data[ $d_id ]['m_qty'], $del_medicine ) ) );
					}
                }
            }
        }
		if( $o_is_status && ( !$order->o_is_status || 'solved' === $order->o_is_status ) ){
			$order->update( [ 'o_is_status' => $o_is_status ] );
		}
        DB::instance()->insertMultiple( 't_o_medicines', $insert );

        Functions::setInternalOrderStatus( $order );

        return true;
    }

    public static function maybeJsonEncode( $value ) {
        if ( is_array( $value ) || is_object( $value ) ) {
            return \json_encode( $value );
        }
        return $value;
    }

    public static function maybeJsonDecode( $value ) {
        if ( ! is_string( $value ) ) { return $value; }
        if ( strlen( $value ) < 2 ) { return $value; }
        if( 0 !== \strpos( $value, '{') && 0 !== \strpos( $value, '[') ) { return $value; }
    
        $json_data = \json_decode( $value, true );
        if ( \json_last_error() !== \JSON_ERROR_NONE ) { return $value; }
        return $json_data;
    }

    public static function pointInPolygon($p, $polygon) {
        if( !$p || !\is_array($p) || !\is_array($polygon) ) {
            return false;
        }
        //if you operates with (hundred)thousands of points
        //set_time_limit(60);
        $c = 0;
        $p1 = $polygon[0];
        $n = count($polygon);
    
        for ($i=1; $i<=$n; $i++) {
            $p2 = $polygon[$i % $n];
            if ($p[1] > min($p1[1], $p2[1])
                && $p[1] <= max($p1[1], $p2[1])
                && $p[0] <= max($p1[0], $p2[0])
                && $p1[1] != $p2[1]) {
                    $xinters = ($p[1] - $p1[1]) * ($p2[0] - $p1[0]) / ($p2[1] - $p1[1]) + $p1[0];
                    if ($p1[0] == $p2[0] || $p[0] <= $xinters) {
                        $c++;
                    }
            }
            $p1 = $p2;
        }
        // if the number of edges we passed through is even, then it's not in the poly.
        return $c%2!=0;
    }

    public static function isInside($lat, $long, $area = false ) {      
        $locations = [
            'dhaka' => [
              [23.663722, 90.456617],
              [23.710720, 90.509878],
              [23.782770, 90.473925],
              [23.826433, 90.488741],
              [23.901250, 90.448270],
              [23.883042, 90.398767],
              [23.901272, 90.384341],
              [23.882106, 90.348942],
              [23.752972, 90.328691],
              [23.709614, 90.363012],
              [23.706776, 90.404532],
            ],
            'chittagong' => [
              [22.368476, 91.753262],
              [22.371652, 91.754636],
              [22.430376, 91.871818],
              [22.429093, 91.890720],
              [22.416709, 91.883488],
              [22.403997, 91.890347],
              [22.332232, 91.863481],
              [22.307491, 91.800634],
              [22.281449, 91.796849],
              [22.262373, 91.838371],
              [22.224910, 91.801293],
              [22.273198, 91.763899],
            ],
        ];
      
        $isInside = false;
        foreach ($locations as $city => $location) {
            if( $area && $area != $city ){
                continue;
            }
            if( static::pointInPolygon([$lat, $long], $location) ){
                $isInside = true;
                break;
            }
        }
        return $isInside;
      }

      public static function ledgerCreate( $reason, $amount, $type, $method = 'Cash' ) {
          if( \in_array( $type, ['collection', 'input', 'Share Money Deposit', 'Directors Loan', 'Other Credit'] ) ){
            $amount = \abs( $amount );
          } else {
            $amount = \abs( $amount ) * -1;
          }
          $data = [
            'l_uid' => Auth::id(),
            'l_created' => \date( 'Y-m-d H:i:s' ),
            'l_reason' => \mb_strimwidth( $reason, 0, 255, '...' ),
            'l_type'   => $type,
            'l_method'   => $method,
            'l_amount' => \round( $amount, 2 ),
            'l_files' => '',
          ];
          return DB::instance()->insert( 't_ledger', $data );
      }

    public static function getPicUrl( $images ){
        $url = '';
        if( $images && is_array( $images ) ){
            foreach( $images as $image ){
                $url = static::getS3Url( $image['s3key']??'', 200, 200 );
                break;
            }
        }
        return $url;
    }
    
    public static function getProfilePicUrl( $u_id ){
        $url = '';
        if( ! $u_id ){
            return $url;
        }
        $path = \sprintf( '/users/%d/%d-*.{jpg,jpeg,png,gif}', \floor( $u_id / 1000 ), $u_id );
        $image_path = '';
        foreach( glob( STATIC_DIR . $path, GLOB_BRACE ) as $image ){
            $image_path = $image;
            break;
        }
        if ( $image_path ) {
            $url = str_replace( STATIC_DIR, STATIC_URL, $image_path );
        }
        return $url;
    }

    public static function getPicUrls( $images ){
        $url = [];
        if( $images && is_array( $images ) ){
            foreach( $images as $image ){
                if( isset($_GET['f']) && 'app' == $_GET['f'] ){
                    $url[] = static::getS3Url( $image['s3key']??'' );
                } else {
                    $url[] = static::getS3Url( $image['s3key']??'', 1000, 1000, true );
                }
            }
        }
        return $url;
    }

    public static function getPicUrlsAdmin( $images ){
        if( $images && is_array( $images ) ){
            foreach( $images as &$image ){
                $image['src'] = static::getS3Url( $image['s3key']??'', 200, 200 );
            }
            unset( $image );
        } else {
            $images = [];
        }
        return $images;
    }

    public static function modifyMedicineImages( $m_id, $images ){
        if( ! $m_id || ! is_array( $images ) ){
            return false;
        }
        if( ! ($medicine = Medicine::getMedicine( $m_id ) ) ){
            return false;
        }
        // Instantiate an Amazon S3 client.
        $s3 = Functions::getS3();

        $s3_key_prefix = \sprintf( 'medicine/%s/%s-', \floor( $m_id / 1000 ), $m_id );

        if( $images && is_array( $images ) ){
            $imgArray = [];
            $limit = 8;
            foreach ( $images as $file ) {
                if( !$limit ){
                    break;
                }
                if( ! $file || ! is_array( $file ) ){
                    continue;
                }
                if( 0 === strpos( $file['src'], 'http') ) {
                    if( !empty($file['s3key']) && strpos( $file['s3key'], $s3_key_prefix ) !== false && $s3->doesObjectExist( Functions::getS3Bucket(), $file['s3key'] ) ){
                        array_push( $imgArray, [
                            'title' => $file['title'],
                            's3key' => $file['s3key']
                        ]);
                        $limit--; 
                    }                  
                } else {
                    $mime = @mime_content_type($file['src']);
                    if( ! $mime ){
                        continue;
                    }
                    $ext = strtolower( explode('/', $mime )[1] );
                    if( ! $ext || ! in_array( $ext, ['jpg', 'jpeg', 'png'] ) ){
                        continue;
                    }
                    $title = trim( $medicine->m_name . ' ' . $medicine->m_strength );
                    $name = preg_replace('/[^a-zA-Z0-9\-\._]/','-', $title );
                    $name = explode( '.', $name )[0];
                    $name = trim( preg_replace('/-+/', '-', $name ), '-' ) ;
                    $s3key = \sprintf( '%s%s-%s.%s', $s3_key_prefix, $name, Functions::randToken( 'alnumlc', 4 ), $ext );

                    $imgstring = trim( explode( ',', $file['src'] )[1] );
                    $imgstring = str_replace( ' ', '+', $imgstring );
                    if( strlen( $imgstring ) < 12 || strlen( $imgstring ) > 10 * 1024 * 1024 ){
                        continue;
                    }
                    // Upload file. The file size are determined by the SDK.
                    try {
                        $s3->putObject([
                            'Bucket' => Functions::getS3Bucket(),
                            'Key'    => $s3key,
                            'Body'   => base64_decode( $imgstring ),
                            'ContentType' => $mime,
                        ]);
                        //$s3->upload( Functions::getS3Bucket(), $s3key, base64_decode( $imgstring ) );
                        array_push( $imgArray, [
                            'title' => $title,
                            's3key' => $s3key
                        ]);
                        $limit--; 
                    } catch (S3Exception $e) {
                        error_log( $e->getAwsErrorMessage() );
                        continue;
                    }
                }
            }
            if( $medicine->setMeta( 'images', $imgArray ) ){
                \OA\Search\Medicine::init()->update( $medicine->m_id, [ 'images' => $imgArray, 'imagesCount' => count( $imgArray ) ] );
                Cache::instance()->incr( 'suffixForMedicines' );
            }
        }
        return true;
    }

    public static function modifyLedgerFiles( $l_id, $ledgerFIles ){
        if( ! $l_id || ! is_array( $ledgerFIles ) ){
            return false;
        }
        // Instantiate an Amazon S3 client.
        $s3 = Functions::getS3();

        $s3_key_prefix = \sprintf( 'ledger/%s/%s-', \floor( $l_id / 1000 ), $l_id );

        if( $ledgerFIles && is_array( $ledgerFIles ) ){
            $imgArray = [];
            foreach ( $ledgerFIles as $file ) {
                if( ! $file || ! is_array( $file ) ){
                    continue;
                }
                if( 0 === strpos( $file['src'], 'http') ){
                    if( !empty($file['s3key']) && strpos( $file['s3key'], $s3_key_prefix ) !== false ){
                        array_push( $imgArray, [
                            'title' => $file['title'],
                            's3key' => $file['s3key']
                        ]);
                    }
                } else {
                    $mime = @mime_content_type($file['src']);
                    if( ! $mime ){
                        continue;
                    }
                    $ext = explode('/', $mime )[1];
                    if( ! $ext || ! in_array( strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'pdf'] ) ){
                        continue;
                    }
                    $name = preg_replace('/[^a-zA-Z0-9\-\._]/','-', $file['title']);
                    $name = explode( '.', $name )[0];
                    $name = trim( preg_replace('/-+/', '-', $name ), '-' ) ;
                    $s3key = \sprintf( '%s%s-%s.%s', $s3_key_prefix, $name, Functions::randToken( 'alnumlc', 4 ), $ext );

                    //$imgstring = trim( str_replace("data:{$mime};base64,", "", $file['src'] ) );
                    $imgstring = trim( explode( ',', $file['src'] )[1] );
                    $imgstring = str_replace( ' ', '+', $imgstring );
                    if( strlen( $imgstring ) > 10 * 1024 * 1024 ){
                        continue;
                    }
                    // Upload file. The file size are determined by the SDK.
                    try {
                        $s3->putObject([
                            'Bucket' => Functions::getS3Bucket( 'secure' ),
                            'Key'    => $s3key,
                            'Body'   => base64_decode( $imgstring ),
                            'ContentType' => $mime,
                        ]);
                        array_push( $imgArray, [
                            'title' => $file['title'],
                            's3key' => $s3key
                        ]);
                    } catch (S3Exception $e) {
                        error_log( $e->getAwsErrorMessage() );
                        continue;
                    }
                }
            }
            if( $imgArray ){
                DB::instance()->update( 't_ledger', [ 'l_files' =>  Functions::maybeJsonEncode( $imgArray ) ], [ 'l_id' => $l_id ] );
            }
        }

        return true;
    }

    public static function getLedgerFiles( $images ){
        if( !$images || !is_array( $images ) ){
            return [];
        }
        foreach ( $images as $key => $image  ) {
            $images[$key]['src'] = Functions::getPresignedUrl( $image['s3key'] );
        }
        return $images;
    }  

    public static function qtyText( $qty, $medicine ) {
        if( !$medicine ){
          return '';
        }
        if( $medicine['form'] == $medicine['unit'] ) {
          $s = ( $qty === 1 ) ? '' : 's';
          return $qty . ' ' . $medicine['unit'] . $s;
        }
        if( $medicine['unit'] == 10 . ' ' . $medicine['form'] . 's' ) {
          return $qty*10 . ' ' . $medicine['form'] . 's';
        }
    
        return $qty . 'x' . $medicine['unit'];
    }

    public static function qtyTextClass( $qty, $medicine ) {
        if( !$medicine ){
          return '';
        }
        if( $medicine->m_form == $medicine->m_unit ) {
          $s = ( $qty === 1 ) ? '' : 's';
          return $qty . ' ' . $medicine->m_unit . $s;
        }
        if( $medicine->m_unit == 10 . ' ' . $medicine->m_form . 's' ) {
          return $qty*10 . ' ' . $medicine->m_form . 's';
        }
    
        return $qty . 'x' . $medicine->m_unit;
    }

    public static function getCategories(){
        
        if ( $cache_data = Cache::instance()->get( 'categories' ) ){
            return $cache_data;
        }
        $query = DB::db()->prepare( 'SELECT c_id, c_name FROM t_categories ORDER BY c_order' );
        $query->execute();
        $data = $query->fetchAll( \PDO::FETCH_KEY_PAIR );

        Cache::instance()->set( 'categories', $data );

        return $data;
    }

    public static function getCategoryName( $c_id ){
        
        if ( ! $c_id ){
            return '';
        }
        $categories = Functions::getCategories();
        if( isset( $categories[ $c_id ] ) ){
            return $categories[ $c_id ];
        }
        return '';
    }

    public static function getLocations(){
        if ( $cache_data = Cache::instance()->get( 'locations' ) ){
            return $cache_data;
        }

        $query = DB::db()->prepare( 'SELECT l_id, l_division, l_district, l_area FROM t_locations WHERE l_status = ? ORDER BY l_division, l_district, l_area' );
        $query->execute( [1] );
        $locations = [];
        while( $l = $query->fetch() ){
            $locations[ $l['l_division'] ][ $l['l_district'] ][ $l['l_area'] ] = [
                'l_id' => $l['l_id'],
            ];
        }
        Cache::instance()->set( 'locations', $locations );

        return $locations;

    }

    public static function getDivisions(){

        $locations = Functions::getLocations();
        return array_unique( array_filter( array_keys( $locations ) ) );
    }

    public static function getDistricts( $division ){
        if( !$division ){
            return [];
        }

        $locations = Functions::getLocations();
        if( ! isset( $locations[ $division ] ) ){
            return [];
        }

        return array_unique( array_filter( array_keys( $locations[ $division ] ) ) );
    }

    public static function getAreas( $division, $district ){
        if( !$division || !$district ){
            return [];
        }

        $locations = Functions::getLocations();
        if( ! isset( $locations[ $division ] ) || ! isset( $locations[ $division ][ $district ] ) ){
            return [];
        }

        return array_unique( array_filter( array_keys( $locations[ $division ][ $district ] ) ) );
    }

    public static function getAddressByPostcode( $post_code, $map_area = false ){
        $return = [];
        $location = Location::getByPostcode( $post_code );
        if ( $location ){
            $return = [
                'division' => $location->l_division,
                'district' => $location->l_district,
                'area' => $location->l_area,
            ];
        }
        return $return;
    }

    public static function getIdByLocation( $type, $division, $district, $area ){
        if( !$division || !$district || !$area ){
            return 0;
        }
        if( ! in_array( $type, [ 'l_id', 'l_ph_id', 'l_de_id', 'l_postcode' ] ) ){
            return 0;
        }

        $location = static::isLocationValid( $division, $district, $area );
        if ( $location ){
            return (int)$location->$type;
        }
        return 0;
    }

    public static function isLocationValid( $division, $district, $area ){
        $location = Location::getByDivDistArea( $division, $district, $area );
        if ( $location ){
            return $location;
        }
        return false;
    }

	public static function getPharmacyZones( $ph_id ){
        if( !$ph_id || !is_numeric( $ph_id ) ){
            return [];
        }
        if ( $cache_data = Cache::instance()->get( 'zones-'.$ph_id,'locations' ) ){
            return $cache_data;
        }
        $query = DB::db()->prepare( "SELECT DISTINCT l_zone FROM t_locations WHERE l_ph_id = ? ORDER BY l_zone" );
        $query->execute( [ $ph_id ] );
        $zones = $query->fetchAll( \PDO::FETCH_COLUMN );
        Cache::instance()->set(  'zones-'. $ph_id, $zones, 'locations' );
        return $zones;
    }

    public static function getS3(){
        // Instantiate an Amazon S3 client.
        $s3 = new S3Client([
            'version' => 'latest',
            'region'  => 'ap-southeast-1',
            'credentials' => [
                'key'    => S3_KEY,
                'secret' => S3_SECRET,
            ],
        ]);
        return $s3;
    }

    public static function getS3Bucket( $secure = false ){
        if ( $secure ){
            return MAIN ? "arogga-{$secure}" : "arogga-staging";
        }
        return MAIN ? 'arogga' : 'arogga-staging';
    }

    public static function getS3Url( $key, $width = '', $height = '', $watermark = false ){
        if( ! $key ){
            return '';
        }
        $edits = [];
        if( $width && $height ){
            $edits['resize'] = [
                "width" => $width,
                "height" => $height,
                "fit" => "outside",
            ];
        }
        if( $watermark ){
            $edits['overlayWith'] = [
                'bucket' => Functions::getS3Bucket(),
                'key' => 'misc/wm.png',
                'alpha' => 90,
            ];
        }
        $s3_params = [
            "bucket" => Functions::getS3Bucket(),
            "key" => $key,
            "edits" => $edits,
        ];
        $base64 = base64_encode(json_encode($s3_params));
        return CDN_URL . '/' . $base64;
    }

    public static function changableData( $get ){
        $array = [
            'refBonus' => 40,
        ];
        if( $get && isset( $array[ $get ] ) ){
            return $array[ $get ];
        }
        return '';
    }

    public static function url( ...$args ) {
        if ( is_array( $args[0] ) ) {
            if ( count( $args ) < 2 || false === $args[1] ) {
                $uri = SITE_URL . $_SERVER['REQUEST_URI'];
            } else {
                $uri = $args[1];
            }
        } else {
            if ( count( $args ) < 3 || false === $args[2] ) {
                $uri = SITE_URL . $_SERVER['REQUEST_URI'];
            } else {
                $uri = $args[2];
            }
        }
     
        $frag = strstr( $uri, '#' );
        if ( $frag ) {
            $uri = substr( $uri, 0, -strlen( $frag ) );
        } else {
            $frag = '';
        }
     
        if ( 0 === stripos( $uri, 'http://' ) ) {
            $protocol = 'http://';
            $uri      = substr( $uri, 7 );
        } elseif ( 0 === stripos( $uri, 'https://' ) ) {
            $protocol = 'https://';
            $uri      = substr( $uri, 8 );
        } else {
            $protocol = '';
        }
     
        if ( strpos( $uri, '?' ) !== false ) {
            list( $base, $query ) = explode( '?', $uri, 2 );
            $base                .= '?';
        } elseif ( $protocol || strpos( $uri, '=' ) === false ) {
            $base  = $uri . '?';
            $query = '';
        } else {
            $base  = '';
            $query = $uri;
        }
     
        parse_str( $query, $qs );
        //$qs = urlencode_deep( $qs ); // This re-URL-encodes things that were already in the query string.
        if ( is_array( $args[0] ) ) {
            foreach ( $args[0] as $k => $v ) {
                $qs[ $k ] = $v;
            }
        } else {
            $qs[ $args[0] ] = $args[1];
        }
     
        foreach ( $qs as $k => $v ) {
            if ( false === $v ) {
                unset( $qs[ $k ] );
            }
        }
     
        $ret = http_build_query( $qs );
        $ret = trim( $ret, '?' );
        $ret = preg_replace( '#=(&|$)#', '$1', $ret );
        $ret = $protocol . $base . $ret . $frag;
        $ret = rtrim( $ret, '?' );
        return $ret;
    }

    public static function checkMobile( $mobile ){
        if( ! preg_match( '/(^(\+8801|008801|8801|01))(\d){9}$/', $mobile ) ) {
            return '';
        }
        $mobile = '+88' . substr( $mobile, -11 );
        return $mobile;
    }

    public static function uploadToS3( $id, $file, $folder = 'order', $fileName = '', $mime = '', $prevS3key = '' ){
        $s3 = static::getS3();
        $s3keyReturn = '';
        try {
            $s3key = sprintf( '%s/%d/%s', $folder, \floor( $id / 1000 ), $fileName ?: basename($file) );
            $args = [
                'Bucket' => static::getS3Bucket(),  
                'Key' => $s3key,
            ];
            if( $mime ){
                $args['ContentType'] = $mime;
            }
            if( $prevS3key ){
                $args['CopySource'] = sprintf('%s/%s', static::getS3Bucket(), $prevS3key );
                $s3->copyObject( $args );
                $s3keyReturn = $s3key;
            } elseif( $file ){
                $args['SourceFile'] = $file;
                $s3->putObject( $args );
                $s3keyReturn = $s3key;
            }
        } catch (S3Exception $e) {
            error_log( $e->getAwsErrorMessage() );
        } catch( \Exception $e ) {
            error_log( $e->getMessage() );
        }
        return $s3keyReturn;
    }

    public static function getPresignedUrl( $s3Key, $secure = 'secure' ){
        $url = '';
        try {
            $s3 = static::getS3();
            $cmd = $s3->getCommand('GetObject', [
                'Bucket' => static::getS3Bucket( $secure ),
                'Key' => $s3Key
            ]);
            $request = $s3->createPresignedRequest($cmd, '+6 days 23 hours');
            $url = (string)$request->getUri();
        } catch (S3Exception $e) {
            error_log( $e->getAwsErrorMessage() );
        } catch( \Exception $e ) {
            error_log( $e->getMessage() );
        }
        return $url;
    }

    public static function modifyPrescriptionsImages( $o_id, $images ){
        if( ! $o_id || ! is_array( $images ) ){
            return false;
        }

        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            return false;
        }

        // Instantiate an Amazon S3 client.
        $s3 = Functions::getS3();

        $s3_key_prefix = \sprintf( 'order/%s/%s-', \floor( $o_id / 1000 ), $o_id );

        if( $images && is_array( $images ) ){
            $imgArray = [];
            $limit = 8;
            $i = 1;
            foreach ( $images as $file ) {
                if( !$limit ){
                    break;
                }
                if( ! $file || ! is_array( $file ) ){
                    continue;
                }
                if( 0 === strpos( $file['src'], 'http') ) {
                    if( !empty($file['s3key']) && strpos( $file['s3key'], $s3_key_prefix ) !== false /* && $s3->doesObjectExist( Functions::getS3Bucket(), $file['s3key'] ) */ ){
                        array_push( $imgArray, $file['s3key'] );
                        $limit--; 
                    }                  
                } else {
                    $mime = @mime_content_type($file['src']);
                    if( ! $mime ){
                        continue;
                    }
                    $ext = strtolower( explode('/', $mime )[1] );
                    if( ! $ext || ! in_array( $ext, ['jpg', 'jpeg', 'png'] ) ){
                        continue;
                    }
                    
                    $s3key = \sprintf( '%s%s.%s', $s3_key_prefix, $i++ . Functions::randToken( 'alnumlc', 12 ), $ext );

                    $imgstring = trim( explode( ',', $file['src'] )[1] );
                    $imgstring = str_replace( ' ', '+', $imgstring );
                    if( strlen( $imgstring ) < 12 || strlen( $imgstring ) > 10 * 1024 * 1024 ){
                        continue;
                    }
                    // Upload file. The file size are determined by the SDK.
                    try {
                        $s3->putObject([
                            'Bucket' => Functions::getS3Bucket( 'secure' ),
                            'Key'    => $s3key,
                            'Body'   => base64_decode( $imgstring ),
                            'ContentType' => $mime,
                        ]);
                        //$s3->upload( Functions::getS3Bucket(), $s3key, base64_decode( $imgstring ) );
                        array_push( $imgArray, $s3key );
                        $limit--; 
                    } catch (S3Exception $e) {
                        error_log( $e->getAwsErrorMessage() );
                        continue;
                    }
                }
            }
            if ( $imgArray ){
                $order->setMeta( 'prescriptions', $imgArray );
                $oldMeta = Meta::get( 'user', $order->u_id, 'prescriptions' );
                Meta::set( 'user', $order->u_id, 'prescriptions', ( $oldMeta && is_array( $oldMeta ) ) ? array_unique( array_merge( $imgArray, $oldMeta ) ) : $imgArray );
            }
        }
        return true;
    }

    public static function getOrderPicUrlsAdmin( $o_id, $s3keys ){
        $imgArray = [];
        if( $s3keys && is_array( $s3keys ) ){
            foreach( $s3keys as $s3key ){
                array_push( $imgArray, [
                    'title' => $o_id,
                    's3key' => $s3key,
                    'src'   => Functions::getPresignedUrl( $s3key ),
                ]);
            }
        } 
        return $imgArray;
    }

    public static function orderMedicinesChanged( $o_id, $c_medicines ){
        if( $o_id ){
            $query = DB::instance()->select( 't_o_medicines', [ 'o_id' => $o_id ], 'm_id, m_qty' );
            $olds = $query->fetchAll();
            if( count( $c_medicines ) !== count( $olds ) ){
                return true;
            }

            foreach( $olds as $old ){
                if( isset( $c_medicines[ $old['m_id'] ] ) ){
                    if( $old['m_qty'] != $c_medicines[ $old['m_id'] ]['qty'] ){
                        return true;
                    }
                } else {
                    return true;
                }
            }
        }
        return false;
    }

    public static function miscMedicineUpdate( $prev_medicine, $medicine ){
        if( !$prev_medicine || !$medicine ) {
            return false;
        }
        //Functions::availableNotification( $prev_medicine, $medicine );
    }

    public static function availableNotification( $prev_medicine, $medicine ){
        if( !$prev_medicine || !$medicine ) {
            return false;
        }
        if( !$prev_medicine->m_rob && $medicine->m_rob ){
            $title = 'Product Available';
            $message = "Your requested product {$medicine->m_name} came in stock recently. Please check https://www.arogga.com/brand/" . $medicine->m_id;
            $query = DB::db()->prepare( 'SELECT tu.u_mobile, tu.fcm_token FROM t_request_stock AS trs INNER JOIN t_users AS tu ON trs.r_u_id = tu.u_id WHERE trs.r_m_id = ?' );
            $query->execute( [ $medicine->m_id ] );
            $u_mobile = [];
            $fcm_token = [];

            while( $user = $query->fetch() ){
                array_push( $u_mobile, $user['u_mobile'] );
                array_push( $fcm_token, $user['fcm_token'] );
            }
            $u_mobile = array_filter( array_unique( $u_mobile ) );
            $fcm_token = array_filter( array_unique( $fcm_token ) );

            if( $u_mobile ){
                Functions::sendSMS( $u_mobile, $message );
            }
            if( $fcm_token ){
                Functions::sendNotification( $fcm_token, $title, $message );
            }

            DB::instance()->delete( 't_request_stock', [ 'r_m_id' => $medicine->m_id ] );
        }
    }

    public static function miscOptionModifyFiles( $option_name, $optionFIles ){

        if( ! $option_name || ! is_array( $optionFIles ) ){
            return false;
        }
        // Instantiate an Amazon S3 client.
        $s3 = Functions::getS3();

        $s3_key_prefix = \sprintf( 'option/%s-', $option_name );

        if( $optionFIles && is_array( $optionFIles ) ){
            $imgArray = [];
            foreach ( $optionFIles as $file ) {
                if( ! $file || ! is_array( $file ) ){
                    continue;
                }
                if( 0 === strpos( $file['src'], 'http') ){
                    if( !empty($file['s3key']) && strpos( $file['s3key'], $s3_key_prefix ) !== false && $s3->doesObjectExist( Functions::getS3Bucket(), $file['s3key'] ) ){
                        array_push( $imgArray, [
                            's3key' => $file['s3key']
                        ]);
                    }
                } else {
                    $mime = @mime_content_type($file['src']);
                    if( ! $mime ){
                        continue;
                    }
                    $ext = explode('/', $mime )[1];
                    if( ! $ext || ! in_array( strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'] ) ){
                        continue;
                    }
                    $s3key = \sprintf( '%s%s.%s', $s3_key_prefix, Functions::randToken( 'alnumlc', 6 ), $ext );

                    //$imgstring = trim( str_replace("data:{$mime};base64,", "", $file['src'] ) );
                    $imgstring = trim( explode( ',', $file['src'] )[1] );
                    $imgstring = str_replace( ' ', '+', $imgstring );
                    if( strlen( $imgstring ) > 10 * 1024 * 1024 ){
                        continue;
                    }
                    // Upload file. The file size are determined by the SDK.
                    try {
                        $s3->putObject([
                            'Bucket' => Functions::getS3Bucket(),
                            'Key'    => $s3key,
                            'Body'   => base64_decode( $imgstring ),
                            'ContentType' => $mime,
                        ]);
                        array_push( $imgArray, [
                            's3key' => $s3key
                        ]);
                    } catch (S3Exception $e) {
                        error_log( $e->getAwsErrorMessage() );
                        continue;
                    }
                }
            }
            Option::set( $option_name, $imgArray );
        }
        return true;
    }
}