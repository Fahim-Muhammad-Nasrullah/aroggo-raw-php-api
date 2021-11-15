<?php

namespace OA;
use OA\Factory\{User, Medicine, Discount, Order, Option, CacheUpdate, Log};
use GuzzleHttp\{Client, Promise};
use Aws\S3\Exception\S3Exception;

class CronResponse {
    private $response = [];

    function __construct() {
        if( ! defined( 'CRON_KEY' ) || ! isset( $_GET['cron_key'] ) || CRON_KEY !== $_GET['cron_key'] ) {
            Response::instance()->setCode( 401 );
            Response::instance()->sendMessage( 'Your account does not have permission to access this.');
        }

        ignore_user_abort( true );
        set_time_limit(3600);
        /* Don't make the request block till we finish, if possible. */
        /*
        if ( function_exists( 'fastcgi_finish_request' ) ) {
            fastcgi_finish_request();
        }
        */
    }

    private function die(){
        Log::instance()->insert([
            'log_response' => $this->response,
        ]);
        die();
    }

    function daily( $type = '' ){
        switch ( $type ) {
            case 'dumpLog':
                $this->dumpLog();
                break;
            case 'dumpLogToS3':
                $this->dumpLogToS3();
                break;
            case 'reOrder':
                $this->reOrder();
                break;
            case 'updateWeeklyRequirements':
                $this->updateWeeklyRequirements();
                break;
            
            default:
                # code...
                break;
        }
        $this->die();
    }

    function hourly( $type = '' ){
        switch ( $type ) {
            case 'notifyPayment':
                $this->notifyPayment();
                break;
            case 'cancelOrders':
                $this->cancelOrders();
                break;
        
            default:
                # code...
                break;
        }
        $this->die();
    }

    function halfhourly( $type = '' ){
        switch ( $type ) {
            case 'updateLaterMedicines':
                $this->updateLaterMedicines();
                break;
            case 'smsForPayment':
                $this->smsForPayment();
                break;

            default:
                # code...
                break;
        }
        $this->die();
    }

    function dumpLog(){
        $last_id = DB::db()->query('SELECT MAX(log_id) FROM t_logs')->fetchColumn();
        if( !$last_id ){
            return null;
        }
        DB::db()->beginTransaction();
        try {
            $query = DB::db()->prepare( 'INSERT INTO t_logs_backup (SELECT * FROM t_logs WHERE log_id <= ?)' );
            $query->execute( [ $last_id ] );

            $query2 = DB::db()->prepare( 'DELETE FROM t_logs WHERE log_id <= ?' );
            $query2->execute( [ $last_id ] );

            DB::db()->commit();
        } catch(\PDOException $e) {
            DB::db()->rollBack();
        }
    }
	
    function dumpLogToS3(){
        
        $query = DB::db()->query('SELECT MAX(t1.log_id) AS last_id, COUNT(t1.log_id) AS total FROM ( SELECT log_id FROM t_logs ORDER BY log_id LIMIT 50000) as t1');
        $row = $query->fetch();
        if( !$row || empty( $row['last_id'] ) || empty( $row['total'] ) || $row['total'] < 50000 ){
            return null;
        }
        $last_id = $row['last_id'];

        DB::db()->beginTransaction();
        try {
            $output = fopen('php://memory', 'w+');
            $i=0;
            $query = DB::db()->prepare('SELECT * FROM t_logs WHERE log_id <= ?');
            $query->execute( [ $last_id ] );
            while( $log = $query->fetch()){
                if ($i==0){
                    fputcsv($output, array_keys($log));
                    $i++;
                }
                fputcsv($output, $log);
            }
            rewind($output);

            $s3 = Functions::getS3();
            $fileName = sprintf( 'apiLogs/%d/%s/log_%d.csv.gz', \date('Y'), \date('m'), \date('YmdHis') );
            $s3->putObject([
                'Bucket' => Functions::getS3Bucket( 'secure' ),  
                'Key' => $fileName,
                //'Body' => stream_get_contents($output),
                'Body' => gzencode(stream_get_contents($output)),
                'ContentType' => 'text/csv',
                'ContentEncoding' => 'gzip'
            ]);

            fclose($output);

            $query2 = DB::db()->prepare( 'DELETE FROM t_logs WHERE log_id <= ?' );
            $query2->execute( [ $last_id ] );

            DB::db()->commit();
        } catch(\PDOException $e) {
            $this->response = $e->getMessage();
            DB::db()->rollBack();
        } catch (S3Exception $e) {
            $this->response = $e->getAwsErrorMessage();
            DB::db()->rollBack();
        } catch( \Exception $e ) {
			$this->response = $e->getMessage();
		}
    }
	
