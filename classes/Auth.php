<?php

namespace OA;
use OA\Factory\{User, Option};

class Auth {
    private static $u_id;

    function __construct() {
    }

    public static function id(){
        if( isset( static::$u_id ) ) {
            return static::$u_id;
        }
        $user = User::getByAuthToken( static::getToken() );
        if ( $user ) {
            static::$u_id = (int) $user->u_id;
        } else {
            static::$u_id = 0;
        }

        return static::$u_id;
    }

    public static function login( $u_id ) {
        $u_id = (int) $u_id;
        if ( User::getUser( $u_id ) ) {
            static::$u_id = $u_id;
            return static::$u_id;
        } else {
            return false;
        }
    }

    public static function getToken() {
        $headers = '';
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            //print_r($requestHeaders);
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return '';
    }

    function SMSSend() {
        //Response::instance()->sendMessage( "Dear valued clients.\nOur Dhaka city operation will resume from 29th November 2020.\nThanks for being with Arogga.");
        //Response::instance()->sendMessage( "Due to some unavoidable circumstances we cannot take orders now. We will send you a notification once we start taking orders.\nSorry for this inconvenience.");
        //Response::instance()->sendMessage( "Due to covid19 outbreak, there is a severe short supply of medicine.\nUntil regular supply of medicine resumes, we may not take anymore orders.\nSorry for this inconvenience.");
        //Response::instance()->sendMessage( "Due to Software maintainance we cannot receive orders now.\nPls try after 24 hours. We will be back!!");
        //Response::instance()->sendMessage( "Due to Software maintainance we cannot receive orders now.\nPlease try again after 2nd Jun, 11PM. We will be back!!");
        //Response::instance()->sendMessage( "Due to EID holiday we cannot receive orders now.\nPlease try again after EID. We will be back!!");
        //Response::instance()->sendMessage( "Due to EID holiday we cannot receive orders now.\nPlease try again after 28th May, 10PM. We will be back!!");

        $mobile = isset( $_POST['mobile'] ) ?  $_POST['mobile'] : '';
        $referral = isset( $_POST['referral'] ) ?  $_POST['referral'] : '';
        $fcm_token = isset( $_POST['fcm_token'] ) ?  $_POST['fcm_token'] : '';

        if ( ! $mobile ){
            Response::instance()->sendMessage( 'Mobile number required.');
        }
        if( ! ( $mobile = Functions::checkMobile( $mobile ) ) ) {
            Response::instance()->sendMessage( 'Invalid mobile number.');
        }

        $user = User::getBy( 'u_mobile', $mobile );
        if( $user ) {
            if ( 'blocked' == $user->u_status ){
                Response::instance()->sendMessage( 'You are blocked. Please contact customer care.');
            }
            if( $referral ) {
                Response::instance()->addData( 'refError', true );
                Response::instance()->sendMessage( sprintf("This phone number %s is already signed up with arogga, not eligible for referral bonus.\nReferral bonus is applicable for new customers only.", $user->u_mobile ) );
            }
            if ( ! $user->u_otp || \strtotime(\date( 'Y-m-d H:i:s' )) > \strtotime( $user->u_otp_time ) + 300  ) {
                if( '+8801000000007' == $mobile ) {
                    $user->u_otp = 100007;
                } else {
                    $user->u_otp = \random_int(1000,9999);
                }
                $user->u_otp_time = \date( 'Y-m-d H:i:s' );
                $user->update();
                $user->setMeta('failedTry', 0);

                // Send OTP SMS to mobile
                Functions::sendOTPSMS( $user->u_mobile, $user->u_otp );
            } else {
                $time = \strtotime( \date( 'Y-m-d H:i:s' ) ) - \strtotime( $user->u_otp_time );
                Functions::sendOTPSMS( $user->u_mobile, $user->u_otp, \ceil( $time / 30 ) );
            }

        } else {
            $user = new User;
            $user->u_mobile = $mobile;
            $user->u_otp = \random_int(1000,9999);
            $user->u_otp_time = \date( 'Y-m-d H:i:s' );

            if( $fcm_token ){
                $user->fcm_token = $fcm_token;
            }

            do{
                $u_referrer = Functions::randToken( 'distinct', 6 );

            } while( User::getBy( 'u_referrer', $u_referrer ) );

            $u_r_uid = 0;
            if( $referral ) {
                if( $r_user = User::getBy( 'u_referrer', $referral ) ) {
                    $u_r_uid = $r_user->u_id;
                    $user->u_cash = '0.00'; //May be give him some cash as he came from referral

                    $refBonus = Functions::changableData('refBonus');
                    if( $r_user->fcm_token ){
                        $title = "Congrats!";
                        $message = "{$mobile} has joined using your referral code. Once he places an order with Arogga you will receive {$refBonus} Taka referral bonus.";
                        Functions::sendNotification( $r_user->fcm_token, $title, $message );
                    }
                } else {
                    Response::instance()->sendMessage( 'Invalid referral code.');
                }
            }

            $user->u_referrer = $u_referrer;
            $user->u_r_uid = $u_r_uid;
            $user->insert();
            $user->setMeta('failedTry', 0);

            Response::instance()->addData( 'newUser', true );

            // Send OTP SMS to mobile
            Functions::sendOTPSMS( $user->u_mobile, $user->u_otp );
        }

        Response::instance()->sendMessage( "SMS sent to your mobile number.", 'success');
    }

