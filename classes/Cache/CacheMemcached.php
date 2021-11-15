<?php

namespace OA\Cache;

/**
 * WordPress Object Cache
 *
 * The WordPress Object Cache is used to save on trips to the database. The
 * Object Cache stores all of the cache data to memory and makes the cache
 * contents available by using a key, which is used to name and later retrieve
 * the cache contents.
 *
 * The Object Cache can be replaced by other caching mechanisms by placing files
 * in the dc-content folder which is looked at in dc-settings. If that file
 * exist, then this file will not be included.
 *
 * @package WordPress
 * @subpackage Cache
 * @since 2.0.0
 */
class CacheMemcached implements Cache {

	/**
	 * Holds the cached objects
	 *
	 * @var array
	 * @access private
	 * @since 2.0.0
	 */
	private $cache = array();

	/**
	 * The amount of times the cache data was already stored in the cache.
	 *
	 * @since 2.5.0
	 * @access private
	 * @var int
	 */
	private $cache_hits = 0;

	/**
	 * Amount of times the cache did not have the request in cache
	 *
	 * @var int
	 * @access public
	 * @since 2.0.0
	 */
	public $cache_misses = 0;
	
	private static $instance;

	private $mc;
	
	function __construct() {
		if( empty( $this->mc ) && \class_exists( '\Memcached' ) ){
			$this->mc = new \Memcached();
			$this->mc->addServer("127.0.0.1", 11211);
		}
	}
	
	public static function init() {
		if(!self::$instance instanceof self) {
			self::$instance = new self;
		}
		return self::$instance;
	}
	/**
	 * Adds data to the cache if it doesn't already exist.
	 *
	 * @uses DC_Object_Cache::_exists Checks to see if the cache already has data.
	 * @uses DC_Object_Cache::set Sets the data after the checking the cache
	 *		contents existence.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $key What to call the contents in the cache
	 * @param mixed $data The contents to store in the cache
	 * @param string $group Where to group the cache contents
	 * @param int $expire When to expire the cache contents
	 * @return bool False if cache key and group already exist, true on success
	 */
	public function add( $key, $data, $group = 'default', $expire = 0 ) {

		if ( empty( $group ) )
			$group = 'default';

		if ( $this->_exists( $key, $group ) )
			return false;

		return $this->set( $key, $data, $group, (int) $expire );
	}

	/**
	 * Remove the contents of the cache key in the group
	 *
	 * If the cache key does not exist in the group, then nothing will happen.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $key What the contents in the cache are called
	 * @param string $group Where the cache contents are grouped
	 *
	 * @return bool False if the contents weren't deleted and true on success
	 */
	public function delete( $key, $group = 'default' ) {
		if ( empty( $group ) )
			$group = 'default';

		unset( $this->cache[$group][$key] );
		return $this->mc->delete( $this->getMcKey( $key, $group ) );
		//return true;
	}

	/**
	 * Clears the object cache of all data
	 *
	 * @since 2.0.0
	 *
	 * @return true Always returns true
	 */
	public function flush() {
		$this->cache = array();

		return $this->mc->flush();
	}

	/**
	 * Retrieves the cache contents, if it exist
	 *
	 * The contents will be first attempted to be retrieved by searching by the
	 * key in the cache group. If the cache is hit (success) then the contents
	 * are returned.
	 *
	 * On failure, the number of cache misses will be incremented.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $key What the contents in the cache are called
	 * @param string $group Where the cache contents are grouped
	 * @param string $force Whether to force a refetch rather than relying on the local cache (default is false)
	 * @return false|mixed False on failure to retrieve contents or the cache
	 *		               contents on success
	 */
	public function get( $key, $group = 'default', $force = false, &$found = null ) {
		if ( empty( $group ) )
			$group = 'default';

		if ( !$force && $this->_exists( $key, $group ) ) {
			$found = true;
			$this->cache_hits += 1;
			if ( is_object($this->cache[$group][$key]) )
				return clone $this->cache[$group][$key];
			else
				return $this->cache[$group][$key];
		} else {
			$data = $this->mc->get( $this->getMcKey( $key, $group ) );
			if ($this->mc->getResultCode() == \Memcached::RES_NOTFOUND) {
				$found = false;
				$this->cache_misses += 1;
			} else {
				$found = true;
				$this->cache_hits += 1;

				if ( is_object( $data ) ){
					$this->cache[$group][$key] = clone $data;
					return clone $data;
				} else {
					$this->cache[$group][$key] = $data;
					return $data;
				}
			}
		}
		return false;
	}

