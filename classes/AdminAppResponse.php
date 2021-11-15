<?php

namespace OA;
use OA\Factory\{User, Medicine, Discount, Order, Option, CacheUpdate, Inventory, Generic, Company, Meta, Bag, Location};
use GuzzleHttp\Client;

class AdminAppResponse {
    private $user;

    function __construct() {
        if ( ! ( $user = User::getUser( Auth::id() ) ) ) {
            Response::instance()->loginRequired( true );
            Response::instance()->sendMessage( 'Invalid id token' );
        }
        $this->user = $user;
        if ( !\in_array( $this->user->u_role, [ 'delivery', 'pharmacy', 'packer', 'purchaser' ] ) ) {
            Response::instance()->sendMessage( 'Your account does not have permission to access this.' );
        }
    }

    function orders() {
        $status = isset( $_GET['status'] ) ? $_GET['status'] : '';
        $page = isset( $_GET['page'] ) ? (int)$_GET['page'] : 1;
        $lat = isset( $_GET['lat'] ) ? $_GET['lat'] : '';
        $long = isset( $_GET['long'] ) ? $_GET['long'] : '';
        $search = isset( $_GET['search'] ) ? $_GET['search'] : '';
        $zone = isset($_GET['zone']) ? $_GET['zone'] : '';
        $hideOutsideDhaka = isset( $_GET['hideOutsideDhaka'] ) ? filter_var( $_GET['hideOutsideDhaka'], FILTER_VALIDATE_BOOLEAN ) : '';
        $per_page = 10;
        $limit    = $per_page * ( $page - 1 );

        $db = new DB;

        $db->add( 'SELECT SQL_CALC_FOUND_ROWS tr.* FROM t_orders tr' );
        $db->add( ' INNER JOIN t_locations tl ON tr.o_l_id = tl.l_id' );
        $db->add( ' WHERE 1 = 1' );
        if ( $zone ){
            $db->add( ' AND tl.l_zone = ?', $zone);
        }
        if ( $hideOutsideDhaka && in_array( $this->user->u_role, [ 'packer', 'pharmacy' ] ) ){
            $db->add( ' AND ( tl.l_district = ? OR tr.o_payment_method IN (?,?,?) )', 'Dhaka City', 'fosterPayment', 'bKash', 'nagad' );
        }
        if ( $search && \is_numeric( $search ) ) {
            $db->add( ' AND tr.o_id = ?', $search );
        }
        if ( 'pharmacy' == $this->user->u_role ) {
            $db->add( ' AND tr.o_ph_id = ?', $this->user->u_id );
        } elseif( 'delivery' == $this->user->u_role ) {
            $db->add( ' AND tr.o_de_id = ?', $this->user->u_id );
        } elseif( 'packer' == $this->user->u_role ) {
            if( $ph_id = $this->user->getMeta( 'packer_ph_id' ) ){
                $db->add( ' AND tr.o_ph_id = ?', $ph_id );
            }
        }
        if ( \in_array( $status, [ 'ph_new' ] ) ) {
            $db->add( ' AND tr.o_status = ? AND tr.o_i_status = ?', 'confirmed', 'ph_fb' );
        } elseif ( \in_array( $status, [ 'de_new' ] ) ) {
            $db->add( ' AND tr.o_status = ? AND tr.o_i_status IN (?,?,?)', 'confirmed', 'ph_fb', 'packing', 'checking' );
        } elseif ( \in_array( $status, [ 'ph_issue' ] ) ) {
            $db->add( ' AND tr.o_is_status = ?', 'delivered' );
        } elseif ( \in_array( $status, [ 'packing', 'checking' ] ) ) {
            $db->add( ' AND ( ( tr.o_status = ? AND tr.o_i_status = ? ) OR tr.o_is_status = ? )', 'confirmed', $status, $status );
        } elseif ( \in_array( $status, [ 'confirmed' ] ) ) {
            $db->add( ' AND ( tr.o_status = ? OR tr.o_is_status = ? )', $status, 'packed' );
            $db->add( ' AND tr.o_i_status IN (?,?)', 'confirmed', 'paid' );
        } elseif ( \in_array( $status, [ 'delivering', 'delivered' ] ) ) {
            $db->add( ' AND ( tr.o_status = ? OR tr.o_is_status = ? )', $status, $status );
            $db->add( ' AND tr.o_i_status IN (?,?)', 'confirmed', 'paid' );
        } elseif ( $status ) {
            $db->add( ' AND tr.o_status = ?', $status );
            $db->add( ' AND tr.o_i_status IN (?,?)', 'confirmed', 'paid' );
        }

        if( 'delivered' == $status ) {
            $db->add( ' ORDER BY tr.o_delivered DESC' );
        } elseif( 'delivering' == $status ) {
            $db->add( ' ORDER BY tr.o_priority DESC, tr.o_id ASC' );
        } else {
            $db->add( ' ORDER BY tr.o_id ASC' );
        }

        $db->add( ' LIMIT ?, ?', $limit, $per_page );
        
        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Order');

        $orders = $query->fetchAll();
        if( ! $orders ){
            Response::instance()->sendMessage( 'No Orders Found' );
        }
        $o_ids = array_map(function($o) { return $o->o_id;}, $orders);
        CacheUpdate::instance()->add_to_queue( $o_ids , 'order_meta');
        CacheUpdate::instance()->update_cache( [], 'order_meta' );

        $laterCount = [];
        if ( 'pharmacy' == $this->user->u_role ) {
            $in  = str_repeat('?,', count($o_ids) - 1) . '?';
            $query2 = DB::db()->prepare( "SELECT o_id, COUNT(m_id) FROM t_o_medicines WHERE o_id IN ($in) AND om_status = ? GROUP BY o_id" );
            $query2->execute([...$o_ids, 'later']);
            $laterCount = $query2->fetchAll( \PDO::FETCH_KEY_PAIR );
        }

        $deliveredCount = [];
        if ( \in_array( $this->user->u_role, [ 'pharmacy', 'delivery' ] ) ) {
            $u_ids = array_map(function($o) { return $o->u_id;}, $orders);
            $u_ids = array_filter( array_unique( $u_ids ) );
            $in  = str_repeat('?,', count($u_ids) - 1) . '?';
            $query2 = DB::db()->prepare( "SELECT u_id, COUNT(o_id) FROM t_orders WHERE u_id IN ($in) AND o_status = ? GROUP BY u_id" );
            $query2->execute([...$u_ids, 'delivered']);
            $deliveredCount = $query2->fetchAll( \PDO::FETCH_KEY_PAIR );
        }

        foreach( $orders as $order ){
            $data = $order->toArray();
            $data['paymentStatus'] = ( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ) ? 'paid' : '';
            $data['o_i_note'] = (string)$order->getMeta('o_i_note');
            $data['cold'] = $order->hasColdItem();

            if ( in_array( $this->user->u_role, [ 'pharmacy', 'packer' ] ) && $order->o_l_id && ( $l_zone = Location::getValueByLocationId( $order->o_l_id, 'zone' ) ) ){
                $b_id = $order->getMeta( 'bag' );
                if( $b_id && ( $bag = Bag::getBag( $b_id ) ) ){
                    $data['zone'] = $bag->fullZone();
                } else {
                    $data['zone'] = $l_zone;
                }
            }

            if ( \in_array( $this->user->u_role, [ 'pharmacy' ] ) ) {
                if ( !empty($data['o_de_id']) && ( $user = User::getUser( $data['o_de_id'] ) ) ) {
                    $data['o_de_name'] = $user->u_name;
                }
            }
            if ( 'pharmacy' == $this->user->u_role && isset( $laterCount[ $order->o_id ] ) ) {
                $data['laterCount'] = $laterCount[ $order->o_id ];
            }
            if ( isset( $deliveredCount[ $order->u_id ] ) ) {
                $data['uOrderCount'] = $deliveredCount[ $order->u_id ];
            }
            if ( 'packing' === $order->o_i_status || 'packing' === $order->o_is_status ) {
                $data['packedWrong'] = (bool)$order->getMeta( 'packedWrong' );
            }

            Response::instance()->appendData( '', $data );
        }
        if ( ! Response::instance()->getData() ) {
            Response::instance()->sendMessage( 'No Orders Found' );
        } else {
            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        }
    }