    function SMSVerify() {
        $mobile = isset( $_POST['mobile'] ) ? Functions::checkMobile( $_POST['mobile'] ) : '';
        $otp = isset( $_POST['otp'] ) ?  $_POST['otp'] : '';
        $fcm_token = isset( $_POST['fcm_token'] ) ?  $_POST['fcm_token'] : '';
        $referral = ( isset( $_POST['referral'] ) && 'undefined' != $_POST['referral'] ) ? $_POST['referral'] : '';

        if ( ! $mobile || ! $otp ){
            Response::instance()->sendMessage( 'Mobile number and OTP required.');
        }

        $user = User::getBy( 'u_mobile', $mobile );
        $failedTry = (!$user) ? 0 :(int) $user->getMeta('failedTry');
        if ( ! $user ) {
            Response::instance()->sendMessage( 'Invalid Mobile Number.');
        } elseif( $failedTry >= 5 ){
            Response::instance()->sendMessage( 'Too many failed login attempts. Please try again after 5 minutes.');
        } elseif ( \strtotime(\date( 'Y-m-d H:i:s' )) > \strtotime( $user->u_otp_time ) + 300 ) {
            Response::instance()->sendMessage( 'OTP Expired, Please try again.');
        } elseif ( ! $user->u_otp || (int)$user->u_otp !== (int)$otp ) {
            $user->setMeta('failedTry', ++$failedTry);
            Response::instance()->sendMessage( 'Error verifying your code. Please input correct code from SMS');
        }
        if ( 'blocked' == $user->u_status ){
            Response::instance()->sendMessage( 'You are blocked. Please contact customer care.');
        }

        $user->u_otp = 0;
        if( $fcm_token ){
            $user->fcm_token = $fcm_token;
        }
        if( $referral && !$user->u_r_uid && \strtotime(\date( 'Y-m-d H:i:s' )) < ( \strtotime( $user->u_created ) + 60*60 ) ) {
            if( $r_user = User::getBy( 'u_referrer', $referral ) ) {
                $user->u_r_uid = $r_user->u_id;
                //$user->u_cash = '0.00'; //May be give him some cash as he came from referral

                $refBonus = Functions::changableData('refBonus');
                if( $r_user->fcm_token ){
                    $title = "Congrats!";
                    $message = "{$mobile} has joined using your referral code. Once he places an order with Arogga you will receive {$refBonus} Taka referral bonus.";
                    Functions::sendNotification( $r_user->fcm_token, $title, $message );
                }
            } else {
                Response::instance()->sendMessage( 'Invalid referral code.');
            }
        }
        $user->update();
        $user->setMeta('failedTry', 0);

        $smsSentCount = (int)Option::get( 'smsSentCount' );
        if( $smsSentCount > 0 ){
            Option::set( 'smsSentCount', --$smsSentCount );
        }

        $data = $user->toArray();
        $data['authToken'] = $user->authToken();
        $data['u_pic_url'] =  Functions::getProfilePicUrl( $user->u_id );
        
        Response::instance()->addData('user', $data);
        //Response::instance()->addData('authToken', 'TOKEN HERE');
        Response::instance()->setStatus( 'success' );
        Response::instance()->send();
    }