	/**
	 * Increment numeric cache item's value
	 *
	 * @since 3.3.0
	 *
	 * @param int|string $key The cache key to increment
	 * @param int $offset The amount by which to increment the item's value. Default is 1.
	 * @param string $group The group the key is in.
	 * @return false|int False on failure, the item's new value on success.
	 */
	public function incr( $key, $offset = 1, $group = 'default' ) {
		if ( empty( $group ) )
			$group = 'default';

		$offset = (int) $offset;

		unset( $this->cache[$group][$key] );

		return $this->mc->increment( $this->getMcKey( $key, $group ), $offset );
	}

	/**
	 * Decrement numeric cache item's value
	 *
	 * @since 3.3.0
	 *
	 * @param int|string $key The cache key to increment
	 * @param int $offset The amount by which to decrement the item's value. Default is 1.
	 * @param string $group The group the key is in.
	 * @return false|int False on failure, the item's new value on success.
	 */
	public function decr( $key, $offset = 1, $group = 'default' ) {
		if ( empty( $group ) )
			$group = 'default';

		$offset = (int) $offset;

		unset( $this->cache[$group][$key] );

		return $this->mc->decrement( $this->getMcKey( $key, $group ), $offset );
	}

	/**
	 * Replace the contents in the cache, if contents already exist
	 *
	 * @since 2.0.0
	 * @see DC_Object_Cache::set()
	 *
	 * @param int|string $key What to call the contents in the cache
	 * @param mixed $data The contents to store in the cache
	 * @param string $group Where to group the cache contents
	 * @param int $expire When to expire the cache contents
	 * @return bool False if not exist, true if contents were replaced
	 */
	public function replace( $key, $data, $group = 'default', $expire = 0 ) {
		if ( empty( $group ) )
			$group = 'default';

		return $this->set( $key, $data, $group, (int) $expire );
	}

	/**
	 * Sets the data contents into the cache
	 *
	 * The cache contents is grouped by the $group parameter followed by the
	 * $key. This allows for duplicate ids in unique groups. Therefore, naming of
	 * the group should be used with care and should follow normal function
	 * naming guidelines outside of core WordPress usage.
	 *
	 * The $expire parameter is not used, because the cache will automatically
	 * expire for each time a page is accessed and PHP finishes. The method is
	 * more for cache plugins which use files.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $key What to call the contents in the cache
	 * @param mixed $data The contents to store in the cache
	 * @param string $group Where to group the cache contents
	 * @param int $expire Not Used
	 * @return true Always returns true
	 */
	public function set( $key, $data, $group = 'default', $expire = 0 ) {
		if ( empty( $group ) )
			$group = 'default';

		if ( is_object( $data ) )
			$data = clone $data;

		$this->cache[$group][$key] = $data;
		$this->mc->set( $this->getMcKey( $key, $group ), $data, (int) $expire );

		return true;
	}

	/**
	 * Echoes the stats of the caching.
	 *
	 * Gives the cache hits, and cache misses. Also prints every cached group,
	 * key and the data.
	 *
	 * @since 2.0.0
	 */
	public function stats() {
		//send as json.
		\OA\Response::instance()->sendData( $this->mc->getStats(), 'success' );

		//Other cache may not send as json. they may pass as html
		foreach( $this->mc->getStats() as $status ){
			$this->echoDetails($status);
		} 
	}

