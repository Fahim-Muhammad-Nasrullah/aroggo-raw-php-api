<?php

namespace OA;
use OA\Factory\{User, Medicine, Discount, Order, Option, CacheUpdate, Generic, Company, Meta};
use OA\Payment\{FosterPayment, Nagad, Bkash};
use GuzzleHttp\Client;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class OnetimeResponse {
    private $enabled;

    function __construct() {
        $user = User::getUser( Auth::id() );
        if ( ! $user || 'administrator' !== $user->u_role ) {
            Response::instance()->sendMessage( 'Your account does not have permission to do this.' );
        }

        $this->enabled = true;
    }

    function addImageLinkInMeta(){
        if( ! $this->enabled ){
            Response::instance()->sendMessage( 'Not enabled.' );
        }
        for( $i = 0; $i <= 30; $i++ ){
            $filePaths = [];
            foreach( glob( sprintf( '%s/images/medicine/%d/*.jpg', STATIC_DIR, $i) ) as $path ){
                $filename = basename($path);
                $m_id = explode('-',str_replace('.jpg', '', $filename))[0];
                if(!isset($filePaths[$m_id])){
                    $filePaths[$m_id] = [];
                }
                array_push($filePaths[$m_id], $path);
            }
            foreach($filePaths as $m_id => $paths){
                if( ! ($medicine = Medicine::getMedicine( $m_id ) ) ){
                    continue;
                }
                sort( $paths );
                $f = array_pop( $paths );
                array_unshift( $paths, $f );

                $imgArray = [];
                foreach($paths as $path){
                    $title = trim( $medicine->m_name . ' ' . $medicine->m_strength );
                    $src = str_replace( STATIC_DIR,  STATIC_URL, $path );
                    array_push( $imgArray, [
                        'title' => $title,
                        'name' => basename($src),
                        'src' => $src,
                    ]);
                }
                $medicine->setMeta( 'images', $imgArray );
                \OA\Search\Medicine::init()->update( $medicine->m_id, [ 'images' => $imgArray, 'imagesCount' => count( $imgArray ) ] );
            }
        }
        Cache::instance()->incr( 'suffixForMedicines' );
        Response::instance()->sendMessage( 'Success.', 'success' );
    }

    function addImageLinkInMetaS3(){
        if( ! $this->enabled ){
            Response::instance()->sendMessage( 'Not enabled.' );
        }
        $s3 = Functions::getS3();

        for( $i = 0; $i <= 30; $i++ ){
            $filePaths = [];
            foreach( glob( sprintf( '%s/images/medicine/%d/*.jpg', STATIC_DIR, $i) ) as $path ){
                $filename = basename($path);
                $m_id = explode('-',str_replace('.jpg', '', $filename))[0];
                if(!isset($filePaths[$m_id])){
                    $filePaths[$m_id] = [];
                }
                array_push($filePaths[$m_id], $path);
            }
            foreach($filePaths as $m_id => $paths){
                if( ! ($medicine = Medicine::getMedicine( $m_id ) ) ){
                    continue;
                }
                $s3_key_prefix = 'medicine/' . \floor( $m_id / 1000 );

                sort( $paths );
                $f = array_pop( $paths );
                array_unshift( $paths, $f );

                $imgArray = [];
                foreach($paths as $path){
                    $title = trim( $medicine->m_name . ' ' . $medicine->m_strength );
                    $name = preg_replace('/[^a-zA-Z0-9\-\._]/','-', $title );
                    $name = explode( '.', $name )[0];
                    $name = trim( preg_replace('/-+/', '-', $name ), '-' ) ;
                    $s3key = \sprintf( '%s/%s-%s-%s.jpg', $s3_key_prefix, $m_id, $name, Functions::randToken( 'alnumlc', 4 ) );

                    // Upload a publicly accessible file. The file size are determined by the SDK.
                    try {
                        $s3->putObject([
                            'Bucket' => Functions::getS3Bucket(),
                            'Key'    => $s3key,
                            'SourceFile' => $path,
                        ]);
                        array_push( $imgArray, [
                            'title' => $title,
                            's3key' => $s3key
                        ]);
                    } catch (S3Exception $e) {
                        error_log( $e->getAwsErrorMessage() );
                        continue;
                    }
                }
                $medicine->setMeta( 'images', $imgArray );
                \OA\Search\Medicine::init()->update( $medicine->m_id, [ 'images' => $imgArray, 'imagesCount' => count( $imgArray ) ] );
            }
        }
        Cache::instance()->incr( 'suffixForMedicines' );
        Response::instance()->sendMessage( 'Success.', 'success' );
    }

    function sendOTPSMS( $number, $count ){
        if( ! $this->enabled ){
            Response::instance()->sendMessage( 'Not enabled.' );
        }
        for ($i=0; $i < $count; $i++) { 
            Functions::sendOTPSMS( $number, $i . \random_int(1000,9999) );
        }
        Response::instance()->sendMessage( 'Success.', 'success' );
    }

    function sendNotificationToAllUsers(){
        if( ! $this->enabled ){
            Response::instance()->sendMessage( 'Not enabled.' );
        }
        $title = 'We are back in Dhaka again';
        $message = "Dear sir/mam\nExcited :) to inform you that we are back in Dhaka again. From now you can place order at Arogga.\nThanks for being with us.";

        $query = DB::db()->prepare( 'SELECT DISTINCT fcm_token FROM t_users WHERE fcm_token != ?' );
        $query->execute( [ '' ] );
        $data = [];
        while( $user = $query->fetch() ){
            Functions::sendNotification( $user['fcm_token'], $title, $message );
            //$data[] = $user['fcm_token'];
        }
        //Response::instance()->sendData( $data, 'success' );
        Response::instance()->sendMessage( 'Success.', 'success' );
    }

    public function bulkCreateOrder( $count ){
        if( ! $this->enabled ){
            Response::instance()->sendMessage( 'Not enabled.' );
        }
        if( ! $count || ! is_numeric( $count ) ){
            Response::instance()->sendMessage( 'Invalid count.' );
        }
        for( $i = 0; $i < $count; $i++ ){
            $this->orderCreate();
        }
        Response::instance()->sendMessage( 'Success.', 'success' );
    }

    public function orderCreate() {
        $u_array = [
            'u_name' => Functions::randToken( 'alpha', 6 ) . ' ' . Functions::randToken( 'alpha', 6 ),
            'u_mobile' => '+8801' . Functions::randToken( 'numeric', 9 ),
        ];
        $user = User::getBy( 'u_mobile', $u_array['u_mobile'] );

        if( ! $user ) {
            do{
                $u_referrer = Functions::randToken( 'distinct', 6 );
    
            } while( User::getBy( 'u_referrer', $u_referrer ) );
    
            $u_array['u_referrer'] = $u_referrer;
            $user = new User;
            $user->insert( $u_array );
        }
        $medicineQty = [];

        $query = DB::db()->prepare( 'SELECT m_id FROM t_medicines WHERE m_status = ? AND m_rob > ? ORDER BY RAND() LIMIT 10' );
        $query->execute( [ 'active', 0 ] );
        while( $m = $query->fetch() ){
            $medicineQty[ $m['m_id'] ] = rand(1,10);
        }
        
        $order = new Order;
        $cart_data = Functions::cartData( $user, $medicineQty );

        $c_medicines = $cart_data['medicines'];
        unset( $cart_data['medicines'] );

        $o_data = [
            'u_id' => $user->u_id,
            'u_name' => $user->u_name,
            'u_mobile' => $user->u_mobile,
            'o_address' => Functions::randToken( 'alpha', 6 ) . ' ' . Functions::randToken( 'alpha', 6 ),
            'o_status' => array_rand( array_flip( ['processing', 'confirmed', 'delivering', 'delivered'] )),
            'o_i_status' => array_rand( array_flip( ['dncall', 'confirmed', 'packing'] )),
            'o_created' => \date( 'Y-m-d H:i:s',time() - random_int( 0, 7884000) ), //3 months
        ];
        if( 'delivered' == $o_data['o_status'] ){
            $o_data['o_delivered'] = \date( 'Y-m-d H:i:s',\strtotime( $o_data['o_created'] ) + \rand( 122400, 165600) );
            $o_data['o_i_status'] = 'paid';
        }
        $o_data['o_subtotal'] = $cart_data['subtotal'];
        $o_data['o_addition'] = $cart_data['a_amount'];
        $o_data['o_deduction'] = $cart_data['d_amount'];
        $o_data['o_total'] = $cart_data['total'];

        $order->insert( $o_data  );
        Functions::ModifyOrderMedicines( $order, $c_medicines );
        $meta = [
            'o_data' => $cart_data,
            'o_secret' => Functions::randToken( 'alnumlc', 16 ),
        ];
        $order->insertMetas( $meta );

        $cash_back = $order->cashBackAmount();

        //again get user. User data may changed.
        $user = User::getUser( $order->u_id );
        
        if ( $cash_back ) {
            $user->u_p_cash = $user->u_p_cash + $cash_back;
        }
        if( isset($cart_data['deductions']['cash']) ){
            $user->u_cash = $user->u_cash - $cart_data['deductions']['cash']['amount'];
        }
        $user->update();
    }

    function orderDuplicate(){
        if( ! $this->enabled ){
            Response::instance()->sendMessage( 'Not enabled.' );
        }
        $query = DB::db()->prepare( 'SELECT * FROM t_orders WHERE o_id <= ? AND o_status = ? ORDER BY RAND() LIMIT 230' );
        $query->execute( [ 1308, 'delivered' ] );
        while( $o = $query->fetch() ){
            if( $this->orderDuplicateInsert( $o ) ){
                $data_array = [ 
                    'o_status' => 'cancelled',
                    'o_i_status' => 'cancelled',
                ];
                DB::instance()->update( 't_orders', $data_array, [ 'o_id' => $o['o_id'] ] );
            }
            //$this->orderDuplicateInsert( $o );
        }
        Response::instance()->sendMessage( 'Success.', 'success' );
    }
    function orderDuplicateInsert( $o ){
        $o_id = $o['o_id'];
        unset($o['o_id']);
        $next_id = DB::db()->query('SELECT o_id + 1 available_id FROM t_orders t WHERE NOT EXISTS ( SELECT * FROM t_orders WHERE o_id = t.o_id + 1 ) ORDER BY o_id LIMIT 1')->fetchColumn();
        if( !$next_id || $next_id > 1062 ){
            return false;
        }

        $o['o_id'] = $next_id;
        //$o['o_status'] = 'cancelled';
        //$o['o_i_status'] = 'cancelled';

        $new_o_id = DB::instance()->insert( 't_orders', $o );
        if( !$new_o_id ){
            return false;
        }
        DB::instance()->delete( 't_o_medicines', [ 'o_id' => $new_o_id ] );
        DB::instance()->delete( 't_order_meta', [ 'o_id' => $new_o_id ] );

        $prescription_dir = STATIC_DIR . '/orders/' . \floor( $o_id / 1000 );
        $p_array = glob( $prescription_dir . '/' . $o_id . "-*.*", GLOB_NOSORT );
        foreach ( $p_array as $name ) {
            //@\rename( $name );
            \rename( $name, \str_replace( "/{$o_id}-", "/{$new_o_id}-", $name ) );
            //$prescriptions[] = \str_replace( STATIC_DIR, STATIC_URL, $name );

        }

        $query = DB::instance()->select( 't_o_medicines', [ 'o_id' => $o_id ] );
        $data = [];
        while ( $om = $query->fetch() ) {
            unset($om['om_id']);
            $om['o_id'] = $new_o_id;
            $data[] = $om;
        }
        if( $data ){
            DB::instance()->insertMultiple( 't_o_medicines', $data );
        }

        $query = DB::instance()->select( 't_order_meta', [ 'o_id' => $o_id ] );
        $data = [];
        while ( $meta = $query->fetch() ) {
            if( 'o_secret' ==  $meta['meta_key'] ){
                continue;
            }
            unset($meta['meta_id']);
            $meta['o_id'] = $new_o_id;
            $data[] = $meta;
        }
        if( $data ){
            DB::instance()->insertMultiple( 't_order_meta', $data );
        }
        return true;
    }

    public function stockUpdate( $rob, $by, $id ){
        if( ! $this->enabled ){
            Response::instance()->sendMessage( 'Not enabled.' );
        }
        if( ! $by || ! $id ){
            Response::instance()->sendMessage( 'all fields required' );
        }
        $rob = (bool) $rob;

        $db = new DB;
        $db->add( 'SELECT * FROM t_medicines WHERE m_rob != ? AND m_status = ?', $rob, 'active' );

        switch ( $by ) {
            case 'm_id':
            case 'm_g_id':
            case 'm_c_id':
                $db->add( " AND $by = ?", $id );
                break;
            
            default:
                Response::instance()->sendMessage( 'No valid field name' );
                break;
        }
        $query = $db->execute();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Medicine');

        $m_ids = [];
        while( $medicine = $query->fetch() ){
            $medicine->update( [ 'm_rob' => $rob ] );
            $m_ids[] = $medicine->m_id;
        }
        Response::instance()->addData( 'm_ids', $m_ids );
        Response::instance()->sendMessage( 'Success.', 'success' );
    }

    public function priceUpdate( $discountPercent, $by, $id, $prevDiscountPercent = null ){
        if( ! $this->enabled ){
            Response::instance()->sendMessage( 'Not enabled.' );
        }
        if( ! is_numeric( $discountPercent ) || ! $by || ! $id ){
            Response::instance()->sendMessage( 'all fields required' );
        }

        $db = new DB;
        $db->add( 'SELECT * FROM t_medicines WHERE m_price > ? AND m_status = ?', 0, 'active' );

        switch ( $by ) {
            case 'm_id':
            case 'm_g_id':
            case 'm_c_id':
                $db->add( " AND $by = ?", $id );
                break;
            
            default:
                Response::instance()->sendMessage( 'No valid field name' );
                break;
        }
        if( is_numeric( $prevDiscountPercent ) ){
            $db->add( " AND m_d_price BETWEEN ( m_price * ? ) AND ( m_price * ? )", (100-$prevDiscountPercent-0.5)/100, (100-$prevDiscountPercent+0.5)/100 );
        }
        $query = $db->execute();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Medicine');

        $m_ids = [];
        while( $medicine = $query->fetch() ){
            $medicine->update( [ 'm_d_price' => $medicine->m_price * ((100-$discountPercent) / 100) ] );
            $m_ids[] = $medicine->m_id;
        }
        Response::instance()->addData( 'm_ids', $m_ids );
        Response::instance()->sendMessage( 'Success.', 'success' );
    }

    public function deliverymanUpdate( $prev_u_id, $curr_u_id ){
        if( ! $this->enabled ){
            Response::instance()->sendMessage( 'Not enabled.' );
        }
        $updated = DB::instance()->update( 't_locations', ['l_de_id' => $curr_u_id], ['l_de_id' => $prev_u_id] );

        if( $updated ){
            Cache::instance()->delete( 'locations' );

            Response::instance()->addData( 'count', $updated );
            Response::instance()->sendMessage( 'Success.', 'success' );
        }
        Response::instance()->sendMessage( 'Failed.' );
    }

    public function medicineCSVImport($number){
        $ids = [];
        if (($file = fopen(STATIC_DIR . "/import/medicines-{$number}.csv","r")) !== FALSE) {
            while(! feof($file))
            {
                
                $fileRow = fgetcsv($file);
                $g_id = 0;
                $c_id = 0;
                if ($fileRow && $fileRow[0] != "m_id") {

                    //Generic
                    if($fileRow[2]){
                        $generic = Generic::getGeneric($fileRow[2]);
                        if( $generic ){
                            $g_id = $generic->g_id;
                        }
                    }
                    if( !$g_id && $fileRow[3] ){
                        $query = DB::db()->prepare( 'SELECT g_id FROM t_generics_v2 WHERE g_name = ? LIMIT 1' );
                        $query->execute( [ $fileRow[3] ] );
                        $g_id = $query->fetchColumn();
                    }
                    if( !$g_id && $fileRow[3] ){
                        $newGeneric = new Generic;
                        $g_id = $newGeneric->insert([ 'g_name' => $fileRow[3] ]);
                    }

                    //Company
                    if($fileRow[4]){
                        $company = Company::getCompany($fileRow[4]);
                        if( $company ){
                            $c_id = $company->c_id;
                        }
                    }
                    if( !$c_id && $fileRow[5] ){
                        $query = DB::db()->prepare( 'SELECT c_id FROM t_companies WHERE c_name = ? LIMIT 1' );
                        $query->execute( [ $fileRow[5] ] );
                        $c_id = $query->fetchColumn();
                    }
                    if(!$c_id && $fileRow[5] ){
                        $newCompany = new Company;
                        $c_id = $newCompany->insert([ 'c_name' => $fileRow[5] ]);
                    }

                    //medicine
                    $medicineDetails = [
                        "m_name" => $fileRow[1],
                        "m_g_id" => $g_id,
                        "m_c_id" => $c_id,
                        "m_form" => trim( $fileRow[6] ),
                        "m_strength" => str_replace( ' ', '', $fileRow[7] ),
                        "m_price" => $fileRow[8],
                        "m_d_price" => $fileRow[9],
                        "m_unit" => trim( $fileRow[10] ),
                        "m_category" => $fileRow[11],
                        "m_status" => $fileRow[12],
                        "m_rob" => $fileRow[13],
                        "m_cat_id" => $fileRow[14],
                        "m_comment" => $fileRow[15],
                        "m_i_comment" => $fileRow[16],
                        "m_u_id" => $fileRow[17],
                    ];
                    $medicine = false;
                    if( $fileRow[0] ){
                        $medicine = Medicine::getMedicine($fileRow[0]);
                        if( $medicine ){
                            $medicine->update($medicineDetails);
                        } else {
                            $medicine = new Medicine;
                            $medicine->insert($medicineDetails);
                        }
                    } else {
                        $medicine = new Medicine;
                        $medicine->insert($medicineDetails);
                    }
                    if($medicine && $fileRow[11] == "healthcare"){
                        $medicine->setMeta('description', trim( $fileRow[18] ));
                    }
                    if( $medicine ){
                        array_push($ids, $medicine->m_id );
                    }
                }
            }
        }
        fclose($file);
        Response::instance()->sendData( $ids , 'success');
    }

    private function brandSlug( $brand ){
        if( ! $brand || ! is_array( $brand ) ){
            return '';
        }
        $slug = $brand['m_name']?? '';
        $slug .= '-';
        $slug .= $brand['m_form'] ?? '';
        $slug .= '-';
        $slug .= $brand['m_strength'] ?? '';

        $slug = strtolower( $slug );
        $slug = preg_replace('/[^a-zA-Z0-9\-\._]/','-', $slug);
        $slug = trim( preg_replace('/-+/', '-', $slug ), '-' ) ;
        return $slug;
    }

    function sitemap(){
        header('Content-Description: File Transfer');
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="sitemap.xml"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        echo '<?xml version="1.0" encoding="UTF-8"?>'. PHP_EOL;
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">'. PHP_EOL;

        $query = DB::db()->prepare( 'SELECT m_id, m_name, m_form, m_strength FROM t_medicines WHERE m_status = ?' );
        $query->execute( [ 'active' ] );
        $data = [];
        while( $m = $query->fetch() ){
            echo '<url>'. PHP_EOL;
            echo '<loc>'.sprintf( 'https://www.arogga.com/brand/%d/%s', $m['m_id'], $this->brandSlug($m) ).'</loc>'. PHP_EOL;
            echo '</url>'. PHP_EOL;
        }

        echo '</urlset>'. PHP_EOL;
        die;
    }

    function addPrescriptionToS3(){
        if( ! $this->enabled ){
            Response::instance()->sendMessage( 'Not enabled.' );
        }
        $s3 = Functions::getS3();
        $query = DB::db()->prepare( 'SELECT o_id, u_id FROM t_orders Order by o_id ASC' );
        $query->execute();
        while ( $order = $query->fetch() ) {
            $prescription_dir = STATIC_DIR . '/orders/' . \floor( $order['o_id'] / 1000 );
            $prescriptionArray = glob( $prescription_dir . '/' . $order['o_id'] . "-*.{jpg,jpeg,png,gif}", GLOB_NOSORT | GLOB_BRACE );
            $imgArray = [];
            foreach ( $prescriptionArray as $fileName ) {
                $s3key = str_replace( STATIC_DIR . '/orders/', 'order/', $fileName );
                try {
                    $s3->putObject([
                        'Bucket' => Functions::getS3Bucket(),  
                        'Key' => $s3key,
                        'SourceFile' => $fileName
                    ]);
                    $imgArray[] = $s3key;
                } catch (S3Exception $e) {
                    error_log( $e->getAwsErrorMessage() );
                    continue;
                } catch( \Exception $e ) {
                    error_log( $e->getMessage() );
                    continue;
                }
            }
            if ( count($imgArray) ){
                Meta::set( 'order', $order['o_id'], 'prescriptions', $imgArray );
                $oldMeta = Meta::get( 'user', $order['u_id'], 'prescriptions' );
                Meta::set( 'user', $order['u_id'], 'prescriptions', ( $oldMeta && is_array($oldMeta ) ) ? array_merge( $oldMeta, $imgArray ) : $imgArray );
            }
        }
        Response::instance()->sendMessage( 'Success.', 'success' );
    }

    public function addLedgerFilesToS3(){
        $s3 = Functions::getS3();
        $query = DB::db()->prepare( 'SELECT l_id, l_files FROM t_ledger ORDER BY l_id ASC' );
        $query->execute( );
        while( $ledger = $query->fetch() ){
            $ledger_dir = STATIC_DIR . '/ledger/' . \floor( $ledger['l_id'] / 1000 );
            $ledgerArray = glob( $ledger_dir . '/' . $ledger['l_id'] . "-*.{jpg,jpeg,png,gif,pdf}", GLOB_NOSORT | GLOB_BRACE );
            $imgArray = [];
            foreach ( $ledgerArray as $fileName ) {
                $s3key = str_replace( STATIC_DIR . '/ledger/', 'ledger/', $fileName );
                try {
                    $s3->putObject([
                        'Bucket' => Functions::getS3Bucket(),  
                        'Key' => $s3key,
                        'SourceFile' => $fileName
                    ]);

                    array_push( $imgArray, [
                        'title' => $this->ledgerFileNameFromPath( $fileName ),
                        's3key' => $s3key
                    ]);
                } catch (S3Exception $e) {
                    error_log( $e->getAwsErrorMessage() );
                    continue;
                } catch( \Exception $e ) {
                    error_log( $e->getMessage() );
                    continue;
                }
            }
            if ( $imgArray ){
                DB::instance()->update( 't_ledger', [ 'l_files' => Functions::maybeJsonEncode( $imgArray ) ], [ 'l_id' => $ledger['l_id'] ] );
            }
        }
        Response::instance()->sendMessage( 'Success.', 'success' );		
    }

    private function ledgerFileNameFromPath( $path ){
        $name = basename( $path );
        list( $name, $ext ) = explode('.', $name );
        $name = substr($name, 0, strrpos($name, '-'));
        $name = substr($name, strpos($name, '-')+1 );
        return "{$name}.{$ext}";
    }

    public function updateLocationsTable(){
        $url = sprintf( 'https://barikoi.xyz/v1/api/search/%s/rupantor/geocode', BARIKOI_API_KEY );
        $client = new Client();

        $query = DB::db()->prepare( 'SELECT * FROM t_locations WHERE l_lat = ? AND l_comment = ? LIMIT 30' );
        $query->execute( ['', ''] );

        while ( $location = $query->fetch() ){
            $update_data = [];
            $district = trim( str_replace( [ 'City', 'District' ], '', $location['l_district'] ) );
            $address = sprintf( '%s, %s, %s', $location['l_area'], $district, $location['l_division'] );

            $res = $client->post($url,
                ["form_params" => [
                    'q' => $address,
                    'zone' => 'true',
                ]]
            );
            $res_data = Functions::maybeJsonDecode( $res->getBody()->getContents() );
            if( !$res_data || !is_array( $res_data ) ){
                $update_data = [
                    'l_comment' => 'No data',
                ];
            } elseif( empty( $res_data['geocoded'] ) || empty( $res_data['geocoded']['latitude'] ) ){
                $update_data = [
                    'l_comment' => 'No lat',
                ];
            } else {
                $update_data = [
                    'l_lat' => round( $res_data['geocoded']['latitude'], 6 ),
                    'l_long' => round( $res_data['geocoded']['longitude'], 6 ),
                ];
                if( !$location['l_postcode'] ){
                    $update_data['l_postcode'] = $res_data['geocoded']['postCode'];
                }
            }
            DB::instance()->update('t_locations', $update_data, ['l_id' => $location['l_id']]);
            //To prevent too many requests error as api rate is 60 requests per minute
            //sleep(1);
        }
        Response::instance()->sendMessage( 'Success.', 'success' );
    }

    public function genericsMerge(){
        $query = DB::db()->prepare( 'SELECT tg.* FROM t_generics tg INNER JOIN t_generics_v2 tg2 ON tg.g_id = tg2.g_id' );
        $query->execute();
        while ( $row = $query->fetch() ){
            if( !( $generic = Generic::getGeneric( $row['g_id'] ) ) ){
                continue;
            }
            if( ! $generic->g_quick_tips ){
                continue;
            }
            $val = [];
            if( !empty( $row['indication'] ) ){
                $val[] = [
                    'title' => 'Indication',
                    'content' => $row['indication'],
                ];
            }
            if( !empty( $row['administration'] ) ){
                $val[] = [
                    'title' => 'Administration',
                    'content' => $row['administration'],
                ];
            }
            if( !empty( $row['adult_dose'] ) ){
                $val[] = [
                    'title' => 'Adult Dose',
                    'content' => $row['adult_dose'],
                ];
            }
            if( !empty( $row['child_dose'] ) ){
                $val[] = [
                    'title' => 'Child Dose',
                    'content' => $row['child_dose'],
                ];
            }
            if( !empty( $row['renal_dose'] ) ){
                $val[] = [
                    'title' => 'Renal Dose',
                    'content' => $row['renal_dose'],
                ];
            }
            if( !empty( $row['contra_indication'] ) ){
                $val[] = [
                    'title' => 'Contraindication',
                    'content' => $row['contra_indication'],
                ];
            }
            if( !empty( $row['mode_of_action'] ) ){
                $val[] = [
                    'title' => 'Mode of Action',
                    'content' => $row['mode_of_action'],
                ];
            }
            if( !empty( $row['precaution'] ) ){
                $val[] = [
                    'title' => 'Precaution',
                    'content' => $row['precaution'],
                ];
            }
            if( !empty( $row['side_effect'] ) ){
                $val[] = [
                    'title' => 'Side Effect',
                    'content' => $row['side_effect'],
                ];
            }
            if( !empty( $row['pregnancy_category_note'] ) ){
                $val[] = [
                    'title' => 'Pregnancy Category Note',
                    'content' => $row['pregnancy_category_note'],
                ];
            }
            if( !empty( $row['interaction'] ) ){
                $val[] = [
                    'title' => 'Interaction',
                    'content' => $row['interaction'],
                ];
            }
            if( $val ){
                $generic->update([ 'g_brief_description' => Functions::maybeJsonEncode( $val ) ]);
            }
        }
        Response::instance()->sendMessage( 'Success.', 'success' );
    }

    public function updateFosterGatewayFee(){
        $db = new DB;
        $db->add( "SELECT tr.* FROM t_orders AS tr LEFT JOIN t_order_meta AS tom ON tr.o_id = tom.o_id AND tom.meta_key = ? WHERE 1=1", "paymentGatewayFee");
        $db->add( " AND tr.o_payment_method = ? AND tom.meta_value IS NULL", "fosterPayment" );
        $db->add( " GROUP BY tr.o_id" );
        $query = $db->execute();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Order' );
        while( $order = $query->fetch() ){
            FosterPayment::instance()->fosterpaymentsStatus( $order );
        }
        Response::instance()->sendMessage( 'Success.', 'success' );
    }

    public function updateFosterGatewayFeeSingle( $o_id ){
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        $success = FosterPayment::instance()->fosterpaymentsStatus( $order );
        if( $success ){
            Response::instance()->sendMessage( 'Success.', 'success' );
        } else {
            Response::instance()->sendMessage( 'Something wrong.');
        }
    }
}