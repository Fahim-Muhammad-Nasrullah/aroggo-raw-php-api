<?php

namespace OA;
use OA\Factory\{User, Medicine, Discount, Order, Option, CacheUpdate, PDF, Log, Bag, Location};
use GuzzleHttp\Client;
use OA\CustomClasses\FPDF_Merge;

class RouteResponse {

    function __construct() {
    }

    public function dataInitial( $version, $table, $page ) {
        $per_page = 300;
        $limit    = $per_page * ( $page - 1 );
        Response::instance()->addData( 'current', "/$version/data/initial/$table/$page/" );

        switch ( $table ) {
            case 'companies':
                $query = DB::db()->prepare( 'SELECT c_id, c_name FROM t_companies ORDER BY c_id LIMIT ?, ?' );
                $query->execute( [ $limit, $per_page ] );
                $companies = $query->fetchAll();
                if ( count( $companies ) < $per_page ) {
                    Response::instance()->addData( 'next', "/$version/data/initial/generics/1/" );
                } else {
                    Response::instance()->addData( 'next', sprintf('/%s/data/initial/companies/%d/', $version, ++$page ) );
                }
                Response::instance()->addData( 'table', 't_companies' );
                Response::instance()->addData( 'insert', $companies );
                break;
            case 'generics':
                if( 'v1' == $version ){
                    $query = DB::db()->prepare( 'SELECT * FROM t_generics ORDER BY g_id LIMIT ?, ?' );
                } elseif ( 'v2' == $version ) {
                    $query = DB::db()->prepare( 'SELECT * FROM t_generics_v2 ORDER BY g_id LIMIT ?, ?' );
                } else {
                    Response::instance()->sendMessage( 'Something wrong. Plase try again.' );
                }
                $query->execute( [ $limit, $per_page ] );
                $generics = $query->fetchAll();
                if ( count( $generics ) < $per_page ) {
                    Response::instance()->addData( 'next', "/$version/data/initial/medicines/1/" );
                } else {
                    Response::instance()->addData( 'next', sprintf('/%s/data/initial/generics/%d/', $version, ++$page ) );
                }
                if( 'v2' == $version ){
                    foreach ( $generics as &$generic ) {
                        foreach ( $generic as &$value ) {
                            $value = Functions::maybeJsonDecode($value);
                        }
                        unset( $value );
                    }
                    unset( $generic );
                }
                Response::instance()->addData( 'table', 't_generics' );
                Response::instance()->addData( 'insert', $generics );
            break;
            case 'medicines':
                $count = 0;
                $query = DB::db()->prepare( 'SELECT m_id, m_name, m_form, m_strength, m_unit, m_g_id, m_c_id, m_category FROM t_medicines WHERE m_status = ? ORDER BY m_name, m_form DESC, m_strength, m_unit LIMIT ?, ?' );
                $query->execute( [ 'active', $limit, $per_page ] );
                //$query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Medicine');

                while( $medicine = $query->fetch() ){
                    //$data = $medicine->toArray();
                    //$data['id'] = $medicine->m_id;
                    Response::instance()->appendData( 'insert', $medicine );
                    $count++;
                }
                if ( $count < $per_page ) {
                    Response::instance()->addData( 'dbVersion', Option::get('dbVersion') );
                } else {
                    Response::instance()->addData( 'next', sprintf('/%s/data/initial/medicines/%d/', $version, ++$page ) );
                }
                Response::instance()->addData( 'table', 't_medicines' );
            break;
            
            default:
                Response::instance()->addData( 'next', "/$version/data/initial/companies/1/" );
                break;
        }
        Response::instance()->setStatus( 'success' );
        Response::instance()->send();
        
    }

    public function dataInitial_v1( $table = 'companies', $page = 1 ) {
        $this->dataInitial( 'v1', $table, $page );
    }
    public function dataInitial_v2( $table = 'companies', $page = 1 ) {
        $this->dataInitial( 'v2', $table, $page );
    }
    public function dataCheck( $dbVersion ) {
        $dbVersion = \intval( $dbVersion );
        $current_dbVersion = \intval( Option::get('dbVersion') );
        
        if( $dbVersion === $current_dbVersion ) {
            Response::instance()->sendMessage( 'No data update available' );
        }
        $v = min( $dbVersion+10, $current_dbVersion ); //Send maximum 10 updates in one call
        $data = [];
        for ($i=$dbVersion+1; $i <= $v; $i++) {
            $db_data = Option::get( "dbData_{$i}" );
            if( $db_data && is_array( $db_data ) ) {
                $data = \array_merge_recursive( $data, $db_data );
            }
        }
        Response::instance()->setResponse( 'dbVersion', $v );
        Response::instance()->setData( $data );
        Response::instance()->setStatus( 'success' );
        Response::instance()->send();
    }

    public function home() {
        $carousel = [];
        foreach ( glob( STATIC_DIR . '/images/carousel/*.{jpg,jpeg,png,gif}', GLOB_BRACE ) as $value ) {
            $carousel[] = \str_replace( STATIC_DIR, STATIC_URL, $value ) . '?v=' . @\filemtime($value) ?: 1;
        }
        $deals = [];
        foreach ( [ 27948,27933,27621,27932,27931,27716,27620,27619,28163,28165,28164,27930 ] as $m_id ) {
            if( $medicine = Medicine::getMedicine( $m_id ) ){
                $deals[] = [
                    'id' => $medicine->m_id,
                    'name' => $medicine->m_name,
                    'price' => $medicine->m_price,
                    'd_price' => $medicine->m_d_price,
                    'pic_url' => $medicine->m_pic_url,
                ];
            }
        }
        
        $feature = [];
        foreach ( [ 27931,27932,27360,27336,27369,27525,27716,27619,27458,27374 ] as $m_id ) {
            if( $medicine = Medicine::getMedicine( $m_id ) ){
                $feature[] = [
                    'id' => $medicine->m_id,
                    'name' => $medicine->m_name,
                    'price' => $medicine->m_price,
                    'd_price' => $medicine->m_d_price,
                    'pic_url' => $medicine->m_pic_url,
                ];
            }
        }
        
        Response::instance()->addData( 'carousel', $carousel );
        Response::instance()->addData( 'feature', $feature );
        Response::instance()->addData( 'deals', $deals );
        Response::instance()->setStatus( 'success' );
        Response::instance()->send();
    }

    public function home_v2() {
        $from = isset( $_GET['f'] ) ? preg_replace("/[^a-zA-Z0-9]+/", "",$_GET['f']) : 'app';
        /*
        if ( $cache_data = Cache::instance()->get( $from, 'HomeData' ) ){
            Response::instance()->replaceResponse( $cache_data );
            Response::instance()->send();
        }
        */
        Response::instance()->setResponse( 'refBonus', Functions::changableData('refBonus') );
        Response::instance()->setResponse( 'versions', [
            'current' => '4.2.1',
            'min' => '3.1.1',
            'android' => [
                'current' => '4.2.1',
                'min' => '3.1.1',
            ],
            'ios' => [
                'current' => '4.2.1',
                'min' => '3.1.1',
            ],
        ]);

        $extraData = [
            'yt_video' => [
                'key' => Option::get('yt_video_key'),
                'title' => Option::get('yt_video_title'),
            ],
        ];
        if ( ( $banners = Option::get( 'attachedFilesHomepageBanner' ) ) && is_array( $banners ) ){
            $extraData['banner1']= Functions::getS3Url( $banners[0]['s3key']??'', 1000, 1000 );
        }
        Response::instance()->setResponse( 'extraData', $extraData );
        /*
        Response::instance()->appendData( '', [
            'type' => 'notice',
            'bgColor' => '#FFA07A',
            'color' => '#FF0000',
            'title' => "Due to Covid-19 pandmic some of our delivery is getting delyaed.\nPlease have paitence if your order is not delivered yet.\nWe will reach you ASAP.",
            'data' => [],
        ]);
        */

        $data = [];
        /*
        foreach ( glob( STATIC_DIR . '/images/carousel/*.{jpg,jpeg,png,gif}', GLOB_BRACE ) as $value ) {
            $data[] = \str_replace( STATIC_DIR, STATIC_URL, $value ) . '?v=' . @\filemtime($value) ?: 1;
        }
        */

        $carouselImgType = $from == 'web' ? 'attachedFilesWeb' : 'attachedFilesApp';
        if ( ( $carouselImages = Option::get( $carouselImgType ) ) && is_array( $carouselImages ) ){
            foreach ($carouselImages as $image){
                if( $from == 'web' ){
                    $data[] = Functions::getS3Url( $image['s3key'], 2732, 500 );
                } else {
                    $data[] = Functions::getS3Url( $image['s3key'], 750, 300 );
                }
            }
        }

        if( $data ){
            Response::instance()->appendData( '', [
                'type' => 'carousel',
                'title' => '',
                'data' => $data,
            ]);
        }
        Response::instance()->appendData( '', [
            'type' => 'actions',
            'title' => '',
            'data' => [
                //discount percents
                'order' => (int)Option::get('prescription_percent'),
                'call' => (int)Option::get('call_percent'),
                'healthcare' => (int)Option::get('healthcare_percent'),
                //heading text
                'callTime' => Option::get('call_time'),
            ],
        ]);
        $categories = Functions::getCategories();
        foreach ( $categories as $cat_id => $catName ) {
            $data = [];
            if( ( $cat_m_ids = Option::get( "categories_sidescroll-{$cat_id}" ) ) && is_array( $cat_m_ids ) ){
                $data = $this->medicinesQueryES(['ids' => $cat_m_ids]);
            }
            $data = array_merge( $data, $this->categoryMedicinesES( $cat_id, 15 - count( $data ) ) );
            
            if( $data ){
                Response::instance()->appendData( '', [
                    'type' => "sideScroll-{$cat_id}",
                    'title' => $catName,
                    'cat_id' => $cat_id,
                    'data' => $data,
                ]);
            }
        }
        Response::instance()->setStatus( 'success' );

        //Cache::instance()->set( $from, Response::instance()->getResponse(), 'HomeData', 60 * 60 );

        Response::instance()->send();

    }