    function later(){
        $page = isset( $_GET['page'] ) ? (int)$_GET['page'] : 1;

        $per_page = 20;
        $limit    = $per_page * ( $page - 1 );

        $db = new DB;
        $db->add( 'SELECT SQL_CALC_FOUND_ROWS tlm.m_id, tlm.total_qty, tm.m_name, tm.m_unit, tm.m_form, tm.m_strength, tm.m_price, tm.m_g_id, tm.m_c_id FROM t_later_medicines AS tlm INNER JOIN t_medicines AS tm ON tlm.m_id = tm.m_id WHERE 1 = 1' );
        if ( \in_array( $this->user->u_role, [ 'pharmacy' ] ) ) {
            $db->add( ' AND tlm.o_ph_id = ?', Auth::id() );
        }
        $db->add( ' LIMIT ?, ?', $limit, $per_page );
        
        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();
        $datas = $query->fetchAll();

        $m_ids = array_map(function($d) { return $d['m_id'];}, $datas);
        CacheUpdate::instance()->add_to_queue( $m_ids , 'medicine_meta');
        CacheUpdate::instance()->update_cache( [], 'medicine_meta' );

        foreach( $datas as $medicine ){
            $data = [
                'm_id' => $medicine['m_id'],
                'total_qty' => $medicine['total_qty'],
                'name' => $medicine['m_name'],
                'strength' => $medicine['m_strength'],
                'form' => $medicine['m_form'],
                'unit' => $medicine['m_unit'],
                'price' => $medicine['m_price'],
                'pic_url' => Functions::getPicUrl( Meta::get( 'medicine', $medicine['m_id'], 'images' ) ),
                'generic' => Generic::getName( $medicine['m_g_id'] ),
                'company' => Company::getName( $medicine['m_c_id'] ),
            ];

            Response::instance()->appendData( '', $data );
        }

        if ( ! Response::instance()->getData() ) {
            Response::instance()->sendMessage( 'No Medicines Found' );
        } else {
            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        }
    }

