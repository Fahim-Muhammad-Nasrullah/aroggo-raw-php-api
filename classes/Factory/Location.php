<?php

namespace OA\Factory;
use OA\{DB, Cache, Auth, Functions};

/**
 * 
 */
class Location {
	
	private $l_id = 0;
	private $l_division = '';
	private $l_district = '';
	private $l_area = '';
	private $l_postcode = '';
	private $l_de_id = 0;
	private $l_ph_id = 0;
	private $l_zone = '';
	private $l_redx_area_id = 0;
	private $l_status = 1;

	public static $instance;
	
	function __construct( $l_id = 0 ){
		if( $l_id instanceof self ){
			foreach( $l_id->toArray() as $k => $v ){
				$this->$k = $v;
			}
		} elseif( is_numeric( $l_id ) && $l_id && $location = static::getLocation( $l_id ) ){
			foreach( $location->toArray() as $k => $v ){
				$this->$k = $v;
			}
		}
	}

	public static function instance() {
		if( ! self::$instance instanceof self ) {
			self::$instance = new self;
		}
		return self::$instance;
	}
	
    public static function getLocation( $l_id ) {
        return static::getBy( 'l_id', $l_id );
    }

    public static function getByPostcode( $l_postcode ) {
        return static::getBy( 'l_postcode', $l_postcode );
    }

    public static function getByDivDistArea( $division, $district, $area ) {
        return static::getBy( 'div_dist_area', $division, $district, $area );
    }

    public static function getBy( $field, $id_or_postcode_or_division, $district = '', $area = '' ) {
        $value = $id_or_postcode_or_division;

        if ( in_array( $field, [ 'l_id', 'l_postcode' ] ) ) {
            // Make sure the value is numeric to avoid casting objects, for example,
            // to int 1.
            if ( ! is_numeric( $value ) ){
                return false;
            }
            $value = intval( $value );
            if ( $value < 1 ){
                return false;
            }
        } else {
            $value = trim( $value );
        }

        if ( !$value ){
            return false;
        }

        if ( $field == 'div_dist_area' && ( !$district || !$area ) ){
            return false;
        }
        switch ( $field ) {
            case 'l_id':
                $id = $value;
                break;
            case 'l_postcode':
                $id = Cache::instance()->get( $value, 'postcode_to_lid' );
                break;
            case 'div_dist_area':
                $id = Cache::instance()->get( $value.'-'.$district.'-'.$area, 'div_dist_area_to_lid' );
                break;
            default:
                return false;
        }

        if ( false !== $id ) {
            if ( $location = Cache::instance()->get( $id, 'location' ) ){
                return $location;
            }
        }
        if ( in_array( $field, [ 'l_id', 'l_postcode' ] ) ) {
            $query = DB::db()->prepare( "SELECT * FROM t_locations WHERE {$field} = ? LIMIT 1" );
            $query->execute( [ $value ] );
        } else {
            $query = DB::db()->prepare( "SELECT * FROM t_locations WHERE l_division = ? AND l_district = ? AND l_area = ? LIMIT 1" );
            $query->execute( [ $value, $district, $area ] );
        }

        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Location');
        if( $location = $query->fetch() ){
            $location->updateCache();
            return $location;
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
		return ! empty( $this->l_id );
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
		unset( $data_array['l_id'] );

		$this->l_id = DB::instance()->insert( 't_locations', $data_array );

		if( $this->l_id ){
			Cache::instance()->add( $this->l_id, $this, 'location' );
		}

		return $this->l_id;
	}

	public function update( $data = array() ){
		if( ! $this->exist() ){
			return false;
		}
		$location = static::getLocation( $this->l_id );
		if( ! $location ) {
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
			if ( $location->$key != $value ) {
				$data_array[ $key ] = $value;
			}
		}

		unset( $data_array['l_id'] );
		if ( ! $data_array ) {
			return false;
		}

		$updated = DB::instance()->update( 't_locations', $data_array, [ 'l_id' => $this->l_id ] );

		if( $updated ){
			Cache::instance()->set( $this->l_id, $this, 'location' );
		}

		return $updated;
	}

	public function delete(){
		if( ! $this->exist() ){
			return false;
		}

		$deleted = DB::instance()->delete( 't_locations', [ 'l_id' => $this->l_id ] );

		if( $deleted ){
			Cache::instance()->delete( $this->l_id, 'location' );
		}

		return $deleted;
	}

    public static function getValueByLocationId( $l_id, $value ){
        if( !$l_id || !$value){
            return false;
        }
        $location = static::getLocation( $l_id );
        if ( $location ){
            switch ( $value ) {
                case 'district':
                    return $location->l_district;
                    break;
                case 'zone':
                    return $location->l_zone;
                    break;
            }
        }
        return false;
    }

    function updateCache() {
        if( $this->l_id ) {
            Cache::instance()->set( $this->l_id, $this, 'location' );
            Cache::instance()->set( $this->l_postcode, $this->l_id, 'postcode_to_lid' );
            Cache::instance()->set( "{$this->l_division}-{$this->l_district}-{$this->l_area}", $this->l_id, 'div_dist_area_to_lid' );
        }
    }
}