	public function reOrder(){
        $page = ! empty( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = 1000;
        $db = new DB;
        $db->add( 'SELECT tr.* FROM t_orders as tr JOIN t_order_meta AS tom ON tr.o_id = tom.o_id WHERE tr.o_status = ? ', 'delivered' );
        $db->add( ' AND tr.o_created < ? AND MOD(TIMESTAMPDIFF(DAY, tr.o_created, NOW()), 28) = 0', \date( 'Y-m-d H:i:s', strtotime("-3 days") ) );
        $db->add( ' AND tom.meta_key = ?', 'subscriptionFreq' );
        $db->add( ' AND tom.meta_value = ?', 'monthly' );
        $limit    = $perPage * ( $page - 1 );
        $db->add( ' LIMIT ?, ?', $limit, $perPage );
        $query = $db->execute();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Order');
        $orders = $query->fetchAll();
        if( ! $orders ){
            return false;
        }

        $o_ids = array_map(function($o) { return $o->o_id;}, $orders);
        CacheUpdate::instance()->add_to_queue( $o_ids , 'order_meta');
        CacheUpdate::instance()->update_cache( [], 'order_meta' );

        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'key='. FCM_SERVER_KEY,
            ],
            'http_errors' => false,
        ]);
        $promises = [];
        $date = new \DateTime();
        $date->modify("-3 days");

        foreach($orders as $order){
            if( $order->getMeta('lastOrderTime') && new \DateTime( $order->getMeta('lastOrderTime') ) > $date ){
                continue;
            }
            $result = Functions::reOrder($order->o_id);
            if( !$result ){
                continue;
            }
            $this->response[] = [
                'old' => $order->o_id,
                'new' => $result->o_id,
            ];
            $o_i_note = $result->getMeta('o_i_note');
            if( $o_i_note ){
                $o_i_note .= "\n";
            }
            $o_i_note .= sprintf( 'Previous order id = %s', $order->o_id);
            $result->setMeta( 'o_i_note', $o_i_note );
            $result->setMeta( 'prevOrder', $order->o_id );

            $o_i_note = $order->getMeta('o_i_note');
            if( $o_i_note ){
                $o_i_note .= "\n";
            }
            $o_i_note .= sprintf( 'New order id = %s', $result->o_id);
            $order->setMeta( 'o_i_note', $o_i_note );
            $order->setMeta( 'lastOrderTime', \date( 'Y-m-d H:i:s' ) );
            $user = User::getUser($order->u_id);
            $promise = Functions::sendAsyncNotification($client, $user->fcm_token, 'Monthly Order', "Your monthly order has been placed!", ['screen' => 'Orders', 'btnScreen' => 'SingleOrder', 'btnScreenParams' => ['o_id' => $result->o_id], 'btnLabel' => 'View Order'] );
            if( $promise ){
                $promises[] = $promise;
            }
        }
        $page++;

        if(count($orders) === $perPage){
            $client2 = new Client([
                'timeout' => 0.2,
                'http_errors' => false,
            ]);
            try {
                $client2->get( Functions::url( '_page', $page ) );
            } catch (\Exception $e) {
                //do nothing
            }
        }
        if( $promises ){
            Promise\Utils::settle($promises)->wait();
        }
    }

	 public function notifyPayment(){
        $page = ! empty( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = 1000;
        $db = new DB;
        $db->add( 'SELECT tr.* FROM t_orders as tr JOIN t_order_meta as tm1 ON tr.o_id = tm1.o_id JOIN t_locations as tl ON tr.o_l_id = tl.l_id LEFT JOIN t_order_meta as tm11 ON tr.o_id = tm11.o_id AND tm11.meta_key = ? WHERE tr.o_status = ? AND tr.o_payment_method = ? AND tm11.meta_key IS NULL', 'paymentStatus', 'confirmed', 'online' );
        $db->add( ' AND tl.l_district != ?', 'Dhaka City' );
        $db->add( ' AND tm1.meta_key = ?', 'paymentEligibleTime' );
        $db->add( ' AND tm1.meta_value > ? AND MOD(TIMESTAMPDIFF(HOUR, tm1.meta_value, ?), 8 ) = 0', \date( 'Y-m-d H:i:s', strtotime("-3 days") ),  \date( 'Y-m-d H:i:s' ) );
        $limit    = $perPage * ( $page - 1 );
        $db->add( ' LIMIT ?, ?', $limit, $perPage );

        $query = $db->execute();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Order');
        $orders = $query->fetchAll();
        if( ! $orders ){
            return false;
        }

        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'key='. FCM_SERVER_KEY,
            ],
            'http_errors' => false,
        ]);
        $o_ids = array_map(function($o) { return $o->o_id;}, $orders);
        $u_ids = array_map(function($o) { return $o->u_id;}, $orders);

        CacheUpdate::instance()->add_to_queue( $o_ids , 'order_meta');
        CacheUpdate::instance()->add_to_queue( $u_ids , 'user');
        CacheUpdate::instance()->update_cache( [], 'order_meta' );
        CacheUpdate::instance()->update_cache( [], 'user' );

        $this->response = $o_ids;

        $promises = [];
        foreach($orders as $order){
            $datetime = new \DateTime( $order->getMeta('paymentEligibleTime') );
            $datetime->modify('+3 days');

            $user = User::getUser($order->u_id);
            $promise = Functions::sendAsyncNotification($client, $user->fcm_token, 'Payment Due', sprintf( 'Please pay online for order id #%s. Once we receive your payment your order will go out for delivery. If we do not receive payment within %s, this order will automatically be cancelled.', $order->o_id, $datetime->format('F j, Y, g:i a') ) );
            if( $promise ){
                $promises[] = $promise;
            }
        }
        $page++;

        if(count($orders) === $perPage){
            $client2 = new Client([
                'timeout' => 0.2,
                'http_errors' => false,
            ]);
            try {
                $client2->get( Functions::url( '_page', $page ) );
            } catch (\Exception $e) {
                //do nothing
            }
        }

        if( $promises ){
            Promise\Utils::settle($promises)->wait();
        }
    }

    public function cancelOrders(){
        $db = new DB;
        $db->add( 'SELECT tr.* FROM t_orders as tr JOIN t_order_meta as tm1 ON tr.o_id = tm1.o_id JOIN t_locations as tl ON tr.o_l_id = tl.l_id LEFT JOIN t_order_meta as tm11 ON tr.o_id = tm11.o_id AND tm11.meta_key = ? WHERE tr.o_status = ? AND tr.o_payment_method = ? AND tm11.meta_key IS NULL', 'paymentStatus', 'confirmed', 'online' );
        $db->add( ' AND tl.l_district != ?', 'Dhaka City' );
        $db->add( ' AND tm1.meta_key = ?', 'paymentEligibleTime' );
        $db->add( ' AND tm1.meta_value < ?', \date( 'Y-m-d H:i:s', strtotime("-3 days") ) );
        $query = $db->execute();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Order');
        $orders = $query->fetchAll();
        if( ! $orders ){
            return false;
        }

        $o_ids = array_map(function($o) { return $o->o_id;}, $orders);
        CacheUpdate::instance()->add_to_queue( $o_ids , 'order_meta');
        CacheUpdate::instance()->update_cache( [], 'order_meta' );

        $this->response = $o_ids;

        foreach($orders as $order){
            $order->setMeta( 'o_note', 'Order cancelled automatically due to no payment within 3 days.' );
            $order->update(['o_status' => 'cancelled']);
        }
    }

	public function updateWeeklyRequirements(){
        $today = date("Y-m-d H:i:s");
        $dateRange = date("Y-m-d H:i:s",strtotime('-10 weeks', strtotime($today)));
        $db = new DB();
        $db->add( 'UPDATE t_inventory ti INNER JOIN (SELECT CEIL((SUM(tom.m_qty)/10)*1.2) as wkly_req, tr.o_ph_id, tom.m_id FROM t_o_medicines tom INNER JOIN t_orders tr ON tom.o_id = tr.o_id WHERE tr.o_status = ? AND tr.o_delivered BETWEEN ? AND ? GROUP BY tr.o_ph_id, tom.m_id) AS x ON ti.i_ph_id = x.o_ph_id AND ti.i_m_id = x.m_id SET ti.wkly_req = x.wkly_req', 'delivered', $dateRange, $today);
        $db->execute();
    }

    public function updateLaterMedicines(){
        $db = new DB;
        $db->add( 'SELECT tr.o_ph_id, tom.m_id, tr.o_created, SUM(tom.m_qty) as total_qty FROM t_o_medicines tom INNER JOIN t_orders tr ON tom.o_id = tr.o_id WHERE 1=1' );
        $db->add( ' AND tr.o_status IN (?,?)', 'processing', 'confirmed' );
        $db->add( ' AND tr.o_i_status IN (?,?,?)', 'ph_fb', 'later', 'confirmed' );
        $db->add( ' AND tom.om_status = ?', 'later' );
        $db->add( ' GROUP BY tr.o_ph_id, tom.m_id' );
        //$db->add( " ORDER BY tr.o_created ASC" );
        $query = $db->execute();
        $datas = $query->fetchAll();
        if( !$datas ){
            $query = DB::db()->prepare( 'DELETE FROM t_later_medicines' );
            $query->execute();
            return true;
        }

        DB::instance()->insertMultiple( 't_later_medicines', $datas, true );

        $ph_m_ids = [];
        foreach ( $datas as $data ){
            $ph_m_ids[ $data['o_ph_id'] ][] = $data['m_id'];
        }

        foreach ( $ph_m_ids as $ph_id => $m_ids ){
            $in  = str_repeat('?,', count($m_ids) - 1) . '?';
            $query = DB::db()->prepare( "DELETE FROM t_later_medicines WHERE o_ph_id = ? AND m_id NOT IN ($in)" );
            $query->execute( [ $ph_id, ...$m_ids ] );
        }

        $ph_ids = array_keys( $ph_m_ids );
        $in  = str_repeat('?,', count($ph_ids) - 1) . '?';
        $query = DB::db()->prepare( "DELETE FROM t_later_medicines WHERE o_ph_id NOT IN ($in)" );
        $query->execute( $ph_ids );

        return true;
    }

    public function smsForPayment(){
        $db = new DB;
        $db->add( 'SELECT tr.o_id, tr.u_mobile FROM t_orders as tr JOIN t_order_meta as tm1 ON tr.o_id = tm1.o_id JOIN t_locations as tl ON tr.o_l_id = tl.l_id WHERE tr.o_status = ? AND tr.o_payment_method = ?', 'confirmed', 'online' );
        $db->add( ' AND tl.l_district != ?', 'Dhaka City' );
        $db->add( ' AND tm1.meta_key = ?', 'paymentEligibleTime' );
        $db->add( ' AND tm1.meta_value BETWEEN ? AND ?', \date( 'Y-m-d H:i:s', strtotime("-1 hour -30 minutes") ), \date( 'Y-m-d H:i:s', strtotime("-1 hour") ) );
        $query = $db->execute();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Order');
        $orders = $query->fetchAll();
        if( ! $orders ){
            return false;
        }
        $o_ids = array_map(function($o) { return $o->o_id;}, $orders);
        $this->response = $o_ids;

        foreach($orders as $order){
            $message = "প্রিয় গ্রাহক, আপনার অর্ডারটির (#{$order->o_id}) প্যাকিং সম্পন্ন হয়েছে। দয়া করে Arogga এপ থেকে My Orders অপশনে গিয়ে পেমেন্ট সম্পন্ন করুন। পেমেন্ট হওয়ার পর আপনার অর্ডারটি ডেলিভারির জন্য বের হবে অন্যথায় অর্ডারটি স্বয়ংক্রিয়ভাবে ক্যান্সেল হয়ে যাবে ৭২ ঘন্টার মধ্যে।";
            Functions::sendSMS( $order->u_mobile, $message );
        }
        return true;
    }
}