    function purchaseRequest(){
        $page = isset( $_GET['page'] ) ? (int)$_GET['page'] : 1;

        $per_page = 20;
        $limit    = $per_page * ( $page - 1 );

        $db = new DB;
        $db->add( 'SELECT SQL_CALC_FOUND_ROWS tpr.*, tm.m_name, tm.m_unit, tm.m_form, tm.m_strength, tm.m_price, tm.m_g_id, tm.m_c_id FROM t_purchase_request AS tpr INNER JOIN t_medicines AS tm ON tpr.m_id = tm.m_id WHERE 1 = 1' );
        $db->add( ' AND tpr.u_id = ?', Auth::id() );
        $db->add( ' LIMIT ?, ?', $limit, $per_page );
        
        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();
        $datas = $query->fetchAll();

        $m_ids = array_map(function($d) { return $d['m_id'];}, $datas);
        CacheUpdate::instance()->add_to_queue( $m_ids , 'medicine_meta');
        CacheUpdate::instance()->update_cache( [], 'medicine_meta' );

        foreach( $datas as $medicine ){
            $data = [
                'm_id' => $medicine['m_id'],
                'qty_text' => $medicine['qty_text'],
                'name' => $medicine['m_name'],
                'strength' => $medicine['m_strength'],
                'form' => $medicine['m_form'],
                'unit' => $medicine['m_unit'],
                'price' => $medicine['m_price'],
                'pic_url' => Functions::getPicUrl( Meta::get( 'medicine', $medicine['m_id'], 'images' ) ),
                'generic' => Generic::getName( $medicine['m_g_id'] ),
                'company' => Company::getName( $medicine['m_c_id'] ),
            ];

            Response::instance()->appendData( '', $data );
        }

        if ( ! Response::instance()->getData() ) {
            Response::instance()->sendMessage( 'No Medicines Found' );
        } else {
            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        }
    }

