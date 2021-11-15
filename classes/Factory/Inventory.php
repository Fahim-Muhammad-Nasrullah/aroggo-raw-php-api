<?php

namespace OA\Factory;
use OA\{DB, Cache, Functions};

/**
 * 
 */
class Inventory {
	
    private $i_id = 0;
    private $i_ph_id = 0;
    private $i_m_id = 0;
    private $i_price = 0.0000;
    private $i_qty = 0;
    
    function __construct( $id = 0 )
    {
        if( $id instanceof self ){
            foreach( $id->toArray() as $k => $v ){
                $this->$k = $v;
            }
        } elseif( is_numeric( $id ) && $id && $inventory = static::getInventory( $id ) ){
            foreach( $inventory->toArray() as $k => $v ){
                $this->$k = $v;
            }
        }
    }
    
    public static function getInventory( $id ) {
        return static::getBy( 'i_id', $id );
    }

    public static function getByPhMid( $ph_id, $m_id ) {
        return static::getBy( 'ph_m_id', $ph_id, $m_id );
    }
    public static function qtyUpdateByPhMid( $ph_id, $m_id, $qty ) {
        $inv = static::getBy( 'ph_m_id', $ph_id, $m_id );
        if( $inv ){
            return $inv->qtyUpdate( $qty );
        }
        return false;
    }

    public static function getBy( $field, $iid_or_phid, $m_id = 0 ) {
        $value = $iid_or_phid;

    	if ( 'i_id' == $field ) {
    		// Make sure the value is numeric to avoid casting objects, for example,
    		// to int 1.
    		if ( ! is_numeric( $value ) )
    			return false;
    		$value = intval( $value );
    		if ( $value < 1 )
    			return false;
    	} else {
    		$value = trim( $value );
    	}

    	if ( !$value )
    		return false;

    	switch ( $field ) {
    		case 'i_id':
    			$id = $value;
                break;
            case 'ph_m_id':
    			$id = Cache::instance()->get( "{$value}_{$m_id}", 'ph_m_id_to_iid' );
    			break;
    		default:
    			return false;
    	}

    	if ( false !== $id ) {
    		if ( $inventory = Cache::instance()->get( $id, 'inventory' ) ){
                return $inventory;
            }
        }
        if ( 'i_id' == $field ) {
            $query = DB::db()->prepare( "SELECT * FROM t_inventory WHERE i_id = ? LIMIT 1" );
            $query->execute( [ $value ] );
        } else {
            $query = DB::db()->prepare( "SELECT * FROM t_inventory WHERE i_ph_id = ? AND i_m_id = ? LIMIT 1" );
            $query->execute( [ $value, $m_id ] );
        }
        
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Inventory');
        if( $inventory = $query->fetch() ){
            $inventory->updateCache();
            return $inventory;
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
		return ! empty( $this->i_id );
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
            case 'i_id':
            case 'i_ph_id':
            case 'i_m_id':
            case 'i_qty':
                $value = (int) $value;
            break;
            case 'i_price':
                $value = \round( $value, 4 );
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
            case 'i_id':
                return false;
            break;
            case 'i_ph_id':
            case 'i_m_id':
            case 'i_qty':
                $value = (int) $value;
            break;
            case 'i_price':
                if( \round( $this->i_price, 2 ) === \round( $value, 2 ) ){
                    $value = $this->i_price;
                } else {
                    $value = \round( $value, 4 );
                }
            break;
            default:
            break;
        }
        
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
    
    public function getMeta( $key ) {
        return Meta::get( 'inventory', $this->i_id, $key );
    }

    public function setMeta( $key, $value ) {
        return Meta::set( 'inventory', $this->i_id, $key, $value );
    }

    public function deleteMeta( $key, $value = false ) {
        return Meta::delete( 'inventory', $this->i_id, $key, $value );
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
        
        unset( $data_array['i_id'] );

        $this->i_id = DB::instance()->insert( 't_inventory', $data_array );
        
        if( $this->i_id ){
            $this->updateCache();
        }

        return $this->i_id;
    }
    public function update( $data = array() ){
        if( ! $this->exist() ){
            return false;
        }
        $inventory = static::getInventory( $this->i_id );
        if( ! $inventory ) {
            return false;
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
            if ( $inventory->$key != $value ) {
                $data_array[ $key ] = $value;
            }
        }
        
        unset( $data_array['i_id'] );
        if ( ! $data_array ) {
            return false;
        }
        
        $updated = DB::instance()->update( 't_inventory', $data_array, [ 'i_id' => $this->i_id ] );
        
        if( $updated ){
            $this->updateCache();
        }

        return $updated;
    }
    public function delete(){
        if( ! $this->exist() ){
            return false;
        }
        
        $deleted = DB::instance()->delete( 't_inventory', [ 'i_id' => $this->i_id ] );
        
        if( $deleted ){
            Cache::instance()->delete( $this->i_id, 'inventory' );
        }

        return $deleted;
    }

    public function qtyUpdate( $qty ){
        return $this->update( [ 'i_qty' => $this->get( 'i_qty' ) + $qty ] );
    }

    function updateCache() {
        if( $this->i_id ) {
            Cache::instance()->set( $this->i_id, $this, 'inventory' );
            Cache::instance()->set( "{$this->i_ph_id}_{$this->i_m_id}", $this->i_id, 'ph_m_id_to_iid' );
        }
    }
}
