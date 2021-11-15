<?php

namespace OA\Factory;
use OA\{DB, Cache, Auth, Functions};

/**
 * 
 */
class History {
	
	private $h_id = 0;
	private $h_obj = '';
	private $obj_id = 0;
	private $u_id = 0;
	private $h_created = '0000-00-00 00:00:00';
	private $h_action = '';
	private $h_from = '';
	private $h_to = '';

	public static $instance;
	
	function __construct( $h_obj = '' ){
		$this->set( 'u_id', Auth::id() );
		$this->set( 'h_created', \date( 'Y-m-d H:i:s' ) );
		if( $h_obj ){
			$this->set( 'h_obj', $h_obj );
		}
	}

	public static function instance() {
		if( ! self::$instance instanceof self ) {
			self::$instance = new self;
		}
		return self::$instance;
	}
	
	public static function getHistories( $obj_id, $limit = 100 ) {
		if ( ! $obj_id ){
			return [];
		}
		
		$query = DB::db()->prepare( 'SELECT * FROM t_histories WHERE obj_id = ? ORDER BY h_id DESC LIMIT ?' );
		$query->execute( [ $obj_id, $limit ] );

		if( $histories = $query->fetchAll() ){
			return $histories;
		} else {
			return [];
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
		return ! empty( $this->h_id );
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
			$value = Functions::maybeJsonEncode( $value );

			if( $old_value !== $value && is_scalar( $value ) ){
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
		if( !$this->h_obj || !$this->obj_id ){
			return false;
		}

		$data_array = $this->toArray();
		unset( $data_array['h_id'] );

		$this->h_id = DB::instance()->insert( 't_histories', $data_array );
		
		if( $this->h_id ){
			//No need to cache, Its the log
			//Cache::instance()->add( $this->h_id, $this, 'history' );
		}

		return $this->h_id;
	}
}