    function collections() {

        $page = isset( $_GET['page'] ) ? (int)$_GET['page'] : 1;
        $per_page = 10;
        $limit    = $per_page * ( $page - 1 );

        $query = DB::db()->prepare( 'SELECT * FROM t_collections WHERE co_fid = ? OR co_tid = ? ORDER BY co_created DESC LIMIT ?,?' );
        $query->execute( [ Auth::id(), Auth::id(), $limit, $per_page ] );

        while( $data = $query->fetch() ){
            $data['co_fname'] = User::getName( $data['co_fid'] );
            $data['co_tname'] = User::getName( $data['co_tid'] );
            $data['co_bag'] = Functions::maybeJsonDecode( $data['co_bag'] );

            unset( $data['o_ids'] );

            Response::instance()->appendData( '', $data );
        }
        if ( ! Response::instance()->getData() ) {
            Response::instance()->sendMessage( 'No Collections Found' );
        } else {
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        }
    }

    function pendingCollection() {
        if ( !\in_array( $this->user->u_role, [ 'delivery', 'pharmacy' ] ) ) {
            Response::instance()->sendMessage( 'Your account does not have permission to do this.' );
        }
        $query = DB::db()->prepare( 'SELECT o_ph_id, SUM(o_total) as amount FROM t_orders WHERE o_de_id = ? AND o_status = ? AND o_i_status = ? GROUP BY o_ph_id' );
        $query->execute( [ Auth::id(), 'delivered', 'confirmed' ] );

        while( $data = $query->fetch() ){
            if ( !empty($data['o_ph_id']) ) {
                $data['o_ph_name'] = User::getName( $data['o_ph_id'] );
                Response::instance()->appendData( '', $data );
            }
        }
        if ( ! Response::instance()->getData() ) {
            Response::instance()->sendMessage( 'No Collections Found' );
        } else {
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        }
    }

    function sendCollection(){
        if ( !\in_array( $this->user->u_role, [ 'delivery', 'pharmacy' ] ) ) {
            Response::instance()->sendMessage( 'Your account does not have permission to do this.' );
        }

        $ph_id = isset( $_POST['o_ph_id'] ) ? (int)$_POST['o_ph_id'] : 0;
        $amount = isset( $_POST['amount'] ) ? round( $_POST['amount'], 2 ) : 0.00;

        if( ! $ph_id || ! $amount ) {
            Response::instance()->sendMessage( 'Invalid pharmacy id or amount' );
        }

        $query = DB::db()->prepare( 'SELECT o_id, o_total FROM t_orders WHERE o_de_id = ? AND o_ph_id = ? AND o_status = ? AND o_i_status = ?' );
        $query->execute( [ Auth::id(), $ph_id, 'delivered', 'confirmed' ] );
        $o_ids = [];
        $total = 0;
        while( $order = $query->fetch() ){
            $o_ids[] = $order['o_id'];
            $total += $order['o_total'];
        }

        if( \round( $amount ) != \round( $total ) ) {
            Response::instance()->sendMessage( 'Amount Mismatch. Contact customer care.' );
        }
        $s_price_total = 0;
        if( $o_ids ) {
            $in  = str_repeat('?,', count($o_ids) - 1) . '?';
            $query = DB::db()->prepare( "SELECT SUM(s_price*m_qty) FROM t_o_medicines WHERE om_status = ? AND o_id IN ($in)" );
            $query->execute( \array_merge( ['available'], $o_ids ) );
            $s_price_total = $query->fetchColumn();
        }
        $bag = Bag::deliveryBag( $ph_id, Auth::id() );
        $bag_data = [];
        $currentBag = '';
        if( $bag ){
            $bag_data['f_b_id'] = $bag->b_id;
            $bag_data['f_bag'] = $bag->fullZone();
            if( $undeliveredIds = $bag->bagUndeliveredIds() ){
                $currentBag = Bag::getCurrentBag( $ph_id, $bag->b_zone);
                if( ! $currentBag ){
                    Response::instance()->sendMessage( 'No available bag for this zone' );
                }
                $bag_data['t_b_id'] = $currentBag->b_id;
                $bag_data['t_bag'] = $currentBag->fullZone();
                $bag_data['o_ids'] = $undeliveredIds;

                $notInBag_o_ids = array_values( array_diff( $bag->o_ids, $undeliveredIds ) );
                if( $notInBag_o_ids ){
                    $in  = str_repeat('?,', count($notInBag_o_ids) - 1) . '?';
                    $query = DB::db()->prepare( "SELECT o_id FROM t_orders WHERE o_status = ? AND o_id IN ($in)" );
                    $query->execute( [ 'cancelled', ...$notInBag_o_ids ] );
                    $c_o_ids = $query->fetchAll( \PDO::FETCH_COLUMN );
                    if( $c_o_ids ){
                        $bag_data['c_o_ids'] = $c_o_ids;
                    }
                }
            }
        }

        $data_array = [
            'co_fid' => Auth::id(),
            'co_tid' => $ph_id,
            'o_ids' => \json_encode( $o_ids ),
            'co_amount' => \round( $total, 2 ),
            'co_s_amount' => \round( $s_price_total, 2 ),
            'co_created' => \date( 'Y-m-d H:i:s' ),
            'co_bag' => Functions::maybeJsonEncode( $bag_data ),
        ];

        $id = DB::instance()->insert( 't_collections', $data_array );
        
        if( $id ) {
            if( $o_ids ) {
                $in  = str_repeat('?,', count($o_ids) - 1) . '?';
                $query = DB::db()->prepare( "UPDATE t_orders SET o_i_status = ? WHERE o_id IN ($in)" );
                $query->execute( \array_merge(['paid'], $o_ids) );

                foreach ( $o_ids as $o_id ) {
                    Cache::instance()->delete( $o_id, 'order' );
                }
            }
            Response::instance()->sendMessage( 'Collection done', 'success' );
        }

        Response::instance()->sendMessage( 'Something wrong. Plase try again.' );
    }

