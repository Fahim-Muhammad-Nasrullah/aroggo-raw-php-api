<?php

namespace OA\Factory;
use OA\{DB, Cache, Functions};

/**
 * 
 */
class Option {
    
    function __construct()
    {
    }
    
    public function __get( $key ){
		return $this->get( $key );
	}
	
	public static function get( $key ){
        $found = false;
        $value = Cache::instance()->get( $key, 'option', false, $found );
        if ( $found ){
            return $value;
        }
        $query = DB::db()->prepare( "SELECT * FROM t_options WHERE option_name = ? LIMIT 1" );
        $query->execute( [ $key ] );
        if( $option = $query->fetch() ){
            $value = Functions::maybeJsonDecode( $option['option_value'] );
            Cache::instance()->set( $key, $value, 'option' );
            return $value;
        } else {
            Cache::instance()->set( $key, false, 'option' );
            return false;
        }
	}
    public function __set( $key, $value ){
		return $this->set( $key, $value );
	}
    public static function set( $key, $value ){
        $prevValue = static::get( $key );
        $value = false === $value ? '' : $value;

        if( false !== $prevValue ){
            if( $prevValue != $value ){
                $updated = DB::instance()->update( 't_options', [ 'option_value' => Functions::maybeJsonEncode( $value ) ], [ 'option_name' => $key ] );
                if( $updated ) {
                    Cache::instance()->set( $key, $value, 'option' );
                    return true;
                }
            }
        } else {
            $inserted = DB::instance()->insert( 't_options', [ 'option_name' => $key, 'option_value' => Functions::maybeJsonEncode( $value ) ], true );
            if( $inserted ) {
                Cache::instance()->set( $key, $value, 'option' );
                return true;
            }
        }
        return false;
    }

    public static function delete( $key ) {
        $deleted = DB::instance()->delete( 't_options', [ 'option_name' => $key ] );
        
        if( $deleted ){
            Cache::instance()->delete( $key, 'option' );
        }

        return $deleted;
    }
}
