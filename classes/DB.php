<?php

namespace OA;
use PDO;

/**
 * 
 */
class DB
{
	public static $instance;
	public static $db;
	private $sql = '';
	private $values = [];
	
	public static function instance() {
		if( ! self::$instance instanceof self ) {
			self::$instance = new self;
		}
		return self::$instance;
    }

	public static function db() {
		if ( self::$db instanceof PDO ) {
			return self::$db;
		}
		$options = array(
			PDO::ATTR_EMULATE_PREPARES   => false,
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		);
	
		try {
			//self::$db=new PDO(DB_HOST, DB_USER, DB_PASS);
			self::$db = new PDO( 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8', DB_USER, DB_PASS, $options );

		} catch ( \PDOException $e ) {
			//die("DB ERROR: ". __LINE__ . $e->getMessage());
			\error_log( $e->getMessage() );
			Response::instance()->sendMessage( 'DB error.', 'error' );
		} catch( \Exception $e ) {
			\error_log( $e->getMessage() );
			Response::instance()->sendMessage( 'DB error.', 'error' );
		}
		return self::$db;
	}

	function insert( $table, $data, $on_duplicate_key_update = false ) {		
		if( ! $table || ! $data || ! is_array( $data ) ) {
			return 0;
		}
		
		$col_array = array();
		$val_array = array();
		$duplicate = array();
		$val_insert = [];
		
		foreach ( $data as $column => $value ) {
			$col_array[] = $column;
			$val_array[] = '?';
			$val_insert[] = Functions::maybeJsonEncode( $value );

			if( $on_duplicate_key_update ) {
				$duplicate[] = "{$column} = VALUES({$column})";
			}
		}
		
		$col_string = implode('`, `', $col_array );
		$val_string = implode(', ', $val_array );
		$duplicate_string = $on_duplicate_key_update ? ' ON DUPLICATE KEY UPDATE ' . implode(', ', $duplicate ) : '';
		
		$sql = "INSERT INTO `$table` (`{$col_string}`) VALUES ($val_string){$duplicate_string}";

		$stmt = $this->db()->prepare( $sql );
		$stmt->execute( $val_insert );
		
		//$stmt->debugDumpParams();
		
		return (int) $this->db()->lastInsertId();
	}

	function insertMultiple( $table, $dataMulti, $on_duplicate_key_update = false ) {		
		if( ! $table || ! $dataMulti || ! \is_array( $dataMulti ) || ! \is_array( $dataMulti[0] ) ) {
			return 0;
		}
		
		$col_array = array();
		$val_array = array();
		$duplicate = array();
		$val_insert = [];
		
		$i = 0;
		foreach ( $dataMulti as $data ) {
			foreach ( $data as $column => $value ) {
				if( 0 === $i ) {
					$col_array[] = $column;
					$duplicate[] = "{$column} = VALUES({$column})";
				}
				$val_insert[] = Functions::maybeJsonEncode( $value );
			}
			$val_array[] = '(' . str_repeat('?,', count($data) - 1) . '?' . ')';
			$i++;
		}
		
		
		$col_string = implode('`, `', $col_array );
		$val_string = implode(', ', $val_array );
		$duplicate_string = $on_duplicate_key_update ? ' ON DUPLICATE KEY UPDATE ' . implode(', ', $duplicate ) : '';
		
		$sql = "INSERT INTO `$table` (`{$col_string}`) VALUES {$val_string}{$duplicate_string}";

		$stmt = $this->db()->prepare( $sql );
		$stmt->execute( $val_insert );
		
		//$stmt->debugDumpParams();
		
		return (int) $this->db()->lastInsertId();
	}

	function update( $table, $data, $where, $relation = 'AND' ){
		if( !$table || !$data || !is_array( $data ) || !$where || !is_array( $where ) ){
			return 0;
		}		
		$relation =  ( 'OR' == $relation ) ? 'OR' : 'AND';

		$params = [];
		$set_array = [];
		foreach ( $data as $column => $value ) {
			$set_array[] = "`{$column}` = ?";
			$params[] = Functions::maybeJsonEncode( $value );
		}
		
		// Use implode to create the 'SET' string
		$set_string = implode(', ', $set_array );
		
		$where_array = array();
		foreach ( $where as $column => $value ) {
			$col = "`{$column}`";

			if( is_array(  $value ) ){
				$in  = str_repeat('?,', count($value) - 1) . '?';
				$col .= " IN ($in)";
				$params = array_merge( $params, $value );
			} else {
				$col .= ' = ?';
				$params[] = $value;
			}
			$where_array[] = $col;
		}
		
		// Use implode to create the 'WHERE' string
		$where_string = implode(" {$relation} ", $where_array );
		
		$sql = "UPDATE `{$table}` SET $set_string WHERE $where_string";
		
		$stmt = $this->db()->prepare( $sql );
		$stmt->execute( $params );
		
		return $stmt->rowCount();
	}

	function delete( $table, $where, $relation = 'AND' ){	
		if( !$table || !$where || !is_array( $where ) ){
			return 0;
		}	
		$relation =  ( 'OR' == $relation ) ? 'OR' : 'AND';

		$params = [];
		$where_array = array();
		foreach ( $where as $column => $value) {
			$col = "`{$column}`";

			if( is_array(  $value ) ){
				$in  = str_repeat('?,', count($value) - 1) . '?';
				$col .= " IN ($in)";
				$params = array_merge( $params, $value );
			} else {
				$col .= ' = ?';
				$params[] = $value;
			}
			$where_array[] = $col;
		}
		
		// Use implode to create the 'WHERE' string
		$where_string = implode(" {$relation} ", $where_array );
		
		$sql = "DELETE FROM `{$table}` WHERE $where_string";
		
		$stmt = $this->db()->prepare( $sql );		
		$stmt->execute( $params );
		
		return $stmt->rowCount();
	}

	function select( $table, $where, $what = '*', $where_relation = 'AND' ){		
		$where_relation =  ( 'OR' == $where_relation ) ? 'OR' : 'AND';
		
		$params = [];
		$where_array = array();
		foreach ( $where as $column => $value) {
			$col = "`{$column}`";

			if( is_array(  $value ) ){
				$in  = str_repeat('?,', count($value) - 1) . '?';
				$col .= " IN ($in)";
				$params = array_merge( $params, $value );
			} else {
				$col .= ' = ?';
				$params[] = $value;
			}
			$where_array[] = $col;
		}
		
		// Use implode to create the 'WHERE' string
		$where_string = implode(" {$where_relation} ", $where_array );
		
		$sql = "SELECT {$what} FROM `{$table}` WHERE $where_string";
		
		$stmt = $this->db()->prepare( $sql );		
		$stmt->execute( $params );
		
		return $stmt;
	}

	function reset() {
		$this->sql = '';
		$this->values = [];
	}

	function add( $sql, ...$param ) {
		$this->sql .= $sql;
		if ( $param ) {
			$this->values = array_merge( $this->values, $param );
		}
	}

	function execute() {
		$query = $this->db()->prepare( $this->sql );
		$query->execute( $this->values );
		return $query;
	}

	function getSql(){
		return $this->sql;
	}

	function getParams(){
		return $this->values;
	}
}