    function receivedCollection( $co_id ) {
        if( ! $co_id ) {
            Response::instance()->sendMessage( 'No collection found.' );
        }
        $query = DB::db()->prepare( 'SELECT * FROM t_collections WHERE co_id = ? AND co_tid = ? AND co_status = ? LIMIT 1' );
        $query->execute( [ $co_id, Auth::id(), 'pending' ] );

        if( $collection = $query->fetch() ){
            $updated = DB::instance()->update( 't_collections', ['co_status' => 'confirmed'], [ 'co_id' => $co_id ] );
            if( $updated ){
                $user = User::getUser( Auth::id() );
                $user->cashUpdate( -$collection['co_amount'] );

                $fm_name = User::getName( $collection['co_fid'] );
                $to_name = User::getName( $collection['co_tid'] );
                $reason = \sprintf( 'Collected by %s from %s', $to_name, $fm_name );
                Functions::ledgerCreate( $reason, $collection['co_amount'], 'collection' );

                $bag = Functions::maybeJsonDecode( $collection['co_bag'] );
                if( $bag ){
                    if( ! empty( $bag['o_ids'] ) && ! empty( $bag['t_b_id'] ) && ( $to_bag = Bag::getBag( $bag['t_b_id'] ) ) ){
                        $all_ids = array_merge( $to_bag->o_ids, $bag['o_ids'] );
                        $to_bag->update( [ 'o_ids' => $all_ids, 'o_count' => count( $all_ids ) ] );
                    }
                    if( ! empty( $bag['f_b_id'] ) && ( $from_bag = Bag::getBag( $bag['f_b_id'] ) ) ){
                        $from_bag->release();
                    }
                }

                Response::instance()->sendMessage( 'Confirmed received collection.', 'success' );
            }
        } else {
            Response::instance()->sendMessage( 'No collection found to confirm.' );
        }
    }

    function statusTo( $o_id, $status ){
        if ( !$o_id || !$status || !\in_array( $status, ['delivering', 'delivered'] ) ) {
            Response::instance()->sendMessage( 'No orders found.' );
        }
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        if( Auth::id() != $order->o_de_id ){
            Response::instance()->sendMessage( 'You are not delivery man for this order.' );
        }
        $medicines = $order->medicines;
        if( 'delivered' == $status ) {
            foreach ( $medicines as $key => $value ) {
                if( $value['qty'] && 'available' != $value['om_status'] ){
                    Response::instance()->sendMessage( 'All medicines price are not set. Contact Pharmacy and tell them to input all medicines price.' );
                }
            }
        }

        if( $order->update( [ 'o_status' => $status ] ) ){
            $data = $order->toArray();
            $data['prescriptions'] = $order->prescriptions;
            $data['o_data'] = (array)$order->getMeta( 'o_data' );
            $data['o_data']['medicines'] = $medicines;
            $data['timeline'] = $order->timeline();
            
            Response::instance()->sendData( $data, 'success' );
        }
        Response::instance()->sendMessage( 'Something wrong. Please try again.' );
    }

