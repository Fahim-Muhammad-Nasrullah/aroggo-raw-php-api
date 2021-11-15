<?php

namespace OA\Factory;
use OA\{DB, Cache, Functions};

/**
 * 
 */
class Company {
	
	private $c_id = 0;
    private $c_name = '';
    
    function __construct( $id = 0 )
    {
        if( $id instanceof self ){
            foreach( $id->toArray() as $k => $v ){
                $this->$k = $v;
            }
        } elseif( is_numeric( $id ) && $id && ( $company = static::getcompany( $id ) ) ){
            foreach( $company->toArray() as $k => $v ){
                $this->$k = $v;
            }
        } elseif( is_array( $id ) && $id ){
            foreach( $id as $k => $v ){
                $this->$k = $v;
            }
        }
    }
    
    public static function getCompany( $id ) {
        if ( ! \is_numeric( $id ) ){
            return false;
        }
        $id = \intval( $id );
        if ( $id < 1 ){
            return false;
        }

        if ( $company = Cache::instance()->get( $id, 'company' ) ){
            return $company;
        }
        
        $query = DB::db()->prepare( 'SELECT * FROM t_companies WHERE c_id = ? LIMIT 1' );
        $query->execute( [ $id ] );
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Company');
        if( $company = $query->fetch() ){     
            Cache::instance()->add( $company->c_id, $company, 'company' );
            return $company;
        } else {
            return false;
        }
    }

    public static function getName( $id ) {
        $company = static::getCompany( $id );
        if( $company ) {
            return $company->c_name;
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
		return ! empty( $this->c_id );
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
            case 'c_id':
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
            case 'c_id':
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
        
        unset( $data_array['c_id'] );

        $this->c_id = DB::instance()->insert( 't_companies', $data_array );

        if( $this->c_id ){
            Cache::instance()->add( $this->c_id, $this, 'company' );
        }
        return $this->c_id;
    }
    public function update( $data = array() ){
        if( ! $this->exist() ){
            return false;
        }

        $company = static::getcompany( $this->c_id );
        if( ! $company ) {
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
            if ( $company->$key != $value ) {
                $data_array[ $key ] = $value;
            }
        }
        
        unset( $data_array['c_id'] );
        if ( ! $data_array ) {
            return false;
        }
        
        $updated = DB::instance()->update( 't_companies', $data_array, [ 'c_id' => $this->c_id ] );
        
        if( $updated ){
            Cache::instance()->set( $this->c_id, $this, 'company' );
        }

        return $updated;
    }

    public function delete(){
        if( ! $this->exist() ){
            return false;
        }
        
        $deleted = DB::instance()->delete( 't_companies', [ 'c_id' => $this->c_id ] );
        
        if( $deleted ){
            Cache::instance()->delete( $this->c_id, 'company' );
        }

        return $deleted;
    }

    function updateCache() {
        if( $this->c_id ) {
            Cache::instance()->set( $this->c_id, $this, 'company' );
        }
    }
}
