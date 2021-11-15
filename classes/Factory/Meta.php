<?php

namespace OA\Factory;
use OA\{DB, Cache, Functions};

/**
 * 
 */
class Meta {

    private static function variables( $type ){
		$cache_group = $db_table = $id_column = '';
        if( 'user' == $type ){
            $cache_group = 'user_meta';
            $db_table = 't_user_meta';
            $id_column = 'u_id';
        } elseif( 'order' == $type ){
            $cache_group = 'order_meta';
            $db_table = 't_order_meta';
            $id_column = 'o_id';
        } elseif( 'medicine' == $type ){
            $cache_group = 'medicine_meta';
            $db_table = 't_medicine_meta';
            $id_column = 'm_id';
        } elseif( 'inventory' == $type ){
            $cache_group = 'inventory_meta';
            $db_table = 't_inventory_meta';
            $id_column = 'i_id';
        }
		return compact( 'cache_group', 'db_table', 'id_column' );
    }

    public static function get( $type, $id, $key ) {
        if( ! $id || ! $key ){
            return false;
        }
        extract( static::variables( $type ) );

        if( ! $cache_group ){
            return false;
        }

        $metas = CacheUpdate::instance()->update_cache( [ $id ], $cache_group );
        if( !isset( $metas[ $id ][ $key ] ) ){
            return false;
        }
        return $metas[ $id ][ $key ]; //Already json decoded
    }

    public static function set( $type, $id, $key, $value ) {
        if( ! $id || ! $key ){
            return false;
        }
        extract( static::variables( $type ) );

        if( ! $cache_group ){
            return false;
        }
        $return = false;
        $metas = CacheUpdate::instance()->update_cache( [ $id ], $cache_group );
        if( !isset( $metas[ $id ][ $key ] ) ){
            if( $value ) {
                $return = static::insert( $type, $id, [ $key => $value ] );
            }
        } elseif( $metas[ $id ][ $key ] != $value ) {
            $return = DB::instance()->update( $db_table, [ 'meta_value' => Functions::maybeJsonEncode( $value ) ], [ $id_column => $id, 'meta_key' => $key ] );
        }
        if( $return ){
            Cache::instance()->delete( $id, $cache_group );
        }
        return $return;
    }

    public static function insert( $type, $id, $keyValues ) {
        if( ! $id ){
            return false;
        }
        extract( static::variables( $type ) );

        if( ! $cache_group ){
            return false;
        }
        if( ! $keyValues || ! \is_array( $keyValues ) ){
            return false;
        }
        $data = [];
        foreach ( $keyValues as $key => $value ) {
            if( ! $key || ! \is_string( $key ) ){
                continue;
            }
            $data[] = [
                $id_column => $id,
                'meta_key' => $key,
                'meta_value' => Functions::maybeJsonEncode( $value ),
            ];
        }
        $return = DB::instance()->insertMultiple( $db_table, $data );
        if( $return ){
            Cache::instance()->delete( $id, $cache_group );
        }
        return $return;
    }

    public static function delete( $type, $id, $key = false, $value = false ) {
        if( ! $id ){
            return false;
        }
        extract( static::variables( $type ) );
		
        if( ! $cache_group ){
            return false;
        }
        $return = false;
        $where = [
            $id_column => $id,
        ];
        if( $key ){
            $where['meta_key'] = $key;
        }
        if( $value ){
            $where['meta_value'] = Functions::maybeJsonEncode( $value );
        }
        $return = DB::instance()->delete( $db_table, $where );

        if( $return ){
            Cache::instance()->delete( $id, $cache_group );
        }
        return $return;
    }

}
