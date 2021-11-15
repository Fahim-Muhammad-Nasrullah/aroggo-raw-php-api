<?php

namespace OA\Factory;
use OA\{DB, Cache, Functions};

/**
 * 
 */
class Medicine {
	
	private $m_id = 0;
    private $m_name = '';
    private $m_g_id = 0;
    //private $m_generic = '';
    private $m_strength = '';
    private $m_unit = '';
    private $m_price = '0.00';
    private $m_d_price = '0.00';
    private $m_c_id = 0;
    //private $m_company = '';
    private $m_form = '';
    private $m_rob = 0;
    private $m_status = 'active';
    private $m_category = 'allopathic';
    private $m_comment = '';
    private $m_i_comment = '';
    private $m_u_id = 0;
    private $m_cat_id = 0;
    private $m_min = 1;
    private $m_max = 200;
    private $m_cold = 0;
    private $m_r_count = 0;

    function __construct( $id = 0 )
    {
        if( $id instanceof self ){
            foreach( $id->toArray() as $k => $v ){
                $this->$k = $v;
            }
        } elseif( is_numeric( $id ) && $id && ( $medicine = static::getMedicine( $id ) ) ){
            foreach( $medicine->toArray() as $k => $v ){
                $this->$k = $v;
            }
        } elseif( is_array( $id ) && $id ){
            foreach( $id as $k => $v ){
                $this->$k = $v;
            }
        }
    }
    
