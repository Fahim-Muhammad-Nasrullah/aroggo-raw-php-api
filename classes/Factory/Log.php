<?php

namespace OA\Factory;
use OA\{DB, Cache, Auth, Functions};

/**
 * 
 */
class Log {
    
    private $log_id = 0;
    private $u_id = 0;
    private $log_ip = '';
    private $log_ua = '';
    private $log_created = '0000-00-00 00:00:00';
    private $log_http_method = '';
    private $log_uri = '';
    private $log_get = 0;
    private $log_post = 0;
    private $log_response_code = 0;
    private $log_response = '';

    public static $instance;
    
    function __construct( $log_id = 0 )
    {
        if( $log_id instanceof self ){
            foreach( $log_id->toArray() as $k => $v ){
                $this->set( $k, $v );
            }
        } elseif( $log_id && $log = static::getLog( $log_id ) ){
            foreach( $log->toArray() as $k => $v ){
                $this->set( $k, $v );
            }
        } else {
            $this->set( 'u_id', Auth::id() );
            $this->set( 'log_ip', $_SERVER['REMOTE_ADDR'] );
            $this->set( 'log_ua', isset( $_SERVER['HTTP_USER_AGENT'] ) ?  filter_var( $_SERVER['HTTP_USER_AGENT'], FILTER_SANITIZE_STRING ) : '' );
            $this->set( 'log_created', \date( 'Y-m-d H:i:s' ) );
            $this->set( 'log_http_method', $_SERVER['REQUEST_METHOD'] );
            $this->set( 'log_get', is_array( $_GET ) ? $_GET : [] );
            $this->set( 'log_post', is_array( $_POST ) ? $_POST : [] );
        }
    }

    public static function instance() {
		if( ! self::$instance instanceof self ) {
			self::$instance = new self;
		}
		return self::$instance;
    }
    
    public static function getLog( $log_id ) {
        if ( ! $log_id ){
            return false;
        }

        if ( $log = Cache::instance()->get( $log_id, 'log' ) ){
            return $log;
        }
        
        $query = DB::db()->prepare( 'SELECT * FROM t_logs WHERE log_id = ? LIMIT 1' );
        $query->execute( [ $log_id ] );
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Log');
        if( $log = $query->fetch() ){     
            Cache::instance()->add( $log->log_id, $log, 'log' );
            return $log;
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
		return ! empty( $this->log_id );
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
            case 'log_get':
            case 'log_post':
            case 'log_response':
                //FIX-ME:
                //do not json decode as this will conflict when insert call
                //$value = Functions::maybeJsonDecode( $value );
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
        $return = false;
        
        if( property_exists( $this, $key ) ) {
            $old_value = $this->$key;

            switch ( $key ) {
                case 'log_get':
                case 'log_post':
                case 'log_response':
                    if( $value && is_array( $value ) ){
                        if( !empty( $value['api_key'] ) ){
                            $value['api_key'] = 'xxxxx';
                        }
                        if( !empty( $value['cron_key'] ) ){
                            $value['cron_key'] = 'xxxxx';
                        }
                        if( !empty( $value['attachedFiles'] ) ){
                            $value['attachedFiles'] = count( $value['attachedFiles'] );
                        }
                        if( isset( $value['data'] ) && isset( $value['data']['user'] ) && ! empty( $value['data']['user']['authToken'] ) ){
                            $value['data']['user']['authToken'] = 'xxxxx';
                        }
                    }
                    $value = Functions::maybeJsonEncode( $value );
                break;
                default:
                    break;
            }
            
            if( $old_value !== $value && is_scalar( $value ) && strlen( $value ) < 1024 * 1024 ){
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

        if( 'POST' === $this->log_http_method ) {

        } elseif( strpos( $this->log_uri, '/admin/' ) === 0 && '/admin/v1/laterMedicines/' != $this->log_uri && fnmatch( '/admin/v1/*/', $this->log_uri, FNM_PATHNAME ) ){
            return false;
        }
        $data_array = $this->toArray();
        unset( $data_array['log_id'] );

        $this->log_id = DB::instance()->insert( 't_logs', $data_array );
        
        if( $this->log_id ){
            //No need to cache, Its the log
            //Cache::instance()->add( $this->log_id, $this, 'log' );
        }

        return $this->log_id;
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
        
        $updated = DB::instance()->update( 't_logs', $data_array, [ 'log_id' => $this->log_id ] );
        
        if( $updated ){
            Cache::instance()->set( $this->log_id, $this, 'log' );
        }

        return $updated;
    }
}
