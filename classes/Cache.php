<?php

namespace OA;
use OA\Cache\Cache as Ce;
use OA\Cache\CacheDefault;
/**
 * 
 */
class Cache
{
	public static $instance;
	
	function __construct()
	{
		# code...
	}
	public static function instance(){
		if( ! self::$instance instanceof Ce ){
			
			$cache = 'Default';
			if( defined('CACHE') && CACHE ){
				$cache = CACHE;
			}
			$class = "\OA\Cache\Cache{$cache}";
			
			if( class_exists( $class ) ){
				if( $class::isSupported() ){
					self::$instance = new $class;
				} else {
					self::$instance = new CacheDefault();
					error_log( "$cache not supported");
				}               
			} else {
				self::$instance = new CacheDefault();
				error_log( "$class class not exist");
			}
		}
		return self::$instance;
	}
	public static function setCache( Ce $cache ){
		self::$instance = $cache;
	}
}
