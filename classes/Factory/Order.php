<?php

namespace OA\Factory;
use OA\{DB, Cache, Functions};

/**
 * 
 */
class Order {
	
	private $o_id = 0;
    private $u_id = 0;
    private $u_name = '';
    private $u_mobile = '';
    private $o_subtotal = '0.00';
    private $o_addition = '0.00';
    private $o_deduction = '0.00';
    private $o_total = '0.00';
    private $o_created = '0000-00-00 00:00:00';
    private $o_updated = '0000-00-00 00:00:00';
    private $o_delivered = '0000-00-00 00:00:00';
    private $o_status = 'processing';
    private $o_i_status = 'processing';
    private $o_is_status = '';
    private $o_address = '';
    private $o_lat = 0.0;
    private $o_long = 0.0;
    private $o_gps_address = '';
    private $o_payment_method = 'cod';
    private $o_de_id = 0;
    private $o_ph_id = 0;
    private $o_priority = false;
    private $o_l_id = 0;
    
    function __construct( $id = 0 )
    {
        if( $id instanceof self ){
            foreach( $id->toArray() as $k => $v ){
                $this->$k = $v;
            }
        } elseif( is_numeric( $id ) && $id && $order = static::getOrder( $id ) ){
            foreach( $order->toArray() as $k => $v ){
                $this->$k = $v;
            }
        }
    }
    
    public static function getOrder( $id ) {
        if ( ! \is_numeric( $id ) ){
            return false;
        }
        $id = \intval( $id );
        if ( $id < 1 ){
            return false;
        }

        if ( $order = Cache::instance()->get( $id, 'order' ) ){
            return $order;
        }
        
        $query = DB::db()->prepare( 'SELECT * FROM t_orders WHERE o_id = ? LIMIT 1' );
        $query->execute( [ $id ] );
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Order');
        if( $order = $query->fetch() ){     
            Cache::instance()->add( $order->o_id, $order, 'order' );
            return $order;
        } else {
            return false;
        }
    }
    
    public function toArray(){
        $array = [];
        foreach ( \array_keys( \get_object_vars( $this ) ) as $key ) {
            $array[ $key ] = $this->get( $key );
        };
        return $array;
    }
    
    public function exist(){
		return ! empty( $this->o_id );
	}
    
    public function __get( $key ){
		return $this->get( $key );
	}
	
