<?php

namespace OA\Factory;
use OA\{DB, Cache, Functions};

/**
 * 
 */
class Generic {
	
	private $g_id = 0;
    private $g_name = '';
    private $g_overview = '';
    private $g_quick_tips = '';
    private $g_safety_advices = '';
    private $g_question_answer = '';
    private $g_brief_description = '';

    function __construct( $id = 0 )
    {
        if( $id instanceof self ){
            foreach( $id->toArray() as $k => $v ){
                $this->$k = $v;
            }
        } elseif( is_numeric( $id ) && $id && ( $generic = static::getGeneric( $id ) ) ){
            foreach( $generic->toArray() as $k => $v ){
                $this->$k = $v;
            }
        } elseif( is_array( $id ) && $id ){
            foreach( $id as $k => $v ){
                $this->$k = $v;
            }
        }
    }
    
    public static function getGeneric( $id ) {
        if ( ! \is_numeric( $id ) ){
            return false;
        }
        $id = \intval( $id );
        if ( $id < 1 ){
            return false;
        }

        if ( $generic = Cache::instance()->get( $id, 'generic' ) ){
            return $generic;
        }
        
        $query = DB::db()->prepare( 'SELECT * FROM t_generics_v2 WHERE g_id = ? LIMIT 1' );
        $query->execute( [ $id ] );
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Generic');
        if( $generic = $query->fetch() ){     
            Cache::instance()->add( $generic->g_id, $generic, 'generic' );
            return $generic;
        } else {
            return false;
        }
    }

    public static function getName( $id ) {
        $generic = static::getGeneric( $id );
        if( $generic ) {
            return $generic->g_name;
        }
        return '';
    }
    
    public function toArray(){
        $array = [];
        foreach ( \array_keys( \get_object_vars( $this ) ) as $key ) {
            $array[ $key ] = $this->get( $key );
        };
        return $array;
    }
    
    public function exist(){
		return ! empty( $this->g_id );
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
            case 'g_id':
                $value = (int) $value;
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
            case 'g_id':
                return false;
            break;
            default:
                $value = (string) $value;
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
        
        unset( $data_array['g_id'] );

        $this->g_id = DB::instance()->insert( 't_generics_v2', $data_array );

        if( $this->g_id ){
            Cache::instance()->add( $this->g_id, $this, 'generic' );
        }
        return $this->g_id;
    }
    public function update( $data = array() ){
        if( ! $this->exist() ){
            return false;
        }

        $generic = static::getGeneric( $this->g_id );
        if( ! $generic ) {
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
            if ( $generic->$key != $value ) {
                $data_array[ $key ] = $value;
            }
        }
        
        unset( $data_array['g_id'] );
        if ( ! $data_array ) {
            return false;
        }
        
        $updated = DB::instance()->update( 't_generics_v2', $data_array, [ 'g_id' => $this->g_id ] );
        
        if( $updated ){
            Cache::instance()->set( $this->g_id, $this, 'generic' );
        }

        return $updated;
    }

    public function delete(){
        if( ! $this->exist() ){
            return false;
        }
        
        $deleted = DB::instance()->delete( 't_generics_v2', [ 'g_id' => $this->g_id ] );
        
        if( $deleted ){
            Cache::instance()->delete( $this->g_id, 'generic' );
        }

        return $deleted;
    }

    function updateCache() {
        if( $this->g_id ) {
            Cache::instance()->set( $this->g_id, $this, 'generic' );
        }
    }
}
