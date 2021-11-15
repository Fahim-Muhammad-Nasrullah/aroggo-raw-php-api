<?php

//date_default_timezone_set('UTC');
date_default_timezone_set('Asia/Dhaka');

header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
header( 'Content-Type: application/json; charset=utf8' ); //It is only for api, so always send json

//30 seconds browser Cache
//header('Cache-Control: max-age=30'); // HTTP/1.1
//header('Expires: '.gmdate('D, d M Y H:i:s', time()+30).'GMT');

define( 'ABSPATH', __DIR__ . '/' );

if( file_exists( ABSPATH . 'config.php' ) ){
	require ABSPATH . 'config.php';
} else {
	die( 'Config file is not available' );
}

if( MAIN ){
	error_reporting(0);
} else {
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1');
	error_reporting(E_ALL);
}

require 'vendor/autoload.php';

//file_put_contents( STATIC_DIR . '/error_log.txt', "Test", FILE_APPEND | LOCK_EX);

spl_autoload_register(
	function( $class_name ) {
		if ( 0 === strpos( $class_name, 'OA' ) ) {
			$class_name = str_replace( array( 'OA\\', '\\' ), array( '', DIRECTORY_SEPARATOR ), $class_name );

			if ( file_exists( ABSPATH . 'classes'. DIRECTORY_SEPARATOR . $class_name . '.php' ) ) {
				require ABSPATH . 'classes'. DIRECTORY_SEPARATOR . $class_name . '.php';
			}
		}		
	}
);

\OA\Route::instance()->dispatch();