	public function get( $key, $filter = false ){
		if( property_exists( $this, $key ) ) {
			$value = $this->$key;
		} else {
			$value = false;
        }
        switch ( $key ) {
            case 'o_id':
            case 'u_id':
            case 'o_de_id':
            case 'o_ph_id':
            case 'o_l_id':
                $value = (int) $value;
            break;
            case 'o_subtotal':
            case 'o_addition':
            case 'o_deduction':
            case 'o_total':
                $value = \round( $value, 2 );
            break;
            case 'o_priority':
                $value = (bool) $value;
            break;
            case 'prescriptions':
                $p_array = $this->getMeta( 'prescriptions' );
                $p_array = ( $p_array && is_array($p_array) ) ? $p_array : [];
                $value = [];
                foreach ( $p_array as $s3key ) {
                    $value[] = Functions::getPresignedUrl( $s3key );
                }
            break;
            case 'medicineQty':
                $value = [];
                $query = DB::db()->prepare( 'SELECT tom.*, tom.m_qty AS qty, tm.m_name, tm.m_form, tm.m_strength, tm.m_c_id FROM t_o_medicines tom INNER JOIN t_medicines tm ON tom.m_id = tm.m_id WHERE tom.o_id = ? ORDER BY tm.m_c_id LIMIT 100' );
                $query->execute( [ $this->o_id ] );
                while ( $om = $query->fetch() ) {
                    $om['m_company'] = Company::getName( $om['m_c_id'] );
                    $value[] = $om;
                }
            break;
            case 'medicines':
                $value = [];
                $query = DB::db()->prepare( 'SELECT tom.om_id, tom.m_id, tom.m_unit, tom.m_price, tom.m_d_price, tom.m_qty, tom.s_price, tom.om_status, tm.m_name, tm.m_form, tm.m_strength, tm.m_g_id, tm.m_c_id, tm.m_cold FROM t_o_medicines tom INNER JOIN t_medicines tm ON tom.m_id = tm.m_id WHERE tom.o_id = ? ORDER BY tm.m_c_id LIMIT 100' );
                $query->execute( [ $this->o_id ] );
                while( $om = $query->fetch() ){
                    $value[] = [
                        'om_id' => $om['om_id'],
                        'qty' => (int)$om['m_qty'],
                        'name' => $om['m_name'],
                        'form' => $om['m_form'],
                        'company' => Company::getName( $om['m_c_id'] ),
                        'strength' => $om['m_strength'],
                        'unit' => $om['m_unit'],
                        'price' => \round( $om['m_price'] * $om['m_qty'], 2),
                        'd_price' => \round( $om['m_d_price'] * $om['m_qty'], 2),
                        's_price' => \round( $om['s_price'] * $om['m_qty'], 2),
                        'm_id' => $om['m_id'],
                        'om_status' => $om['om_status'],
                        'pic_url' => Functions::getPicUrl( Meta::get( 'medicine', $om['m_id'], 'images' ) ),
                        'cold' => (bool)$om['m_cold'],
                    ];
                }
            break;
            default:
                break;
        }
		return $value;
	}
    public function __set( $key, $value ){
		return $this->set( $key, $value );
	}
    public function set( $key, $value ){

        switch( $key ){
            case 'o_id':
                return false;
            break;
            case 'u_id':
            case 'o_de_id':
            case 'o_ph_id':
            case 'o_l_id':
                $value = (int) $value;
            break;
            case 'o_lat':
            case 'o_long':
                $value = \round( $value, 6 );
            break;
            case 'o_priority':
                $value = (bool) $value;
            break;
            default:
            break;
        }
        
        $return = false;
        
        if( property_exists( $this, $key ) ) {
            $old_value = $this->$key;
            
            if( $old_value != $value ){
                $this->$key = $value;
                $return = true;
            }
        }
        return $return;
    }

    public function cashBackAmount(){
        $cash_back = 0.00;
        if( 'call' == $this->o_i_status ) {
            return $cash_back;
        }
        $o_data = $this->getMeta( 'o_data' );
        if( \is_array( $o_data ) && ! empty( $o_data['cash_back'] ) ){
            $cash_back = \round( $o_data['cash_back'], 2 );
        }
        return $cash_back;
    }

    public function hasColdItem(){
        $isCold = false;
        $o_data = $this->getMeta( 'o_data' );
        if( \is_array( $o_data ) && ! empty( $o_data['cold'] ) ){
            $isCold = true;
        }
        return $isCold;
    }

    public function isPaid(){
        $isPaid = false;
        if( 'paid' === $this->o_i_status || ! in_array( $this->o_payment_method, ['cod', 'online'] ) || 'paid' === $this->getMeta( 'paymentStatus' ) ){
            $isPaid = true;
        }
        return $isPaid;
    }

    public function signedUrl( $prefix, $suffix = '', $time = '' ){
        if( ! $time ){
            $time = time() + 60 * 60;
        }
        $url = \sprintf( SITE_URL . '%s/%d/%s%s', $prefix, $this->o_id, Functions::jwtEncode( ['o_id' => $this->o_id, 'exp' => $time ] ), $suffix );

        return $url;
    }

    public function validateToken( $token ){
        $tokenDecoded = Functions::jwtDecode( $token );
        if( $tokenDecoded && !empty( $tokenDecoded['o_id'] ) && $this->o_id === $tokenDecoded['o_id']  ){
            return true;
        }
        return false;
    }

    public function isCancelable(){
        return in_array( $this->o_status, [ 'processing', 'confirmed' ] ) && in_array( $this->o_i_status, [ 'processing', 'ph_fb', 'packing' ] );
    }

    public function getMeta( $key ) {
        return Meta::get( 'order', $this->o_id, $key );
    }

    public function setMeta( $key, $value ) {
        $prev_value = $this->getMeta( $key );
        $updated = Meta::set( 'order', $this->o_id, $key, $value );
        if( $updated ){
            Functions::orderMetaUpdated( $key, $prev_value, $value, $this );
        }
        return $updated;
    }