    public function medicinesES( $q = '', $page = 0 ) {
        $from = isset( $_GET['f'] ) ? preg_replace("/[^a-zA-Z0-9]+/", "",$_GET['f']) : 'app';
        if( ! $q ){
            $q = isset( $_GET['search'] ) ? $_GET['search'] : '';
        }
        $q = $org_q = mb_strtolower( $q );
        $q = trim( preg_replace('/[^\w\ \.\-]+/', '', $q) );
        if( !$q && $q != $org_q ){
            Response::instance()->sendMessage( 'No medicines Found' );
        }

        if( ! $page ){
            $page = !empty( $_GET['page'] ) ? (int)$_GET['page'] : 1;
        }
        $category = isset( $_GET['category'] ) ? $_GET['category'] : '';
        $cat_id = isset( $_GET['cat_id'] ) ? (int)$_GET['cat_id'] : 0;
        $havePic = !empty( $_GET['havePic'] ) ? true : false;

        if( 'healthcare' == $category || 'web' == $from ){
            $per_page = 12;
        } else {
            $per_page = 10;
        }
        $args = [
            'search' => $q,
            'per_page' => $per_page,
            'limit' => $per_page * ( $page - 1 ),
            'm_status' => 'active',
            'm_category' => $category,
            'm_cat_id' => $cat_id,
            'havePic' => $havePic,
        ];
        $data = \OA\Search\Medicine::init()->search( $args );

        if ( $data && $data['data'] ) {
            Response::instance()->sendData( $data['data'], 'success' );
        } else {
            if( $page > 1 ){
                Response::instance()->sendMessage( 'No more medicines Found' );
            } else {
                Response::instance()->sendMessage( 'No medicines Found' );
            }
        }
    }

    public function categoryMedicinesES( $cat_id, $per_page = 15 ) {
        $args = [
            'per_page' => $per_page,
            'm_cat_id' => $cat_id,
        ];
        return $this->medicinesQueryES( $args );
    }

    public function medicinesQueryES( $args = [] ) {
        $args = array_merge([
            'per_page' => 10,
            'm_status' => 'active',
            'havePic' => true,
            'm_rob' => true,
        ], $args );
        $data = \OA\Search\Medicine::init()->search( $args );

        if ( $data && $data['data'] ) {
            return $data['data'];
        } 
        return [];
    }