    public static function getMedicine( $id ) {
        if ( ! \is_numeric( $id ) ){
            return false;
        }
        $id = \intval( $id );
        if ( $id < 1 ){
            return false;
        }

        if ( $medicine = Cache::instance()->get( $id, 'medicine' ) ){
            return $medicine;
        }
        
        $query = DB::db()->prepare( 'SELECT * FROM t_medicines WHERE m_id = ? LIMIT 1' );
        $query->execute( [ $id ] );
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Medicine');
        if( $medicine = $query->fetch() ){     
            Cache::instance()->add( $medicine->m_id, $medicine, 'medicine' );
            return $medicine;
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
		return ! empty( $this->m_id );
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
            case 'm_id':
            case 'm_g_id':
            case 'm_c_id':
            case 'm_cat_id':
            case 'm_min':
            case 'm_max':
            case 'm_r_count':
            case 'm_cold':
                $value = (int) $value;
            break;
            case 'm_rx_req':
                $value = 11 == $this->m_cat_id;
            break;
            case 'm_rob':
                $value = (bool) $value;
            break;
            case 'm_generic':
                $value = Generic::getName( $this->m_g_id );
            break;
            case 'm_company':
                $value = Company::getName( $this->m_c_id );
            break;
            case 'm_description':
                if( ! $this->m_g_id ){
                    break;
                }
                $query = DB::db()->prepare( 'SELECT * FROM t_generics WHERE g_id = ? LIMIT 1' );
                $query->execute( [ $this->m_g_id ] );
                $value = $query->fetch();
                unset( $value['g_id'], $value['g_name'] );
            break;
            case 'm_description_dims':
                if( ! $this->m_g_id ){
                    break;
                }
                $val = [];
                $query = DB::db()->prepare( 'SELECT * FROM t_generics WHERE g_id = ? LIMIT 1' );
                $query->execute( [ $this->m_g_id ] );
                $row = $query->fetch();

                if( $row ){
                    if( !empty( $row['indication'] ) ){
                        $val[] = [
                            'title' => 'Indication',
                            'content' => $row['indication'],
                        ];
                    }
                    if( !empty( $row['administration'] ) ){
                        $val[] = [
                            'title' => 'Administration',
                            'content' => $row['administration'],
                        ];
                    }
                    if( !empty( $row['adult_dose'] ) ){
                        $val[] = [
                            'title' => 'Adult Dose',
                            'content' => $row['adult_dose'],
                        ];
                    }
                    if( !empty( $row['child_dose'] ) ){
                        $val[] = [
                            'title' => 'Child Dose',
                            'content' => $row['child_dose'],
                        ];
                    }
                    if( !empty( $row['renal_dose'] ) ){
                        $val[] = [
                            'title' => 'Renal Dose',
                            'content' => $row['renal_dose'],
                        ];
                    }
                    if( !empty( $row['contra_indication'] ) ){
                        $val[] = [
                            'title' => 'Contraindication',
                            'content' => $row['contra_indication'],
                        ];
                    }
                    if( !empty( $row['mode_of_action'] ) ){
                        $val[] = [
                            'title' => 'Mode of Action',
                            'content' => $row['mode_of_action'],
                        ];
                    }
                    if( !empty( $row['precaution'] ) ){
                        $val[] = [
                            'title' => 'Precaution',
                            'content' => $row['precaution'],
                        ];
                    }
                    if( !empty( $row['side_effect'] ) ){
                        $val[] = [
                            'title' => 'Side Effect',
                            'content' => $row['side_effect'],
                        ];
                    }
                    if( !empty( $row['pregnancy_category_note'] ) ){
                        $val[] = [
                            'title' => 'Pregnancy Category Note',
                            'content' => $row['pregnancy_category_note'],
                        ];
                    }
                    if( !empty( $row['interaction'] ) ){
                        $val[] = [
                            'title' => 'Interaction',
                            'content' => $row['interaction'],
                        ];
                    }
                }
                if( $val ){
                    $value = $val;
                }
            break;
            case 'm_description_v2':
                $value = [];
                if( $generic = Generic::getGeneric( $this->m_g_id ) ){
                    $value['g_overview'] = Functions::maybeJsonDecode( $generic->g_overview );
                    $value['g_quick_tips'] = Functions::maybeJsonDecode( $generic->g_quick_tips );
                    $value['g_safety_advices'] = Functions::maybeJsonDecode( $generic->g_safety_advices );
                    $value['brief_description'] = Functions::maybeJsonDecode( $generic->g_brief_description );
                }
            break;
            case 'm_pic_url':
                $value = Functions::getPicUrl( $this->getMeta( 'images' ) );
            break;
            case 'm_pic_urls':
                $value = Functions::getPicUrls( $this->getMeta( 'images' ) );
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

        if( ! property_exists( $this, $key ) ) {
			return false;
        }

        switch( $key ){
            case 'm_id':
                return false;
            break;
            case 'm_rob':
                $value = ( ! $value || 'false' === $value ) ? 0 : 1;
            break;
            case 'm_g_id':
            case 'm_c_id':
            case 'm_cat_id':
            case 'm_min':
            case 'm_max':
            case 'm_r_count':
            case 'm_cold':
                $value = (int) $value;
            break;
            default:
                $value = (string) $value;
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
        return Meta::get( 'medicine', $this->m_id, $key );
    }

    public function setMeta( $key, $value ) {
        return Meta::set( 'medicine', $this->m_id, $key, $value );
    }

    public function insertMetas( $keyValues ) {
        return Meta::insert( 'medicine', $this->m_id, $keyValues );
    }

    public function getCount( $key ) {
        //We are using Memcached, So use it for count performance
        $found = false;
        $count = (int)Cache::instance()->get( $this->m_id, "medicineCount{$key}", false, $found );
        if( ! $found ){
            $count = (int)$this->getMeta( "medicineCount{$key}" );
            Cache::instance()->set( $this->m_id, $count, "medicineCount{$key}" );
        }
        return $count;
    }
    
    public function incrCount( $key, $offset = 1, $update_count = 10 ) {
        $count = $this->getCount( $key );
        $count = Cache::instance()->incr( $this->m_id, $offset, "medicineCount{$key}" );

        if( $count && ( $count % $update_count == 0 ) ){
            //Update every "$update_count"th count
            $this->setMeta( "medicineCount{$key}", $count );

            \OA\Search\Medicine::init()->update( $this->m_id, ["medicineCount{$key}" => $count] );
        }
        return $count;
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
        $data_array['m_rob'] = (int) $data_array['m_rob'];
        
        unset( $data_array['m_id'] );

        $this->m_id = DB::instance()->insert( 't_medicines', $data_array );

        if( $this->m_id ){
            Cache::instance()->add( $this->m_id, $this, 'medicine' );
            $es_data = $this->toArray();
            $es_data['m_generic'] = $this->m_generic;
            $es_data['m_company'] = $this->m_company;

            \OA\Search\Medicine::init()->index( $this->m_id, $es_data );

            if( 'active' == $this->m_status ) {
                Cache::instance()->incr( 'suffixForMedicines' );
            }
        }

        return $this->m_id;
    }
    public function update( $data = array() ){
        if( ! $this->exist() ){
            return false;
        }

        $medicine = static::getMedicine( $this->m_id );
        if( ! $medicine ) {
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
            if ( $medicine->$key != $value ) {
                $data_array[ $key ] = $value;
            }
        }
        
        unset( $data_array['m_id'] );
        if ( ! $data_array ) {
            return false;
        }
        if( isset( $data_array['m_rob'] ) ){
            $data_array['m_rob'] = (int) $data_array['m_rob'];
        }
        
        $updated = DB::instance()->update( 't_medicines', $data_array, [ 'm_id' => $this->m_id ] );
        
        if( $updated ){
            Cache::instance()->set( $this->m_id, $this, 'medicine' );
            Cache::instance()->incr( 'suffixForMedicines' );

            $es_data = $this->toArray();
            $es_data['m_generic'] = $this->m_generic;
            $es_data['m_company'] = $this->m_company;

            \OA\Search\Medicine::init()->update( $this->m_id, $es_data );

            Functions::miscMedicineUpdate( $medicine, $this );

        }

        return $updated;
    }

    public function delete(){
        if( ! $this->exist() ){
            return false;
        }
        
        $deleted = DB::instance()->delete( 't_medicines', [ 'm_id' => $this->m_id ] );
        
        if( $deleted ){
            Meta::delete( 'medicine', $this->m_id );
            Cache::instance()->delete( $this->m_id, 'medicine' );
            Cache::instance()->incr( 'suffixForMedicines' );

            //delete images here

            \OA\Search\Medicine::init()->delete( $this->m_id );
        }

        return $deleted;
    }

    function updateCache() {
        if( $this->m_id ) {
            Cache::instance()->set( $this->m_id, $this, 'medicine' );
        }
    }
}