    public function appendMeta( $key, $value ) {
        if( !$key || !$value ){
            return false;
        }
        $meta = $this->getMeta( $key );
        if( ! is_scalar( $meta ) ){
            $meta = '';
        }
        if( $meta ){
            $meta .= "\n";
        }
        $meta .= $value;

        return $this->setMeta( $key, $meta );
    }

    public function deleteMeta( $key, $value = false ) {
        return Meta::delete( 'order', $this->o_id, $key, $value );
    }

    public function insertMetas( $keyValues ) {
        return Meta::insert( 'order', $this->o_id, $keyValues );
    }

    public function addHistory( $action, $from, $to = '' ) {
        $history = new History( 'order' );
        return $history->insert( [
            'obj_id' => $this->o_id,
            'h_action' => $action,
            'h_from' => $from,
            'h_to' => $to,
        ] );
    }

    public function addTimeline( $key, $data, $after = '' ){
        $timeline = $this->getMeta( 'timeline' );
        if( ! is_array( $timeline ) ){
            $timeline = [];
        }
        if( $timeline ){
            if( ! isset( $timeline[ $key ] ) ){
                if( $after ){
                    $keys = array_keys( $timeline );
                    $index = array_search( $after, $keys );
                    $pos = false === $index ? count( $keys ) : $index + 1;

                    $timeline[ $key ] = array_merge( array_slice( $timeline, 0, $pos ), [], array_slice( $timeline, $pos ) );
                } else {
                    $timeline[ $key ] = [];
                }
            }
            $timeline[ $key ] = array_merge( $timeline[ $key ], [
                'time' => \date( 'Y-m-d H:i:s' ),
                'done' => true,
            ], $data );
        }
        return $this->setMeta( 'timeline', $timeline );
    }

    public function city(){
        $city = '';
        $s_address = $this->getMeta('s_address')?:[];
        if( is_array($s_address) && ! empty( $s_address['district'] ) ){
            $city = $s_address['district'];
        }
        return $city;
    }

    public function timeline(){
        $timeline = $this->getMeta( 'timeline' );
        if( ! is_array( $timeline ) ){
            $timeline = [];
        }
        if( $timeline ){
            $default = [
                'placed' => [
                    'time' => $this->o_created,
                    'title' => 'Order Placed',
                    'body' => 'Your order is successfully placed to Arogga. Order id #' . $this->o_id,
                    'done' => true,
                ],
                'processing' => [
                    'time' => $this->o_created,
                    'title' => 'Processing',
                    'body' => 'We have received your order, our pharmacist will check and confirm shortly.',
                    'done' => true,
                ],
                'confirmed' => [
                    'title' => 'Confirmed',
                    'body' => 'We have confirmed your order.',
                ],
                'packing' => [
                    'title' => 'Packing',
                    'body' => 'We are currently packing your order.',
                ],
                'packed' => [
                    'title' => 'Packed',
                    'body' => 'Your order is packed now.',
                ],
                'payment' => [
                    'title' => 'Payment',
                    'body' => '',
                ],
                'delivering' => [
                    'title' => 'Delivering',
                    'body' => $this->city() == 'Dhaka City' ? 'Deliveryman has picked up your order for delivering.' : 'Our delivery partner has picked up your order for delivering. it generally takes 1-5 days to deliver outside dhaka.',
                ],
                'delivered' => [
                    'title' => 'Delivered',
                    'body' => 'You have received your order.',
                ],
            ];

            if( 'cancelled' == $this->o_status ){
                $default['cancelled'] = [
                    'time' => $this->o_updated,
                    'title' => 'Cancelled',
                    'body' => (string)$this->getMeta('o_note'),
                    'done' => true,
                ];
            }
            if( 'delivering' == $this->o_status ){
                if( $this->city() == 'Dhaka City' ){
                    $deliveryman = User::getUser( $this->o_de_id );
                    $default['delivering']['body'] = sprintf('Deliveryman (%s: %s) has picked up your order for delivering.', $deliveryman ? $deliveryman->u_name : '', $deliveryman ? $deliveryman->u_mobile : '' );
                } elseif( $redx_tracking_id = $this->getMeta( 'redx_tracking_id' ) ){
                    $default['delivering']['body'] = 'Our delivery partner REDX has picked up your order for delivering.';
                    $default['delivering']['link'] = [
                        'src' => 'https://redx.com.bd/track-global-parcel/?trackingId=' . $redx_tracking_id,
                        'title' => 'Track Order'
                    ];
                }
            }
            $merged = [];
            foreach ( $default as $key => $value ) {
                if( isset( $timeline[ $key ] ) && is_array( $timeline[ $key ] ) ){
                    $merged[ $key ] = array_merge( $value, $timeline[ $key ] ); 
                } else {
                    $merged[ $key ] = $value;
                }
            }
            $timeline = $merged;
        }
        return $timeline;
    }
    
