<?php

namespace OA\Factory;
use OA\Cache;
use OA\DB;

/**
 * 
 */
class Discount {
    
    private $d_id = 0;
    private $d_code = '';
    private $d_type = '';
    private $d_amount = 0;
    private $d_max = 0;
    private $d_max_use = 1; //per user
    private $d_status = 'pending';
    private $d_expiry = '0000-00-00 00:00:00';
    private $u_id = 0;
    
    function __construct( $d_code = '' )
    {
        if( $d_code instanceof self ){
            foreach( $d_code->toArray() as $k => $v ){
                $this->$k = $v;
            }
        } elseif( $d_code && $discount = static::getDiscount( $d_code ) ){
            foreach( $discount->toArray() as $k => $v ){
                $this->$k = $v;
            }
        }
    }
    
    public static function getDiscount( $d_code ) {
        if ( ! $d_code ){
            return false;
        }
        $d_code = strtolower( $d_code );

        if ( $discount = Cache::instance()->get( $d_code, 'discount' ) ){
            return $discount;
        }
        
        $query = DB::db()->prepare( 'SELECT * FROM t_discounts WHERE d_code = ? LIMIT 1' );
        $query->execute( [ $d_code ] );
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Discount');
        if( $discount = $query->fetch() ){     
            Cache::instance()->add( strtolower( $discount->d_code ), $discount, 'discount' );
            return $discount;
        } else {
            return false;
        }
    }

    public function canUserUse( $u_id ) {
        $u_id = (int) $u_id;
        if ( ! $u_id || ! $this->exist() || 'active' !== $this->d_status ) {
            return false;
        }
        //Local time
        if ( $this->d_expiry !== '0000-00-00 00:00:00' && \strtotime(\date( 'Y-m-d H:i:s' )) > \strtotime( $this->d_expiry )  ) {
            $this->d_status = 'expired';
            $this->update();
            return false;
        }
        $allow = 0;
        if( \defined( 'ADMIN' ) && ADMIN ) {
            $allow++;
        }
        if( \in_array( $this->d_type, [ 'firstPercent', 'firstFixed' ] ) ) {
            $query = DB::db()->prepare( 'SELECT o_id FROM t_orders WHERE u_id = ? LIMIT 2' );
            $query->execute( [ $u_id ] );
        } else {
            $query = DB::db()->prepare( 'SELECT tr.o_id FROM t_orders tr INNER JOIN t_order_meta tom ON tr.o_id = tom.o_id WHERE tr.u_id = ? AND tom.meta_key = ? AND tom.meta_value = ? LIMIT 2' );
            $query->execute( [ $u_id, 'd_code', $this->d_code ] );
        }
 
        if ( \count( $query->fetchAll() ) > $allow ) {
            return false;
        }
        return true;
    }
    
    public function toArray(){
        $array = [];
        foreach ( \array_keys( \get_object_vars( $this ) ) as $key ) {
            $array[ $key ] = $this->get( $key );
        };
        return $array;
    }
    
    public function exist(){
		return ! empty( $this->d_id );
	}
    
    public function __get( $key ){
		return $this->get( $key );
	}
	
	public function get( $key ){
		if( property_exists( $this, $key ) ) {
			$value = $this->$key;
		} else {
			$value = false;
        }
		return $value;
	}
    public function __set( $key, $value ){
		return $this->set( $key, $value );
	}
    public function set( $key, $value ){        
        $return = false;
        
        if( property_exists( $this, $key ) ) {
            $old_value = $this->$key;
            
            if( $old_value !== $value ){
                $this->$key = $value;
                $return = true;
            }
        }
        return $return;
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
        unset( $data_array['d_id'] );

        DB::instance()->insert( 't_discounts', $data_array );
        
        if( $this->d_code ){
            Cache::instance()->add( strtolower( $this->d_code ), $this, 'discount' );
        }

        return $this->d_code;
    }
    public function update( $data = array() ){
        if( ! $this->exist() ){
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
        
        $updated = DB::instance()->update( 't_discounts', $data_array, [ 'd_code' => $this->d_code ] );
        
        if( $updated ){
            Cache::instance()->set( strtolower( $this->d_code ), $this, 'discount' );
        }

        return $updated;
    }
}