    function internalStatusTo( $o_id, $status ){
        if ( !$o_id || !$status || !\in_array( $status, [ 'packing', 'checking', 'confirmed'] ) ) {
            Response::instance()->sendMessage( 'No orders found.' );
        }
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        $allowed_ids = [
            $order->o_ph_id
        ];
        if( 'packer' == $this->user->u_role && $order->o_ph_id == $this->user->getMeta( 'packer_ph_id' ) ) {
            $allowed_ids[] = Auth::id();
        }
        if( ! in_array( Auth::id(), $allowed_ids ) ){
            Response::instance()->sendMessage( 'You cannot do this.' );
        }
        if( 'confirmed' == $status && $this->user->u_id == $order->getMeta( 'packedBy' ) ) {
            Response::instance()->sendMessage( 'You cannot check your own packed order.' );
        }

        if ( \in_array( $status, [ 'checking' ] ) ) {
            $l_zone = Location::getValueByLocationId( $order->o_l_id, 'zone' );
            $bag = Bag::getCurrentBag( $order->o_ph_id, $l_zone );
            if( ! $bag ){
                Response::instance()->sendMessage( 'No available bag for this zone' );
            }
            if( 'confirmed' !== $order->o_status ){
                Response::instance()->sendMessage( 'You cannot pack this order' );
            }
        }

        if( $order->update( [ 'o_i_status' => $status ] ) ){
            $mgs = '';
            if( 'checking' == $status ){
                $mgs = sprintf( '%s: Packed by %s', \date( 'Y-m-d H:i:s' ), $this->user->u_name );
            } elseif( 'confirmed' == $status ){
                $mgs = sprintf( '%s: Checked by %s', \date( 'Y-m-d H:i:s' ), $this->user->u_name );
            }
            if( $mgs ){
                $order->appendMeta( 'o_admin_note', $mgs );
            }
            if( 'packing' == $status ){
                $order->setMeta( 'packedWrong', 1 );
                if( $b_id = $order->getMeta( 'bag' ) ){
                    $bag = Bag::getBag( $b_id );
                    $bag->removeOrder( $order->o_id );
                    $order->deleteMeta( 'bag' );
                }
            } elseif( 'checking' == $status ){
                $order->setMeta( 'packedBy', $this->user->u_id );
                $order->deleteMeta( 'packedWrong' );
                $bag->addOrder( $order->o_id );
                $order->setMeta( 'bag', $bag->b_id );
            }

            Response::instance()->sendMessage( 'Successfully changed status.', 'success' );
        }
        Response::instance()->sendMessage( 'Something wrong. Please try again.' );
    }

