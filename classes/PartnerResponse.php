<?php

namespace OA;
use OA\Factory\{User, Medicine, Discount, Order, Option, CacheUpdate, Inventory};
use GuzzleHttp\Client;

class PartnerResponse {
    private $user;

    function __construct() {
        \header("Access-Control-Allow-Origin: *");
        //\header("Access-Control-Request-Headers: *");
        //\define( 'ADMIN', true );

        if ( ! ( $user = User::getUser( Auth::id() ) ) ) {
            Response::instance()->setCode( 403 );
            Response::instance()->loginRequired( true );
            Response::instance()->sendMessage( 'You are not logged in' );
        }
        //Response::instance()->sendData( $user->toArray() );
        
        if( 'partner' !== $user->u_role) {
            Response::instance()->setCode( 401 );
            Response::instance()->sendMessage( 'Your account does not have permission to access this.');
        }

        $this->user = $user;
    }

    public function orderCreate() {
        //Response::instance()->sendMessage( "Dear valued clients.\nOur Dhaka city operation will resume from 29th November 2020.\nThanks for being with Arogga.");
        //Response::instance()->sendMessage( "Due to some unavoidable circumstances we cannot take orders now. We will send you a notification once we start taking orders.\nSorry for this inconvenience.");
        //Response::instance()->sendMessage( "Due to covid19 outbreak, there is a severe short supply of medicine.\nUntil regular supply of medicine resumes, we may not take anymore orders.\nSorry for this inconvenience.");
        //Response::instance()->sendMessage( "Due to Software maintainance we cannot receive orders now.\nPls try after 24 hours. We will be back!!");
        //Response::instance()->sendMessage( "Due to Software maintainance we cannot receive orders now.\nPlease try again after 2nd Jun, 11PM. We will be back!!");
        //Response::instance()->sendMessage( "Due to recent coronavirus outbreak, we are facing delivery man shortage.\nOnce our delivery channel is optimised, we may resume taking your orders.\nThanks for your understanding.");
        //Response::instance()->sendMessage( "Due to EID holiday we cannot receive orders now.\nPlease try again after EID. We will be back!!");
        //Response::instance()->sendMessage( "Due to EID holiday we cannot receive orders now.\nPlease try again after 28th May, 10PM. We will be back!!");

        $medicines = ( isset( $_POST['medicines'] ) && is_array( $_POST['medicines'] ) ) ?  $_POST['medicines'] : [];
        $d_code = isset( $_POST['d_code'] ) ?  $_POST['d_code'] : '';
        $prescriptions = isset( $_FILES['prescriptions'] ) ? $_FILES['prescriptions'] : [];
        $prescriptions_urls = ( isset( $_POST['prescriptions_urls'] ) && is_array($_POST['prescriptions_urls']) )? $_POST['prescriptions_urls']: [];

        $name = isset( $_POST['name'] ) ?  filter_var($_POST['name'], FILTER_SANITIZE_STRING) : '';
        $mobile = isset( $_POST['mobile'] ) ?  filter_var($_POST['mobile'], FILTER_SANITIZE_STRING) : '';
        $gps_address = '';
        $s_address = ( isset( $_POST['s_address'] ) && is_array($_POST['s_address']) )? $_POST['s_address']: [];
        $monthly = !empty( $_POST['monthly'] ) ?  1 : 0;
        $payment_method = ( isset( $_POST['payment_method'] ) && in_array($_POST['payment_method'], ['cod', 'online']) ) ? $_POST['payment_method'] : 'cod';

        if ( ! $name ){
            Response::instance()->sendMessage( 'name required.');
        }
        if ( ! $mobile ){
            Response::instance()->sendMessage( 'Mobile number required.');
        }
        if( ! ( $mobile = Functions::checkMobile( $mobile ) ) ) {
            Response::instance()->sendMessage( 'Invalid mobile number.');
        }
        $user = User::getBy( 'u_mobile', $mobile );
        if( !$user ){
            $user = new User;
            $user->u_name = $name;
            $user->u_mobile = $mobile;

            do{
                $u_referrer = Functions::randToken( 'distinct', 6 );

            } while( User::getBy( 'u_referrer', $u_referrer ) );

            $user->u_referrer = $u_referrer;
            $user->insert();
        }


        if ( ! $s_address ){
            Response::instance()->sendMessage( 'Address is required.');
        }
        if ( ! Functions::isLocationValid( @$s_address['division'], @$s_address['district'], @$s_address['area'] ) ){
            Response::instance()->sendMessage( 'invalid location.');
        }
        if( $s_address ){
            $s_address['location'] = sprintf('%s, %s, %s, %s', $s_address['homeAddress'], $s_address['area'], $s_address['district'], $s_address['division'] );
            $gps_address = $s_address['location'];
        }

        if ( ! $medicines && ! $prescriptions && ! $prescriptions_urls ){
            Response::instance()->sendMessage( 'medicines or prescription are required.');
        }
        if ( $medicines && ! is_array( $medicines ) ){
            Response::instance()->sendMessage( 'medicines need to be an array with id as key and quantity as value.');
        }
        if ( $prescriptions && ! is_array( $prescriptions ) ){
            Response::instance()->sendMessage( 'prescription need to be an file array.');
        }

        $discount = Discount::getDiscount( $d_code );

        if( ! $discount || ! $discount->canUserUse( $user->u_id ) ) {
            $d_code = '';
        }
        if ( !$user->u_name && $name ) {
            $user->u_name = $name;
        }
        if ( ! $user->u_mobile && $mobile ) {
            $user->u_mobile = $mobile;
        }

        $files_to_save = [];
        if ( $prescriptions ) {
            if ( empty( $prescriptions['tmp_name'] ) || ! is_array( $prescriptions['tmp_name'] ) ) {
                Response::instance()->sendMessage( 'prescription need to be an file array.');
            }
            if ( count( $prescriptions['tmp_name'] ) > 5 ) {
                Response::instance()->sendMessage( 'Maximum 5 prescription pictures allowed.');
            }
            $i = 1;
            foreach( $prescriptions['tmp_name'] as $key => $tmp_name ) {
                if( $i > 5 ){
                    break;
                }
                if( ! $tmp_name ) {
                    continue;
                }
                if ( UPLOAD_ERR_OK !== $prescriptions['error'][$key] ) {
                    Response::instance()->sendMessage( \sprintf('Upload error occured when upload %s. Please try again', \strip_tags( $prescriptions['name'][$key] ) ) );
                }
                $size = \filesize( $tmp_name );
                if( $size < 12 ) {
                    Response::instance()->sendMessage( \sprintf('File %s is too small.', \strip_tags( $prescriptions['name'][$key] ) ) );
                } elseif ( $size > 10 * 1024 * 1024 ) {
                    Response::instance()->sendMessage( \sprintf('File %s is too big. Maximum size is 10MB.', \strip_tags( $prescriptions['name'][$key] ) ) );
                }
                $imagetype = exif_imagetype( $tmp_name );
                $mime      = ( $imagetype ) ? image_type_to_mime_type( $imagetype ) : false;
                $ext       = ( $imagetype ) ? image_type_to_extension( $imagetype ) : false;
                if( ! $ext || ! $mime ) {
                    Response::instance()->sendMessage( 'Only prescription pictures are allowed.');
                }
                $files_to_save[ $tmp_name ] = ['name' => $i++ . Functions::randToken( 'alnumlc', 12 ) . $ext, 'mime' => $mime ];
            }
        }

        $cart_data = Functions::cartData( $user, $medicines, $d_code, null, false, ['s_address' => $s_address] );
        if ( ! empty( $cart_data['rx_req'] ) && ! $files_to_save ) {
            Response::instance()->sendMessage( 'Rx required.');
        }
        if( isset($cart_data['deductions']['cash']) && !empty($cart_data['deductions']['cash']['info'])) {
            $cart_data['deductions']['cash']['info'] = "Didn't apply because the order value was less than ৳499.";
        }
        if( isset($cart_data['additions']['delivery']) && !empty($cart_data['additions']['delivery']['info'])) {
            $cart_data['additions']['delivery']['info'] = str_replace('To get free delivery order more than', 'Because the order value was less than', $cart_data['additions']['delivery']['info']);
        }
        $c_medicines = $cart_data['medicines'];
        unset( $cart_data['medicines'] );

        $order = new Order;
        $order->u_id = $user->u_id;
        $order->u_name = $user->u_name;
        $order->u_mobile = $user->u_mobile;
        $order->o_subtotal = $cart_data['subtotal'];
        $order->o_addition = $cart_data['a_amount'];
        $order->o_deduction = $cart_data['d_amount'];
        $order->o_total = $cart_data['total'];
        $order->o_status = 'processing';
        $order->o_i_status = 'processing';
        //$order->o_address = $address;
        $order->o_gps_address = $gps_address;
        $order->o_payment_method = $payment_method;


        $order->o_ph_id = 6139;

        if( !isset( $s_address['district'] ) ){
        } elseif( $s_address['district'] != 'Dhaka City' ){
            //Outside Dhaka delivery ID
            $order->o_de_id = 143;
            $order->o_payment_method = 'online';
        } elseif( $d_id = Functions::getIdByLocation( 'l_de_id', $s_address['division'], $s_address['district'], $s_address['area'] ) ) {
            $order->o_de_id = $d_id;
        }
        if( isset( $s_address['district'] ) ){
            $order->o_l_id = Functions::getIdByLocation( 'l_id', $s_address['division'], $s_address['district'], $s_address['area'] );
        }
        $user->update();
        $order->insert();
        Functions::ModifyOrderMedicines( $order, $c_medicines );
        $meta = [
            'o_data' => $cart_data,
            'o_secret' => Functions::randToken( 'alnumlc', 16 ),
            's_address' => $s_address,
            'partner' => $this->user->u_id,
            'from' => 'partner',
            'o_i_note' => sprintf( 'Created through %s', $this->user->u_name ),
        ];
        if( $d_code ) {
            $meta['d_code'] = $d_code;
        }
        if( $monthly ) {
            $meta['subscriptionFreq'] = 'monthly';
        }

        $imgArray = [];
        if ( $files_to_save ) {
            $upload_folder = STATIC_DIR . '/orders/' . \floor( $order->o_id / 1000 );

            if ( ! is_dir($upload_folder)) {
                @mkdir($upload_folder, 0755, true);
            }
            foreach ( $files_to_save as $tmp_name => $file ) {
                $fileName = \sprintf( '%s-%s', $order->o_id, $file['name'] );
                $s3key = Functions::uploadToS3( $order->o_id, $tmp_name, 'order', $fileName, $file['mime'] );
                if ( $s3key ){
                    array_push( $imgArray, $s3key );
                }
            }
        } elseif( $prescriptions_urls ){
            $upload_folder = STATIC_DIR . '/orders/' . \floor( $order->o_id / 1000 );

            if ( ! is_dir($upload_folder)) {
                @mkdir($upload_folder, 0755, true);
            }

            $client = new Client(['verify' => false, 'http_errors' => false]);

            $i = 1;
            foreach ( $prescriptions_urls as $prescriptions_url ) {
                if( $i > 5 ){
                    break;
                }
                $filename = $i++ . Functions::randToken( 'alnumlc', 12 );
                $new_file = \sprintf( '%s/%s-%s', $upload_folder, $order->o_id, $filename );

                try {
                    $client->request('GET', $prescriptions_url, ['sink' => $new_file]);
                } catch (\Exception $e) {
                    continue;
                }

                $imagetype = exif_imagetype( $new_file );
                $mime      = ( $imagetype ) ? image_type_to_mime_type( $imagetype ) : false;
                $ext       = ( $imagetype ) ? image_type_to_extension( $imagetype ) : false;
                if( ! $ext || ! $mime ) {
                    if( 'application/pdf' === @mime_content_type( $new_file ) ){
                        $imagick = new \Imagick();
                        //$imagick->setResolution(595, 842);
                        $imagick->setResolution(300, 300);
                        //$imagick->setBackgroundColor('white');
                        $imagick->readImage( $new_file );
                        $imagick->setImageFormat('jpeg');
                        $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG);
                        $imagick->setImageCompressionQuality(82);
                        $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                        $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                        $imagick->writeImages( $new_file . '.jpeg', false);
                        $imagick->clear();
                        $imagick->destroy();

                        //@rename( $new_file, $new_file . '.pdf' );
                        $prescriptionArray = glob( $upload_folder . '/' . $order->o_id . "-*.jpeg", GLOB_NOSORT );
                        foreach ( $prescriptionArray as $fileName ) {
                            $s3key = Functions::uploadToS3( $order->o_id, $fileName);
                            if ( $s3key ){
                                array_push( $imgArray, $s3key );
                                unlink($fileName);
                            }
                        }
                    } else {
                        @unlink( $new_file );
                        Response::instance()->sendMessage( 'Invalid file type.');
                    }
                    @unlink( $new_file );
                } else {
                    $s3key = Functions::uploadToS3( $order->o_id, $new_file, 'order', basename( $new_file . $ext ), $mime );
                    if ( $s3key ){
                        array_push( $imgArray, $s3key );
                        unlink($new_file);
                    }
                }

            }
        }

