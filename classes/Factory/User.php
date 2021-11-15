<?php

namespace OA\Factory;
use OA\{DB, Cache, Functions};
use Firebase\JWT\JWT;

/**
 * 
 */
class User {
	
    private $u_id = 0;
    private $u_name = '';
    private $u_mobile = '';
    private $u_email;
    private $u_token = '';
    private $fcm_token = '';
    private $u_lat = 0.0;
    private $u_long = 0.0;
    private $u_created = '0000-00-00 00:00:00';
    private $u_updated = '0000-00-00 00:00:00';
    private $u_role = 'user';
    private $u_status = 'active';
    private $u_cash = 0.00;
    private $u_p_cash = 0.00;
    private $u_otp = 0;
    private $u_otp_time = '0000-00-00 00:00:00';

    private $u_referrer = '';
    private $u_r_uid = 0;
    private $u_o_count = 0;
    
    function __construct( $id = 0 )
    {
        if( $id instanceof self ){
            foreach( $id->toArray() as $k => $v ){
                $this->$k = $v;
            }
        } elseif( is_numeric( $id ) && $id && $user = static::getUser( $id ) ){
            foreach( $user->toArray() as $k => $v ){
                $this->$k = $v;
            }
        }
    }
    
    public static function getUser( $id ) {
        return static::getBy( 'u_id', $id );
    }

    public static function getName( $id ) {
        $user = static::getBy( 'u_id', $id );
        if( $user ) {
            return $user->u_name;
        }
        return '';
    }

    public static function getBy( $field, $value ) {

    	if ( 'u_id' == $field ) {
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
    		case 'u_id':
    			$id = $value;
                break;
            case 'u_mobile':
    			$id = Cache::instance()->get( $value, 'u_mobile_to_id' );
    			break;
    		case 'u_email':
    			$id = Cache::instance()->get( $value, 'u_email_to_id' );
                break;
            case 'u_referrer':
                $id = Cache::instance()->get( $value, 'u_referrer_to_id' );
                break;
    		default:
    			return false;
    	}

    	if ( false !== $id ) {
    		if ( $user = Cache::instance()->get( $id, 'user' ) )
    			return $user;
        }
        
        $query = DB::db()->prepare( "SELECT * FROM t_users WHERE {$field} = ? LIMIT 1" );
        $query->execute( [ $value ] );
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\User');
        if( $user = $query->fetch() ){
            $user->updateCache();
            return $user;
        } else {
            return false;
        }
    }