	private function echoDetails($status){

		echo "<table border='1'>";

		echo "<tr><td>Memcache Server version:</td><td> ".$status ["version"]."</td></tr>";
		echo "<tr><td>Process id of this server process </td><td>".$status ["pid"]."</td></tr>";
		echo "<tr><td>Number of seconds this server has been running </td><td>".$status ["uptime"]."</td></tr>";
		echo "<tr><td>Accumulated user time for this process </td><td>".$status ["rusage_user"]." seconds</td></tr>";
		echo "<tr><td>Accumulated system time for this process </td><td>".$status ["rusage_system"]." seconds</td></tr>";
		echo "<tr><td>Total number of items stored by this server ever since it started </td><td>".$status ["total_items"]."</td></tr>";
		echo "<tr><td>Number of open connections </td><td>".$status ["curr_connections"]."</td></tr>";
		echo "<tr><td>Total number of connections opened since the server started running </td><td>".$status ["total_connections"]."</td></tr>";
		echo "<tr><td>Number of connection structures allocated by the server </td><td>".$status ["connection_structures"]."</td></tr>";
		echo "<tr><td>Cumulative number of retrieval requests </td><td>".$status ["cmd_get"]."</td></tr>";
		echo "<tr><td> Cumulative number of storage requests </td><td>".$status ["cmd_set"]."</td></tr>";

		$percCacheHit=((float)$status ["get_hits"]/ (float)$status ["cmd_get"] *100);
		$percCacheHit=round($percCacheHit,3);
		$percCacheMiss=100-$percCacheHit;

		echo "<tr><td>Number of keys that have been requested and found present </td><td>".$status ["get_hits"]." ($percCacheHit%)</td></tr>";
		echo "<tr><td>Number of items that have been requested and not found </td><td>".$status ["get_misses"]."($percCacheMiss%)</td></tr>";

		$MBRead= (float)$status["bytes_read"]/(1024*1024);

		echo "<tr><td>Total number of bytes read by this server from network </td><td>".$MBRead." Mega Bytes</td></tr>";
		$MBWrite=(float) $status["bytes_written"]/(1024*1024) ;
		echo "<tr><td>Total number of bytes sent by this server to network </td><td>".$MBWrite." Mega Bytes</td></tr>";
		$MBSize=(float) $status["limit_maxbytes"]/(1024*1024) ;
		echo "<tr><td>Number of bytes this server is allowed to use for storage.</td><td>".$MBSize." Mega Bytes</td></tr>";
		echo "<tr><td>Number of valid items removed from cache to free memory for new items.</td><td>".$status ["evictions"]."</td></tr>";

		echo "</table>";
		
	} 

	/**
	 * Utility function to determine whether a key exist in the cache.
	 *
	 * @since 3.4.0
	 *
	 * @access protected
	 * @param string $key
	 * @param string $group
	 * @return bool
	 */
	protected function _exists( $key, $group ) {
		return isset( $this->cache[ $group ] ) && ( isset( $this->cache[ $group ][ $key ] ) || array_key_exists( $key, $this->cache[ $group ] ) );
	}
	
	/**
	 * unction to determine if supported.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	 
	public static function isSupported(){
		return \class_exists( '\Memcached' );
	}
	
	public function getCacheName(){
		return 'Memcached';
	}

	private function getSuffix( $group ){
		$suffix = '';
		$key = '';
		switch ( $group ) {
			case 'adminMedicines':
			case 'userMedicines':
			case 'userSameGeneric':
				$key = 'suffixForMedicines';
				break;
			case 'adminUsers':
				$key = 'suffixForUsers';
				break;
			
			default:
				# code...
				break;
		}
		if( $key ){
			$suffix = $this->get( $key );
			if( !$suffix || !\is_numeric( $suffix ) ){
				$this->set( $key, 1 );
				$suffix = 1;
			}
		}
		
		return $suffix;
	}

	public function getMcKey( $key, $group ){
		$mcKey = "{$group}:{$key}";
		if( $suffix = $this->getSuffix( $group ) ){
			$mcKey .= ":{$suffix}";
		}
		if( !MAIN ) {
            $mcKey .= ":staging";
        }
		return $mcKey;
	}

	/**
	 * Will save the object cache before object is completely destroyed.
	 *
	 * Called upon object destruction, which should be when PHP ends.
	 *
	 * @since  2.0.8
	 *
	 * @return true True value. Won't be used by PHP
	 */
	public function __destruct() {
		if( ! empty( $this->mc ) ){
			return $this->mc->quit();
		}
		return false;
	}
}