        if ( count($imgArray) ){
            $meta['prescriptions'] = $imgArray ;

            $oldMeta = $user->getMeta( 'prescriptions' );
            $user->setMeta( 'prescriptions', ( $oldMeta && is_array($oldMeta ) ) ? array_merge( $oldMeta, $imgArray ) : $imgArray );
        }
        $order->insertMetas( $meta );
        $order->addHistory( 'Created', sprintf( 'Created through %s', $this->user->u_name ) );

        //Get user again, User data may changed
        $user = User::getUser( $user->u_id );

        $cash_back = $order->cashBackAmount();
        if ( $cash_back ) {
            $user->u_p_cash = $user->u_p_cash + $cash_back;
        }
        
        if( isset($cart_data['deductions']['cash']) ){
            $user->u_cash = $user->u_cash - $cart_data['deductions']['cash']['amount'];
        }
        $user->update();

        $message = 'Order created successfully.';

        Response::instance()->setStatus( 'success' );
        Response::instance()->setMessage( $message );
        Response::instance()->addData( 'o_id', $order->o_id );
        Response::instance()->addData( 'u_id', $user->u_id );
        Response::instance()->send();
    }

    function orders() {
        $per_page = 10;

        $status = !empty( $_GET['o_status'] ) ? $_GET['o_status'] : 'all';
        $page = !empty( $_GET['page'] ) ? (int)$_GET['page'] : 1;
        $u_id = !empty( $_GET['u_id'] ) ? (int)$_GET['u_id'] : 0;

        $limit    = $per_page * ( $page - 1 );

        $db = new DB;

        $db->add( 'SELECT tr.* FROM t_orders tr INNER JOIN t_order_meta tm ON tr.o_id = tm.o_id AND tm.meta_key = ? WHERE tm.meta_value = ?', 'partner', Auth::id() );
        if ( 'all' !== $status ) {
            $db->add( ' AND tr.o_status = ?', $status );
        }
        if ( $u_id ) {
            $db->add( ' AND tr.u_id = ?', $u_id );
        }
        $db->add( ' ORDER BY tr.o_id DESC' );
        $db->add( ' LIMIT ?, ?', $limit, $per_page );
        
        $query = $db->execute();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Order');

        while( $order = $query->fetch() ){
            $data = $order->toArray();
            $data['o_address'] = $data['o_gps_address'];
            unset( $data['o_gps_address'], $data['o_i_status'] );
            //TO-DO: Later queue all ids then get meta so that it will use one query
            $data['o_note'] = (string)$order->getMeta('o_note');
            $data['paymentStatus'] = ( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ) ? 'paid' : '';

            Response::instance()->appendData( '', $data );
        }
        if ( ! Response::instance()->getData() ) {
            Response::instance()->sendMessage( 'No Orders Found' );
        } else {
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        }
    }

    function orderSingle( $o_id ) {
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }

        if( Auth::id() != $order->getMeta('partner') ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        
        $data = $order->toArray();
        $data['prescriptions'] = $order->prescriptions;
        $data['o_data'] = (array)$order->getMeta( 'o_data' );
        $data['o_data']['medicines'] = $order->medicines;
        $data['s_address'] = $order->getMeta('s_address')?:[];

        $data['invoiceUrl'] = $order->signedUrl( '/v1/invoice' );
        $data['paymentStatus'] = ( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ) ? 'paid' : '';
        if( \in_array( $order->o_status, [ 'confirmed', 'delivering', 'delivered' ] ) && \in_array( $order->o_i_status, ['packing', 'checking', 'confirmed'] ) && 'paid' !== $order->getMeta( 'paymentStatus' ) ){
            $data['paymentUrl'] = $order->signedUrl( '/payment/v1' );
        }

        Response::instance()->sendData( $data, 'success' );
    }

    function locationData(){
       (new RouteResponse)->locationData();
    }

    function userSingle( $mobile ) {
        $mobile = Functions::checkMobile( $mobile );
        if( ! $mobile ) {
            Response::instance()->sendMessage( 'Invalid mobile number.');
        }
        $user = User::getBy( 'u_mobile', $mobile );
        if( ! $user ){
            Response::instance()->sendMessage( 'No users found.' );
        }
        $data = [
            'u_id' => $user->u_id,
            'u_name' => $user->u_name,
            'u_mobile' => $user->u_mobile,
        ];
        
        Response::instance()->sendData( $data, 'success' );
    }

}