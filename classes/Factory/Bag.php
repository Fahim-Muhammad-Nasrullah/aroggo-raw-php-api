<?php

namespace OA\Factory;
use OA\{DB, Cache, Functions};

/**
 * 
 */
class Bag {
	
    private $b_id = 0;
    private $b_ph_id = 0;
    private $b_zone = '';
    private $b_no = 0;
    private $b_de_id = 0;
    private $o_count = 0;
    private $o_ids = '';
    
    function __construct( $id = 0 )
    {
        if( $id instanceof self ){
            foreach( $id->toArray() as $k => $v ){
                $this->$k = $v;
            }
        } elseif( is_numeric( $id ) && $id && $bag = static::getBag( $id ) ){
            foreach( $bag->toArray() as $k => $v ){
                $this->set( $k, $v );
            }
        }
    }
    
    public static function getBag( $id ) {
        if( !$id || !is_numeric( $id ) ){
            return false;
        }
        if ( $bag = Cache::instance()->get( $id, 'bag' ) ){
            return $bag;
        }
        $query = DB::db()->prepare( "SELECT * FROM t_bags WHERE b_id = ? LIMIT 1" );
        $query->execute( [ $id ] );
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Bag');
        if( $bag = $query->fetch() ){
            Cache::instance()->set( $bag->b_id, $bag, 'bag' );
            return $bag;
        } else {
            return false;
        }
    }

    public static function getCurrentBag( $ph_id, $zone ){
        if( !$ph_id || !$zone ) {
            return false;
        }
        $cached_id = Cache::instance()->get( "{$ph_id}_{$zone}", 'currentBag' );
        if( $cached_id && ( $cached = static::getBag( $cached_id ) ) && !$cached->b_de_id && $cached->o_count < 35 ){
            return $cached;
        }
        $query = DB::db()->prepare( "SELECT * FROM t_bags WHERE b_ph_id = ? AND b_zone = ? AND b_de_id = ? AND o_count < ? ORDER BY o_count DESC LIMIT 1" );
        $query->execute( [ $ph_id, $zone, 0, 35 ] );
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Bag');
        if( $bag = $query->fetch() ){
            Cache::instance()->set( $bag->b_id, $bag, 'bag' );
            Cache::instance()->set( "{$ph_id}_{$zone}", $bag->b_id, 'currentBag' );
            return $bag;
        } else {
            return false;
        }
    }

    public static function deliveryBag( $ph_id, $de_id ){
        if( !$ph_id || !$de_id ) {
            return [];
        }

        $query = DB::db()->prepare( "SELECT * FROM t_bags WHERE b_ph_id = ? AND b_de_id = ? LIMIT 1" );
        $query->execute( [ $ph_id, $de_id ] );
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Bag');
        return $query->fetch();
    }

    public function bagUndeliveredIds(){
        if( !$this->o_count ) {
            return [];
        }

        $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', $this->get('o_ids') ) ) );
        $in  = str_repeat('?,', count($ids) - 1) . '?';

        $query = DB::db()->prepare( "SELECT o_id FROM t_orders WHERE ( o_status IN (?,?,?) OR o_is_status IN (?,?,?) ) AND o_id IN ($in)" );
        $query->execute( [ 'checking', 'confirmed', 'delivering', 'checking', 'packed', 'delivering', ...$ids ] );
        $o_ids = $query->fetchAll( \PDO::FETCH_COLUMN );

        return $o_ids;
    }

    public function release(){
        $data = [
            'b_de_id' => 0,
            'o_count' => 0,
            'o_ids' => ''
        ];
        return $this->update( $data );
    }
    
    public function toArray(){
        $array = [];
        foreach ( \array_keys( \get_object_vars( $this ) ) as $key ) {
            $array[ $key ] = $this->get( $key );
        };
        return $array;
    }
    
    public function exist(){
		return ! empty( $this->b_id );
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
        switch ( $key ) {
            case 'b_id':
            case 'b_ph_id':
            case 'b_no':
            case 'b_de_id':
            case 'o_count':
                $value = (int) $value;
            break;
            case 'o_ids':
                $value = Functions::maybeJsonDecode( $value );
                if( !is_array( $value ) ){
                    $value = [];
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
            case 'b_id':
                return false;
            break;
            case 'b_ph_id':
            case 'b_no':
            case 'b_de_id':
            case 'o_count':
                $value = (int) $value;
            break;
            case 'o_ids':
                $value = Functions::maybeJsonEncode( $value );
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
        
        unset( $data_array['b_id'] );

        $this->b_id = DB::instance()->insert( 't_bags', $data_array );

        return $this->b_id;
    }
    public function update( $data = array() ){
        if( ! $this->exist() ){
            return false;
        }
        $bag = static::getBag( $this->b_id );
        if( ! $bag ) {
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
            if ( $bag->$key != $value ) {
                $data_array[ $key ] = $value;
            }
        }
        
        unset( $data_array['b_id'] );
        if ( ! $data_array ) {
            return false;
        }
        
        $updated = DB::instance()->update( 't_bags', $data_array, [ 'b_id' => $this->b_id ] );
        
        if( $updated ){
            Cache::instance()->set( $this->b_id, $this, 'bag' );
        }

        return $updated;
    }
    public function delete(){
        if( ! $this->exist() ){
            return false;
        }
        
        $deleted = DB::instance()->delete( 't_bags', [ 'b_id' => $this->b_id ] );
        
        if( $deleted ){
            Cache::instance()->delete( $this->b_id, 'bag' );
        }

        return $deleted;
    }

    public function addOrder( $o_id ){
        $o_ids = $this->get( 'o_ids' );
        if( ! is_array( $o_ids ) ){
            $o_ids = [];
        }
        $o_ids[] = $o_id;
        $o_ids = array_values( array_filter( array_unique( $o_ids ) ) );
        $data = [
            'o_ids' => $o_ids,
            'o_count' => count( $o_ids ),
        ];
        return $this->update( $data );
    }

    public function removeOrder( $o_id ){
        $o_ids = $this->get( 'o_ids' );
        if( ! is_array( $o_ids ) ){
            $o_ids = [];
        }
        if ( ( $key = array_search( $o_id, $o_ids ) ) !== false ) {
            unset( $o_ids[ $key ] );
        }
        $o_ids = array_values( array_filter( array_unique( $o_ids ) ) );
        $data = [
            'o_ids' => $o_ids,
            'o_count' => count( $o_ids ),
        ];
        return $this->update( $data );
    }

    public function fullZone(){
        return sprintf( '%s-%d', $this->b_zone, $this->b_no );
    }

}