    function issueStatusTo( $o_id, $status ){
        if ( !$o_id || !$status || !\in_array( $status, [ 'packing', 'checking', 'packed', 'delivering', 'delivered', 'operator', 'solved' ] ) ) {
            Response::instance()->sendMessage( 'No orders found.' );
        }
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        $allowed_ids = [
            $order->o_ph_id,
            $order->o_de_id,
        ];
        if( 'packer' == $this->user->u_role && $order->o_ph_id == $this->user->getMeta( 'packer_ph_id' ) ) {
            $allowed_ids[] = Auth::id();
        }
        if( ! in_array( Auth::id(), $allowed_ids ) ){
            Response::instance()->sendMessage( 'You cannot do this.' );
        }
        if( 'packed' == $status && $this->user->u_id == $order->getMeta( 'packedBy' ) ) {
            Response::instance()->sendMessage( 'You cannot check your own packed order.' );
        }
        if ( \in_array( $status, [ 'checking' ] ) ) {
            $l_zone = Location::getValueByLocationId( $order->o_l_id, 'zone' );
            $bag = Bag::getCurrentBag( $order->o_ph_id, $l_zone );
            if( ! $bag ){
                Response::instance()->sendMessage( 'No available bag for this zone' );
            }
        }

        if( $order->update( [ 'o_is_status' => $status ] ) ){
            $mgs = '';
            if( 'checking' == $status ){
                $mgs = sprintf( '%s: Issue packed by %s', \date( 'Y-m-d H:i:s' ), $this->user->u_name );
            } elseif( 'packed' == $status ){
                $mgs = sprintf( '%s: Issue checked by %s', \date( 'Y-m-d H:i:s' ), $this->user->u_name );
            } elseif( 'delivering' == $status ){
                $mgs = sprintf( '%s: Issue delivering by %s', \date( 'Y-m-d H:i:s' ), $this->user->u_name );
            } elseif( 'delivered' == $status ){
                $mgs = sprintf( '%s: Issue delivered by %s', \date( 'Y-m-d H:i:s' ), $this->user->u_name );
            } elseif( 'operator' == $status ){
                $mgs = sprintf( '%s: Issue marked to operator by %s', \date( 'Y-m-d H:i:s' ), $this->user->u_name );
            } elseif( 'solved' == $status ){
                $mgs = sprintf( '%s: Issue marked solved by %s', \date( 'Y-m-d H:i:s' ), $this->user->u_name );
            }
            if( $mgs ){
                $order->appendMeta( 'o_admin_note', $mgs );
            }
            if( 'packing' == $status ){
                $order->setMeta( 'packedWrong', 1 );
                if( $b_id = $order->getMeta( 'bag' ) ){
                    $bag = Bag::getBag( $b_id );
                    $bag->removeOrder( $order->o_id );
                    $order->deleteMeta( 'bag' );
                }
            } elseif( 'checking' == $status ){
                $order->setMeta( 'packedBy', $this->user->u_id );
                $order->deleteMeta( 'packedWrong' );
                $bag->addOrder( $order->o_id );
                $order->setMeta( 'bag', $bag->b_id );
            }
            Response::instance()->sendMessage( 'Successfully changed issue status.', 'success' );
        }
        Response::instance()->sendMessage( 'Something wrong. Please try again.' );
    }

    function saveInternalNote( $o_id ){
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }

        if( ! \in_array( Auth::id(), [ $order->o_de_id, $order->o_ph_id ] ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        $o_i_note = isset($_POST['o_i_note']) ? filter_var($_POST['o_i_note'], FILTER_SANITIZE_STRING) : '';
        $order->setMeta( 'o_i_note', $o_i_note );

        Response::instance()->sendData( [ 'o_i_note' => $order->getMeta( 'o_i_note' ) ], 'success' );
    }
	
	 function sendDeSMS( $o_id ){
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        $mobile  = '';
        $s_address = $order->getMeta('s_address');
        if( is_array( $s_address ) && ! empty( $s_address['mobile'] ) ){
            $mobile = Functions::checkMobile( $s_address['mobile'] );
        }

        if( ! $mobile  ){
            Response::instance()->sendMessage( 'No numbers found.' );
        }
        $deliveryman = User::getUser( $order->o_de_id );

        $message = sprintf("Dear client, Arogga's Deliveryman (%s: %s) has called you several times to deliver your order #%d. Please call him urgently to receive your order", $deliveryman ? $deliveryman->u_name : '', $deliveryman ? $deliveryman->u_mobile : '', $order->o_id );
        Functions::sendSMS( $mobile, $message );
        $order->appendMeta( 'o_i_note', date( "d-M h:ia" ) . ": SMS Sent" );
        //$order->addHistory( 'SMS', 'Delivery SMS sent' );

        Response::instance()->sendMessage( 'SMS Sent', 'success' );
    }

    function zones(){
        if( 'pharmacy' == $this->user->u_role ) {
            $ph_id = $this->user->u_id;
        } elseif( 'packer' == $this->user->u_role ) {
            $ph_id = $this->user->getMeta( 'packer_ph_id' );
        } else {
            Response::instance()->sendMessage( 'You cannot access zones' );
        }
        $zones = Functions::getPharmacyZones( $ph_id );
        Response::instance()->sendData( ['zones' => $zones], 'success' );
    }

}