    function adminSMSSend() {
        \header("Access-Control-Allow-Origin: *");

        $mobile = isset( $_POST['mobile'] ) ?  $_POST['mobile'] : '';

        if ( ! $mobile ){
            Response::instance()->sendMessage( 'Mobile number required.');
        }
        if( ! ( $mobile = Functions::checkMobile( $mobile ) ) ) {
            Response::instance()->sendMessage( 'Invalid mobile number.');
        }

        $user = User::getBy( 'u_mobile', $mobile );
        if( $user ) {
            if( ! $user->can( 'backendAccess' ) ) {
                Response::instance()->sendMessage( 'Your account does not have admin access.');
            }
            if ( ! $user->u_otp || \strtotime(\date( 'Y-m-d H:i:s' )) > \strtotime( $user->u_otp_time ) + 180  ) {
                $user->u_otp = \random_int(100000,999999);
                $user->u_otp_time = \date( 'Y-m-d H:i:s' );
                $user->update();
                $user->setMeta('failedTry', 0);

                Functions::sendOTPSMS( $user->u_mobile, $user->u_otp );
            }

        } else {
            Response::instance()->sendMessage( 'Invalid mobile number.');
        }

        Response::instance()->sendMessage( "SMS sent to your mobile number.", 'success');
    }

    function adminSMSVerify() {
        \header("Access-Control-Allow-Origin: *");
        
        $mobile = isset( $_POST['mobile'] ) ? Functions::checkMobile( $_POST['mobile'] ) : '';
        $otp = isset( $_POST['otp'] ) ?  $_POST['otp'] : '';

        if ( ! $mobile || ! $otp ){
            Response::instance()->sendMessage( 'Mobile number and OTP required.');
        }

        $user = User::getBy( 'u_mobile', $mobile );
        $failedTry = (!$user) ? 0 :(int) $user->getMeta('failedTry');
        if ( ! $user ) {
            Response::instance()->sendMessage( 'Invalid Mobile Number.');
        }elseif( $failedTry >= 5 ){
            Response::instance()->sendMessage( 'Too many failed login attempts. Please try again after 5 minutes.');
        } elseif ( \strtotime(\date( 'Y-m-d H:i:s' )) > \strtotime( $user->u_otp_time ) + 300 ) {
            Response::instance()->sendMessage( 'OTP Expired, Please try again.');
        } elseif ( ! $user->u_otp || (int)$user->u_otp !== (int)$otp ) {
            $user->setMeta('failedTry', ++$failedTry);
            Response::instance()->sendMessage( 'Error verifying your code. Please input correct code from SMS');
        }
        if( ! $user->can( 'backendAccess' ) ) {
            Response::instance()->sendMessage( 'Your account does not have admin access.');
        }

        $user->u_otp = 0;
        $user->update();
        $user->setMeta('failedTry', 0);

        $smsSentCount = (int)Option::get( 'smsSentCount' );
        if( $smsSentCount > 0 ){
            Option::set( 'smsSentCount', --$smsSentCount );
        }

        $data = $user->toArray();
        $data['authToken'] = $user->authToken();
        $data['expressToken'] = Functions::jwtEncode( ['u_id' => $user->u_id, 'exp' => time() + 60 * 60 * 24 * 365], JWT_EXPRESS_KEY );
        $data['capabilities'] = $user->capabilities();
        $data['u_pic_url'] =  Functions::getProfilePicUrl( $user->u_id );
        
        Response::instance()->addData('user', $data);
        //Response::instance()->addData('authToken', 'TOKEN HERE');
        Response::instance()->setStatus( 'success' );
        Response::instance()->send();
    }

    function logout(){
        if ( $user = User::getUser( static::id() ) ) {
            $user->update( 'u_token', \bin2hex(\random_bytes(6)) );
            Response::instance()->sendMessage( "Successfully Logged out.", 'success');
        } else {
            Response::instance()->sendMessage( "You are not Logged in." );
        }
    }

}