    public static function getByAuthToken( $authToken ) {
        if ( ! $authToken ){
            return false;
        }
        try {
            $payload = JWT::decode( $authToken, JWT_KEY, array('HS256'));
            $u_id = isset($payload->u_id) ? (int)$payload->u_id : 0;
            $token = isset($payload->token) ? $payload->token : '';
            $exp = isset($payload->exp) ? $payload->exp : '';
            $user = static::getBy( 'u_id', $u_id );
            if ( !$user ) {
                return false;
            }
            if ( $token != $user->u_token ) {
                return false;
            }
            /*
            $auth_tokens = $user->getMeta( 'auth_tokens');
            if( is_array( $auth_tokens ) && $exp && ! isset( $auth_tokens[ $exp ] ) ) {
                return false;
            }
            */
            return $user;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function authToken() {
        $exp = time() + (60 * 60 * 24 * 365);
        $payload = array(
            'exp' => $exp,
            'u_id' => $this->u_id,
        );
        if ( ! $this->u_token ) {
            $this->u_token = \bin2hex(\random_bytes(6));
            $this->update();
        }
        $payload['token'] = $this->u_token;

        $jwt = JWT::encode($payload, JWT_KEY);

        $auth_tokens = $this->getMeta( 'auth_tokens');
        if( ! is_array( $auth_tokens ) ) {
            $auth_tokens = [];
        }
        if( ! isset( $auth_tokens[ $exp ] ) ) {
            $auth_tokens[ $exp ] = [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'ua' => isset( $_SERVER['HTTP_USER_AGENT'] ) ?  filter_var( $_SERVER['HTTP_USER_AGENT'], FILTER_SANITIZE_STRING ) : '',
                'time' => \date( 'Y-m-d H:i:s' ),
            ];
        }
        if( count( $auth_tokens ) > 10 ){
            // 1 is the index of the first object to get
            // NULL to get everything until the end
            // true to preserve keys
            $auth_tokens = array_slice($auth_tokens, 1, NULL, true);
        }

        $this->setMeta( 'auth_tokens', $auth_tokens );

        return $jwt;
    }
    
    public function toArray(){
        $array = [];
        foreach ( \array_keys( \get_object_vars( $this ) ) as $key ) {
            $array[ $key ] = $this->get( $key );
        };
        return $array;
    }
    
    public function exist(){
		return ! empty( $this->u_id );
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
            case 'u_id':
            case 'u_r_uid':
            case 'u_o_count':
                $value = (int) $value;
            break;
            case 'u_cash':
            case 'u_p_cash':
                $value = \round( $value, 2 );
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
            case 'u_id':
                return false;
            break;
            case 'u_r_uid':
            case 'u_o_count':
                $value = (int) $value;
            break;
            case 'u_cash':
            case 'u_p_cash':
                $value = \round( $value, 2 );
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
        return Meta::get( 'user', $this->u_id, $key );
    }

    public function setMeta( $key, $value ) {
        return Meta::set( 'user', $this->u_id, $key, $value );
    }

    public function insertMetas( $keyValues ) {
        return Meta::insert( 'user', $this->u_id, $keyValues );
    }

    public function cashUpdate( $amount ) {
        return $this->update( [ 'u_cash' => \round( $this->get( 'u_cash' ) + $amount, 2 ) ] );
    }

    public function pCashUpdate( $amount ) {
        return $this->update( [ 'u_p_cash' => \round( $this->get( 'u_p_cash' ) + $amount, 2 ) ] );
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
        $this->u_created = $data_array['u_created'] = \date( 'Y-m-d H:i:s' );
        $this->u_updated = $data_array['u_updated'] = \date( 'Y-m-d H:i:s' );
        
        unset( $data_array['u_id'] );
        if ( empty( $data_array['u_email'] ) ) {
            unset( $data_array['u_email'] );
        }
        if ( empty( $data_array['u_mobile'] ) ) {
            unset( $data_array['u_mobile'] );
        }

        $this->u_id = DB::instance()->insert( 't_users', $data_array );
        
        if( $this->u_id ){
            $this->updateCache();
        }

        return $this->u_id;
    }
    public function update( $data = array() ){
        if( ! $this->exist() ){
            return false;
        }
        $user = static::getUser( $this->u_id );
        if( ! $user ) {
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
            if ( $user->$key != $value ) {
                $data_array[ $key ] = $value;
            }
        }
        
        unset( $data_array['u_id'] );
        if ( empty( $data_array['u_email'] ) ) {
            unset( $data_array['u_email'] );
        }
        if ( empty( $data_array['u_mobile'] ) ) {
            unset( $data_array['u_mobile'] );
        }
        if ( ! $data_array ) {
            return false;
        }
        $this->u_updated = $data_array['u_updated'] = \date( 'Y-m-d H:i:s' );
        
        $updated = DB::instance()->update( 't_users', $data_array, [ 'u_id' => $this->u_id ] );
        
        if( $updated ){
            $this->updateCache();
        }

        return $updated;
    }
    public function delete(){
        if( ! $this->exist() ){
            return false;
        }
        
        $deleted = DB::instance()->delete( 't_users', [ 'u_id' => $this->u_id ] );
        
        if( $deleted ){
            Meta::delete( 'user', $this->u_id );
            Cache::instance()->delete( $this->u_id, 'user' );
        }

        return $deleted;
    }

    function capabilities( $role = '' ) {
        if( ! $role ) {
            $role = $this->u_role;
        }
        $roles = [
            'administrator' => [
                'role:administrator',
                'backendAccess',
                'orderCreate',
                'orderEdit',
                'orderDelete',
                //'offlineOrderCreate',
                'medicineCreate',
                'medicineEdit',
                'medicineDelete',
                'userCreate',
                'userEdit',
                'userDelete',
                'userChangeRole',
                'collectionsView',
                'ledgerView',
                'ledgerCreate',
                'ledgerEdit',
                'inventoryView',
                'inventoryEdit',
                'purchasesView',
                'genericCreate',
                //'genericEdit',
                'companyCreate',
                //'companyEdit',
            ],
            'operator' => [
                'role:operator',
                'backendAccess',
                'orderCreate',
                'orderEdit',
                'userEdit',
                //'inventoryView',
                //'purchasesView',
            ],
            'pharmacy' => [
                'role:pharmacy',
                'backendAccess',
                'medicineCreate',
                'medicineEdit',
                'orderCreate',
                'offlineOrderCreate',
                'orderEdit',
                'inventoryView',
                'purchasesView',
                'collectionsView',
            ],
            'investor' => [
                'role:investor',
                'backendAccess',
                'onlyGET', //only GET request allowed in backend
                'collectionsView',
                'ledgerView',
                'inventoryView',
                'purchasesView',
            ],
        ];
        if ( isset( $roles[ $role ] ) ) {
            return  $roles[ $role ];
        } else {
            return [];
        }
    }

    function can( $cap ) {
        if( ! $this->exist() || ! $cap ){
            return false;
        }
        $caps = $this->capabilities();
        if( in_array( $cap, $caps ) ) {
            return true;
        }
        return false;
    }

    function updateCache() {
        if( $this->u_id ) {
            Cache::instance()->set( $this->u_id, $this, 'user' );
            Cache::instance()->set( $this->u_mobile, $this->u_id, 'u_mobile_to_id' );
            Cache::instance()->set( $this->u_referrer, $this->u_id, 'u_referrer_to_id' );
            if( $this->u_email ) {
                Cache::instance()->set( $this->u_email, $this->u_id, 'u_email_to_id' );
            }
        }
    }
}
