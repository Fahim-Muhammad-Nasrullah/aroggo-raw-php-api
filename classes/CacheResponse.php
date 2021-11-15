<?php

namespace OA;
use OA\Factory\{User, Medicine, Discount, Order, Option, CacheUpdate};

class CacheResponse {

    function __construct() {
        $user = User::getUser( Auth::id() );
        if ( ! $user || 'administrator' !== $user->u_role ) {
            Response::instance()->sendMessage( 'Your account does not have permission to do this.' );
        }
    }

    function cacheFlush(){
        if( Cache::instance()->flush() ){
            Response::instance()->sendMessage( 'Cache Successfully flushed.', 'success' );
        } else {
            Response::instance()->sendMessage( 'Something went wrong. Please try again' );
        }
    }

    function cacheStats(){
        //header('Content-Type: text/html; charset=utf-8');
        Cache::instance()->stats();
        die;
    }
    function set( $key, $value, $group = 'default' ){
        if( Cache::instance()->set( $key, $value, $group ) ){
            Response::instance()->sendMessage( 'Cache Successfully set.', 'success' );
        } else {
            Response::instance()->sendMessage( 'Something went wrong. Please try again' );
        }
    }
    function get( $key, $group = 'default' ){
        if( $data = Cache::instance()->get( $key, $group ) ){
            if( \is_array( $data ) || \is_object( $data ) ){
                Response::instance()->sendData( (array)$data, 'success' );
            } else {
                Response::instance()->sendMessage( $data, 'success' );
            }
        } else {
            Response::instance()->sendMessage( 'Something went wrong. Please try again2' );
        }
    }

    function delete( $key, $group = 'default' ){
        if( Cache::instance()->delete( $key, $group ) ){
            Response::instance()->sendMessage( 'Cache Successfully deleted.', 'success' );
        } else {
            Response::instance()->sendMessage( 'Something went wrong. Please try again' );
        }
    }

}