    public function medicines( $search = '', $page = 0 ) {
        $this->medicinesES( $search, $page );

        if( ! $search ){
            $search = isset( $_GET['search'] ) ? $_GET['search'] : '';
        }
        if( ! $page ){
            $page = !empty( $_GET['page'] ) ? (int)$_GET['page'] : 1;
        }
        $category = isset( $_GET['category'] ) ? $_GET['category'] : '';
        $cat_id = isset( $_GET['cat_id'] ) ? (int)$_GET['cat_id'] : 0;

        if( 'healthcare' == $category ){
            $per_page = 12;
        } else {
            $per_page = 10;
        }
        $limit    = $per_page * ( $page - 1 );
        $db = new DB;

        $db->add( 'SELECT * FROM t_medicines WHERE 1=1' );
        if ( $search ) {
            $search = preg_replace('/[^a-z0-9\040\.\-]+/i', ' ', $search);

            //$search = \rtrim( addcslashes( $search, '_%\\' ), '-');
            $org_search = $search = \rtrim( \trim(preg_replace('/\s\s+/', ' ', $search ) ), '-' );

            //$db->add( ' AND ( m_name LIKE ? OR m_generic LIKE ? )', "{$search}%", "{$search}%" );
            //$db->add( ' AND m_name LIKE ?', "{$search}%" );
            if( false === \strpos( $search, ' ' ) ){
                $search .= '*';
            } else {
                $search = '+' . \str_replace( ' ', ' +', $search) . '*';
            }
            if( \strlen( $org_search ) > 2 ){
                $db->add( " AND (MATCH(m_name) AGAINST (? IN BOOLEAN MODE) OR m_name LIKE ?)", $search, "{$org_search}%" );
                //$db->add( " AND MATCH(m_name) AGAINST (? IN BOOLEAN MODE)", $search );
            } elseif( $org_search ) {
                $db->add( ' AND m_name LIKE ?', "{$org_search}%" );
            }
        }
        if ( $cat_id ) {
            $db->add( ' AND m_cat_id = ?', $cat_id );
        }
        if( $category ) {
            $db->add( ' AND m_category = ?', $category );
        }
        $db->add( ' AND m_status = ?', 'active' );
        $db->add( ' ORDER BY m_rob DESC, m_category, m_name, m_form DESC, m_strength, m_unit LIMIT ?, ?', $limit, $per_page );
        
        $cache_key = \md5( $db->getSql() . \json_encode($db->getParams()) );
        
        if ( $cache_data = Cache::instance()->get( $cache_key, 'userMedicines' ) ){
            Response::instance()->setData( $cache_data['data'] );
            //Response::instance()->setResponse( 'total', $cache_data['total'] );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        }

        $query = $db->execute();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Medicine');

        while( $medicine = $query->fetch() ){
            $data = [
                'id' => $medicine->m_id,
                'name' => $medicine->m_name,
                'generic' => $medicine->m_generic,
                'strength' => $medicine->m_strength,
                'form' => $medicine->m_form,
                'company' => $medicine->m_company,
                'unit' => $medicine->m_unit,
                'pic_url' => $medicine->m_pic_url,
                'rx_req' => $medicine->m_rx_req,
                'rob' => $medicine->m_rob,
                'comment' => $medicine->m_comment,
                'price' => $medicine->m_price,
                'd_price' => $medicine->m_d_price,
            ];
            Response::instance()->appendData( '', $data );
        }
        if ( $all_data = Response::instance()->getData() ) {
            $cache_data = [
                'data' => $all_data,
                //'total' => $total,
            ];
            //pic_url may change. So cache for sort period of time
            Cache::instance()->set( $cache_key, $cache_data, 'userMedicines', 60 * 60 );

            //Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        } else {
            if( $page > 1 ){
                Response::instance()->sendMessage( 'No more medicines Found' );
            } else {
                Response::instance()->sendMessage( 'No medicines Found' );
            }
        }
    }

    public function sameGeneric( $g_id, $page = 1 ){
        $per_page = 10;
        $limit    = $per_page * ( $page - 1 );

        $db = new DB;
        $db->add( 'SELECT *  FROM t_medicines WHERE 1=1' );
        $db->add( ' AND m_g_id = ?', $g_id );
        $db->add( ' ORDER BY m_rob DESC, m_name, m_form DESC, m_strength, m_unit LIMIT ?, ?', $limit, $per_page );
        $query = $db->execute();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Medicine');

        while( $medicine = $query->fetch() ){
            $data = [
                'id' => $medicine->m_id,
                'name' => $medicine->m_name,
                'generic' => $medicine->m_generic,
                'strength' => $medicine->m_strength,
                'form' => $medicine->m_form,
                'company' => $medicine->m_company,
                'unit' => $medicine->m_unit,
                'pic_url' => $medicine->m_pic_url,
            ];
            Response::instance()->appendData( '', $data );
        }
        if ( ! Response::instance()->getData() ) {
            Response::instance()->sendMessage( 'No medicines Found' );
        } else {
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        }
    }

    public function medicineSingle( $version, $m_id ) {

        $m_id = (int)$m_id;
        if ( ! $m_id ) {
            Response::instance()->sendMessage( 'No medicines Found' );
        }

        if( $medicine = Medicine::getMedicine( $m_id ) ){
            $medicine->incrCount( 'Viewed' );

            Response::instance()->setStatus( 'success' );
            //$price = $medicine->m_price * (intval($medicine->m_unit));
            //$d_price = ( ( $price * 90 ) / 100 );
            $data = [
                'id' => $medicine->m_id,
                'name' => $medicine->m_name,
                'g_id' => $medicine->m_g_id,
                'generic' => $medicine->m_generic,
                'strength' => $medicine->m_strength,
                'form' => $medicine->m_form,
                'c_id' => $medicine->m_c_id,
                'cat_id' => $medicine->m_cat_id,
                'company' => $medicine->m_company,
                'unit' => $medicine->m_unit,
                'price' => $medicine->m_price,
                'd_price' => $medicine->m_d_price,
                'pic_url' => $medicine->m_pic_url,
                'pic_urls' => $medicine->m_pic_urls,
                'rob' => $medicine->m_rob,
                'rx_req' => $medicine->m_rx_req,
                'r_bought' => $medicine->getCount( 'Viewed' ),
                'comment' => $medicine->m_comment,
                'category' => $medicine->m_category,
				'min' => $medicine->m_min,
				'max' => $medicine->m_max,
                'cold' => (bool) $medicine->m_cold,
                'note1' => $medicine->m_cold ? 'শুধুমাত্র ঢাকা শহরে ডেলিভারি হবে।' : 'সারা বাংলাদেশ থেকে অর্ডার করা যাবে।',
                //'note' => 'Use coupon code "arogga11" at checkout to get 11% cashback',
            ];
            if( 'v1' == $version ){
                $data['description'] = $medicine->m_description;
            } elseif ( 'v2' == $version ) {
                if( 'allopathic' == $medicine->m_category ){
                    $data['description'] = $medicine->m_description_v2;
                } else {
                    $data['description'] = [ 'html' => (string)$medicine->getMeta( 'description' ) ];
                }
            }
            //$data['sideScroll'] = $this->getSingleMedicineSideScroll( $medicine );

            Response::instance()->addData( 'medicine', $data );
            if( in_array( $version, ['v1', 'v2'] ) ){
                Response::instance()->addData( 'same_generic', $this->getSingleMedicineSameGeneric( 'v1', $medicine ) );
            }
            
        } else {
            Response::instance()->sendMessage( 'No medicines Found' );
        }

        Response::instance()->send();
    }

    private function getSingleMedicineSideScroll( $medicine ){
        if( ! $medicine || 'allopathic' == $medicine->m_category ){
            return [];
        }
        if ( $cache_data = Cache::instance()->get( 'singleMedicineSideScroll', 'userSameGeneric' ) ){
            return $cache_data;
        }
        $data = [];
        $query = DB::db()->prepare( 'SELECT tm.m_id, tm.m_name, tm.m_price, tm.m_d_price, COUNT(*) c FROM t_medicines tm INNER JOIN t_o_medicines tom ON tm.m_id = tom.m_id  WHERE tm.m_status = ? AND tm.m_category = ? AND tm.m_rob = ? AND tom.om_status = ? GROUP BY tom.m_id ORDER BY c DESC LIMIT 10' );
        $query->execute( [ 'active', 'healthcare', 1, 'available' ] );
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Medicine');
        while( $ss_medicine = $query->fetch() ){
            $data[] = [
                'id' => $ss_medicine->m_id,
                'name' => $ss_medicine->m_name,
                'price' => $ss_medicine->m_price,
                'd_price' => $ss_medicine->m_d_price,
                'pic_url' => $ss_medicine->m_pic_url,
            ];
        }
        if( $data ){
            Cache::instance()->set( 'singleMedicineSideScroll', $data, 'userSameGeneric', 60 * 60 );
        }

        return $data;
    }

    private function getSingleMedicineSameGeneric( $version, $medicine ){
        if( ! $medicine || ! $medicine->m_g_id ){
            return [];
        }
        if ( $cache_data = Cache::instance()->get( $medicine->m_id, 'userSameGeneric' ) ){
            return $cache_data;
        }
        $data = [];
        $db = new DB;

        $db->add( 'SELECT * FROM t_medicines WHERE m_g_id = ?', $medicine->m_g_id );
        $db->add( ' AND m_strength = ? AND m_form = ? AND m_status = ? AND m_id <> ?', $medicine->m_strength, $medicine->m_form, 'active', $medicine->m_id );
        if( 'v1' == $version ){
            $db->add( ' AND m_unit = ?', $medicine->m_unit );
        }
        $db->add( ' ORDER BY m_rob DESC, m_name LIMIT ?', 50 );
        $query = $db->execute();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Medicine');

        while( $sg_medicine = $query->fetch() ){
            $data[] = [
                'id' => $sg_medicine->m_id,
                'name' => $sg_medicine->m_name,
                'form' => $sg_medicine->m_form,
                'unit' => $sg_medicine->m_unit,
                'company' => $sg_medicine->m_company,
                'price' => $sg_medicine->m_price,
                'd_price' => $sg_medicine->m_d_price,
                'rob' => $sg_medicine->m_rob,
                'strength' => $sg_medicine->m_strength,
                //'pic_url' => $sg_medicine->m_pic_url,
            ];
        }
        if( $data ){
            Cache::instance()->set( $medicine->m_id, $data, 'userSameGeneric', 60 * 60 * 24 );
        }

        return $data;
    }

    public function medicineSingle_v1( $m_id ) {
        $this->medicineSingle( 'v1', $m_id );
     }

    public function medicineSingle_v2( $m_id ) {
       $this->medicineSingle( 'v2', $m_id );
    }

    public function medicineSingle_v3( $m_id ) {
        $this->medicineSingle( 'v3', $m_id );
     }

     public function medicineSingleExtra_v1( $m_id ){
         $this->medicineSingleExtra( 'v1', $m_id );
     }

     public function medicineSingleExtra_v2( $m_id ){
        $this->medicineSingleExtra( 'v2', $m_id );
    }

     public function medicineSingleExtra( $version, $m_id ){
        if( $medicine = Medicine::getMedicine( $m_id ) ){
            $description = [];
            if( 'allopathic' == $medicine->m_category ){
                $description = $medicine->m_description_v2;
                if( ! empty( $description['g_quick_tips'] ) ){
                    $description['brief_description'] = $medicine->m_description_dims;
                }
            } else {
                $description = [ 'html' => (string)$medicine->getMeta( 'description' ) ];
            }

            Response::instance()->addData( 'description', $description );
            Response::instance()->addData( 'same_generic', $this->getSingleMedicineSameGeneric( $version, $medicine ) );
            if ( ( $banners = Option::get( 'attachedFilesUnderProductBanner' ) ) && is_array( $banners ) ){
                Response::instance()->addData( 'banner_1', Functions::getS3Url( $banners[0]['s3key']??'', 750, 300 ) );
            }
            //Response::instance()->addData( 'similiar', $this->medicinesQueryES( ['m_cat_id' => $medicine->m_cat_id] ) );
            Response::instance()->setStatus( 'success' );
        } else {
            Response::instance()->sendMessage( 'No medicines Found' );
        }
        Response::instance()->send();
     }

    public function medicinePrice( $m_ids ) {
        $m_ids = \array_filter( \array_map( 'trim', \explode( ',', $m_ids ) ) );
        $in  = str_repeat('?,', count($m_ids) - 1) . '?';
        if ( ! $m_ids ) {
            Response::instance()->sendMessage( 'No medicines Found' );
        }

        $query = DB::db()->prepare( "SELECT * FROM t_medicines WHERE m_id IN ($in)" );
        $query->execute( $m_ids );
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Medicine');
        while( $medicine = $query->fetch() ){
            Response::instance()->setStatus( 'success' );
            //$price = $medicine->m_price * (intval($medicine->m_unit));
            //$d_price = ( ( $price * 90 ) / 100 );
            $data = [
                'price' => $medicine->m_price,
                'd_price' => $medicine->m_d_price,
                //'d_price' => \round( $medicine->m_d_price, 2 ),
                'pic_url' => $medicine->m_pic_url,
                'rx_req' => $medicine->m_rx_req,
                //'r_bought' => \rand( 100,800),
                'r_bought' => $medicine->m_rob ? \round( $medicine->m_id % 70 + \floor(\time()/3600) - 439625 ) : 0,
                'rob' => $medicine->m_rob,
                'comment' => $medicine->m_comment,
            ];
            Response::instance()->addData( $medicine->m_id, $data );
        }
        Response::instance()->send();
    }

    public function medicineSuggest() {
        $name = isset( $_POST['name'] ) ? \strip_tags( $_POST['name'] ) : '';
        $generic = isset( $_POST['generic'] ) ? \strip_tags( $_POST['generic'] ) : '';
        $strength = isset( $_POST['strength'] ) ? \strip_tags( $_POST['strength'] ) : '';
        $form = isset( $_POST['form'] ) ? \strip_tags( $_POST['form'] ) : '';
        $company = isset( $_POST['company'] ) ? \strip_tags( $_POST['company'] ) : '';

        if ( ! $name || ! $form ){
            Response::instance()->sendMessage( 'Name and Form are required.');
        }
        if ( ! Auth::id() ){
            Response::instance()->loginRequired( true );
            Response::instance()->sendMessage( 'Login required.');
        }
        $medicine = new Medicine;
        $medicine->m_name = $name;
        //$medicine->m_generic = $generic;
        $medicine->m_strength = $strength;
        $medicine->m_form = $form;
        //$medicine->m_company = $company;
        $medicine->m_status = 'suggested';
        $medicine->m_u_id = Auth::id();
        $medicine->insert();
        Response::instance()->sendMessage( 'Thank you. we will manually review this medicine and update.', 'success' );
    }

    function dicountCheck() {
        $d_code = isset( $_POST['d_code'] ) ?  $_POST['d_code'] : '';

        if ( ! $d_code ){
            Response::instance()->sendMessage( 'd_code is required.');
        }
        if ( ! ( $user = User::getUser( Auth::id() ) ) ) {
            Response::instance()->loginRequired( true );
            Response::instance()->sendMessage( 'Invalid id token' );
        }
        $discount = Discount::getDiscount( $d_code );
        if ( $discount ) {
            if( $discount->canUserUse( $user->u_id ) ){
                $data = [
                    'code' => $d_code,
                    'type' => $discount->d_type,
                    'amount' => $discount->d_amount,
                    'max'    => $discount->d_max,
                ];
                Response::instance()->sendData( $data, 'success' );
            } elseif( in_array( $discount->d_type, [ 'firstPercent', 'firstFixed' ] ) ){
                Response::instance()->sendMessage( 'এই কোডটি শুদুমাত্র প্রথম অর্ডারের জন্য প্রজোয্য।');
            }
        }
        Response::instance()->sendMessage( 'wrong discount code.');
    }

    function cartDetails() {
        $medicines = isset( $_POST['medicines'] ) ?  $_POST['medicines'] : '';
        $d_code = isset( $_POST['d_code'] ) ?  $_POST['d_code'] : '';
        $s_address = ( isset( $_POST['s_address'] ) && is_array($_POST['s_address']) )? $_POST['s_address']: [];

        if ( ! $medicines ){
            Response::instance()->sendMessage( 'medicines are required.');
        }
        if ( ! is_array( $medicines ) ){
            Response::instance()->sendMessage( 'medicines need to be an array with m_id as key and quantity as value.');
        }

        Response::instance()->sendData( Functions::cartData( User::getUser( Auth::id() ), $medicines, $d_code, null, false, ['s_address' => $s_address] ), 'success' );
    }

    function checkoutInitiated(){
        if ( ! ( $user = User::getUser( Auth::id() ) ) ) {
            Response::instance()->loginRequired( true );
            Response::instance()->sendMessage( 'Invalid id token' );
        }

        $s_address = ( isset( $_GET['s_address'] ) && is_array($_GET['s_address']) )? $_GET['s_address']: [];

        $data = [];

        /*
        if( $s_address && 'Dhaka City' == $s_address['district'] ){
            $data['note'] = '* Estimated Delivery Time: 12-48 hours';
        } else {
            $data['note'] = '* Estimated Delivery Time: 1-5 days';
        }
        */
		$data['maxCod'] = 20000;
        $data['note'] = "* Estimated Delivery Time for Dhaka 12-48 Hours\n* Estimated Delivery time for Outside Dhaka 1-5 Days";

        //$data['note'] = "ঈদ মোবারক! ঈদের ছুটিতে ফার্মাসিউটিক্যাল কোম্পানিগুলো বন্ধ থাকায় সম্মানিত গ্রাহকদের অর্ডার ডেলিভারি বিলম্বিত হচ্ছে।\nতাই ১১-১৫ তারিখ পর্যন্ত আমরা অর্ডার নেওয়া বন্ধ রাখছি।\nসম্মানিত গ্রাহকদের সাময়িক এই অসুবিধার জন্য আমরা আন্তরিকভাবে দুঃখিত। আনন্দে কাটুক সবার ঈদ।\n\nEid Mubarak! Due to Eid holiday, the pharmaceutical companies will be closed during the period 11th - 15th May. We will not be taking orders during this period and will resume delivery from the 15th of May. We sincerely apologize for the inconvenience caused. Wishing everyone a happy and safe Eid festivities!";

        Response::instance()->sendData( $data, 'success' );
    }

    function closest( $role, $lat, $long ) {
        if( ! $role || ! $lat || ! $long ) {
            return 0;
        }
        $query = DB::db()->prepare( "SELECT u_id FROM t_users WHERE u_role = ? AND u_status = ? ORDER BY (ABS(u_lat - ?) + ABS(u_long - ?)) ASC LIMIT 1" );
        $query->execute( [ $role, 'active', $lat, $long ] );
        if( $u_id = $query->fetchColumn() ){
            return $u_id;
        } else {
            return 0;
        }
    }

    function orderAdd() {
        //Response::instance()->sendMessage( "ঈদ মোবারক! ঈদের ছুটিতে ফার্মাসিউটিক্যাল কোম্পানিগুলো বন্ধ থাকায় সম্মানিত গ্রাহকদের অর্ডার ডেলিভারি বিলম্বিত হচ্ছে।\nতাই ১১-১৫ তারিখ পর্যন্ত আমরা অর্ডার নেওয়া বন্ধ রাখছি।\nসম্মানিত গ্রাহকদের সাময়িক এই অসুবিধার জন্য আমরা আন্তরিকভাবে দুঃখিত। আনন্দে কাটুক সবার ঈদ।");
        //Response::instance()->sendMessage( "Dear valued clients.\nOur Dhaka city operation will resume from 29th November 2020.\nThanks for being with Arogga.");
        //Response::instance()->sendMessage( "Due to some unavoidable circumstances we cannot take orders now. We will send you a notification once we start taking orders.\nSorry for this inconvenience.");
        //Response::instance()->sendMessage( "Due to covid19 outbreak, there is a severe short supply of medicine.\nUntil regular supply of medicine resumes, we may not take anymore orders.\nSorry for this inconvenience.");
        //Response::instance()->sendMessage( "Due to Software maintainance we cannot receive orders now.\nPls try after 24 hours. We will be back!!");
        //Response::instance()->sendMessage( "Due to Software maintainance we cannot receive orders now.\nPlease try again after 2nd Jun, 11PM. We will be back!!");
        //Response::instance()->sendMessage( "Due to recent coronavirus outbreak, we are facing delivery man shortage.\nOnce our delivery channel is optimised, we may resume taking your orders.\nThanks for your understanding.");
        //Response::instance()->sendMessage( "Due to EID holiday we cannot receive orders now.\nPlease try again after EID. We will be back!!");
        //Response::instance()->sendMessage( "Due to EID holiday we cannot receive orders now.\nPlease try again after 28th May, 10PM. We will be back!!");

        $from = isset( $_GET['f'] ) ? preg_replace("/[^a-zA-Z0-9]+/", "",$_GET['f']) : 'app';
        $medicines = ( isset( $_POST['medicines'] ) && is_array( $_POST['medicines'] ) ) ?  $_POST['medicines'] : [];
        $d_code = isset( $_POST['d_code'] ) ?  $_POST['d_code'] : '';
        $prescriptions = isset( $_FILES['prescriptions'] ) ? $_FILES['prescriptions'] : [];
        $prescriptionKeys = ( isset( $_POST['prescriptionKeys'] ) && is_array( $_POST['prescriptionKeys'] ) ) ?  $_POST['prescriptionKeys'] : [];

        $name = isset( $_POST['name'] ) ?  filter_var($_POST['name'], FILTER_SANITIZE_STRING) : '';
        $mobile = isset( $_POST['mobile'] ) ?  filter_var($_POST['mobile'], FILTER_SANITIZE_STRING) : '';
        $address = isset( $_POST['address'] ) ?  filter_var($_POST['address'], FILTER_SANITIZE_STRING) : '';
        $lat = isset( $_POST['lat'] ) ?  filter_var($_POST['lat'], FILTER_SANITIZE_STRING) : '';
        $long = isset( $_POST['long'] ) ?  filter_var($_POST['long'], FILTER_SANITIZE_STRING) : '';
        $gps_address = isset( $_POST['gps_address'] ) ?  filter_var($_POST['gps_address'], FILTER_SANITIZE_STRING) : '';
        $s_address = ( isset( $_POST['s_address'] ) && is_array($_POST['s_address']) )? $_POST['s_address']: [];
        $monthly = !empty( $_POST['monthly'] ) ?  1 : 0;
        $payment_method = ( isset( $_POST['payment_method'] ) && in_array($_POST['payment_method'], ['cod', 'online']) ) ? $_POST['payment_method'] : 'cod';

        if ( ! $name || ! $mobile ){
            Response::instance()->sendMessage( 'name and mobile are required.');
        }
        $lat = $long = '';

        if ( ! $address && ( ! $lat || ! $long ) && ! $s_address ){
            Response::instance()->sendMessage( 'Address is required.');
        }

        if ( $s_address && ! Functions::isLocationValid( @$s_address['division'], @$s_address['district'], @$s_address['area'] ) ){
            Response::instance()->sendMessage( 'invalid location.');
        }

        /*
        if ( $lat && $long ){
            if( Functions::isInside( $lat, $long, 'chittagong' ) ){
                Response::instance()->sendMessage( "Our Chattogram operation temporarily off due to some unavoidable circumstances. We will send you a notification once our Chattogram operation resumes.\nSorry for this inconvenience.");
            }
            if( ! Functions::isInside( $lat, $long ) ){
                Response::instance()->sendMessage( "Our delivery service comming to this area very soon, please stay with us.");
            }
        }
        */
        if ( ! $medicines && ! $prescriptions && !$prescriptionKeys ){
            Response::instance()->sendMessage( 'medicines or prescription are required.');
        }
        if ( $medicines && ! is_array( $medicines ) ){
            Response::instance()->sendMessage( 'medicines need to be an array with m_id as key and quantity as value.');
        }
        if ( $prescriptions && ! is_array( $prescriptions ) ){
            Response::instance()->sendMessage( 'prescription need to be an file array.');
        }
        if ( ! ( $user = User::getUser( Auth::id() ) ) ) {
            Response::instance()->loginRequired( true );
            Response::instance()->sendMessage( 'Invalid id token' );
        }
        if ( 'blocked' == $user->u_status ){
            Response::instance()->sendMessage( 'You are blocked. Please contact customer care.');
        }
        if ( 'user' !== $user->u_role ){
            Response::instance()->sendMessage( 'You cannot make order using this number.');
        }
        $order_check = DB::db()->prepare( 'SELECT COUNT(*) FROM t_orders WHERE u_id = ? AND o_status = ?' );
        $order_check->execute( [ Auth::id(), 'processing' ] );
        if( $order_check->fetchColumn() >= 3  ){
            Response::instance()->sendMessage( 'Please wait until your current orders are confirmed. After that you can submit another order OR call customer care if you need further assistance.');
        }
        $discount = Discount::getDiscount( $d_code );

        if( ! $discount || ! $discount->canUserUse( $user->u_id ) ) {
            $d_code = '';
        }
        if ( $name && !$user->u_name ) {
            $user->u_name = $name;
        }
        if ( ! $user->u_mobile && $mobile ) {
            $m_user = User::getBy( 'u_mobile', $mobile );
            if ( $m_user ) {
                Response::instance()->sendMessage( 'Sorry for this but this number is already registered with another account. Please sign in with that account if it is you or login with your own phone number.');
            } else {
                $user->u_mobile = $mobile;
            }
        }
        if ( $lat && $user->u_lat != $lat ) {
            $user->u_lat = $lat;
        }
        if ( $long &&  $user->u_long != $long ) {
            $user->u_long = $long;
        }

        $files_to_save = [];
        if ( $prescriptions ) {
            if ( empty( $prescriptions['tmp_name'] ) || ! is_array( $prescriptions['tmp_name'] ) ) {
                Response::instance()->sendMessage( 'prescription need to be an file array.');
            }
            if ( count( $prescriptions['tmp_name'] ) > 5 ) {
                Response::instance()->sendMessage( 'Maximum 5 prescription pictures allowed.');
            }
            $i = count( $prescriptionKeys ) ?: 1;
            foreach( $prescriptions['tmp_name'] as $key => $tmp_name ) {
                if( $i > 5 ){
                    break;
                }
                if( ! $tmp_name ) {
                    continue;
                }
                if ( UPLOAD_ERR_OK !== $prescriptions['error'][$key] ) {
                    Response::instance()->sendMessage( \sprintf('Upload error occured when upload %s. Please try again', \strip_tags( $prescriptions['name'][$key] ) ) );
                }
                $size = \filesize( $tmp_name );
                if( $size < 12 ) {
                    Response::instance()->sendMessage( \sprintf('File %s is too small.', \strip_tags( $prescriptions['name'][$key] ) ) );
                } elseif ( $size > 10 * 1024 * 1024 ) {
                    Response::instance()->sendMessage( \sprintf('File %s is too big. Maximum size is 10MB.', \strip_tags( $prescriptions['name'][$key] ) ) );
                }
                $imagetype = exif_imagetype( $tmp_name );
                $mime      = ( $imagetype ) ? image_type_to_mime_type( $imagetype ) : false;
                $ext       = ( $imagetype ) ? image_type_to_extension( $imagetype ) : false;
                if( ! $ext || ! $mime ) {
                    Response::instance()->sendMessage( 'Only prescription pictures are allowed.');
                }
                $files_to_save[ $tmp_name ] = ['name' => $i++ . Functions::randToken( 'alnumlc', 12 ) . $ext, 'mime' => $mime ];
            }
        }

        $cart_data = Functions::cartData( $user, $medicines, $d_code, null, false, ['s_address' => $s_address] );
        if ( ! empty( $cart_data['rx_req'] ) && ! $files_to_save ) {
            Response::instance()->sendMessage( 'Rx required.');
        }
        if( isset($cart_data['deductions']['cash']) && !empty($cart_data['deductions']['cash']['info'])) {
            $cart_data['deductions']['cash']['info'] = "Didn't apply because the order value was less than ৳499.";
        }
        if( isset($cart_data['additions']['delivery']) && !empty($cart_data['additions']['delivery']['info'])) {
            $cart_data['additions']['delivery']['info'] = str_replace('To get free delivery order more than', 'Because the order value was less than', $cart_data['additions']['delivery']['info']);
        }
        $c_medicines = $cart_data['medicines'];
        unset( $cart_data['medicines'] );

        $order = new Order;
        $order->u_id = $user->u_id;
        $order->u_name = $user->u_name;
        $order->u_mobile = $user->u_mobile;
        $order->o_subtotal = $cart_data['subtotal'];
        $order->o_addition = $cart_data['a_amount'];
        $order->o_deduction = $cart_data['d_amount'];
        $order->o_total = $cart_data['total'];
        $order->o_status = 'processing';
        $order->o_i_status = 'processing';
        $order->o_address = $address;
        $order->o_gps_address = $gps_address;
        $order->o_lat = $lat;
        $order->o_long = $long;
        $order->o_payment_method = $payment_method;

        /*
        if( $p_id = $this->closest( 'pharmacy', $lat, $long ) ) {
            $order->o_ph_id = $p_id;
        }
        */
        //Currently we have only one pharmacy
        $order->o_ph_id = 6139;

        if( !isset( $s_address['district'] ) ){
            if( $d_id = $this->closest( 'delivery', $lat, $long ) ){
                $order->o_de_id = $d_id;
            }
        } elseif( $s_address['district'] != 'Dhaka City' ){
            //Outside Dhaka delivery ID
            $order->o_de_id = 143;
            $order->o_payment_method = 'online';
        } elseif( $d_id = Functions::getIdByLocation( 'l_de_id', $s_address['division'], $s_address['district'], $s_address['area'] ) ) {
            $order->o_de_id = $d_id;
        }
        if( isset( $s_address['district'] ) ){
            $order->o_l_id = Functions::getIdByLocation( 'l_id', $s_address['division'], $s_address['district'], $s_address['area'] );
        }
        $user->update();
        $order->insert();
        Functions::ModifyOrderMedicines( $order, $c_medicines );
        $meta = [
            'o_data' => $cart_data,
            'o_secret' => Functions::randToken( 'alnumlc', 16 ),
            's_address' => $s_address,
            'from' => $from,
        ];
        if( $d_code ) {
            $meta['d_code'] = $d_code;
        }
        if( $monthly ) {
            $meta['subscriptionFreq'] = 'monthly';
        }

        $imgArray = [];
        if ( $files_to_save ) {
            $upload_folder = STATIC_DIR . '/orders/' . \floor( $order->o_id / 1000 );

            if ( ! is_dir($upload_folder)) {
                @mkdir($upload_folder, 0755, true);
            }

            foreach ( $files_to_save as $tmp_name => $file ) {
                $fileName = \sprintf( '%s-%s', $order->o_id, $file['name'] );
                $s3key = Functions::uploadToS3( $order->o_id, $tmp_name, 'order', $fileName, $file['mime'] );
                if ( $s3key ){
                    array_push( $imgArray, $s3key );
                }
            }
            if ( count($imgArray) ){
                $oldMeta = $user->getMeta( 'prescriptions' );
                $user->setMeta( 'prescriptions', ( $oldMeta && is_array($oldMeta ) ) ? array_merge( $oldMeta, $imgArray ) : $imgArray );
           }
        }
        if( $prescriptionKeys ){
            $oldMeta = $user->getMeta( 'prescriptions' );

            foreach ( $prescriptionKeys as $prescriptionKey ) {
                if( !$prescriptionKey || !$oldMeta || !in_array( $prescriptionKey, $oldMeta ) ){
                    continue;
                }
                $imgNameArray = explode( '-', $prescriptionKey );
                $imgName = end( $imgNameArray );

                $fileName = \sprintf( '%s-%s', $order->o_id, $imgName );
                $s3key = Functions::uploadToS3( $order->o_id, '', 'order', $fileName, '', $prescriptionKey );
                if ( $s3key ){
                    array_push( $imgArray, $s3key );
                }
            }
        }
        if ( count($imgArray) ){
             $meta['prescriptions'] = $imgArray;
        }

        $order->insertMetas( $meta );
        $order->addHistory( 'Created', sprintf( 'Created through %s', $from ) );
        //Get user again, User data may changed
        $user = User::getUser( Auth::id() );
		$cash_back = $order->cashBackAmount();

        $message = 'Order added successfully.';
        if ( !$medicines && $files_to_save ) {
            $message = "Thank you for submitting prescription. You will receive a call shortly from our representatives.\nNote: Depending on the order value,  you may receive cashback from arogga.";
        } else {
            if ( $cash_back ) {
                $user->u_p_cash = $user->u_p_cash + $cash_back;
                $message = "Congratulations!!! You have received a cashback of ৳{$cash_back} from arogga. The cashback will be automatically applied at your next order.";
                Functions::sendNotification( $user->fcm_token, 'Cashback Received.', $message );
            }
        }
        
        if( isset($cart_data['deductions']['cash']) ){
            $user->u_cash = $user->u_cash - $cart_data['deductions']['cash']['amount'];
        }

        $user->update();
        $u_data = $user->toArray();
        $u_data['authToken'] = $user->authToken();

        $o_data = [
            'o_id' => $order->o_id,
        ];

        Response::instance()->setStatus( 'success' );
        Response::instance()->setMessage( $message );
        Response::instance()->addData( 'user', $u_data );
        Response::instance()->addData( 'order', $o_data );
        Response::instance()->send();
    }

    function orderSingle( $o_id ) {
        if ( ! ( $user = User::getUser( Auth::id() ) ) ) {
            Response::instance()->loginRequired( true );
            Response::instance()->sendMessage( 'Invalid id token' );
        }
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        $allowed_ids = [
            $order->u_id,
            $order->o_de_id,
            $order->o_ph_id
        ];
        if( 'packer' == $user->u_role && $order->o_ph_id == $user->getMeta( 'packer_ph_id' ) ) {
            $allowed_ids[] = Auth::id();
        }
        if( ! \in_array( Auth::id(), array_unique( $allowed_ids ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        
        $data = $order->toArray();
        $data['prescriptions'] = $order->prescriptions;
        $data['o_data'] = (array)$order->getMeta( 'o_data' );
        $data['o_data']['medicines'] = $order->medicines;
        $data['s_address'] = $order->getMeta('s_address')?:[];
        $data['timeline'] = $order->timeline();
        $data['cancelable'] = $order->isCancelable();
		//$data['refund'] = $order->getMeta( 'refund' ) ? round( $order->getMeta( 'refund' ), 2 ) : 0;
        
        if ( 'user' !== $user->u_role && $order->o_l_id && ( $l_zone = Location::getValueByLocationId( $order->o_l_id, 'zone' ) ) ){
            $b_id = $order->getMeta( 'bag' );
            if( $b_id && ( $bag = Bag::getBag( $b_id ) ) ){
                $data['zone'] = $bag->fullZone();
            } else {
                $data['zone'] = $l_zone;
            }
        }

        $data['invoiceUrl'] = $order->signedUrl( '/v1/invoice' );
        $data['paymentStatus'] = ( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ) ? 'paid' : '';
        if( \in_array( $order->o_status, [ 'confirmed', 'delivering', 'delivered' ] ) && \in_array( $order->o_i_status, ['packing', 'checking', 'confirmed'] ) && 'paid' !== $order->getMeta( 'paymentStatus' ) ){
            $data['paymentUrl'] = $order->signedUrl( '/payment/v1' );
        }

        if( 'user' !== $user->u_role ) {
            $data['o_i_note'] = (string)$order->getMeta('o_i_note');
        }

        if( 'pharmacy' == $user->u_role ) {
            $query = DB::db()->prepare( 'SELECT SUM(s_price*m_qty) FROM t_o_medicines WHERE o_id = ? AND om_status = ?' );
            $query->execute( [ $order->o_id, 'available' ] );
            $sum = $query->fetchColumn();
            $data['o_data']['a_message'] = "Pharmacy Total = $sum";
        }
        if( \in_array( $user->u_role, [ 'pharmacy' ] ) ) {
            $data['o_de_name'] = User::getName( $order->o_de_id );
        }
        Response::instance()->sendData( $data, 'success' );
    }

    public function orderUpdate( $o_id ){
        if ( ! ( $user = User::getUser( Auth::id() ) ) ) {
            Response::instance()->loginRequired( true );
            Response::instance()->sendMessage( 'Invalid id token' );
        }
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        if( ! $order->isCancelable() ){
            Response::instance()->sendMessage( 'You cannot cancel this order anymore.' );
        }
        $allowed_ids = [
            $order->u_id,
            //$order->o_de_id,
            //$order->o_ph_id,
        ];
        if( 'packer' == $user->u_role && $order->o_ph_id == $user->getMeta( 'packer_ph_id' ) ) {
            $allowed_ids[] = Auth::id();
        }
        if( ! \in_array( Auth::id(), array_unique( $allowed_ids ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        $action = $_POST['action'] ?? '';
        if( 'cancel' === $action ){
            $order->update( ['o_status' => 'cancelled'] );
            $order->appendMeta( 'o_note', 'Cancelled by customer' );
            $this->orderSingle( $o_id );
        }
        Response::instance()->sendMessage( 'Something went wrong, Please try again.' );
    }

    public function invoiceGenerate( $order ) {
        if ( ! ( $user = User::getUser( $order->u_id ) ) ) {
            return false;
        }

        $o_data = (array)$order->getMeta( 'o_data' );
        $deductions = isset( $o_data['deductions'] ) ? $o_data['deductions'] : [];
        $additions = isset( $o_data['additions'] ) ? $o_data['additions'] : [];
        $address = $order->o_gps_address;
        if( $order->o_gps_address && $order->o_address ){
            $address .= "\n";
        }
        $address .= $order->o_address;

        $pdf = new PDF();
        $pdf->paidAmount = ( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ) ? $order->o_total : 0 ;
        $pdf->SetTitle( 'Invoice-' . $order->o_id . '.pdf' );
        $pdf->AliasNbPages();
        $pdf->AddPage();

        $pdf->SetFont('Times','B',9);

        $pdf->Cell(63,5,'Bill From', 0, 0, 'L');
        $pdf->Cell(23,5,'', 0, 0, 'L');
        $pdf->Cell(41,5,'', 0, 0, 'L');
        $pdf->Cell(63,5,'Bill To', 0, 1, 'L');

        $pdf->SetFont('Times','',9);

        $pdf->Cell(63,5,'Arogga Limited', 0, 0, 'L');
        $pdf->Cell(23,5,'Order ID:', 0, 0, 'L');
        $pdf->Cell(41,5,$order->o_id, 0, 0, 'L');
        $pdf->Cell(63,5,$user->u_name, 0, 1, 'L');

        $pdf->Cell(63,5,'+8801810117100', 0, 0, 'L');
        $pdf->Cell(23,5,'Order Date:', 0, 0, 'L');
        $pdf->Cell(41,5, \date('d/m/Y', \strtotime($order->o_created) ), 0, 0, 'L');
        $pdf->Cell(63,5,$user->u_mobile, 0, 1, 'L');
        
        $pdf->Cell(63,5,'www.arogga.com', 0, 0, 'L');
        $pdf->Cell(23,5,'Invoice Date:', 0, 0, 'L');
        $pdf->Cell(41,5,\date('d/m/Y'), 0, 0, 'L');
        $pdf->MultiCell(63,5,$address, 0, 'L');

        $pdf->Ln(10);

        $pdf->SetFont('Times','B',8);

        $pdf->Cell(20,5,'SL No.', 1, 0, 'C');
        $pdf->Cell(65,5,'Medicine', 1, 0, 'C');
        $pdf->Cell(30,5,'Quantity', 1, 0, 'C');
        $pdf->Cell(25,5,'MRP', 1, 0, 'C');
        $pdf->Cell(25,5,'Discount', 1, 0, 'C');
        $pdf->Cell(25,5,'Amount', 1, 1, 'C');

        $pdf->SetFont('Times','',8);

        $pdf->SetWidths([20,65,30,25,25,25]);
        $pdf->SetAligns(['C','L','L','R','R','R']);
        $i = 1;
        foreach ( $order->medicines as $medicine ) {
            $pdf->Row( [
                $i++,
                \rtrim( $medicine['name'] . '-' . $medicine['strength'], '-' ),
                Functions::qtyText( $medicine['qty'], $medicine),
                $medicine['price'],
                \round( $medicine['price']-$medicine['d_price'], 2),
                $medicine['d_price'],
            ]);
        }

        $pdf->Ln(10);

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->Image( STATIC_DIR . '/play_app_store.png',null,null,40);
        $pdf->SetXY($x,$y);

        $pdf->SetWidths([60,30]);
        $pdf->SetAligns(['L','R']);

        $pdf->Cell(100);
        $pdf->Row( [ 'Subtotal', $order->o_subtotal ] );

        foreach ( $deductions as $deduction ) {
            $pdf->Cell(100);
            $pdf->Row( [ $deduction['text'] ."\n". str_replace( '৳', '', $deduction['info'] ), '-' . $deduction['amount'] ] );
        }
        if( $additions ) {
            $pdf->Cell(100);
            $pdf->Row( [ 'Total order value', $order->o_total - $order->o_addition ] );
        }

        foreach ($additions as $addition ) {
            $pdf->Cell(100);
            $pdf->Row( [ $addition['text'] ."\n". str_replace( '৳', '', $addition['info'] ), $addition['amount'] ] );
        }
        $pdf->SetFont('Times','B',8);
        $pdf->Cell(100);
        $pdf->Row( [ 'Amount Payable', $order->o_total . ( $pdf->paidAmount ? ' (Paid)' : '' ) ] );

        // cashback
        //if( isset($o_data['cash_back']) && $o_data['cash_back'] ){
            $amount = $o_data['cash_back']??'00';
            $pdf->Ln(40);
            $pdf->SetFont('Arial', 'B', 20);
            $pdf->Cell(190,5, $amount . ' Taka Cashback Rewarded For This Order', 0, 1, 'C');
            $pdf->Ln(3);
            $pdf->SetFont('Arial', 'I', 10);
            $pdf->Cell(190,5,'* N.B: This cashback will be applicable at your next Order', 0, 1, 'C');
        //}

        @mkdir( STATIC_DIR . '/temp', 0755, true );

        $pdf->Output( 'F', STATIC_DIR . '/temp/Invoice-' . $order->o_id . '.pdf' );
        return STATIC_DIR . '/temp/Invoice-' . $order->o_id . '.pdf';
    }

    public function invoice( $o_id, $token ) {
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        if( ! $order->validateToken( $token ) ){
            Response::instance()->sendMessage( 'Invalid request' );
        }

        if ( ! ( $user = User::getUser( $order->u_id ) ) ) {
            Response::instance()->sendMessage( 'No order user found.' );
        }
        if( ! ( $invoice = $this->invoiceGenerate( $order ) ) ){
            Response::instance()->sendMessage( 'No invoices generated.' );
        }

        header('Content-Type: application/pdf');
        header( sprintf( 'Content-Disposition: inline; filename="%s"', 'Invoice-' . $order->o_id . '.pdf' ) );
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        readfile( $invoice );
        unlink( $invoice );

        Log::instance()->insert([
            'log_response_code' => 200,
            'log_response' => 'Invoice-' . $order->o_id . '.pdf',
        ]);
        exit;
    }

    public function invoiceBag( $b_id, $token ) {
        $tokenDecoded = Functions::jwtDecode( $token );
        if( !$tokenDecoded || empty( $tokenDecoded['b_id'] ) || $b_id != $tokenDecoded['b_id']  ){
            Response::instance()->sendMessage( 'Invalid request' );
        }
        $bag = Bag::getBag( $b_id );

        if( !$bag || !$bag->o_count ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        $o_ids = $bag->o_ids;

        CacheUpdate::instance()->add_to_queue( $o_ids, 'order_meta');
        CacheUpdate::instance()->add_to_queue( $o_ids, 'order');
        CacheUpdate::instance()->update_cache( [], 'order_meta' );
        CacheUpdate::instance()->update_cache( [], 'order' );

        $merge = new FPDF_Merge();
        $gen_o_ids = [];
        foreach ( $o_ids as $o_id ) {
            if( ! ( $order = Order::getOrder( $o_id ) ) ){
                continue;
            }
            if( 'confirmed' != $order->o_status ){
                continue;
            }
            if( $invoice = $this->invoiceGenerate( $order ) ){
                $merge->add($invoice);
                unlink($invoice);
            }
            $gen_o_ids[] = $order->o_id;
        }
        if( !$gen_o_ids ){
            Response::instance()->sendMessage( 'Nothing to output.' );
        }
        Log::instance()->insert([
            'log_response_code' => 200,
            'log_response' => $gen_o_ids,
        ]);

        $merge->output();
        exit;
    }

    function orders( $status = 'all', $page = 1 ) {
        $per_page = 10;
        $page     = (int) $page;
        $limit    = $per_page * ( $page - 1 );

        if ( ! ( $user = User::getUser( Auth::id() ) ) ) {
            Response::instance()->loginRequired( true );
            Response::instance()->sendMessage( 'Invalid id token' );
        }

        $db = new DB;

        $db->add( 'SELECT * FROM t_orders WHERE u_id = ?', Auth::id() );
        if ( 'all' !== $status ) {
            $db->add( ' AND o_status = ?', $status );
        }
        $db->add( ' ORDER BY o_id DESC' );
        $db->add( ' LIMIT ?, ?', $limit, $per_page );
        
        $query = $db->execute();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Order');
		$orders = $query->fetchAll();
        if( ! $orders ){
            Response::instance()->sendMessage( 'No Orders Found' );
        }
        $o_ids = array_map(function($o) { return $o->o_id;}, $orders);
        CacheUpdate::instance()->add_to_queue( $o_ids , 'order_meta');
        CacheUpdate::instance()->update_cache( [], 'order_meta' );

        foreach( $orders as $order ){
            $data = $order->toArray();
            if( 'processing' == $order->o_status && ( \strtotime( $order->o_created ) + ( 10 * 60 ) ) < \strtotime( \date( 'Y-m-d H:i:s' ) ) ){
                $data['o_status'] = 'awaiting feedback';
            }
			
			//$data['refund'] = $order->getMeta( 'refund' ) ? round( $order->getMeta( 'refund' ), 2 ) : 0;
            $data['o_note'] = (string)$order->getMeta('o_note');
            $data['paymentStatus'] = ( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ) ? 'paid' : '';

            if( \in_array( $order->o_status, [ 'confirmed', 'delivering', 'delivered' ] ) && \in_array( $order->o_i_status, ['packing', 'checking', 'confirmed'] ) && 'paid' !== $order->getMeta( 'paymentStatus' ) ){
                $data['paymentUrl'] = $order->signedUrl( '/payment/v1' );
            }
            Response::instance()->appendData( '', $data );
        }
        if ( ! Response::instance()->getData() ) {
            Response::instance()->sendMessage( 'No Orders Found' );
        } else {
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        }
    }

    function cashBalance(){
        $user = User::getUser( Auth::id() );
        if( ! $user ) {
            Response::instance()->loginRequired( true );
            Response::instance()->sendMessage( 'No users Found' );
        }
        
        $data = [
            'u_cash' => \round( $user->u_cash, 2 ),
            'u_p_cash' => \round( $user->u_p_cash, 2 ),
        ];
        Response::instance()->sendData( $data, 'success' );
    }

    function location(){
        $lat = isset( $_GET['lat'] ) ? $_GET['lat'] : 0;
        $long = isset( $_GET['long'] ) ? $_GET['long'] : 0;
        if( ! $lat || ! $lat ){
            Response::instance()->sendMessage( 'Invalid location.' );
        }
        /*
        if( Functions::isInside( $lat, $long, 'chittagong' ) ){
            Response::instance()->sendMessage( "Our Chattogram operation temporarily off due to some unavoidable circumstances. We will send you a notification once our Chattogram operation resumes.\nSorry for this inconvenience.");
        }
        if( ! Functions::isInside( $lat, $long ) ){
            Response::instance()->sendMessage( "Our delivery service comming to this area very soon, please stay with us.");
        }
        */
        $client = new Client();
        $res = $client->get( \sprintf( 'https://barikoi.xyz/v1/api/search/reverse/geocode/server/%s/place', BARIKOI_API_KEY ), [
            'query' => [
                'latitude' => $lat,
                'longitude' => $long,
                'post_code' => 'true',
            ],
        ]);
        if( 200 !== $res->getStatusCode() ){
            Response::instance()->sendMessage( 'Something went wrong, Please try again.' );
        }
        $body = Functions::maybeJsonDecode( $res->getBody()->getContents() );
        if( ! $body || ! \is_array( $body ) || 200 !== $body['status'] ){
            Response::instance()->sendMessage( 'Something went wrong, Please try again' );
        }
        $location = [];
        if( ! empty( $body['place']['address'] ) ){
            $location[] = trim($body['place']['address']);
        }
        if( ! empty( $body['place']['area'] ) ){
            $location[] = trim($body['place']['area']);
        }
        if( ! empty( $body['place']['city'] ) ){
            $location[] = trim($body['place']['city']);
        }
        $postCode = ! empty( $body['place']['postCode'] ) ? trim($body['place']['postCode']) : '';
        $data = Functions::getAddressByPostcode( $postCode, trim($body['place']['area']) );

        $location = \array_unique( \array_filter( $location ) );
        if( ! $location ){
            Response::instance()->sendMessage( 'No address found, Please try again.' );
        }
        $data['homeAddress'] = ! empty( $body['place']['address'] ) ? trim($body['place']['address']) : '';
        $data['location'] = \implode( ', ', $location );
        $data['place'] = $body['place'];
        
        Response::instance()->sendData( $data, 'success' );
    }

    function profile() {
        $user = User::getUser( Auth::id() );
        if( ! $user ){
            Response::instance()->sendMessage( 'You are not logged in' );
        }
        $data = $user->toArray();
        //$data['authToken'] = $user->authToken();
        $data['u_pic_url'] =  Functions::getProfilePicUrl( Auth::id() );
        
        Response::instance()->sendData( ['user' =>  $data], 'success' );
    }

    function profileUpdate() {
        $file_to_save = isset( $_FILES['u_profile_pic'] ) ? $_FILES['u_profile_pic'] : [];
        
        $user = User::getUser( Auth::id() );
        if( ! $user ){
            Response::instance()->sendMessage( 'You are not logged in' );
        }
        $u_name = isset( $_POST['u_name'] ) ?  filter_var($_POST['u_name'], FILTER_SANITIZE_STRING) : '';
        $u_mobile = isset( $_POST['u_mobile'] ) ?  filter_var($_POST['u_mobile'], FILTER_SANITIZE_STRING) : '';
        $u_email = isset( $_POST['u_email'] ) ?  filter_var($_POST['u_email'], FILTER_VALIDATE_EMAIL) : '';

        if( !$user->u_mobile && !$u_mobile ){
            Response::instance()->sendMessage( 'Mobile number required' );
        }

        $update_data = [
            'u_name' => $u_name,
            'u_mobile' => $user->u_mobile ?: $u_mobile,
            'u_email' => $u_email,
        ];
        $user->update( $update_data );
        $data = $user->toArray();
        $data['authToken'] = $user->authToken();
                

        if ( $file_to_save ) {
            $upload_folder = STATIC_DIR . '/users/' . \floor( Auth::id() / 1000 );

            if ( ! is_dir($upload_folder)) {
                @mkdir($upload_folder, 0755, true);
            }
            
            // var_dump($file_to_save);
            $tmp_name = $file_to_save['tmp_name'];

            $imagetype = exif_imagetype( $tmp_name );
            $mime      = ( $imagetype ) ? image_type_to_mime_type( $imagetype ) : false;
            $ext       = ( $imagetype ) ? image_type_to_extension( $imagetype ) : false;
            if( ! $ext || ! $mime ) {
                Response::instance()->sendMessage( 'Only profile picture are allowed.');
            }
            //Delete previous pic here
            $prev_image = \str_replace( STATIC_URL, STATIC_DIR, Functions::getProfilePicUrl( Auth::id() ) );
            if( $prev_image && file_exists( $prev_image ) ){
                @unlink( $prev_image );
            }

            $new_file = Functions::randToken( 'alnumlc', 12 ) . $ext;

            $new_file = \sprintf( '%s/%s-%s', $upload_folder, Auth::id(), $new_file );
            if( @ move_uploaded_file( $tmp_name, $new_file ) ) {
                // Set correct file permissions.
                $stat  = stat( dirname( $new_file ) );
                $perms = $stat['mode'] & 0000666;
                @ chmod( $new_file, $perms );
            }
        
        }
        $data['u_pic_url'] =  Functions::getProfilePicUrl( Auth::id() );
        Response::instance()->sendData( ['user' => $data], 'success' );
    }

    function prescriptions() {
        $page = isset( $_GET['page'] ) ? (int)$_GET['page'] : 1;
        $user = User::getUser( Auth::id() );
        if( ! $user ){
            Response::instance()->sendMessage( 'You are not logged in' );
        }
        $p_array = $user->getMeta( 'prescriptions' );
        $p_array = ( $p_array && is_array($p_array) ) ? $p_array : [];

        $total = count( $p_array ); //total items in array    
        $limit = 20; //per page    
        $totalPages = ceil( $total / $limit ); //calculate total pages
        $page = max($page, 1); //get 1 page when $page <= 0

        if( $page > $totalPages ){
            Response::instance()->sendMessage( 'No saved prescription found' );
        }
        
        $offset = ( $page - 1 ) * $limit;
        if( $offset < 0 ) $offset = 0;

        $p_array = array_slice( $p_array, $offset, $limit );

        $value = [];
        foreach ( $p_array as $s3key ) {
            $value[] = [
                'key' => $s3key,
                'src' => Functions::getPresignedUrl( $s3key ),
            ];
        }
        Response::instance()->sendData( [ 'prescriptions' => $value ], 'success' );
    }

    function offers(){
        $data = [
            [
                'heading' => 'Cashback ৳100',
                'desc' => 'For purchasing above ৳5000+',
            ],
            [
                'heading' => 'Cashback   ৳80',
                'desc' => 'For purchasing above ৳4000+',
            ],
            [
                'heading' => 'Cashback   ৳60',
                'desc' => 'For purchasing above ৳3000+',
            ],
            [
                'heading' => 'Cashback   ৳40',
                'desc' => 'For purchasing above ৳2000+',
            ],
            [
                'heading' => 'Cashback   ৳20',
                'desc' => 'For purchasing above ৳1000+',
            ],

        ];
        Response::instance()->sendData( $data, 'success' );
    }

    private function FAQs(){
        $return = [];
        $return[] = [
            'title' => 'Medicine and Healthcare Orders',
            'slug' => 'medicineAndHealthcareOrders',
            'data' => [
                [
                    'q' => 'When will I receive my order?',
                    'a' => 'Your order will be delivered within 18-48 hours inside dhaka city, 1-5 days outside dhaka city'
                ],
                [
                    'q' => 'I have received damaged items.',
                    'a' => 'We are sorry you had to experience this. Please do not accept the delivery of that order and let us know what happened'
                ],
                [
                    'q' => 'Items are different from what I ordered.',
                    'a' => 'We are sorry you have had to experience this. Please do not accept it from delivery man. Reject the order straightaway and call to arogga customer care'
                ],
                [
                    'q' =>'What if Items are missing from my order.',
                    'a' => 'In no circumstances, you should receive an order that is incomplete. Once delivery man reaches your destination, be sure to check expiry date of medicines and your all ordered items was delivered.',
                ],
                [
                    'q' => 'How do I cancel my order?',
                    'a' => 'Please call us with your order ID and we will cancel it for you.'
                ],
                [
                    'q' => 'I want to modify my order.',
                    'a' => 'Sorry, once your order is confirmed, it cannot be modified. Please place a fresh order with any modifications.'
                ],
                [
                    'q' => 'What is the shelf life of medicines being provided?',
                    'a' => 'We ensure that the shelf life of the medicines being supplied by our partner retailers is, at least, a minimum of 3 months from the date of delivery.'
                ]
            ]
        ];
        $return[] = [
            'title' => 'Delivery',
            'slug' => 'delivery',
            'data' => [
                [
                    'q' => 'When will I receive my order?',
                    'a' => 'Your order will be delivered within the Estimated Delivery Date.'
                ],
                [
                    'q' => 'Order status showing delivered but I have not received my order.',
                    'a' => 'Sorry that you are experiencing this. Please call to connect with us immediately.'
                ],
                [
                    'q' => 'Which cities do you operate in?',
                    'a' => 'We provide healthcare services in all over Bangladesh now'
                ],
                [
                    'q' => 'How can I get my order delivered faster?',
                    'a' => 'Sorry, we currently do not have a feature available to expedite the order delivery. We surely have a plan to introduce 2 hour expedite delivery soon'
                ],
                [
                    'q' => 'Can I modify my address after Order placement?',
                    'a' => 'Sorry, once the order is placed, we are unable to modify the address.'
                ],
            ]
        ];

        $return[] = [
            'title' => 'Payments',
            'slug' => 'payments',
            'data' => [
                [
                    'q' => 'How do customers get discounts.',
                    'a' => 'We deduct the value from every medicines and show it to you before order, so that you can see what you are really paying for each medicines  '
                ],
                [
                    'q' => 'When will I get my refund?',
                    'a' => 'Refund will be in credited in arogga cash (3-5 business days)'
                ],
                [
                    'q' => 'I did not receive cashback for my order.',
                    'a' => 'Please read the T&C of the offer carefully for the eligibility of cashback.'
                ],
                [
                    'q' => 'What are the payment modes at arogga?',
                    'a' => 'Cash on Delivery (COD) and Online payment method Bkash, Nagad, Cards etc.'
                ]
            ]
        ];

        $return[] = [
            'title' => 'Referrals',
            'slug' => 'referrals',
            'data' => [
                [
                    'q' => 'How does your referral program work?',
                    'a' => sprintf('Invite your friend and family members by sharing your referral code. Once they join with your referral code and place their first order, you will get extra %d Taka Referral bonus in your arogga cash.', Functions::changableData('refBonus') ),
                ],
                [
                    'q' => 'Why did I not get the referral benefit?',
                    'a' => "If you are not notified about your referral benefit, it is likely that one or more of the following things happened: \n 1. The referred member did not apply your referral code while placing the order \n 2. The user clicked on your link but did not create an account or complete their first purchase. \n 3. The referred member placed an eligible order, but the order was not fulfilled. \n 4. The person who used the code has already placed an order on arogga. \n 5. Your referral benefit has expired"
                ],
                [
                    'q' => 'Is there an expiry date to my referral benefit?',
                    'a' => 'No, there is no expiry date. Once you are eligible for the additional benefit, you will surely get it.'
                ],
            ]
        ];

        $return[] = [
            'title' => 'Arogga Cash',
            'slug' => 'AroggaCash',
            'data' => [
                [
                    'q' => 'What is arogga cash?',
                    'a' => 'This is a virtual wallet to store arogga Cash in your account..'
                ],
                [
                    'q' => 'How do I check my arogga cash balance?',
                    'a' => 'You can check your arogga cash in Account screen.'
                ],
                [
                    'q' => 'When will the arogga money expire?',
                    'a' => 'Any arogga Cash deposited in your arogga wallet through returns will never expire. At times, our marketing team may deposit promotional cash which will have an expiry that is communicated to you via an SMS.'
                ],
                [
                    'q' => 'Can I add money to my arogga cash?',
                    'a' => 'No, you are unable to transfer or add money to your arogga cash.'
                ],
                [
                    'q' => 'How can I redeem my arogga cash?',
                    'a' => 'If you have any money in your arogga cash, it will be automatically deducted from your next order amount and you will only have to pay for the balance amount (if any).'
                ],
                [
                    'q' => 'Can I transfer money from my arogga cash to the bank account?',
                    'a' => 'No, you are unable to transfer money from your arogga cash to the bank account.'
                ],
                [
                    'q' => 'How much arogga money can I redeem in an order?',
                    'a' => 'There is no limit for redemption of arogga cash  '
                ]
            ]
        ];

        $return[] = [
            'title' => 'Promotions',
            'slug' => 'promotions',
            'data' => [
                [
                    'q' =>'How do I apply a coupon code on my order?',
                    'a' =>'You can apply a coupon on the cart screen while placing an order. If you are getting a message that the coupon code has failed to apply, it may be because you are not eligible for the offer.'
                ],
                [
                    'q' => 'Does arogga offers return of the medicine?',
                    'a' => 'No, Arogga does not accept returns of the medicine from customer. Thats why customers are requested to thoroughly check all the medicine before accepting the order from delivery man. If for any reason you want to return the product, simply reject the order to delivery man. Do not receive it, your order will be automatically cancelled'
                ]
            ]
        ];
        $return[] = [
            'title' => 'Return',
            'slug' => 'return',
            'data' => [
                [
                    'q' => 'How does Arogga’s return policy work?',
                    'a' => "Arogga offers a flexible return policy for items ordered with us. Under this policy, unopened and unused items must be returned within 7 days from the date of delivery. The return window will be listed in the returns section of the order, once delivered.\n\nItems are not eligible for return under the following circumstances:\n\n - If items have been opened, partially used or disfigured. Please check the package carefully at the time of delivery before opening and using.\n - If the item’s packaging/box or seal has been tampered with. Do not accept the delivery if the package appears to be tampered with.\n - If it is mentioned on the product details page that the item is non-returnable.\n - If the return window for items in an order has expired. No items can be returned after 7 days from the the delivery date.\n - If any accessories supplied with the items are missing.\n - If the item does not have the original serial number/UPC number/barcode affixed, which was present at the time of delivery.\n - If there is any damage/defect which is not covered under the manufacturer's warranty.\n - If the item is damaged due to visible misuse.\n - Any refrigerated items like insulin or products that are heat sensitive are non-returnable.\n - Items related to baby care, food & nutrition, healthcare devices and sexual wellness such as but not limited to diapers, health drinks, health supplements, glucometers, glucometer strips/lancets, health monitors, condoms, pregnancy/fertility kits, etc."
                ],
                [
                    'q' => 'Do you sell medicine strips in full or it can be single units too?',
                    'a' => 'We sell in single units to give customers flexibility in selecting specific amounts of medicine required. We provide single units of medicine as our pharmacist can cut strips.'
                ],
                [
                    'q' => 'I have broken the seal, can I return it?',
                    'a' => 'No, you can not return any items with a broken seal.'
                ],
                [
                    'q' => 'Can I return medicine that is partially consumed?',
                    'a' => 'No, you cannot return partially consumed items. Only unopened items that have not been used can be returned.'
                ],
                [
                    'q' => 'Can I ask for a return if the strip is cut?',
                    'a' => 'We provide customers with the option of purchasing medicines as single units. Even if ordering a single tablet of paracetamol, we can deliver that. It is common to have medicines in your order with some strips that are cut. If you want to get a full strip in your order, please order a full strip amount and you will get it accordingly. If you do not order a full strip, you will get cut pieces. If you have ordered 4 single units which are cut pieces and want to return, all 4 pieces must be returned. We do not allow partial return of 1 or 2 pieces.'
                ],
            ]
        ];
        return $return;
    }

    function FAQsHeaders(){
        $FAQs = $this->FAQs();
        $data = array_map( function( $FAQ ){
            return [ 'title' => $FAQ['title'], 'slug' => $FAQ['slug'] ];
        }, $FAQs);
        $data = array_filter( $data );
        if( ! is_array( $data ) ){
            $data = [];
        }

        Response::instance()->sendData( $data, 'success' );
    }

    function FAQsReturn( $slug ){
        $FAQs = $this->FAQs();
        $data = array_map( function( $FAQ ) use ( $slug ){
            if( $FAQ['slug'] == $slug ){
                return $FAQ['data'];
            } else {
                return null;
            }
        }, $FAQs);
        $data = array_filter( $data );
        $return = reset( $data );
        if( ! is_array( $return ) ){
            $return = [];
        }

        Response::instance()->sendData( $return, 'success' );
    }

    function locationData(){
        $get      = isset($_GET['get']) ? $_GET['get'] : '';
        $division = isset($_GET['division']) ? $_GET['division'] : '';
        $district = isset($_GET['district']) ? $_GET['district'] : '';

        $data = [];
        if( in_array( $get, [ 'all', 'divisions'] ) ){
            $data['divisions'] = Functions::getDivisions();
        }
        if( in_array( $get, [ 'all', 'districts'] ) ){
            $data['districts'] = Functions::getDistricts( $division );
        }
        if( in_array( $get, [ 'all', 'areas'] ) ){
            $data['areas'] = Functions::getAreas( $division, $district );
        }

        Response::instance()->sendData( $data, 'success' );
    }

    function allLocations(){
        $locations = Functions::getLocations();
        Response::instance()->sendData( $locations, 'success' );
    }

    function token(){
        $fcm = isset($_POST['fcm']) ? filter_var($_POST['fcm'], FILTER_SANITIZE_STRING) : '';

        if( !$fcm ){
            Response::instance()->sendMessage( 'Invalid token' );
        }
        $query = DB::db()->prepare( 'SELECT t_id FROM t_tokens WHERE t_token = ? LIMIT 1' );
        $query->execute( [ $fcm ] );
        if( $query->fetchColumn() ){
            Response::instance()->sendMessage( 'Token already exists.' );
        }

        $data = [
            't_uid' => Auth::id(),
            't_created' => \date( 'Y-m-d H:i:s' ),
            't_token' => $fcm,
            't_ip'   => $_SERVER['REMOTE_ADDR'],
        ];
        DB::instance()->insert( 't_tokens', $data );

        Response::instance()->sendMessage( 'Token saved', 'success' );
    }

    function categories(){
        $categories = Functions::getCategories();
        $return = [];
        foreach ( $categories as $key => $name ) {
            if( 11 == $key ){
                continue;
            }
            $return[] = [
                'c_id' => $key,
                'c_name' => $name,
                'c_img' => Functions::getS3Url( sprintf( 'category/%d.png', $key ) ),
            ];
        }

        \header("Access-Control-Allow-Origin: *");
        Response::instance()->sendData( [ 'categories' => $return ], 'success' );
    }

    public function requestStockCreate(){
        if( ! ( $user = User::getUser( Auth::id() ) ) ){
            Response::instance()->sendMessage( 'You are not logged in' );
        }
        $m_id = isset( $_POST['m_id'] ) ? $_POST['m_id'] : 0;
        if( ! ( $medicine = Medicine::getMedicine( $m_id ) ) ){
            Response::instance()->sendMessage( 'No medicines Found' );
        }
        if( $medicine->m_rob ){
            Response::instance()->sendMessage( 'This item is already available' );
        }
        $query = DB::db()->prepare( 'SELECT r_id FROM t_request_stock WHERE r_u_id = ? AND r_m_id = ? LIMIT 1' );
        $query->execute( [ Auth::id(), $m_id ] );
        if( $query->fetchColumn() ){
            Response::instance()->sendMessage( 'You already requested for this item.', 'success' );
        }
        $data = [
            'r_m_id' => $medicine->m_id,
            'r_u_id' => Auth::id(),
            'r_created' => \date( 'Y-m-d H:i:s' ),
        ];
        DB::instance()->insert( 't_request_stock', $data );
        $medicine->m_r_count = $medicine->m_r_count + 1;
        $medicine->update();
        Response::instance()->sendMessage( 'Requested, you will receive notification once comes in stock.', 'success' );
    }
}