    public function insert( $data = array() ){
        if( $this->exist() ){
            return false;
        }
        if( is_array( $data ) && $data ){
            foreach( $data as $k => $v ){
                if( property_exists( $this, $k ) ) {
                    $this->set( $k, $v );
                }
            }
        }
        $data_array = $this->toArray();
        $this->o_created = $data_array['o_created'] = \date( 'Y-m-d H:i:s' );
        $this->o_updated = $data_array['o_updated'] = \date( 'Y-m-d H:i:s' );
        $data_array['o_priority'] = (int) $data_array['o_priority'];
        
        unset( $data_array['o_id'] );

        $this->o_id = DB::instance()->insert( 't_orders', $data_array );
        
        if( $this->o_id ){
            Cache::instance()->add( $this->o_id, $this, 'order' );

            Functions::orderCreated( $this );
        }

        return $this->o_id;
    }
    public function update( $data = array() ){
        if( ! $this->exist() ){
            return false;
        }
        $order = static::getOrder( $this->o_id );
        if( ! $order ) {
            return false;
        }
        if( 'delivered' == $order->o_status && $order->o_ph_id != $order->o_de_id ) {
            //return false;
        }
        
        if( is_array( $data ) && $data ){
            foreach( $data as $k => $v ){
                if( property_exists( $this, $k ) ) {
                    $this->set( $k, $v );
                }
            }
        }
        $data_array = [];
        foreach ( $this->toArray() as $key => $value) {
            if ( $order->$key != $value ) {
                $data_array[ $key ] = $value;
            }
        }
        unset( $data_array['o_id'] );

        if ( ! $data_array ) {
            return false;
        }
        $this->o_updated = $data_array['o_updated'] = \date( 'Y-m-d H:i:s' );
        if( !empty( $data_array['o_status'] ) ) {
            if( 'delivered' === $data_array['o_status'] ) {
                $this->o_delivered = $data_array['o_delivered'] = \date( 'Y-m-d H:i:s' );
            }
            if( in_array( $data_array['o_status'], [ 'delivered', 'cancelled' ] ) ){
                $this->o_priority = $data_array['o_priority'] = false;
            }
        }
        if( isset( $data_array['o_priority'] ) ){
            $data_array['o_priority'] = (int) $data_array['o_priority'];
        }
        
        $updated = DB::instance()->update( 't_orders', $data_array, [ 'o_id' => $this->o_id ] );
        
        if( $updated ){
            Cache::instance()->set( $this->o_id, $this, 'order' );
            Functions::sendOrderStatusChangeNotification( $order, $this );
            Functions::calculateCash( $order, $this );
            Functions::referralCash( $order, $this );
            Functions::sendInternalOrderStatusChangeNotification( $order, $this );

            Functions::miscOrderUpdate( $order, $this );
        }

        return $updated;
    }

    public function delete(){
        if( ! $this->exist() ){
            return false;
        }
        
        $deleted = DB::instance()->delete( 't_orders', [ 'o_id' => $this->o_id ] );
        
        if( $deleted ){
            Functions::orderDeleted( $this );
            
            DB::instance()->delete( 't_o_medicines', [ 'o_id' => $this->o_id ] );
            Meta::delete( 'order', $this->o_id );
            Cache::instance()->delete( $this->o_id, 'order' );
        }

        return $deleted;
    }

    function updateCache() {
        if( $this->o_id ) {
            Cache::instance()->set( $this->o_id, $this, 'order' );
        }
    }
}
