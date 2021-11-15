<?php

namespace OA;
use OA\Factory\{User, Medicine, Discount, Order, Option, CacheUpdate, Inventory, Generic, Company, Meta, Bag, Location};
use GuzzleHttp\Client;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class AdminResponse {
    private $user;

    // function __construct() {
    //     \header("Access-Control-Allow-Origin: *");
    //     //\header("Access-Control-Request-Headers: *");
    //     \define( 'ADMIN', true );

    //     if ( ! ( $user = User::getUser( Auth::id() ) ) ) {
    //         Response::instance()->setCode( 403 );
    //         Response::instance()->loginRequired( true );
    //         Response::instance()->sendMessage( 'You are not logged in' );
    //     }
    //     if( ! $user->can( 'backendAccess' ) ) {
    //         Response::instance()->setCode( 401 );
    //         Response::instance()->sendMessage( 'Your account does not have admin access.');
    //     }
    //     $httpMethod = $_SERVER['REQUEST_METHOD'];
    //     if( $user->can( 'onlyGET' ) && $httpMethod != 'GET' ) {
    //         Response::instance()->sendMessage( 'Your account does not have permission to do this.');
    //     }

    //     $this->user = $user;
    // }

public function medicinesES() {
        $ids = isset( $_GET['ids'] ) ? $_GET['ids'] : '';
        $search = isset( $_GET['_search'] ) ? $_GET['_search'] : '';
        $category = isset( $_GET['_category'] ) ? $_GET['_category'] : '';
        $status = isset( $_GET['_status'] ) ? $_GET['_status'] : '';
        $c_id = isset( $_GET['_c_id'] ) ? $_GET['_c_id'] : 0;
        $g_id = isset( $_GET['_g_id'] ) ? $_GET['_g_id'] : 0;
        $cat_id = isset( $_GET['_cat_id'] ) ? (int)$_GET['_cat_id'] : 0;
        $orderBy = isset( $_GET['_orderBy'] ) ? $_GET['_orderBy'] : '';
        $order = ( isset( $_GET['_order'] ) && 'DESC' == $_GET['_order'] ) ? 'DESC' : 'ASC';
        $page = isset( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = isset( $_GET['_perPage'] ) ? (int)$_GET['_perPage'] : 20;
        $available = isset( $_GET['_available'] ) ? (int)$_GET['_available'] : 0;

        $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
        
        if( $search && \is_numeric($search) ){
            $ids[] = (int)$search;
            $search = '';
        }
        if( $ids ){
            $perPage = count($ids);
        }

        $args = [
            'ids' => $ids,
            'search' => $search,
            'per_page' => $perPage,
            'limit' => $perPage * ( $page - 1 ),
            'm_status' => $status,
            'm_category' => $category,
            'm_c_id' => $c_id,
            'm_g_id' => $g_id,
            'orderBy' => $orderBy,
            'order' => $order,
            'available' => $available,
            'isAdmin' => true,
            'm_cat_id' => $cat_id,
        ];
		if( $available ){
            $args['m_rob'] = true;
        } 
        $data = \OA\Search\Medicine::init()->search( $args );

        if ( $data && $data['data'] ) {
            Response::instance()->setResponse( 'total', $data['total'] );
            Response::instance()->sendData( $data['data'], 'success' );
        } else {
            if( $page > 1 ){
                Response::instance()->sendMessage( 'No more medicines Found' );
            } else {
                Response::instance()->sendMessage( 'No medicines Found' );
            }
        }
    }
	
	
   public function medicines() {

        $ids = isset( $_GET['ids'] ) ? $_GET['ids'] : '';
        $search = isset( $_GET['_search'] ) ? $_GET['_search'] : '';
        $category = isset( $_GET['_category'] ) ? $_GET['_category'] : '';
        $status = isset( $_GET['_status'] ) ? $_GET['_status'] : '';
        $c_id = isset( $_GET['_c_id'] ) ? $_GET['_c_id'] : 0;
        $g_id = isset( $_GET['_g_id'] ) ? $_GET['_g_id'] : 0;
        $cat_id = isset( $_GET['_cat_id'] ) ? (int)$_GET['_cat_id'] : 0;
        $m_r_count = isset( $_GET['_m_r_count'] ) ? (int)$_GET['_m_r_count'] : 0;
        $orderBy = isset( $_GET['_orderBy'] ) ? $_GET['_orderBy'] : '';
        $order = ( isset( $_GET['_order'] ) && 'DESC' == $_GET['_order'] ) ? 'DESC' : 'ASC';
        $page = isset( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = isset( $_GET['_perPage'] ) ? (int)$_GET['_perPage'] : 20;
        $available = isset( $_GET['_available'] ) ? (int)$_GET['_available'] : 0;

        if( $search ){
            $this->medicinesES();
        }

        $db = new DB;

        $db->add( 'SELECT SQL_CALC_FOUND_ROWS * FROM t_medicines WHERE 1=1' );
        if( $ids ) {
            $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
            $in  = str_repeat('?,', count($ids) - 1) . '?';
            $db->add( " AND m_id IN ($in)", ...$ids );

            $perPage = count($ids);
        }
        if ( $search ) {
            if( \is_numeric($search) && !$ids ){
                $db->add( ' AND m_id = ?', $search );
            } else {
                $search = preg_replace('/[^a-z0-9\040\.\-]+/i', ' ', $search);
                $org_search = $search = \rtrim( \trim(preg_replace('/\s\s+/', ' ', $search ) ), '-' );

                if( false === \strpos( $search, ' ' ) ){
                    $search .= '*';
                } else {
                    $search = '+' . \str_replace( ' ', ' +', $search) . '*';
                }
                if( \strlen( $org_search ) > 2 ){
                    $db->add( " AND (MATCH(m_name) AGAINST (? IN BOOLEAN MODE) OR m_name LIKE ?)", $search, "{$org_search}%" );
                } elseif( $org_search ) {
                    $db->add( ' AND m_name LIKE ?', "{$org_search}%" );
                }
            }
        }
        if( $category ) {
            $db->add( ' AND m_category = ?', $category );
        }
        if( $status ) {
            $db->add( ' AND m_status = ?', $status );
        }
        if( $c_id ){
            $db->add( ' AND m_c_id = ?', $c_id );
        }
        if( $g_id ){
            $db->add( ' AND m_g_id = ?', $g_id );
        }
        if ( $cat_id ) {
            $db->add( ' AND m_cat_id = ?', $cat_id );
        }
        if( $m_r_count ){
            $db->add( ' AND m_r_count >= ?', $m_r_count );
        }
        if( $available ){
            $db->add( ' AND m_rob = ?', 1 );
        } 

        if( $orderBy && \property_exists('\OA\Factory\Medicine', $orderBy ) ) {
            $db->add( " ORDER BY $orderBy $order" );
        }
        
        $limit    = $perPage * ( $page - 1 );
        $db->add( ' LIMIT ?, ?', $limit, $perPage );

        $cache_key = \md5( $db->getSql() . \json_encode($db->getParams()) );
        
        if ( $cache_data = Cache::instance()->get( $cache_key, 'adminMedicines' ) ){
            Response::instance()->setData( $cache_data['data'] );
            Response::instance()->setResponse( 'total', $cache_data['total'] );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        }
        
        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Medicine');

        while( $medicine = $query->fetch() ){
            $data = $medicine->toArray();
            $data['id'] = $medicine->m_id;
            $data['m_generic'] = $medicine->m_generic;
            $data['m_company'] = $medicine->m_company;
            $data['attachedFiles'] = Functions::getPicUrlsAdmin($medicine->getMeta( 'images' ));

            Response::instance()->appendData( '', $data );
        }
        if ( $all_data = Response::instance()->getData() ) {
            $cache_data = [
                'data' => $all_data,
                'total' => $total,
            ];
            Cache::instance()->set( $cache_key, $cache_data, 'adminMedicines', 60 * 60 * 24 );

            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        } else {
            Response::instance()->sendMessage( 'No medicines Found' );
        }
    }

    public function medicineCreate() {
        if( ! $this->user->can( 'medicineCreate' ) ) {
            Response::instance()->sendMessage( 'Your account does not have medicine create capabilities.');
        }
        if(empty($_POST['m_name']) ) {
            Response::instance()->sendMessage( 'Name is Required' );
        }
        $medicine = new Medicine;
        $_POST['m_u_id'] = Auth::id();
        $medicine->insert( $_POST );
        if( 'allopathic' !== $medicine->m_category && isset( $_POST['description'] ) ){
            $medicine->setMeta( 'description', $_POST['description'] );
        }
        if( isset($_POST['m_status']) && 'active' != $_POST['m_status'] ){
            //If status active, then it will incr from Medicine class
            Cache::instance()->incr( 'suffixForMedicines' );
        }

        if( isset( $_POST['attachedFiles'] ) ){
            Functions::modifyMedicineImages( $medicine->m_id, $_POST['attachedFiles'] );
        }

        $this->medicineSingle( $medicine->m_id );
    }

    public function medicineSingle( $m_id ) {

        if ( ! $m_id ) {
            Response::instance()->sendMessage( 'No medicines Found' );
        }

        if( $medicine = Medicine::getMedicine( $m_id ) ){
            Response::instance()->setStatus( 'success' );
            //$price = $medicine->m_price * (intval($medicine->m_unit));
            //$d_price = ( ( $price * 90 ) / 100 );

            $data = $medicine->toArray();
            $data['id'] = $medicine->m_id;
            $data['m_generic'] = $medicine->m_generic;
            $data['m_company'] = $medicine->m_company;
            $data['attachedFiles'] = Functions::getPicUrlsAdmin($medicine->getMeta( 'images' ));
            if( 'allopathic' !== $medicine->m_category ){
                $data['description'] = (string)$medicine->getMeta( 'description' );
            }

            Response::instance()->setData( $data );
            
        } else {
            Response::instance()->sendMessage( 'No medicines Found' );
        }

        Response::instance()->send();
    }

    public function medicineUpdate( $m_id ) {

        if( ! $this->user->can( 'medicineEdit' ) ) {
            Response::instance()->sendMessage( 'Your account does not have medicine edit capabilities.');
        }
        if ( ! $m_id ) {
            Response::instance()->sendMessage( 'No medicines Found' );
        }

        if( $medicine = Medicine::getMedicine( $m_id ) ){
            //$_POST['m_comment'] = isset($_POST['m_comment'])? $_POST['m_comment'] : '';
            $medicine->update( $_POST );
            if( 'allopathic' !== $medicine->m_category && isset( $_POST['description'] ) ){
                $description = $_POST['description'];
                $description = strip_tags( $description, '<div><p><h1><h2><h3><b><strong><em><u><s><ol><ul><li>' );
                
                $description = preg_replace( '/(<[^>]+) style=\'.*?\'/i', '$1', $description );
                $description = preg_replace( '/(<[^>]+) style=".*?"/i', '$1', $description );

                $medicine->setMeta( 'description', $description );
            }

            Functions::modifyMedicineImages( $medicine->m_id, isset( $_POST['attachedFiles'] ) ? $_POST['attachedFiles'] : [] );

            $this->medicineSingle( $medicine->m_id );
            
        } else {
            Response::instance()->sendMessage( 'No medicines Found' );
        }

        Response::instance()->send();
    }

    public function medicineDelete( $m_id ) {

        if( ! $this->user->can( 'medicineDelete' ) ) {
            Response::instance()->sendMessage( 'Your account does not have medicine delete capabilities.');
        }

        if ( ! $m_id ) {
            Response::instance()->sendMessage( 'No medicines Found' );
        }

        if( $medicine = Medicine::getMedicine( $m_id ) ){
            $medicine->delete();
            Response::instance()->setStatus( 'success' );
            Response::instance()->setData( ['id' => $m_id ] ); 
        } else {
            Response::instance()->sendMessage( 'No medicines Found' );
        }

        Response::instance()->send();
    }

    public function medicineImageDelete( $m_id ){
        if( ! $this->user->can( 'medicineEdit' ) ) {
            Response::instance()->sendMessage( 'Your account does not have medicine edit capabilities.');
        }
        $s3key = isset($_GET['s3key']) ? $_GET['s3key'] : '';

        if ( ! $m_id || !$s3key ) {
            Response::instance()->sendMessage( 'No medicines Found' );
        }

        if( $medicine = Medicine::getMedicine( $m_id ) ){
            $images = $medicine->getMeta( 'images' );
            if( !$images || !is_array( $images ) ){
                Response::instance()->sendMessage( 'No images Found' );
            }
            // Instantiate an Amazon S3 client.
            $s3 = Functions::getS3();

            foreach( $images as $k => $image ){
                if( $s3key == $image['s3key'] ){
                    try {
                        $s3->deleteObject([
                            'Bucket' => Functions::getS3Bucket(),
                            'Key'    => $s3key,
                        ]);
                        unset( $images[ $k ] );
                    } catch (S3Exception $e) {
                        error_log( $e->getAwsErrorMessage() );
                        Response::instance()->sendMessage( 'Something wrong, please try again' );
                    }
                    break;
                }
            }
            $imgArray = array_values( $images );

            if( $medicine->setMeta( 'images', $imgArray) ){
                \OA\Search\Medicine::init()->update( $medicine->m_id, [ 'images' => $imgArray, 'imagesCount' => count( $imgArray ) ] );
                Cache::instance()->incr( 'suffixForMedicines' );
            }
            Response::instance()->sendMessage( 'Image successfully deleted.', 'success' );
        } else {
            Response::instance()->sendMessage( 'No medicines Found' );
        }
    }

    public function users() {
        $ids = isset( $_GET['ids'] ) ? $_GET['ids'] : '';
        $search = isset( $_GET['_search'] ) ? $_GET['_search'] : '';
        $status = isset( $_GET['_status'] ) ? $_GET['_status'] : '';
        $role = isset( $_GET['_role'] ) ? $_GET['_role'] : '';
        $orderBy = isset( $_GET['_orderBy'] ) ? $_GET['_orderBy'] : '';
        $order = ( isset( $_GET['_order'] ) && 'DESC' == $_GET['_order'] ) ? 'DESC' : 'ASC';
        $page = isset( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = isset( $_GET['_perPage'] ) ? (int)$_GET['_perPage'] : 20;

        $db = new DB;

        $db->add( 'SELECT SQL_CALC_FOUND_ROWS * FROM t_users WHERE 1=1' );
        if( $ids ) {
            $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
            $in  = str_repeat('?,', count($ids) - 1) . '?';
            $db->add( " AND u_id IN ($in)", ...$ids );

            $perPage = count($ids);
        }
        if ( $search ) {
            if( \is_numeric( $search ) && 0 === \strpos( $search, '0' ) ) {
                $search = "+88{$search}";
                $search = addcslashes( $search, '_%\\' );
                $db->add( ' AND u_mobile LIKE ?', "{$search}%" );
            } elseif( \is_numeric( $search ) ) {
                $db->add( ' AND u_id = ?', $search );
            } else {
                $search = addcslashes( $search, '_%\\' );
                $db->add( ' AND u_name LIKE ?', "{$search}%" );
            }
        }
        if( $status ) {
            $db->add( ' AND u_status = ?', $status );
        }
        if( $role ) {
            if( false !== \strpos( $role, ',' ) ) {
                $roles = \array_filter( \array_map( 'trim', \explode( ',', $role ) ) );
                $in  = str_repeat('?,', count($roles) - 1) . '?';
                $db->add( " AND u_role IN ($in)", ...$roles );
            } else {
                $db->add( ' AND u_role = ?', $role );
            }
        }
        if( $orderBy && \property_exists('\OA\Factory\User', $orderBy ) ) {
            $db->add( " ORDER BY $orderBy $order" );
        }
        
        $limit    = $perPage * ( $page - 1 );
        $db->add( ' LIMIT ?, ?', $limit, $perPage );
        
        $cache_key = \md5( $db->getSql() . \json_encode($db->getParams()) . $this->user->u_role );
        
        if ( $role && ( $cache_data = Cache::instance()->get( $cache_key, 'adminUsers' ) ) ){
            //send cached data only if there user role. Users are changing too much
            //Currently when searching for roles users
            Response::instance()->setData( $cache_data['data'] );
            Response::instance()->setResponse( 'total', $cache_data['total'] );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        }

        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\User');

        while( $user = $query->fetch() ){
            $data = $user->toArray();
            $data['id'] = $user->u_id;

            if( ! $this->user->can( 'userChangeRole' ) && $user->can( 'userChangeRole' ) ) {
                $data['u_otp'] = 0;
            }

            Response::instance()->appendData( '', $data );
        }
        if ( $all_data = Response::instance()->getData() ) {
            if( $role ){
                $cache_data = [
                    'data' => $all_data,
                    'total' => $total,
                ];
                Cache::instance()->set( $cache_key, $cache_data, 'adminUsers', 60 * 60 * 24 );
            }

            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        } else {
            Response::instance()->sendMessage( 'No users Found' );
        }
    }

    public function userSingle( $u_id ) {

        if ( ! $u_id ) {
            Response::instance()->sendMessage( 'No users Found' );
        }

        if( $user = User::getUser( $u_id ) ){
            Response::instance()->setStatus( 'success' );

            $data = $user->toArray();
            $data['id'] = $user->u_id;
            if( 'packer' === $user->u_role ){
                $data['packer_ph_id'] = (int)$user->getMeta( 'packer_ph_id' );
            }
            if( ! $this->user->can( 'userChangeRole' ) && $user->can( 'userChangeRole' ) ) {
                $data['u_otp'] = 0;
            }

            Response::instance()->setData( $data );
            
        } else {
            Response::instance()->sendMessage( 'No users Found' );
        }

        Response::instance()->send();
    }
    public function userCreate() {
        if( ! $this->user->can( 'userCreate' ) ) {
            Response::instance()->sendMessage( 'Your account does not have user create capabilities.');
        }
        if(empty($_POST['u_name']) || empty($_POST['u_mobile']) ) {
            Response::instance()->sendMessage( 'All Fields Required' );
        }
        if( ! ( $_POST['u_mobile'] = Functions::checkMobile( $_POST['u_mobile'] ) ) ) {
            Response::instance()->sendMessage( 'Invalid mobile number.');
        }
        if( User::getBy( 'u_mobile', $_POST['u_mobile'] ) ){
            Response::instance()->sendMessage( 'Mobile number already exists.' );
        }
        do{
            $u_referrer = Functions::randToken( 'distinct', 6 );

        } while( User::getBy( 'u_referrer', $u_referrer ) );

        $_POST['u_referrer'] = $u_referrer;

        $user = new User;
        if( $user->insert( $_POST ) ) {
            //we want cache update only when admin changes user.
            Cache::instance()->incr( 'suffixForUsers' );
        }
        if( 'packer' === $user->u_role ){
            $user->setMeta( 'packer_ph_id', $_POST['packer_ph_id'] ?? 0 );
        }

        $this->userSingle( $user->u_id );
    }

    public function userUpdate( $u_id ) {

        if( ! $this->user->can( 'userEdit' ) ) {
            Response::instance()->sendMessage( 'Your account does not have user edit capabilities.');
        }

        if ( ! $u_id ) {
            Response::instance()->sendMessage( 'No users Found' );
        }
        if( $user = User::getUser( $u_id ) ){
            $data = $_POST;
            if( ! $this->user->can( 'userChangeRole' ) ) {
                unset( $data['u_role'], $data['u_p_cash'] );
                if( $user->u_cash + 50 < round( $data['u_cash'] ) ){
                    Response::instance()->sendMessage( 'You cannot give more than 50 taka' );
                }
            }
            if( $user->update( $data ) ) {
                //we want cache update only when admin changes user.
                Cache::instance()->incr( 'suffixForUsers' );
            }
            if( 'packer' === $user->u_role ){
                $user->setMeta( 'packer_ph_id', $_POST['packer_ph_id'] ?? 0 );
            }

            $this->userSingle( $user->u_id );
            
        } else {
            Response::instance()->sendMessage( 'No users Found' );
        }

        Response::instance()->send();
    }

    public function userDelete( $u_id ) {
        if( ! $this->user->can( 'userDelete' ) ) {
            Response::instance()->sendMessage( 'Your account does not have user delete capabilities.');
        }

        if ( ! $u_id ) {
            Response::instance()->sendMessage( 'No users Found' );
        }

        if( $user = User::getUser( $u_id ) ){
            if( $user->delete() ){
                //we want cache update only when admin changes user.
                Cache::instance()->incr( 'suffixForUsers' );
            }

            Response::instance()->setStatus( 'success' );
            Response::instance()->setData( ['id' => $u_id ]);
            
        } else {
            Response::instance()->sendMessage( 'No users Found' );
        }

        Response::instance()->send();
    }

    public function orders() {
        $ids = isset( $_GET['ids'] ) ? $_GET['ids'] : '';
        $u_id = isset( $_GET['u_id'] ) ? (int)$_GET['u_id'] : 0;
        $search = isset( $_GET['_search'] ) ? $_GET['_search'] : '';
        $status = isset( $_GET['_status'] ) ? $_GET['_status'] : '';
        $i_status = isset( $_GET['_i_status'] ) ? $_GET['_i_status'] : '';
        $is_status = isset( $_GET['_is_status'] ) ? $_GET['_is_status'] : '';
        $ex_status = isset( $_GET['_ex_status'] ) ? $_GET['_ex_status'] : '';
        $o_created = isset( $_GET['_o_created'] ) ? $_GET['_o_created'] : '';
        $o_created_end = isset( $_GET['_o_created_end'] ) ? $_GET['_o_created_end'] : '';
        $o_delivered = isset( $_GET['_o_delivered'] ) ? $_GET['_o_delivered'] : '';
        $o_delivered_end = isset( $_GET['_o_delivered_end'] ) ? $_GET['_o_delivered_end'] : '';
        $de_id = isset( $_GET['_de_id'] ) ? $_GET['_de_id'] : 0;
        $payment_method = isset( $_GET['_payment_method'] ) ? $_GET['_payment_method'] : '';
        $priority = ( empty($_GET['_priority']) || 'false' === $_GET['_priority'] ) ? 0 : 1;
        $issue = isset( $_GET['_issue'] ) ? filter_var( $_GET['_issue'], FILTER_VALIDATE_BOOLEAN ) : '';
        $orderBy = isset( $_GET['_orderBy'] ) ? $_GET['_orderBy'] : '';
        $order = ( isset( $_GET['_order'] ) && 'DESC' == $_GET['_order'] ) ? 'DESC' : 'ASC';
        $page = isset( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = isset( $_GET['_perPage'] ) ? (int)$_GET['_perPage'] : 20;

        $db = new DB;

        $db->add( 'SELECT SQL_CALC_FOUND_ROWS * FROM t_orders WHERE 1=1' );
        if ( $search ) {
            if( \is_numeric( $search ) && 0 === \strpos( $search, '0' ) ) {
                $search = "+88{$search}";
                $search = addcslashes( $search, '_%\\' );
                $db->add( ' AND u_mobile LIKE ?', "{$search}%" );
            } elseif( \is_numeric( $search ) ) {
                $search = addcslashes( $search, '_%\\' );
                $db->add( ' AND o_id LIKE ?', "{$search}%" );
            }
        }

        if( $ids ) {
            $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
            $in  = str_repeat('?,', count($ids) - 1) . '?';
            $db->add( " AND o_id IN ($in)", ...$ids );

            $perPage = count($ids);
        }
        if( $u_id ){
            $db->add( ' AND u_id = ?', $u_id );
        } else {
            //For offline orders there is no user. So show only online orders
            $db->add( ' AND u_id > ?', 0 );
        }
        if( $status ) {
            $db->add( ' AND o_status = ?', $status );
        }
        if( $i_status ) {
            $db->add( ' AND o_i_status = ?', $i_status );
        }
        if( $is_status ) {
            $db->add( ' AND o_is_status = ?', $is_status );
        }
        if( $ex_status ) {
            $db->add( ' AND o_status != ?', $ex_status );
        }
        if( $o_created ) {
            $db->add( ' AND o_created >= ? AND o_created <= ?', $o_created . ' 00:00:00', ($o_created_end ?: $o_created) . ' 23:59:59' );
        }
        if( $o_delivered ){
            $db->add( ' AND o_delivered >= ? AND o_delivered <= ?', $o_delivered . ' 00:00:00', ($o_delivered_end ?: $o_delivered) . ' 23:59:59' );
        }
        if( $de_id ){
            $db->add( ' AND o_de_id = ?', $de_id );
        }
        if( $payment_method ){
            $db->add( ' AND o_payment_method = ?', $payment_method );
        }
        if( $priority ){
            $db->add( ' AND o_priority = ?', $priority );
        }
        if( $issue ){
            $db->add( ' AND o_is_status != ? AND o_is_status != ?', '', 'solved' );
        }
        if( $orderBy && \property_exists('\OA\Factory\Order', $orderBy ) ) {
            $db->add( " ORDER BY $orderBy $order" );
        }
        
        $limit    = $perPage * ( $page - 1 );
        $db->add( ' LIMIT ?, ?', $limit, $perPage );
        
        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Order');

        $orders = $query->fetchAll();
        if( ! $orders ){
            Response::instance()->sendMessage( 'No Orders Found' );
        }
        $o_ids = array_map(function($o) { return $o->o_id;}, $orders);
        CacheUpdate::instance()->add_to_queue( $o_ids , 'order_meta');
        CacheUpdate::instance()->update_cache( [], 'order_meta' );

        $in  = str_repeat('?,', count($o_ids) - 1) . '?';
        $query2 = DB::db()->prepare( "SELECT o_id, COUNT(m_id) FROM t_o_medicines WHERE o_id IN ($in) AND om_status = ? GROUP BY o_id" );
        $query2->execute([...$o_ids, 'later']);
        $laterCount = $query2->fetchAll( \PDO::FETCH_KEY_PAIR );

        foreach( $orders as $order ){
            $data = $order->toArray();
            $data['id'] = $order->o_id;
            $data['o_i_note'] = (string)$order->getMeta('o_i_note');
            $data['supplierPrice'] = 'delivered' == $order->o_status ? $order->getMeta( 'supplierPrice' ) : 0.00;

            //$data['d_code'] = (string)$order->getMeta( 'd_code' );
            //$data['o_note'] = (string)$order->getMeta('o_note');
            //$data['prescriptions'] = $order->prescriptions;
            //$data['medicineQty'] = $order->medicineQty;

            $data['paymentGatewayFee'] = $order->getMeta( 'paymentGatewayFee' ) ?: 0.00;
            
            if( isset( $laterCount[ $order->o_id ] ) ){
                $data['laterCount'] = $laterCount[ $order->o_id ];
            }

            Response::instance()->appendData( '', $data );
        }
        if ( ! Response::instance()->getData() ) {
            Response::instance()->sendMessage( 'No orders Found' );
        } else {
            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        }
    }

    public function orderSingle( $o_id ) {

        if ( ! $o_id ) {
            Response::instance()->sendMessage( 'No orders Found' );
        }

        if( $order = Order::getOrder( $o_id ) ){
            Response::instance()->setStatus( 'success' );

            $defaultSAddress = [
                'division' => '',
                'district' => '',
                'area' => '',
            ];

            $data = $order->toArray();
            $data['id'] = $order->o_id;
            $data['d_code'] = (string)$order->getMeta( 'd_code' );
            $data['o_note'] = (string)$order->getMeta('o_note');
            $data['o_i_note'] = (string)$order->getMeta('o_i_note');
            $data['o_admin_note'] = (string)$order->getMeta('o_admin_note');
            $data['subscriptionFreq'] = (string)$order->getMeta('subscriptionFreq');
            $data['addressChecked'] = (bool)$order->getMeta('addressChecked');
            $data['s_address'] = $order->getMeta('s_address')?:$defaultSAddress;
            //$data['man_discount'] = (string)$order->getMeta( 'man_discount' );
            //$data['man_addition'] = (string)$order->getMeta('man_addition');
            $data['prescriptions'] = $order->prescriptions;
            $data['medicineQty'] = $order->medicineQty;
            $data['supplierPrice'] = 'delivered' == $order->o_status ? $order->getMeta( 'supplierPrice' ) : 0.00;
            $data['paymentGatewayFee'] = 'fosterPayment' == $order->o_payment_method ? $order->getMeta( 'paymentGatewayFee' ) : 0.00;
            $data['attachedFiles'] = Functions::getOrderPicUrlsAdmin( $order->o_id, $order->getMeta( 'prescriptions' ));

            $data['invoiceUrl'] = $order->signedUrl( '/v1/invoice' );
            $data['paymentResponse'] = $order->getMeta( 'paymentResponse' );
            if( \in_array( $order->o_status, [ 'confirmed', 'delivering', 'delivered' ] ) && \in_array( $order->o_i_status, ['packing', 'checking', 'confirmed'] ) && 'paid' !== $order->getMeta( 'paymentStatus' ) ){
                $data['paymentUrl'] = $order->signedUrl( '/payment/v1' );
            }

            Response::instance()->setData( $data );
            
        } else {
            Response::instance()->sendMessage( 'No orders Found' );
        }

        Response::instance()->send();
    }

    public function orderDelete( $o_id ) {

        if( ! $this->user->can( 'orderDelete' ) ) {
            Response::instance()->sendMessage( 'Your account does not have order delete capabilities.');
        }
        if ( ! $o_id ) {
            Response::instance()->sendMessage( 'No orders Found' );
        }

        if( $order = Order::getOrder( $o_id ) ){
            $order->delete();

            Response::instance()->setStatus( 'success' );
            Response::instance()->setData( ['id' => $o_id ]);
            
        } else {
            Response::instance()->sendMessage( 'No orders Found' );
        }

        Response::instance()->send();
    }

    public function orderUpdate( $o_id ) {

        if( ! $this->user->can( 'orderEdit' ) ) {
            Response::instance()->sendMessage( 'Your account does not have order edit capabilities.');
        }
        if ( ! $o_id ) {
            Response::instance()->sendMessage( 'No orders Found' );
        }
        $updated = false;

        if( $order = Order::getOrder( $o_id ) ){
            $prev_order = clone $order;

            $o_note = isset($_POST['o_note']) ? filter_var($_POST['o_note'], FILTER_SANITIZE_STRING) : '';
            $o_i_note = isset($_POST['o_i_note']) ? filter_var($_POST['o_i_note'], FILTER_SANITIZE_STRING) : '';
            $subscriptionFreq = isset($_POST['subscriptionFreq']) ? filter_var($_POST['subscriptionFreq'], FILTER_SANITIZE_STRING) : (string)$order->getMeta( 'subscriptionFreq' );
            $s_address = ( isset( $_POST['s_address'] ) && is_array($_POST['s_address']) )? $_POST['s_address']: [];
            $o_priority = ( empty($_POST['o_priority']) || 'false' === $_POST['o_priority']) ? 0 : 1;
            $new_status = isset( $_POST['o_status'] ) ? $_POST['o_status'] : '';
            $new_i_status = isset( $_POST['o_i_status'] ) ? $_POST['o_i_status'] : '';
            $new_is_status = isset( $_POST['o_is_status'] ) ? $_POST['o_is_status'] : '';
            $o_de_id = isset( $_POST['o_de_id'] ) ? (int)$_POST['o_de_id'] : 0;

            if( !\in_array( $order->o_status, [ 'delivered', 'cancelled' ] ) && $order->o_is_status !== $new_is_status ) {
                Response::instance()->sendMessage( 'You can not modify issue in this status' );
            }
            if( $new_status !== $order->o_status && \in_array( $new_status, [ 'delivering', 'delivered' ] ) ){
                Response::instance()->sendMessage( 'You cannot set this status from admin panel.');
            }
            if( $new_i_status !== $order->o_i_status && \in_array( $new_i_status, [ 'packing', 'checking', 'confirmed', 'paid' ] ) ){
                Response::instance()->sendMessage( 'You cannot set this status from admin panel.');
            }
            if( $order->u_mobile !== $_POST['u_mobile'] ){
                Response::instance()->sendMessage( 'You cannot change user mobile number. If require change shipping mobile number');
            }
            if ( ! $s_address || ! Functions::isLocationValid( $s_address['division'] ?? '', $s_address['district'] ?? '', $s_address['area'] ?? '' ) ){
                Response::instance()->sendMessage( 'invalid location.');
            }
            if( $order->o_subtotal && empty( $_POST['medicineQty'] ) ){
                Response::instance()->sendMessage( 'Medicines are empty. Order not saved.');
            }
            if( $new_status !== $order->o_status && \in_array( $new_status, [ 'cancelled' ] ) && in_array( $order->o_i_status, [ 'checking' ] ) ){
                Response::instance()->sendMessage( 'You cannot cancel this order. Move from checking status');
            }
            if( 'operator' == $this->user->u_role && 'delivering' == $order->o_status && 'cancelled' == $new_status ){
                Response::instance()->sendMessage( 'You cannot cancel this order, Contact pharmacy');
            }
            if( 'operator' == $this->user->u_role && 'confirmed' == $order->o_status && in_array( $order->o_i_status, [ 'checking', 'confirmed' ] ) && 'cancelled' == $new_status ){
                Response::instance()->sendMessage( 'You cannot cancel this order, Contact pharmacy');
            }
            if( $s_address ){
                $s_address['location'] = sprintf('%s, %s, %s, %s', $s_address['homeAddress']??'', $s_address['area'], $s_address['district'], $s_address['division'] );
            }

            if( $o_note != $order->getMeta( 'o_note' ) ){
                $updated2 = $order->setMeta( 'o_note', $o_note );
            }
            if( $o_i_note != $order->getMeta( 'o_i_note' ) ){
                $updated2 = $order->setMeta( 'o_i_note', $o_i_note );
                $updated = $updated ?: $updated2;
            }
            if( $subscriptionFreq != $order->getMeta( 'subscriptionFreq' ) ){
                if( $subscriptionFreq ){
                    $updated2 = $order->setMeta( 'subscriptionFreq', $subscriptionFreq );
                    $updated = $updated ?: $updated2;
                } else {
                    $updated2 = $order->deleteMeta( 'subscriptionFreq' );
                    $updated = $updated ?: $updated2;
                }
            }
            $order->setMeta( 's_address', $s_address );

            $order->o_gps_address = $s_address['location'];
            $order->o_l_id = Functions::getIdByLocation( 'l_id', $s_address['division'], $s_address['district'], $s_address['area'] );
            $order->o_is_status = $new_is_status;
            $order->o_priority = $o_priority;
            $order->o_de_id = $o_de_id;

            if ( 'Dhaka City' != $s_address['district'] && 'cod' == $order->o_payment_method ){
                $order->o_payment_method = 'online';
            } else {
                $order->o_payment_method = $_POST['o_payment_method'] ?? 'cod';
            }

            if( \in_array( $order->o_status, [ 'cancelled', 'damaged' ] ) ) {
                if( $order->update() || $updated ){
                    $this->orderSingle( $order->o_id );
                }
                Response::instance()->sendMessage( 'You can not edit this order anymore.');
            }
            if( ! ( $user = User::getUser( $_POST['u_id'] ) ) ) {
                Response::instance()->sendMessage( 'Invalid order user.');
            }

            if( 'delivered' == $order->o_status) {
                if( 'returned' == $new_status  ) {
                    $order->o_status = 'returned';
                } elseif( !empty($_POST['refund']) && $user ){
                    $refund = round( $_POST['refund'], 2 );
					if( $refund > 20 ){
						Response::instance()->sendMessage( 'You can not refund more than 20 Taka.');
					}
                    $user->cashUpdate( $refund );

                    $order->appendMeta( 'o_admin_note', sprintf( '%s: %s TK refunded by %s', \date( 'Y-m-d H:i:s' ), $refund, $this->user->u_name ) );
                    $prev_refund = $order->getMeta( 'refund' );
                    if( ! is_numeric( $prev_refund ) ){
                        $prev_refund = 0;
                    }
                    $order->setMeta( 'refund', $prev_refund + $refund );
					$order->addHistory( 'Refund', $prev_refund, $prev_refund + $refund );
                }
                if( $order->update() || $updated ){
                    $this->orderSingle( $order->o_id );
                }

                Response::instance()->sendMessage( 'You can not edit this order anymore.');
            } elseif( 'returned' == $new_status ){
                if( $order->update() || $updated ){
                    $this->orderSingle( $order->o_id );
                }
                Response::instance()->sendMessage( 'You can not return an order which not yet delivered.');
            }

            $prev_o_data = (array)$prev_order->getMeta( 'o_data' );
            $prev_cash_back = 0;
            $prev_applied_cash = 0;
            $prev_delivery_fee = 0;
            if( 'call' != $prev_order->o_i_status ) {
                $prev_cash_back = $prev_order->cashBackAmount();
            }

            if( isset($prev_o_data['deductions']['cash']) ) {
                $prev_applied_cash = $prev_o_data['deductions']['cash']['amount'];
            }
            if( isset($prev_o_data['additions']['delivery']) && !empty($prev_o_data['additions']['delivery']['amount'])) {
                $prev_delivery_fee = round( $prev_o_data['additions']['delivery']['amount'] );
            }
            $d_code = isset($_POST['d_code']) ? $_POST['d_code']: '';

            $cart_data = Functions::cartData( $user, $_POST['medicineQty'] ?? [], $d_code, $order, false, ['s_address' => $s_address] );

            if( isset($cart_data['deductions']['cash']) && !empty($cart_data['deductions']['cash']['info'])) {
                $cart_data['deductions']['cash']['info'] = "Didn't apply because the order value was less than ৳499.";
            }
            if( isset($cart_data['additions']['delivery']) && !empty($cart_data['additions']['delivery']['info'])) {
                $cart_data['additions']['delivery']['info'] = str_replace('To get free delivery order more than', 'Because the order value was less than', $cart_data['additions']['delivery']['info']);
            }
            $c_medicines = $cart_data['medicines'];
            unset( $cart_data['medicines'] );

            if( ! $c_medicines && ( $new_status === 'confirmed' || $new_i_status === 'ph_fb' ) ){
                Response::instance()->sendMessage( 'Please input medicine to confirm this order.');
            }
            if( 'paid' == $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ){
				if( $new_status !== $order->o_status && 'cancelled' == $new_status ){
					$order->o_status = $new_status;
                    if( $order->update() || $updated ){
                        $this->orderSingle( $order->o_id );
                    }
				}
                if( $cart_data['total'] > ( $prev_order->o_total + $user->u_cash ) ){
                    Response::instance()->sendMessage( 'Your changes exceeded his account, cannot save');
                }
                $delivery_fee = 0;
                if( isset($cart_data['additions']['delivery']) && !empty($cart_data['additions']['delivery']['amount'])) {
                    $delivery_fee = round( $cart_data['additions']['delivery']['amount'] );
                }
                if( !$prev_delivery_fee && $delivery_fee ){
                    Response::instance()->sendMessage( 'Your changes increase delivery fee, cannot save');
                }
            }
            if ( 'pharmacy' !== $this->user->u_role && ( 'delivering' === $order->o_status || ( 'confirmed' === $order->o_status && in_array( $order->o_i_status, ['checking', 'confirmed'] ) ) ) ) {
                if( $c_medicines ){
                    $old_data = [];
                    $query = DB::instance()->select( 't_o_medicines', [ 'o_id' => $order->o_id ], 'm_id, m_qty' );
                    while ( $old = $query->fetch() ) {
                        $old_data[ $old['m_id'] ] = $old;
                    }
                    foreach ( $c_medicines as $med ){
                        if( isset( $old_data[ $med['m_id'] ] ) ) {
                            if ( $med['qty'] > $old_data[$med['m_id']]['m_qty'] ) {
                                Response::instance()->sendMessage('You can not increase item quantities for this order.');
                            }
                        } else{
                            Response::instance()->sendMessage( 'You can not add new items for this order.');
                        }
                    }
                }
            }
            if ( 'pharmacy' == $this->user->u_role ) {
                if ( 'confirmed' == $order->o_status && in_array( $order->o_i_status, [ 'confirmed' ] ) && 'cancelled' == $new_status ) {
                    if( $b_id = $order->getMeta( 'bag' ) ){
                        $bag = Bag::getBag( $b_id );
                        $bag->removeOrder( $order->o_id );
                        $order->deleteMeta( 'bag' );
                    }
                }
            }

            $o_data = $_POST;
            $o_data['o_subtotal'] = $cart_data['subtotal'];
            $o_data['o_addition'] = $cart_data['a_amount'];
            $o_data['o_deduction'] = $cart_data['d_amount'];
            $o_data['o_total'] = $cart_data['total'];

            unset( $o_data['o_gps_address'], $o_data['o_l_id'], $o_data['o_priority'], $o_data['o_payment_method']  );

            $order->update( $o_data );
            $order->setMeta( 'd_code', $d_code );
            $order->setMeta( 'o_data', $cart_data );
            Functions::ModifyOrderMedicines( $order, $c_medicines, $prev_order );

            if( isset( $_POST['attachedFiles'] ) ){
                Functions::modifyPrescriptionsImages( $order->o_id, $_POST['attachedFiles'] );
            }

            //again get user. User data may changed.
            $user = User::getUser( $order->u_id );
            if( $user ){
                $user->u_cash += $prev_applied_cash;
                if( isset($cart_data['deductions']['cash']) ) {
                    $user->u_cash -= $cart_data['deductions']['cash']['amount'];
                }
                $cash_back = 0;
                if( 'call' != $order->o_i_status ) {
                    $cash_back = $order->cashBackAmount();
                    $user->u_p_cash = $user->u_p_cash - $prev_cash_back + $cash_back;
                }
                if( ( 'paid' == $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ) && $prev_order->o_total !== $order->o_total ){
                    $user->u_cash += $prev_order->o_total - $order->o_total;
                }
                $user->update();

                if( $cash_back && ! $prev_cash_back ){
                    $message = "Congratulations!!! You have received a cashback of ৳{$cash_back} from arogga. The cashback will be automatically applied at your next order.";
                    Functions::sendNotification( $user->fcm_token, 'Cashback Received.', $message );
                }
            }

            $this->orderSingle( $order->o_id );
            
        } else {
            Response::instance()->sendMessage( 'No orders Found' );
        }

        Response::instance()->send();
    }

    public function orderCreate() {
        if( ! $this->user->can( 'orderCreate' ) ) {
            Response::instance()->sendMessage( 'Your account does not have order create capabilities.');
        }
        if(empty($_POST['u_name']) || empty($_POST['u_mobile']) || empty($_POST['medicineQty']) ) {
            Response::instance()->sendMessage( 'All Fields Required' );
        }
        if( ! ( $_POST['u_mobile'] = Functions::checkMobile( $_POST['u_mobile'] ) ) ) {
            Response::instance()->sendMessage( 'Invalid mobile number.');
        }
        $user = User::getBy( 'u_mobile', $_POST['u_mobile'] );

        if( ! $user ) {
            do{
                $u_referrer = Functions::randToken( 'distinct', 6 );
    
            } while( User::getBy( 'u_referrer', $u_referrer ) );
    
            $_POST['u_referrer'] = $u_referrer;
            $user = new User;
            $user->insert( $_POST );
        }
        $s_address = ( isset( $_POST['s_address'] ) && is_array($_POST['s_address']) )? $_POST['s_address']: [];
        if( $s_address ){
            $s_address['location'] = sprintf('%s, %s, %s, %s', $s_address['homeAddress'], $s_address['area'], $s_address['district'], $s_address['division'] );
            $_POST['o_gps_address'] = $s_address['location'];
        }
        
        $order = new Order;
        $cart_data = Functions::cartData( $user, $_POST['medicineQty'], isset($_POST['d_code']) ? $_POST['d_code']: '', null, false, ['s_address' => $s_address] );

        if( isset($cart_data['deductions']['cash']) && !empty($cart_data['deductions']['cash']['info'])) {
            $cart_data['deductions']['cash']['info'] = "Didn't apply because the order value was less than ৳499.";
        }
        if( isset($cart_data['additions']['delivery']) && !empty($cart_data['additions']['delivery']['info'])) {
            $cart_data['additions']['delivery']['info'] = str_replace('To get free delivery order more than', 'Because the order value was less than', $cart_data['additions']['delivery']['info']);
        }

        $c_medicines = $cart_data['medicines'];
        unset( $cart_data['medicines'] );

        $o_data = $_POST;
        $o_data['o_status'] = 'processing';
        $o_data['o_i_status'] = 'processing';
        $o_data['o_subtotal'] = $cart_data['subtotal'];
        $o_data['o_addition'] = $cart_data['a_amount'];
        $o_data['o_deduction'] = $cart_data['d_amount'];
        $o_data['o_total'] = $cart_data['total'];
        $o_data['u_id'] = $user->u_id;
        $o_data['o_l_id'] = Functions::getIdByLocation( 'l_id', $s_address['division'], $s_address['district'], $s_address['area'] );

        $order->insert( $o_data  );
        Functions::ModifyOrderMedicines( $order, $c_medicines );
        $meta = [
            'o_data' => $cart_data,
            'o_secret' => Functions::randToken( 'alnumlc', 16 ),
            's_address' => $s_address,
        ];
        if( ! empty( $_POST['d_code'] ) ) {
            $meta['d_code'] = $_POST['d_code'];
        }
        if( ! empty( $_POST['subscriptionFreq'] ) ) {
            $meta['subscriptionFreq'] = $_POST['subscriptionFreq'];
        }

        if( isset( $_POST['attachedFiles'] ) ){
            Functions::modifyPrescriptionsImages( $order->o_id, $_POST['attachedFiles'] );
        }

        $order->insertMetas( $meta );
		$order->addHistory( 'Created', 'Created through Admin' );

        $cash_back = $order->cashBackAmount();

        //again get user. User data may changed.
        $user = User::getUser( $order->u_id );
        
        if ( $cash_back ) {
            $user->u_p_cash = $user->u_p_cash + $cash_back;
        }
        if( isset($cart_data['deductions']['cash']) ){
            $user->u_cash = $user->u_cash - $cart_data['deductions']['cash']['amount'];
        }
        $user->update();
        
        $this->orderSingle( $order->o_id );
    }

    public function orderUpdateMany() {
        $o_ids = $_POST['ids'] ?? [];
        $data = $_POST['data'] ?? [];
        if( !$o_ids || !$data || !is_array( $o_ids ) || !is_array( $data ) ){
            Response::instance()->sendMessage( 'Invalid request');
        }
        if( ! $this->user->can( 'orderEdit' ) ) {
            Response::instance()->sendMessage( 'Your account does not have order edit capabilities.');
        }
        $allowed_keys = [ 'o_de_id' ];
        $data = array_intersect_key( $data, array_flip( $allowed_keys ) );

        $updated = DB::instance()->update( 't_orders', $data, [ 'o_id' => $o_ids ] );

        if( $updated ){
            foreach ( $o_ids as $o_id ) {
                Cache::instance()->delete( $o_id, 'order' );
            }

            Response::instance()->sendMessage( sprintf('%d orders updated', $updated ), 'success' );
        } else {
            Response::instance()->sendMessage( 'No orders updated' );
        }
    }

    private function reOrder( $o_id ){
        if( ! $this->user->can( 'orderCreate' ) ) {
            Response::instance()->sendMessage( 'Your account does not have order create capabilities.');
        }
        if ( $order = Functions::reOrder( $o_id ) ){
            //trigger changes
            //$order->update( [ 'o_status' => 'confirmed', 'o_i_status' => 'ph_fb' ] );
            Response::instance()->sendMessage( 'Successfully re-ordered.', 'success' );
        } else {
            Response::instance()->sendMessage( 'Something wrong.');
        }
    }

    public function offlineOrders() {
        $ids = isset( $_GET['ids'] ) ? $_GET['ids'] : '';
        $search = isset( $_GET['_search'] ) ? $_GET['_search'] : '';
        $ph_id = isset( $_GET['_ph_id'] ) ? $_GET['_ph_id'] : 0;
        $status = isset( $_GET['_status'] ) ? $_GET['_status'] : '';
        $i_status = isset( $_GET['_i_status'] ) ? $_GET['_i_status'] : '';
        $o_created = isset( $_GET['_o_created'] ) ? $_GET['_o_created'] : '';
        $orderBy = isset( $_GET['_orderBy'] ) ? $_GET['_orderBy'] : '';
        $order = ( isset( $_GET['_order'] ) && 'DESC' == $_GET['_order'] ) ? 'DESC' : 'ASC';
        $page = isset( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = isset( $_GET['_perPage'] ) ? (int)$_GET['_perPage'] : 20;

        $db = new DB;

        $db->add( 'SELECT SQL_CALC_FOUND_ROWS * FROM t_orders WHERE 1=1' );
        if ( $search && \is_numeric( $search ) ) {
            $search = addcslashes( $search, '_%\\' );
            $db->add( ' AND o_id LIKE ?', "{$search}%" );
        }
        //For offline orders there is no user 
        $db->add( ' AND u_id = ?', 0 );

        if( $ids ) {
            $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
            $in  = str_repeat('?,', count($ids) - 1) . '?';
            $db->add( " AND o_id IN ($in)", ...$ids );

            $perPage = count($ids);
        }
        if( 'pharmacy' == $this->user->u_role ){
            $db->add( ' AND o_ph_id = ?', Auth::id() );
        } elseif( $ph_id ) {
            $db->add( ' AND o_ph_id = ?', $ph_id );
        } else {
            $db->add( ' AND o_ph_id > ?', 0 );
        }

        if( $status ) {
            $db->add( ' AND o_status = ?', $status );
        }
        if( $i_status ) {
            $db->add( ' AND o_i_status = ?', $i_status );
        }
        if( $o_created ) {
            $db->add( ' AND o_created >= ? AND o_created <= ?', $o_created . ' 00:00:00', $o_created . ' 23:59:59' );
        }
        if( $orderBy && \property_exists('\OA\Factory\Order', $orderBy ) ) {
            $db->add( " ORDER BY $orderBy $order" );
        }
        
        $limit    = $perPage * ( $page - 1 );
        $db->add( ' LIMIT ?, ?', $limit, $perPage );
        
        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Order');

        while( $order = $query->fetch() ){
            $data = $order->toArray();
            $data['id'] = $order->o_id;
            $data['supplierPrice'] = 'delivered' == $order->o_status ? $order->getMeta( 'supplierPrice' ) : 0.00;
            //$data['medicineQty'] = $order->medicineQty;

            Response::instance()->appendData( '', $data );
        }
        if ( Response::instance()->getData() ) {
            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        } else {
            Response::instance()->sendMessage( 'No orders Found' );
        }
    }

    public function offlineOrderCreate() {
        if( ! $this->user->can( 'offlineOrderCreate' ) ) {
            Response::instance()->sendMessage( 'Your account does not have order create capabilities.');
        }
        if( empty($_POST['medicineQty']) ) {
            Response::instance()->sendMessage( 'Medicines Required' );
        }
        $args = [];
        $man_discount = ( !empty($_POST['man_discount']) && is_numeric( $_POST['man_discount'] ) ) ? \round( $_POST['man_discount'], 2 ) : 0;
        $man_addition = ( !empty($_POST['man_addition']) && is_numeric( $_POST['man_addition'] ) ) ? \round( $_POST['man_addition'], 2 ) : 0;
        $args['man_discount'] = $man_discount;
        $args['man_addition'] = $man_addition;

        $order = new Order;
        $cart_data = Functions::cartData( '', $_POST['medicineQty'], '', null, true, $args );

        $c_medicines = $cart_data['medicines'];
        unset( $cart_data['medicines'] );

        $o_data = $_POST;
        $o_data['o_subtotal'] = $cart_data['subtotal'];
        $o_data['o_addition'] = $cart_data['a_amount'];
        $o_data['o_deduction'] = $cart_data['d_amount'];
        $o_data['o_total'] = $cart_data['total'];
        $o_data['u_name'] = 'Offline';
        $o_data['u_id'] = 0;
        $o_data['o_ph_id'] = Auth::id();
        $o_data['o_de_id'] = Auth::id();
        $o_data['o_status'] = 'delivered';
        $o_data['o_i_status'] = 'confirmed';
        $o_data['o_delivered'] = \date( 'Y-m-d H:i:s' );

        $order->insert( $o_data  );
        Functions::ModifyOrderMedicines( $order, $c_medicines );
        $meta = [ 
            'o_data' => $cart_data,
            'man_discount' => $man_discount,
            'man_addition' => $man_addition,
        ];
        $order->insertMetas( $meta );

        foreach ( $order->medicineQty as $id_qty ) {
            $m_id = isset($id_qty['m_id']) ? (int)$id_qty['m_id'] : 0;
            $quantity = isset($id_qty['qty']) ? (int)$id_qty['qty'] : 0;

            if( $inventory = Inventory::getByPhMid( $order->o_ph_id, $m_id ) ){
                $inventory->i_qty = $inventory->i_qty - $quantity;
                $inventory->update();
                DB::instance()->update( 't_o_medicines', ['om_status' => 'available', 's_price' => $inventory->i_price ], [ 'o_id' => $order->o_id, 'm_id' => $m_id ] );
            }
        }

        $query2 = DB::db()->prepare( "SELECT SUM(s_price*m_qty) FROM t_o_medicines WHERE o_id = ? AND om_status = ?" );
        $query2->execute( [ $order->o_id, 'available' ] );
        $supplierPrice = round( $query2->fetchColumn(), 2 );
        $order->setMeta( 'supplierPrice', $supplierPrice );

        //To trigger
        //$order->update( ['o_status' => 'delivering', 'o_i_status' => 'confirmed'] );
        //$order->update( ['o_status' => 'delivered'] );
        
        $this->orderSingle( $order->o_id );
    }

    public function offlineOrderUpdate( $o_id ) {

        Response::instance()->sendMessage( 'Offline order update is not possible right now.');

        if( ! $this->user->can( 'orderEdit' ) ) {
            Response::instance()->sendMessage( 'Your account does not have order edit capabilities.');
        }
        if ( ! $o_id ) {
            Response::instance()->sendMessage( 'No orders Found' );
        }

        if( $order = Order::getOrder( $o_id ) ){
            $prev_order = clone $order;
            $prev_o_data = (array)$prev_order->getMeta( 'o_data' );
            $cart_data = Functions::cartData( '', $_POST['medicineQty'], '', $order, true );

            $c_medicines = $cart_data['medicines'];
            unset( $cart_data['medicines'] );

            $o_data = $_POST;
            $o_data['o_subtotal'] = $cart_data['subtotal'];
            $o_data['o_addition'] = $cart_data['a_amount'];
            $o_data['o_deduction'] = $cart_data['d_amount'];
            $o_data['o_total'] = $cart_data['total'];

            $order->update( $o_data );
            $order->setMeta( 'o_data', $cart_data );
            Functions::ModifyOrderMedicines( $order, $c_medicines, $prev_order );

            $this->orderSingle( $order->o_id );
            
        } else {
            Response::instance()->sendMessage( 'No orders Found' );
        }

        Response::instance()->send();
    }

    public function orderMedicines() {
        $ids = isset( $_GET['ids'] ) ? $_GET['ids'] : '';
        $search = isset( $_GET['_search'] ) ? $_GET['_search'] : '';
        $category = isset( $_GET['_category'] ) ? $_GET['_category'] : '';
        $c_id = isset( $_GET['_c_id'] ) ? $_GET['_c_id'] : 0;
        $ph_id = isset( $_GET['_ph_id'] ) ? $_GET['_ph_id'] : 0;
        $om_status = isset( $_GET['_om_status'] ) ? $_GET['_om_status'] : '';
        $orderBy = isset( $_GET['_orderBy'] ) ? $_GET['_orderBy'] : '';
        $order = ( isset( $_GET['_order'] ) && 'DESC' == $_GET['_order'] ) ? 'DESC' : 'ASC';
        $page = isset( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = isset( $_GET['_perPage'] ) ? (int)$_GET['_perPage'] : 20;

        $o_delivered = isset( $_GET['_o_delivered'] ) ? $_GET['_o_delivered'] : '';
        $status = isset( $_GET['_status'] ) ? $_GET['_status'] : '';
        $i_status = isset( $_GET['_i_status'] ) ? $_GET['_i_status'] : '';
        $cat_id = isset( $_GET['_cat_id'] ) ? (int)$_GET['_cat_id'] : 0;

        $db = new DB;

        $db->add( 'SELECT SQL_CALC_FOUND_ROWS tom.*, (100 - (tom.s_price/tom.m_d_price*100)) as supplier_percent, tm.m_name, tm.m_form, tm.m_strength, tr.o_created, tr.o_delivered, tr.o_status, tr.o_i_status FROM t_o_medicines tom INNER JOIN t_medicines tm ON tom.m_id = tm.m_id INNER JOIN t_orders tr ON tom.o_id = tr.o_id WHERE 1=1' );
        if ( $search ) {
            if( \is_numeric( $search ) ) {
                $search = addcslashes( $search, '_%\\' );
                $db->add( ' AND tom.o_id LIKE ?', "{$search}%" );
            } else {
                $search = addcslashes( $search, '_%\\' );
                $db->add( ' AND tm.m_name LIKE ?', "{$search}%" );
            }
        }
        if( $category ) {
            $db->add( ' AND tm.m_category = ?', $category );
        }
        if( $c_id ) {
            $db->add( ' AND tm.m_c_id = ?', $c_id );
        }
        if ( $cat_id ) {
            $db->add( ' AND tm.m_cat_id = ?', $cat_id );
        }
        if( $ph_id ) {
            $db->add( ' AND tr.o_ph_id = ?', $ph_id );
        }
        if( $ids ) {
            $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
            $in  = str_repeat('?,', count($ids) - 1) . '?';
            $db->add( " AND tom.om_id IN ($in)", ...$ids );

            $perPage = count($ids);
        }

        if( $status ) {
            $db->add( ' AND tr.o_status = ?', $status );
        }
        if( $i_status ) {
            $db->add( ' AND tr.o_i_status = ?', $i_status );
        }
        if( $om_status ) {
            $db->add( ' AND tom.om_status = ?', $om_status );
        }

        if( $o_delivered ) {
            $db->add( ' AND tr.o_delivered >= ? AND tr.o_delivered <= ?', $o_delivered . ' 00:00:00', $o_delivered . ' 23:59:59' );
        }

        if( $orderBy && \in_array( $orderBy, ['o_id', 'm_qty', 'm_unit', 'm_price', 'm_d_price', 's_price', 'om_status'] ) ) {
            $db->add( " ORDER BY tom.{$orderBy} $order" );
        } elseif( $orderBy && \in_array( $orderBy, ['m_name', 'm_form', 'm_strength'] ) ) {
            $db->add( " ORDER BY tm.{$orderBy} $order" );
        } elseif( $orderBy && \in_array( $orderBy, ['o_created', 'o_delivered', 'o_status'] ) ) {
            $db->add( " ORDER BY tr.{$orderBy} $order" );
        } elseif( $orderBy && \in_array( $orderBy, ['supplier_percent'] ) ) {
            $db->add( " ORDER BY supplier_percent $order" );
        }

        $limit    = $perPage * ( $page - 1 );
        $db->add( ' LIMIT ?, ?', $limit, $perPage );
        
        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();

        while( $data = $query->fetch() ) {
            $data['id'] = $data['om_id'];
            $data['m_price_total'] = \round( $data['m_qty'] * $data['m_price'], 2 );
            $data['m_d_price_total'] = \round( $data['m_qty'] * $data['m_d_price'], 2 );
            $data['s_price_total'] = \round( $data['m_qty'] * $data['s_price'], 2 );
            $data['supplier_percent'] = \round( $data['supplier_percent'], 1 ) . '%';
            unset($data['o_delivered']);
            $data['attachedFiles'] = Functions::getPicUrlsAdmin( Meta::get( 'medicine', $data['m_id'], 'images' ) );

            Response::instance()->appendData( '', $data );
        }

        if ( ! Response::instance()->getData() ) {
            Response::instance()->sendMessage( 'No orders Found' );
        } else {
            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        }
    }

    function orderMedicineSingle( $om_id ) {
        $query = DB::db()->prepare( 'SELECT tom.*, tm.m_name, tm.m_form, tm.m_strength FROM t_o_medicines tom INNER JOIN t_medicines tm ON tom.m_id = tm.m_id WHERE tom.om_id = ? LIMIT 1' );
        $query->execute( [ $om_id ] );
        if( $om = $query->fetch() ){
            $data = $om;
            $data['id'] = $om['om_id'];

            Response::instance()->setStatus( 'success' );
            Response::instance()->setData( $data );
        } else {
            Response::instance()->sendMessage( 'No order medicine Found' );
        }

        Response::instance()->send();
    }

    public function orderMedicineUpdate( $om_id ) {
        $s_price = isset( $_POST['s_price'] ) ? \round( $_POST['s_price'], 2 ) : 0.00;
        $om_status = isset( $_POST['om_status'] ) ? $_POST['om_status'] : '';

        DB::instance()->update( 't_o_medicines', [ 's_price' => $s_price, 'om_status' => $om_status], [ 'om_id' => $om_id ] );
        
        $this->orderMedicineSingle( $om_id );
    }

    public function orderMedicineDelete( $om_id ) {
        Response::instance()->sendMessage( 'Deleting not alowed. Delete from Order Edit page.');
    }

    public function inventory() {
        if( ! $this->user->can( 'inventoryView' ) ) {
            Response::instance()->sendMessage( 'Your account does not have inventory view capabilities.');
        }
        $ids = isset( $_GET['ids'] ) ? $_GET['ids'] : '';
        $search = isset( $_GET['_search'] ) ? $_GET['_search'] : '';
        $category = isset( $_GET['_category'] ) ? $_GET['_category'] : '';
        $c_id = isset( $_GET['_c_id'] ) ? $_GET['_c_id'] : 0;
        $ph_id = isset( $_GET['_ph_id'] ) ? $_GET['_ph_id'] : 0;
        $u_id = isset( $_GET['_u_id'] ) ? $_GET['_u_id'] : 0;
        $qty = isset( $_GET['_qty'] ) ? $_GET['_qty'] : '';
        $stock = isset( $_GET['_stock'] ) ? $_GET['_stock'] : '';
        $m_r_count = isset( $_GET['_m_r_count'] ) ? (int)$_GET['_m_r_count'] : 0;
        $orderBy = isset( $_GET['_orderBy'] ) ? $_GET['_orderBy'] : '';
        $order = ( isset( $_GET['_order'] ) && 'DESC' == $_GET['_order'] ) ? 'DESC' : 'ASC';
        $page = isset( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = isset( $_GET['_perPage'] ) ? (int)$_GET['_perPage'] : 20;
        $cat_id = isset( $_GET['_cat_id'] ) ? (int)$_GET['_cat_id'] : 0;
        $not_assigned = isset( $_GET['_not_assigned'] ) ? filter_var( $_GET['_not_assigned'], FILTER_VALIDATE_BOOLEAN ) : false;

        $db = new DB;

        $db->add( 'SELECT SQL_CALC_FOUND_ROWS ti.*, (100 - (ti.i_price/tm.m_price*100)) as discount_percent, (100 - (ti.i_price/tm.m_d_price*100)) as profit_percent, (ti.i_qty*ti.i_price) as i_price_total, ROUND(ti.i_qty/(ti.wkly_req/7), 1) as stock_days, tm.m_id, tm.m_name, tm.m_form, tm.m_unit, tm.m_strength, tm.m_price, tm.m_d_price, tm.m_r_count, tpr.u_id FROM t_inventory ti INNER JOIN t_medicines tm ON ti.i_m_id = tm.m_id LEFT JOIN t_purchase_request AS tpr ON ti.i_ph_id = tpr.ph_id AND ti.i_m_id = tpr.m_id WHERE 1=1' );
        if ( $search ) {
            $search = addcslashes( $search, '_%\\' );
            $db->add( ' AND tm.m_name LIKE ?', "{$search}%" );
        }
        if( $category ) {
            $db->add( ' AND tm.m_category = ?', $category );
        }
        if( $c_id ) {
            $db->add( ' AND tm.m_c_id = ?', $c_id );
        }
        if ( $cat_id ) {
            $db->add( ' AND tm.m_cat_id = ?', $cat_id );
        }
        if( $m_r_count ){
            $db->add( ' AND tm.m_r_count >= ?', $m_r_count );
        }
        if( $ph_id ) {
            $db->add( ' AND ti.i_ph_id = ?', $ph_id );
        }
        if( $u_id ) {
            $db->add( ' AND tpr.u_id = ?', $u_id );
        }
        if( $not_assigned ) {
            $db->add( ' AND tpr.u_id IS NULL' );
        }

        if( '<0' == $qty ) {
            $db->add( ' AND ti.i_qty < ?', 0 );
        } elseif( 'zero' == $qty ) {
            $db->add( ' AND ti.i_qty = ?', 0 );
        } elseif( '>0' == $qty ) {
            $db->add( ' AND ti.i_qty > ?', 0 );
        } elseif( '>100' == $qty ) {
            $db->add( ' AND ti.i_qty > ?', 100 );
        } elseif( '1-10' == $qty ) {
            $db->add( ' AND ti.i_qty BETWEEN ? AND ?', 1, 10 );
        } elseif( '11-100' == $qty ) {
            $db->add( ' AND ti.i_qty BETWEEN ? AND ?', 11, 100 );
        }
        if( '<0' == $stock ) {
            $db->add( ' AND ti.i_qty < ?', 0 );
        } elseif( '>0' == $stock ) {
            $db->add( ' AND ti.i_qty > ?', 0 );
        } elseif( '<7' == $stock ) {
            $db->add( ' AND ti.wkly_req > ? AND ti.i_qty/(ti.wkly_req/7) <= ?', 0, 7 );
        } elseif( '7-10' == $stock ) {
            $db->add( ' AND ti.i_qty/(ti.wkly_req/7) > ? AND ti.i_qty/(ti.wkly_req/7) <= ?', 7, 10 );
        } elseif( '10-15' == $stock ) {
            $db->add( ' AND ti.i_qty/(ti.wkly_req/7) > ? AND ti.i_qty/(ti.wkly_req/7) <= ?', 10, 15 );
        } elseif( '15-30' == $stock ) {
            $db->add( ' AND ti.i_qty/(ti.wkly_req/7) > ? AND ti.i_qty/(ti.wkly_req/7) <= ?', 15, 30 );
        }
        if( $ids ) {
            $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
            $in  = str_repeat('?,', count($ids) - 1) . '?';
            $db->add( " AND ti.i_id IN ($in)", ...$ids );

            $perPage = count($ids);
        }

        if( $orderBy && \in_array( $orderBy, [ 'm_name', 'm_form', 'm_unit', 'm_strength', 'm_price', 'm_d_price', 'm_r_count'] ) ) {
            $db->add( " ORDER BY tm.{$orderBy} $order" );
        } elseif( $orderBy && \in_array($orderBy, ['discount_percent', 'profit_percent', 'i_price_total', 'stock_days'] ) ) {
            $db->add( " ORDER BY $orderBy $order" );
        } elseif( $orderBy && \in_array($orderBy, ['u_id'] ) ) {
            //$db->add( " ORDER BY $orderBy $order" );
        } elseif( $orderBy ) {
            $db->add( " ORDER BY ti.{$orderBy} $order" );
        }

        $limit    = $perPage * ( $page - 1 );
        $db->add( ' LIMIT ?, ?', $limit, $perPage );
        
        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();
        $datas = $query->fetchAll();

        foreach( $datas as $data ) {
            $data['id'] = $data['i_id'];
            $data['purchaseAssigned'] = User::getName( $data['u_id'] );
            $data['ph_name'] = User::getName( $data['i_ph_id'] );
            $data['i_price'] = \round( $data['i_price'], 2 );
            $data['i_price_total'] = \round( $data['i_price_total'] );
            $data['discount_percent'] = \round($data['discount_percent'], 1) . '%';
            $data['profit_percent'] = \round($data['profit_percent'], 1) . '%';
            $data['attachedFiles'] = Functions::getPicUrlsAdmin( Meta::get( 'medicine', $data['m_id'], 'images' ) );
            Response::instance()->appendData( '', $data );
        }

        if ( ! Response::instance()->getData() ) {
            Response::instance()->sendMessage( 'No inventory items Found' );
        } else {
            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        }
    }

    function inventorySingle( $i_id ) {
        if( $inventory = Inventory::getInventory( $i_id ) ){
            $data = $inventory->toArray();
            $data['id'] = $inventory->i_id;
            $data['i_price'] = \round( $data['i_price'], 2 );
			$data['i_note'] = (string)( $inventory->getMeta( 'i_note' ) );

            if( $medicine = Medicine::getMedicine( $inventory->i_m_id ) ){
                $data['m_name'] = $medicine->m_name;
                $data['m_form'] = $medicine->m_form;
                $data['m_unit'] = $medicine->m_unit;
                $data['m_strength'] = $medicine->m_strength;
            }

            $db = new DB;
            $db->add( 'SELECT SUM(tom.m_qty) AS packing_qty FROM t_o_medicines AS tom INNER JOIN t_orders AS tr ON tom.o_id = tr.o_id WHERE 1=1' );
            $db->add( ' AND tom.om_status = ?', 'available' );
            $db->add( ' AND tr.o_status IN (?, ?)', 'processing', 'confirmed' );
            $db->add( ' AND tr.o_i_status = ? AND tom.m_id = ?', 'packing', $inventory->i_m_id );
            $query = $db->execute();
            if( $packing_qty = $query->fetchColumn() ){
                $data['packing_qty'] = $packing_qty;
            }

            Response::instance()->sendData( $data, 'success' );
        } else {
            Response::instance()->sendMessage( 'No inventory items Found' );
        }
    }

    public function inventoryUpdate( $i_id ) {
        if( ! $this->user->can( 'inventoryEdit' ) ) {
            Response::instance()->sendMessage( 'Your account does not have inventory edit capabilities.');
        }
        $i_price = isset( $_POST['i_price'] ) ? \round( $_POST['i_price'], 4 ) : 0.0000;
        $i_qty = isset( $_POST['i_qty'] ) ? \intval( $_POST['i_qty'] ) : 0;

        $qty_damage = isset( $_POST['qty_damage'] ) ? \intval( $_POST['qty_damage'] ) : 0;
        $qty_lost = isset( $_POST['qty_lost'] ) ? \intval( $_POST['qty_lost'] ) : 0;
        $qty_found = isset( $_POST['qty_found'] ) ? \intval( $_POST['qty_found'] ) : 0;

        if( $inventory = Inventory::getInventory( $i_id ) ){
			if( ( $qty_damage || $qty_lost || $qty_found ) && ( $medicine = Medicine::getMedicine( $inventory->i_m_id ) ) ) {
				$note = [];
				if( $qty_damage ){
					$note[] = sprintf( '%s: Damage: %s (Change %s to %s)', \date( 'Y-m-d H:i:s' ), Functions::qtyTextClass( $qty_damage, $medicine ), Functions::qtyTextClass( $i_qty, $medicine ), Functions::qtyTextClass( $i_qty - $qty_damage , $medicine ) );
					$i_qty -= $qty_damage;
				}
				if( $qty_lost ){
					$note[] = sprintf( '%s: Lost: %s (Change %s to %s)', \date( 'Y-m-d H:i:s' ), Functions::qtyTextClass( $qty_lost, $medicine ), Functions::qtyTextClass( $i_qty, $medicine ), Functions::qtyTextClass( $i_qty - $qty_lost , $medicine ) );
					$i_qty -= $qty_lost;
				}
				if( $qty_found ){
					$note[] = sprintf( '%s: Found: %s (Change %s to %s)', \date( 'Y-m-d H:i:s' ), Functions::qtyTextClass( $qty_found, $medicine ), Functions::qtyTextClass( $i_qty, $medicine ), Functions::qtyTextClass( $i_qty + $qty_found , $medicine ) );
					$i_qty += $qty_found;
				}
				if( $note ){
					$note = array_filter( array_merge( [$inventory->getMeta( 'i_note' )], $note ) );
        			$inventory->setMeta( 'i_note', implode( "\n", $note ) );
				}
			}
            $inventory->i_price = $i_price;
            $inventory->i_qty = $i_qty;
            $inventory->update();
        }
        
        $this->inventorySingle( $i_id );
    }

    public function inventoryDelete( $i_id ) {
		Response::instance()->sendMessage( 'Inventory cannot be deleted.');

        if( ! $this->user->can( 'inventoryEdit' ) ) {
            Response::instance()->sendMessage( 'Your account does not have inventory edit capabilities.');
        }
        if( $inventory = Inventory::getInventory( $i_id ) ){
            $inventory->delete();
            Response::instance()->sendData( ['id' => $i_id ], 'success');
        } else {
            Response::instance()->sendMessage( 'No inventory items Found' );
        }
    }

    public function inventoryBalance(){
        $query = DB::db()->query( 'SELECT SUM(i_price*i_qty) as totalBalance FROM t_inventory' );
        $balance = $query->fetchColumn();
        $data = [
            'totalBalance' => \round( $balance, 2 ),
        ];
        Response::instance()->sendData( $data, 'success');
    }

   public function purchases() {
        if( ! $this->user->can( 'purchasesView' ) ) {
            Response::instance()->sendMessage( 'Your account does not have purchases view capabilities.');
        }
        $ids = isset( $_GET['ids'] ) ? $_GET['ids'] : '';
        $search = isset( $_GET['_search'] ) ? $_GET['_search'] : '';
        $category = isset( $_GET['_category'] ) ? $_GET['_category'] : '';
        $c_id = isset( $_GET['_c_id'] ) ? $_GET['_c_id'] : 0;
        $ph_id = isset( $_GET['_ph_id'] ) ? $_GET['_ph_id'] : 0;
        $status = isset( $_GET['_status'] ) ? $_GET['_status'] : '';
        $expiry = isset( $_GET['_expiry'] ) ? $_GET['_expiry'] : '';
        $orderBy = isset( $_GET['_orderBy'] ) ? $_GET['_orderBy'] : '';
        $order = ( isset( $_GET['_order'] ) && 'DESC' == $_GET['_order'] ) ? 'DESC' : 'ASC';
        $page = isset( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = isset( $_GET['_perPage'] ) ? (int)$_GET['_perPage'] : 20;
        $cat_id = isset( $_GET['_cat_id'] ) ? (int)$_GET['_cat_id'] : 0;
        
        $db = new DB;

        $db->add( 'SELECT SQL_CALC_FOUND_ROWS tpu.*, (100 - (tpu.pu_price/tm.m_price*100)) as discount_percent, (100 - (tpu.pu_price/tm.m_d_price*100)) as profit_percent, (tpu.pu_qty*tpu.pu_price) as pu_price_total, tm.m_id, tm.m_name, tm.m_form, tm.m_strength, tm.m_price, tm.m_d_price, ti.wkly_req FROM t_purchases tpu INNER JOIN t_medicines tm ON tpu.pu_m_id = tm.m_id LEFT JOIN t_inventory ti ON tpu.pu_ph_id = ti.i_ph_id AND tpu.pu_m_id = ti.i_m_id WHERE 1=1' );
        if ( $search ) {
            $search = addcslashes( $search, '_%\\' );
            if( 0 === \stripos( $search, 'i-' ) ){
                $search = substr( $search, 2 );
                if( $search )
                $db->add( ' AND tpu.pu_inv_id = ?', $search );
            } elseif( 0 === \stripos( $search, 'b-' ) ){
                $search = substr( $search, 2 );
                if( $search )
                $db->add( ' AND tpu.m_batch = ?', $search );
            } else {
                $db->add( ' AND tm.m_name LIKE ?', "{$search}%" );
            }
        }
        if( $category ) {
            $db->add( ' AND tm.m_category = ?', $category );
        }
        if( $c_id ) {
            $db->add( ' AND tm.m_c_id = ?', $c_id );
        }
        if ( $cat_id ) {
            $db->add( ' AND tm.m_cat_id = ?', $cat_id );
        }
        if( $ph_id ) {
            $db->add( ' AND tpu.pu_ph_id = ?', $ph_id );
        }
        if( $status ) {
            $db->add( ' AND tpu.pu_status = ?', $status );
        }
        if( 'expired' == $expiry ) {
            $db->add( ' AND tpu.m_expiry BETWEEN ? AND ?', '0000-00-00', \date( 'Y-m-d H:i:s' ) );
        } elseif( 'n3' == $expiry ){
            $db->add( ' AND tpu.m_expiry BETWEEN ? AND ?', \date( 'Y-m-d H:i:s' ), \date( 'Y-m-d H:i:s', strtotime("+3 months") ) );
        } elseif( 'n6' == $expiry ){
            $db->add( ' AND tpu.m_expiry BETWEEN ? AND ?', \date( 'Y-m-d H:i:s' ), \date( 'Y-m-d H:i:s', strtotime("+6 months") ) );
        }
        if( $ids ) {
            $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
            $in  = str_repeat('?,', count($ids) - 1) . '?';
            $db->add( " AND tpu.pu_id IN ($in)", ...$ids );

            $perPage = count($ids);
        }

        if( $orderBy && \in_array( $orderBy, [ 'm_name', 'm_form', 'm_strength', 'm_price', 'm_d_price'] ) ) {
            $db->add( " ORDER BY tm.{$orderBy} $order" );
        } elseif( $orderBy && \in_array($orderBy, ['discount_percent', 'profit_percent', 'pu_price_total'] ) ) {
            $db->add( " ORDER BY $orderBy $order" );
        } elseif( $orderBy && \in_array($orderBy, ['wkly_req'] ) ) {
            $db->add( " ORDER BY ti.{$orderBy} $order" );
        } elseif( $orderBy ) {
            $db->add( " ORDER BY tpu.{$orderBy} $order" );
        }

        $limit    = $perPage * ( $page - 1 );
        $db->add( ' LIMIT ?, ?', $limit, $perPage );
        
        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();
        $datas = $query->fetchAll();

        foreach( $datas as $data ) {
            $data['id'] = $data['pu_id'];
            $data['ph_name'] = User::getName( $data['pu_ph_id'] );
            $data['pu_price'] = \round( $data['pu_price'], 2 );
            $data['pu_price_total'] = \round( $data['pu_price_total'] );
            $data['discount_percent'] = \round($data['discount_percent'], 1) . '%';
            $data['profit_percent'] = \round($data['profit_percent'], 1) . '%';
            Response::instance()->appendData( '', $data );
        }

        if ( Response::instance()->getData() ) {
            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        } else {
            Response::instance()->sendMessage( 'No purchases items Found' );
        }
    }

    public function purchaseCreate() {
        $items = isset( $_POST['items'] ) ? $_POST['items'] : '';
        if( !\is_array( $items ) ){
            Response::instance()->sendMessage( 'Items are malformed.');
        }

        $ph_id = isset( $_POST['pu_ph_id'] ) ? \intval( $_POST['pu_ph_id'] ) : 0;
        if( !$ph_id ){
            Response::instance()->sendMessage( 'No pharmacy selected.');
        }

        $pu_inv_id = isset( $_POST['pu_inv_id'] ) ? (int)$_POST['pu_inv_id'] : '';
        if( !$pu_inv_id ){
            $pu_inv_id = DB::db()->query('SELECT MAX(pu_inv_id) FROM t_purchases')->fetchColumn() + 1;
            //Response::instance()->sendMessage( 'No invoice number given.');
        }

        $d_percent = isset( $_POST['d_percent'] ) ? \round( $_POST['d_percent'], 8 ) : 0;
        
        $insert = [];
        foreach ( $items as $item ) {
            if( ! \is_array( $item ) ){
                continue;
            }
            $m_id = isset( $item['pu_m_id'] ) ? \intval( $item['pu_m_id'] ) : 0;
            $pu_price = isset( $item['pu_price'] ) ? \round( $item['pu_price'], 4) : 0.0000;
            $pu_qty = isset( $item['pu_qty'] ) ? \intval( $item['pu_qty'] ) : 0;
            $m_unit = isset( $item['m_unit'] ) ? filter_var($item['m_unit'], FILTER_SANITIZE_STRING) : '';
            $expMonth = isset( $item['expMonth'] ) ? \intval( $item['expMonth'] ) : 0;
            $expYear = isset( $item['expYear'] ) ? \intval( $item['expYear'] ) : 0;
            $batch = ( isset( $item['batch'] ) && 'undefined' != $item['batch'] ) ? filter_var($item['batch'], FILTER_SANITIZE_STRING) : '';

            $exp = '0000-00-00';
            if( $expMonth && $expYear && checkdate( $expMonth, 1, $expYear ) ){
                $exp = $expYear . '-' . $expMonth . '-01';
            }

            if( ! $m_id ){
                continue;
            }

            if ( $pu_price && $d_percent ){
                $pu_price = $pu_price - (( $pu_price * $d_percent)/100);
            }

            if( $pu_qty ){
                $per_price = \round( $pu_price / $pu_qty, 4 );
            } else {
                $per_price = 0.0000;
            }

            $insert[] = [
                'pu_inv_id' => $pu_inv_id,
                'pu_ph_id' => $ph_id,
                'pu_m_id' => $m_id,
                'pu_price' => $per_price,
                'pu_qty' => $pu_qty,
                'm_unit' => $m_unit,
                'pu_created' => \date( 'Y-m-d H:i:s' ),
                'm_expiry' => $exp,
                'm_batch' => $batch,
                //'pu_status' => 'pending', //default
            ];
        }
        $id = 0;
        if( $insert ){
            $id = DB::instance()->insertMultiple( 't_purchases', $insert );
        }
        
        Response::instance()->sendData( ['id' => $id], 'success' );
    }

    function purchaseSingle( $pu_id ) {
        if( ! $pu_id ){
            Response::instance()->sendMessage( 'No purchase item Found' );
        }
        $query = DB::db()->prepare( 'SELECT tpu.*, tm.m_name, tm.m_form, tm.m_strength, tm.m_price, tm.m_d_price FROM t_purchases tpu INNER JOIN t_medicines tm ON tpu.pu_m_id = tm.m_id WHERE tpu.pu_id = ? LIMIT 1' );
        $query->execute( [ $pu_id ] );
        $data = $query->fetch();
        if( $data ){
            $data['id'] = $data['pu_id'];
            $data['pu_price'] = \round( $data['pu_price'], 2 );
            Response::instance()->sendData( $data, 'success' );
        } else {
            Response::instance()->sendMessage( 'No purchase item Found' );
        }
    }

    public function purchasesSync(){
        $ph_m_ids = [];
        $inv_ids = [];
		$pu_inv_id = $_POST['pu_inv_id'] ?? 0;
		if( !$pu_inv_id ){
			Response::instance()->sendMessage( 'No invoice selected' );
		}
        DB::db()->beginTransaction();
        try {
            $query = DB::db()->prepare( 'SELECT * FROM t_purchases WHERE pu_inv_id = ? AND pu_status = ?' );
            $query->execute( [ $pu_inv_id, 'pending' ] );
            $insert= [];
            while( $pu = $query->fetch() ) {
                $ph_m_ids[ $pu['pu_ph_id'] ][] = $pu['pu_m_id'];
                if ( ! isset( $inv_ids[ $pu['pu_inv_id'] ] ) ){
                    $inv_ids[ $pu['pu_inv_id'] ] = 0;
                }
                $inv_ids[ $pu['pu_inv_id'] ] += $pu['pu_price'] * $pu['pu_qty'];

                if( $inventory = Inventory::getByPhMid( $pu['pu_ph_id'], $pu['pu_m_id'] ) ){
                    if(($inventory->i_qty + $pu['pu_qty'])){
                        $inventory->i_price = ( ($inventory->i_price * $inventory->i_qty ) + ($pu['pu_price'] * $pu['pu_qty']) ) / ($inventory->i_qty + $pu['pu_qty']);
                    } else {
                        $inventory->i_price = '0.00';
                    }
                    $inventory->i_qty = $inventory->i_qty + $pu['pu_qty'];
                    $inventory->update();
                } else {
                    $insert[] = [
                        'i_ph_id' => $pu['pu_ph_id'],
                        'i_m_id' => $pu['pu_m_id'],
                        'i_price' => $pu['pu_price'],
                        'i_qty' => $pu['pu_qty'],
                    ];
                }
            }
            if( $insert ){
                $i_id = DB::instance()->insertMultiple( 't_inventory', $insert );
            }
            if( $inv_ids ){
                foreach ( $inv_ids as $inv_id => $amount ) {
                    $reason = \sprintf( 'Payment for Invoice %s', $inv_id );
                    Functions::ledgerCreate( $reason, -$amount, 'purchase' );
                    DB::instance()->update( 't_purchases', ['pu_status' => 'sync'], [ 'pu_inv_id' => $inv_id ] );
                }
            }
            foreach ( $ph_m_ids as $ph_id => $m_ids ) {
                DB::instance()->delete( 't_later_medicines', [ 'o_ph_id' => $ph_id, 'm_id' => $m_ids ] );
                DB::instance()->delete( 't_purchase_request', [ 'ph_id' => $ph_id, 'm_id' => $m_ids ] );

                $in  = str_repeat('?,', count($m_ids) - 1) . '?';
                $query2 = DB::db()->prepare( "SELECT * FROM t_medicines WHERE m_id IN ($in) AND m_rob = ?" );
                $query2->execute( [ ...$m_ids, 0 ] );
                $query2->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Medicine');
                while( $medicine = $query2->fetch() ) {
                    $medicine->updateCache();
                    $medicine->m_rob = 1;
                    $medicine->m_r_count = 0;
                    $medicine->update();
                }
            }
            DB::db()->commit();
        } catch(\PDOException $e) {
            DB::db()->rollBack();
            \error_log( $e->getMessage() );
            Response::instance()->sendMessage( 'Something wrong, Please try again.' );
        }
        
        Functions::checkOrdersForInventory( $ph_m_ids );
        Functions::checkOrdersForPacking();

        Response::instance()->sendMessage( 'Successfully sync.', 'success' );
    }

    public function purchaseUpdate( $pu_id ) {
        if( ! $pu_id ){
            Response::instance()->sendMessage( 'No purchase items Found' );
        }
        $query = DB::db()->prepare( 'SELECT * FROM t_purchases WHERE pu_id = ? LIMIT 1' );
        $query->execute( [ $pu_id ] );
        $purchase = $query->fetch();

        $pu_price = isset( $_POST['pu_price'] ) ? \round( $_POST['pu_price'], 4 ) : 0.0000;
        $pu_qty = isset( $_POST['pu_qty'] ) ? \intval( $_POST['pu_qty'] ) : 0;
        $m_expiry = isset( $_POST['m_expiry'] ) ? filter_var($_POST['m_expiry'], FILTER_SANITIZE_STRING) : '0000-00-00';
        $m_batch = ( isset( $_POST['m_batch'] ) && 'undefined' != $_POST['m_batch'] ) ? filter_var($_POST['m_batch'], FILTER_SANITIZE_STRING) : '';

        $data = [
            'm_expiry' => $m_expiry,
            'm_batch' => $m_batch
        ];
        if( $purchase && $purchase['pu_status'] != 'sync' ){
            $data['pu_price'] = $pu_price;
            $data['pu_qty'] = $pu_qty;
        }

        $updated = DB::instance()->update( 't_purchases', $data, [ 'pu_id' => $pu_id ] );
        
        $this->purchaseSingle( $pu_id );
    }

    public function purchaseDelete( $pu_id ) {
        if( ! $pu_id ){
            Response::instance()->sendMessage( 'No purchase item Found' );
        }
        $query = DB::db()->prepare( 'SELECT pu_status FROM t_purchases WHERE pu_id = ? LIMIT 1' );
        $query->execute( [ $pu_id ] );
        $data = $query->fetch();

        if( $data && 'sync' == $data['pu_status'] ){
            Response::instance()->sendMessage( 'You can not delete this purchase anymore' );
        }

        $deleted = DB::instance()->delete( 't_purchases', [ 'pu_id' => $pu_id ] );
        
        if( $deleted ){
            Response::instance()->sendData( ['id' => $pu_id ], 'success');
        } else {
            Response::instance()->sendMessage( 'No purchase item Found' );
        }
    }

    public function purchasesPendingTotal(){
        $query = DB::db()->prepare( 'SELECT pu_inv_id, COUNT(*) as totalItems, SUM(pu_price*pu_qty) as totalAmount FROM t_purchases WHERE pu_status = ? GROUP BY pu_inv_id' );
        $query->execute( [ 'pending' ] );
        $data = $query->fetchAll();
        Response::instance()->sendData( $data, 'success');
    }

    public function collections() {
        if( ! $this->user->can( 'collectionsView' ) ) {
            Response::instance()->sendMessage( 'Your account does not have collections view capabilities.');
        }

        $ids = isset( $_GET['ids'] ) ? $_GET['ids'] : '';
        $fm_id = isset( $_GET['_fm_id'] ) ? $_GET['_fm_id'] : 0;
        $to_id = isset( $_GET['_to_id'] ) ? $_GET['_to_id'] : 0;
        $status = isset( $_GET['_status'] ) ? $_GET['_status'] : '';
        $orderBy = isset( $_GET['_orderBy'] ) ? $_GET['_orderBy'] : '';
        $order = ( isset( $_GET['_order'] ) && 'DESC' == $_GET['_order'] ) ? 'DESC' : 'ASC';
        $page = isset( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = isset( $_GET['_perPage'] ) ? (int)$_GET['_perPage'] : 20;

        $db = new DB;

        $db->add( 'SELECT SQL_CALC_FOUND_ROWS *, (co_amount - co_s_amount) AS profit FROM t_collections WHERE 1=1' );
        if( $fm_id ) {
            $db->add( ' AND co_fid = ?', $fm_id );
        }
        if( $to_id ) {
            $db->add( ' AND co_tid = ?', $to_id );
        }
        if( $status ) {
            $db->add( ' AND co_status = ?', $status );
        }
        if( $ids ) {
            $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
            $in  = str_repeat('?,', count($ids) - 1) . '?';
            $db->add( " AND co_id IN ($in)", ...$ids );

            $perPage = count($ids);
        }

        if( $orderBy ) {
            $db->add( " ORDER BY {$orderBy} $order" );
        }

        $limit    = $perPage * ( $page - 1 );
        $db->add( ' LIMIT ?, ?', $limit, $perPage );
        
        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();

        while( $data = $query->fetch() ) {
            $data['id'] = $data['co_id'];
            $data['fm_name'] = User::getName( $data['co_fid'] );
            $data['to_name'] = User::getName( $data['co_tid'] );
            $data['profit'] = \round( $data['profit'], 2);

            Response::instance()->appendData( '', $data );
        }

        if ( Response::instance()->getData() ) {
            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        } else {
            Response::instance()->sendMessage( 'No collections Found' );
        }
    }

    function collectionSingle( $co_id ) {
        $query = DB::db()->prepare( 'SELECT * FROM t_collections WHERE co_id = ? LIMIT 1' );
        $query->execute( [ $co_id ] );
        if( $data = $query->fetch() ){
            $data['id'] = $data['co_id'];
            $data['fm_name'] = User::getName( $data['co_fid'] );
            $data['to_name'] = User::getName( $data['co_tid'] );
			$data['co_bag'] = Functions::maybeJsonDecode( $data['co_bag'] );
            $data['profit'] = \round( $data['co_amount'] - $data['co_s_amount'], 2);

            Response::instance()->sendData( $data, 'success' );
        } else {
            Response::instance()->sendMessage( 'No items Found' );
        }

        Response::instance()->send();
    }

    public function ledger() {
        if( ! $this->user->can( 'ledgerView' ) ) {
            Response::instance()->sendMessage( 'Your account does not have ledger view capabilities.');
        }
        $search = isset( $_GET['_search'] ) ? $_GET['_search'] : '';
        $ids = isset( $_GET['ids'] ) ? $_GET['ids'] : '';
        $u_id = isset( $_GET['_u_id'] ) ? $_GET['_u_id'] : 0;
        $created = isset( $_GET['_created'] ) ? $_GET['_created'] : '';
        $created_end = isset( $_GET['_created_end'] ) ? $_GET['_created_end'] : '';
        $type = isset( $_GET['_type'] ) ? $_GET['_type'] : '';
        $method = isset( $_GET['_method'] ) ? $_GET['_method'] : '';
        $orderBy = isset( $_GET['_orderBy'] ) ? $_GET['_orderBy'] : '';
        $order = ( isset( $_GET['_order'] ) && 'DESC' == $_GET['_order'] ) ? 'DESC' : 'ASC';
        $page = isset( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = isset( $_GET['_perPage'] ) ? (int)$_GET['_perPage'] : 20;

        $db = new DB;

        $db->add( 'SELECT SQL_CALC_FOUND_ROWS * FROM t_ledger WHERE 1=1' );
        if ( $search ) {
            $search = addcslashes( $search, '_%\\' );
            $db->add( ' AND l_reason LIKE ?', "%{$search}%" );
        }
        if( $u_id ) {
            $db->add( ' AND l_uid = ?', $u_id );
        }
        if( $created ) {
            $db->add( ' AND l_created >= ? AND l_created <= ?', $created . ' 00:00:00', ($created_end ?: $created) . ' 23:59:59' );
        }
        if( $ids ) {
            $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
            $in  = str_repeat('?,', count($ids) - 1) . '?';
            $db->add( " AND l_id IN ($in)", ...$ids );

            $perPage = count($ids);
        }
        if( $type ) {
            $db->add( ' AND l_type = ?', $type );
        }
        if( $method ) {
            $db->add( ' AND l_method = ?', $method );
        }

        if( $orderBy ) {
            $db->add( " ORDER BY {$orderBy} $order" );
        }

        $limit    = $perPage * ( $page - 1 );
        $db->add( ' LIMIT ?, ?', $limit, $perPage );
        
        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();

        while( $data = $query->fetch() ) {
            $data['id'] = $data['l_id'];
            $data['u_name'] = User::getName( $data['l_uid'] );
            $data['attachedFiles'] = Functions::getLedgerFiles( Functions::maybeJsonDecode( $data['l_files'] ) ) ;
            Response::instance()->appendData( '', $data );
        }

        if ( Response::instance()->getData() ) {
            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        } else {
            Response::instance()->sendMessage( 'No items Found' );
        }
    }

    function ledgerSingle( $l_id ) {
        $query = DB::db()->prepare( 'SELECT * FROM t_ledger WHERE l_id = ? LIMIT 1' );
        $query->execute( [ $l_id ] );
        if( $data = $query->fetch() ){
            $data['id'] = $data['l_id'];
            $data['u_name'] = User::getName( $data['l_uid'] );
            $data['attachedFiles'] = Functions::getLedgerFiles( Functions::maybeJsonDecode( $data['l_files'] ) ) ;
            Response::instance()->sendData( $data, 'success' );
        } else {
            Response::instance()->sendMessage( 'No items Found' );
        }

        Response::instance()->send();
    }

    public function ledgerCreate() {
        if( ! $this->user->can( 'ledgerCreate' ) ) {
            Response::instance()->sendMessage( 'Your account does not have ledger create capabilities.');
        }
        if(empty($_POST['l_reason']) || ! isset($_POST['l_amount']) || empty($_POST['l_type']) || empty($_POST['l_method']) ) {
            Response::instance()->sendMessage( 'All Fields Required' );
        }
        
        $l_id = Functions::ledgerCreate( $_POST['l_reason'], $_POST['l_amount'], $_POST['l_type'], $_POST['l_method'] );

        $attachedFiles = isset( $_POST['attachedFiles'] ) ? $_POST['attachedFiles'] : [];
        Functions::modifyLedgerFiles( $l_id, $attachedFiles );

        $this->ledgerSingle( $l_id );
    }

    public function ledgerUpdate( $l_id ) {
        if( ! $this->user->can( 'ledgerEdit' ) ) {
            Response::instance()->sendMessage( 'Your account does not have ledger edit capabilities.');
        }

        if( ! $l_id ){
            Response::instance()->sendMessage( 'No items Found' );
        }

        if(empty($_POST['l_reason']) || ! isset($_POST['l_amount']) || empty($_POST['l_type']) || empty($_POST['l_method']) ) {
            Response::instance()->sendMessage( 'All Fields Required' );
        }
        DB::instance()->update( 't_ledger', ['l_reason' => $_POST['l_reason'], 'l_type' => $_POST['l_type'], 'l_method' => $_POST['l_method'], 'l_amount' => \round($_POST['l_amount'], 2)], [ 'l_id' => $l_id ] );

        $attachedFiles = isset( $_POST['attachedFiles'] ) ? $_POST['attachedFiles'] : [];
        Functions::modifyLedgerFiles( $l_id, $attachedFiles );

        $this->ledgerSingle( $l_id );
    }

    public function ledgerDelete( $l_id ) {
        Response::instance()->sendMessage( 'Deleting ledger item is not permitted' );

        if( ! $l_id ){
            Response::instance()->sendMessage( 'No items Found' );
        }
        $deleted = DB::instance()->delete( 't_ledger', [ 'l_id' => $l_id ] );
        
        if( $deleted ){
            Response::instance()->sendData( ['id' => $l_id ], 'success');
        } else {
            Response::instance()->sendMessage( 'No items Found' );
        }
    }

    public function ledgerBalance(){
        $query = DB::db()->query( "SELECT SUM(l_amount) as totalBalance FROM t_ledger WHERE l_type != 'Credit'" );
        $balance = $query->fetchColumn();
        $data = [
            'totalBalance' => \round( $balance, 2 ),
        ];
        Response::instance()->sendData( $data, 'success');
    }

    public function companies() {
        $ids = isset( $_GET['ids'] ) ? $_GET['ids'] : '';
        $search = isset( $_GET['_search'] ) ? $_GET['_search'] : '';
        $orderBy = isset( $_GET['_orderBy'] ) ? $_GET['_orderBy'] : '';
        $order = ( isset( $_GET['_order'] ) && 'DESC' == $_GET['_order'] ) ? 'DESC' : 'ASC';
        $page = isset( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = isset( $_GET['_perPage'] ) ? (int)$_GET['_perPage'] : 20;

        $db = new DB;

        $db->add( 'SELECT SQL_CALC_FOUND_ROWS * FROM t_companies WHERE 1=1' );
        if( $ids ) {
            $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
            $in  = str_repeat('?,', count($ids) - 1) . '?';
            $db->add( " AND c_id IN ($in)", ...$ids );

            $perPage = count($ids);
        }
        if ( $search ) {
            $search = addcslashes( $search, '_%\\' );
            $db->add( ' AND c_name LIKE ?', "{$search}%" );
        }

        if( $orderBy ) {
            $db->add( " ORDER BY {$orderBy} $order" );
        }

        $limit    = $perPage * ( $page - 1 );
        $db->add( ' LIMIT ?, ?', $limit, $perPage );
        
        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Company');

        while( $company = $query->fetch() ) {
            $data = $company->toArray();
            $data['id'] = $company->c_id;

            Response::instance()->appendData( '', $data );
        }

        if ( Response::instance()->getData() ) {
            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        } else {
            Response::instance()->sendMessage( 'No companies Found' );
        }
    }

    function companySingle( $c_id ) {
        if( $company = Company::getCompany( $c_id ) ){
            $data = $company->toArray();
            $data['id'] = $company->c_id;

            Response::instance()->sendData( $data, 'success' );
        } else {
            Response::instance()->sendMessage( 'No items Found' );
        }

        Response::instance()->send();
    }

    public function companyCreate() {
        if( ! $this->user->can( 'companyCreate' ) ) {
            Response::instance()->sendMessage( 'Your account does not have company create capabilities.');
        }
        if(empty($_POST['c_name']) ) {
            Response::instance()->sendMessage( 'Name is Required' );
        }
        $company = new Company;
        $company->insert( $_POST );
        $this->companySingle( $company->c_id );
    }

    public function companyUpdate( $c_id ) {

        if( ! $this->user->can( 'companyEdit' ) ) {
            Response::instance()->sendMessage( 'Your account does not have company edit capabilities.');
        }
        if ( ! $c_id ) {
            Response::instance()->sendMessage( 'No Company Found' );
        }

        if( $company = Company::getCompany( $c_id ) ){
            $company->update( $_POST );
            $this->companySingle( $company->c_id );
        } else {
            Response::instance()->sendMessage( 'No Company Found' );
        }
        Response::instance()->send();
    }

    public function generics() {
        $ids = isset( $_GET['ids'] ) ? $_GET['ids'] : '';
        $search = isset( $_GET['_search'] ) ? $_GET['_search'] : '';
        $orderBy = isset( $_GET['_orderBy'] ) ? $_GET['_orderBy'] : '';
        $order = ( isset( $_GET['_order'] ) && 'DESC' == $_GET['_order'] ) ? 'DESC' : 'ASC';
        $page = isset( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = isset( $_GET['_perPage'] ) ? (int)$_GET['_perPage'] : 20;

        $db = new DB;

        $db->add( 'SELECT SQL_CALC_FOUND_ROWS * FROM t_generics_v2 WHERE 1=1' );
        if( $ids ) {
            $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
            $in  = str_repeat('?,', count($ids) - 1) . '?';
            $db->add( " AND g_id IN ($in)", ...$ids );

            $perPage = count($ids);
        }
        if ( $search ) {
            $search = addcslashes( $search, '_%\\' );
            $db->add( ' AND g_name LIKE ?', "{$search}%" );
        }

        if( $orderBy ) {
            $db->add( " ORDER BY {$orderBy} $order" );
        }

        $limit    = $perPage * ( $page - 1 );
        $db->add( ' LIMIT ?, ?', $limit, $perPage );
        
        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Generic');

        while( $generic = $query->fetch() ) {
            //$data = $generic->toArray();
            $data = [];
            $data['g_id'] = $generic->g_id;
            $data['g_name'] = $generic->g_name;
            $data['id'] = $generic->g_id;

            Response::instance()->appendData( '', $data );
        }

        if ( Response::instance()->getData() ) {
            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        } else {
            Response::instance()->sendMessage( 'No companies Found' );
        }
    }

    function genericSingle( $g_id ) {
        if( $generic = Generic::getGeneric( $g_id ) ){
            $data = $generic->toArray();
            $data['id'] = $generic->g_id;

            Response::instance()->sendData( $data, 'success' );
        } else {
            Response::instance()->sendMessage( 'No items Found' );
        }

        Response::instance()->send();
    }

    public function genericCreate() {
        if( ! $this->user->can( 'genericCreate' ) ) {
            Response::instance()->sendMessage( 'Your account does not have generic create capabilities.');
        }
        if(empty($_POST['g_name']) ) {
            Response::instance()->sendMessage( 'Name is Required' );
        }
        $generic = new Generic;
        $generic->insert( $_POST );
        $this->genericSingle( $generic->g_id );
    }

    public function genericUpdate( $g_id ) {

        if( ! $this->user->can( 'genericEdit' ) ) {
            Response::instance()->sendMessage( 'Your account does not have generic edit capabilities.');
        }
        if ( ! $g_id ) {
            Response::instance()->sendMessage( 'No generic Found' );
        }

        if( $generic = Generic::getGeneric( $g_id ) ){
            $generic->update( $_POST );
            $this->genericSingle( $generic->g_id );
        } else {
            Response::instance()->sendMessage( 'No generic Found' );
        }
        Response::instance()->send();
    }

	public function locations() {
        $ids = isset( $_GET['ids'] ) ? $_GET['ids'] : '';
        $search = isset( $_GET['_search'] ) ? $_GET['_search'] : '';
		$only_zones = isset( $_GET['_only_zones'] ) ? filter_var( $_GET['_only_zones'], FILTER_VALIDATE_BOOLEAN ) : false;
        $orderBy = isset( $_GET['_orderBy'] ) ? $_GET['_orderBy'] : '';
        $order = ( isset( $_GET['_order'] ) && 'DESC' == $_GET['_order'] ) ? 'DESC' : 'ASC';
        $page = isset( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = isset( $_GET['_perPage'] ) ? (int)$_GET['_perPage'] : 20;

        $db = new DB;

        $db->add( 'SELECT SQL_CALC_FOUND_ROWS * FROM t_locations WHERE 1=1' );
        if( $ids ) {
            $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
            $in  = str_repeat('?,', count($ids) - 1) . '?';
            $db->add( " AND l_id IN ($in)", ...$ids );

            $perPage = count($ids);
        }
        if ( $search ) {
            $search = addcslashes( $search, '_%\\' );
			if( $only_zones ){
				$db->add( ' AND l_zone LIKE ?', "{$search}%" );
			} else {
				$db->add( ' AND l_area LIKE ?', "{$search}%" );
			}
        }
		if( $only_zones ){
			$db->add( " GROUP BY l_zone" );
		}

        if( $orderBy ) {
            $db->add( " ORDER BY {$orderBy} $order" );
        }

        $limit    = $perPage * ( $page - 1 );
        $db->add( ' LIMIT ?, ?', $limit, $perPage );
        
        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();
        //$query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Generic');

        while( $data = $query->fetch() ) {
            $data['id'] = $data['l_id'];

            Response::instance()->appendData( '', $data );
        }

        if ( Response::instance()->getData() ) {
            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        } else {
            Response::instance()->sendMessage( 'No companies Found' );
        }
    }

    function locationSingle( $l_id ) {
		$data = DB::instance()->select( 't_locations', [ 'l_id' => $l_id ] );
        if( $data ){
            $data['id'] = $data['l_id'];
            Response::instance()->sendData( $data, 'success' );
        } else {
            Response::instance()->sendMessage( 'No items Found' );
        }

        Response::instance()->send();
    }

    public function laterMedicines(){
        $ids = isset( $_GET['ids'] ) ? $_GET['ids'] : '';
        $search = isset( $_GET['_search'] ) ? $_GET['_search'] : '';
        $c_id = isset( $_GET['_c_id'] ) ? $_GET['_c_id'] : 0;
        $g_id = isset( $_GET['_g_id'] ) ? $_GET['_g_id'] : 0;
        $ph_id = isset( $_GET['_ph_id'] ) ? $_GET['_ph_id'] : 0;
        $u_id = isset( $_GET['_u_id'] ) ? $_GET['_u_id'] : 0;
        $orderBy = isset( $_GET['_orderBy'] ) ? $_GET['_orderBy'] : '';
        $order = ( isset( $_GET['_order'] ) && 'DESC' == $_GET['_order'] ) ? 'DESC' : 'ASC';
        $page = isset( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = isset( $_GET['_perPage'] ) ? (int)$_GET['_perPage'] : 20;
        $cat_id = isset( $_GET['_cat_id'] ) ? (int)$_GET['_cat_id'] : 0;
        $not_assigned = isset( $_GET['_not_assigned'] ) ? filter_var( $_GET['_not_assigned'], FILTER_VALIDATE_BOOLEAN ) : false;

        if( ! $ph_id && 'pharmacy' == $this->user->u_role ){
            $ph_id = Auth::id();
        }

        $db = new DB;
        $db->add( 'SELECT SQL_CALC_FOUND_ROWS tlm.*, tm.m_name, tm.m_unit, tm.m_form, tm.m_strength, tm.m_g_id, tm.m_c_id, ti.i_qty, ti.wkly_req, tpr.u_id FROM t_later_medicines AS tlm INNER JOIN t_medicines AS tm ON tlm.m_id = tm.m_id LEFT JOIN t_inventory AS ti ON tlm.o_ph_id = ti.i_ph_id AND tlm.m_id = ti.i_m_id LEFT JOIN t_purchase_request AS tpr ON tlm.o_ph_id = tpr.ph_id AND tlm.m_id = tpr.m_id WHERE 1 = 1' );

        if ( $search ) {
            $search = addcslashes( $search, '_%\\' );
            $db->add( ' AND tm.m_name LIKE ?', "{$search}%" );
        }
        if( $g_id ) {
            $db->add( ' AND tm.m_g_id = ?', $g_id );
        }
        if( $c_id ) {
            $db->add( ' AND tm.m_c_id = ?', $c_id );
        }
        if ( $cat_id ) {
            $db->add( ' AND tm.m_cat_id = ?', $cat_id );
        }
        if( $ph_id ) {
            $db->add( ' AND tlm.o_ph_id = ?', $ph_id );
        }
        if( $u_id ) {
            $db->add( ' AND tpr.u_id = ?', $u_id );
        }
        if( $not_assigned ) {
            $db->add( ' AND tpr.u_id IS NULL' );
        }
        if( $ids ) {
            $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
            $in  = str_repeat('?,', count($ids) - 1) . '?';
            $db->add( " AND tlm.lm_id IN ($in)", ...$ids );

            $perPage = count($ids);
        }

        if ( $orderBy && \in_array( $orderBy, ['m_name', 'm_form', 'm_g_id', 'm_c_id', 'm_strength', 'm_unit', 'm_price', 'm_d_price' ] ) ) {
            $db->add( " ORDER BY tm.{$orderBy} $order" );
        } elseif( $orderBy && \in_array($orderBy, ['i_qty', 'wkly_req'] ) ) {
            $db->add( " ORDER BY ti.{$orderBy} $order" );
        } elseif( $orderBy && \in_array( $orderBy, ['o_created', 'total_qty'] ) ) {
            $db->add( " ORDER BY tlm.{$orderBy} $order" );
        } elseif( $orderBy && \in_array($orderBy, ['u_id'] ) ) {
            //$db->add( " ORDER BY $orderBy $order" );
        }

        $limit    = $perPage * ( $page - 1 );
        $db->add( ' LIMIT ?, ?', $limit, $perPage );
        
        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();
        $datas = $query->fetchAll();

        foreach( $datas as $data ) {
            $data['purchaseAssigned'] = User::getName( $data['u_id'] );
            $data['id'] = $data['lm_id'];
            $data['m_generic'] = Generic::getName( $data['m_g_id'] );
            $data['m_company'] = Company::getName( $data['m_c_id'] );
            Response::instance()->appendData( '', $data );
        }

        if ( Response::instance()->getData() ) {
            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        } else {
            Response::instance()->sendMessage( 'No Medicines Found' );
        }
    }

    public function savePurchaseRequest(){
        $u_id = $_POST['u_id'] ?? '';
        if( 'pharmacy' !== $this->user->u_role ) {
            Response::instance()->sendMessage( 'Only pharmacy can create purchase request' );
        }
        if( !$u_id || ! User::getUser( $u_id ) ) {
            Response::instance()->sendMessage( 'No users Found' );
        }
        $purchaseRequest = $_POST['purchaseRequest'] ?? [];
        if ( $purchaseRequest && is_array( $purchaseRequest ) ){
            $data = [];
            foreach( $purchaseRequest as $l_m_order ){
                if( empty( $l_m_order['m_id'] ) || empty( $l_m_order['qty_text'] ) ){
                    continue;
                }
                $data[] = [
                    'ph_id' => $this->user->u_id,
                    'm_id' => $l_m_order['m_id'],
                    'u_id' => $u_id,
                    'qty_text' => $l_m_order['qty_text']
                ];
            }
            if ( $data ){
                DB::instance()->insertMultiple( 't_purchase_request', $data, true );
            } else {
                Response::instance()->sendMessage( 'Nothing to save' );
            }

            Response::instance()->sendMessage( 'Successfully added purchase request', 'success' );
        }
        Response::instance()->sendMessage( 'No medicines to purchase' );
    }

    function allLocations(){
        $locations = Functions::getLocations();
        Response::instance()->sendData( $locations, 'success' );
    }
	
    private function sendOrderSMS( $o_id ){
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }

        $message = $_POST['message'] ?? '';
        $to = $_POST['to'] ?? '';
        $mobile  = '';
        switch ($to) {
            case 'shipping':
                $s_address = $order->getMeta('s_address');
                if( is_array( $s_address ) && ! empty( $s_address['mobile'] ) ){
                    $mobile = Functions::checkMobile( $s_address['mobile'] );
                }
                break;
            case 'billing':
                if( $mobile = $order->u_mobile ){
                    $mobile = Functions::checkMobile( $mobile );
                }
                break;
            default:
                break;
        }

        if( ! $mobile  ){
            Response::instance()->sendMessage( 'No numbers found.' );
        }
        $deliveryman = User::getUser( $order->o_de_id );


        Functions::sendSMS( $mobile, $message );
        $order->appendMeta( 'o_i_note', sprintf("%s : SMS Sent to %s", date( "d-M h:ia" ) ,$to ));
		//$order->addHistory( 'SMS', sprintf( 'SMS Sent to %s', $to ) );

        Response::instance()->sendMessage( 'SMS Sent', 'success' );
    }

    private function returnItems( $o_id ){
		if( ! $this->user->can( 'orderEdit' ) ) {
            Response::instance()->sendMessage( 'Your account does not have order edit capabilities.' );
        }
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        $medicineQty = $_POST['medicineQty'] ?? [];
        if( !$medicineQty || !is_array( $medicineQty ) ){
            Response::instance()->sendMessage( 'Invalid medicines' );
        }
		$status = '';
        $note = [];
        foreach ( $medicineQty as $m ) {
            if( !$m || empty( $m['m_id'] ) ){
                continue;
            }
            if( ! ( $medicine = Medicine::getMedicine( $m['m_id'] ) ) ) {
                continue;
            }
			if( ! empty( $m['missing_qty'] ) ){
				$note[] = sprintf( 'দিবেন: %s %s - %s', $medicine->m_name, $medicine->m_strength, Functions::qtyTextClass( $m['missing_qty'], $medicine ) );
				if( 'packing' !== $status ){
					$status = 'packing';
				}
			}
        }
		foreach ( $medicineQty as $m ) {
            if( !$m || empty( $m['m_id'] ) ){
                continue;
            }
            if( ! ( $medicine = Medicine::getMedicine( $m['m_id'] ) ) ) {
                continue;
            }
			if( ! empty( $m['return_qty'] ) ){
				$note[] = sprintf( 'আনবেন: %s %s - %s', $medicine->m_name, $medicine->m_strength, Functions::qtyTextClass( $m['return_qty'], $medicine ) );
			}
			if( ! empty( $m['replace_id'] ) && ! empty( $m['replace_qty'] ) ){
                if( $replace_medicine = Medicine::getMedicine( $m['replace_id'] ) ) {
                    $note[] = sprintf( 'আনবেন: %s %s - %s', $replace_medicine->m_name, $replace_medicine->m_strength, Functions::qtyTextClass( $m['replace_qty'], $replace_medicine ) );
                }
            }
        }
        if( ! $status && $note ){
            $status = 'delivering';
        }
        $note = array_filter( array_merge( [$order->getMeta( 'o_i_note' )], $note ) );

        $order->setMeta( 'o_i_note', implode( "\n", $note ) );
        if( $status && ( !$order->o_is_status || $order->o_is_status === 'solved' ) ){
            $order->update( [ 'o_is_status' => $status ] );
        }
		if( $status ){
            $order->appendMeta( 'o_admin_note', sprintf( '%s: Issue created by %s', \date( 'Y-m-d H:i:s' ), $this->user->u_name ) );
			Response::instance()->sendMessage( 'Successfully added returned note', 'success' );
		}

        Response::instance()->sendMessage( 'Nothing to save' );
    }

    function orderPostAction( $action, $id ){
        switch ($action) {
            case 'reOrder':
                $this->reOrder( $id );
                break;
            case 'sendOrderSMS':
                $this->sendOrderSMS( $id );
                break;
            case 'returnItems':
                $this->returnItems( $id );
                break;
			case 'refundItems':
				$this->refundItems( $id );
				break;
            case 'shippingAddress':
                $this->shippingAddress( $id );
                break;
			case 'assignToDeliveryMan':
				$this->assignToDeliveryMan( $id );
				break;
            default:
                Response::instance()->sendMessage( 'No actions provided.' );
                break;
        }
    }

    private function refundItems( $o_id ){
		if( ! in_array( $this->user->u_role, [ 'administrator', 'pharmacy' ] ) ){
            Response::instance()->sendMessage( 'You cannot refund items. Contact pharmacy.' );
        }
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        if ( 'delivered' !== $order->o_status ){
            Response::instance()->sendMessage( 'Order not in delivered stage.' );
        }
		$medicineQty = $_POST['medicineQty'] ?? [];
        if( !$medicineQty || !is_array( $medicineQty ) ){
            Response::instance()->sendMessage( 'Invalid medicines' );
        }
		
        $total = isset( $_POST['total'] ) ? round( $_POST['total'], 2 ) : 0;
        $m_d_price_total = 0;
        $midArray = [];
        $oldReturnQty = [];
		foreach ( $medicineQty as $mqty ){
			if ( $mqty["m_id"] ) {
				$midArray[ $mqty["m_id"] ] = [ 'refund_qty' => $mqty['refund_qty'] ?? 0, 'damage_qty' => $mqty['damage_qty'] ?? 0 ];
			}
		}
		$query = DB::instance()->select( 't_o_medicines', [ 'o_id' => $o_id, 'refund_qty' => 0, 'damage_qty' => 0, 'm_id' => array_keys($midArray) ]);
		while( $orderMedicines = $query->fetch() ){
			$m_d_price_total += $orderMedicines['m_d_price'] * $midArray[ $orderMedicines['m_id'] ]['refund_qty'];
			$oldReturnQty[ $orderMedicines['m_id'] ] = [ 'refund_qty' => $orderMedicines['refund_qty'], 'damage_qty' => $orderMedicines['damage_qty'] ];
			
			if ( $orderMedicines['m_qty'] < ( $orderMedicines['refund_qty'] + $orderMedicines['damage_qty'] + $midArray[ $orderMedicines['m_id'] ]['refund_qty'] + $midArray[ $orderMedicines['m_id'] ]['damage_qty'] ) ){
				Response::instance()->sendMessage( 'Medicine return more than Order.' );
			}
		}
        if ( round( $m_d_price_total ) != round( $total ) ){
            Response::instance()->sendMessage( 'Medicines Total not match.' );
        }
		if( $total ){
			if( $user = User::getUser( $order->u_id ) ) {
				$user->cashUpdate( $total );
				$order->appendMeta( 'o_admin_note', sprintf( '%s: %s TK refunded by %s', \date( 'Y-m-d H:i:s' ), $total, $this->user->u_name ) );
			}
	
			$prev_refund = $order->getMeta( 'refund' );
			if( ! is_numeric( $prev_refund ) ){
				$prev_refund = 0;
			}
			$order->setMeta( 'refund', $prev_refund + $total );
			$order->addHistory( 'Refund', $prev_refund, $prev_refund + $total );
		}

        foreach ( $medicineQty as $mqty ){
			if ( empty( $mqty["m_id"] ) ) {
				continue;
			}
			$newRefund = $mqty['refund_qty'] ?? 0;
			$newDamage = $mqty['damage_qty'] ?? 0;

            $refund_qty = isset( $oldReturnQty[ $mqty['m_id'] ] ) ? $oldReturnQty[ $mqty['m_id'] ]['refund_qty'] + $newRefund : $newRefund;
            $damage_qty = isset( $oldReturnQty[ $mqty['m_id'] ] ) ? $oldReturnQty[ $mqty['m_id'] ]['damage_qty'] + $newDamage : $newDamage;
            DB::instance()->update( 't_o_medicines', [ 'refund_qty' => $refund_qty, 'damage_qty' => $damage_qty ], [ 'o_id' => $order->o_id, 'm_id' => $mqty['m_id'] ] );
			if( $newRefund ){
				Inventory::qtyUpdateByPhMid( $order->o_ph_id, $mqty['m_id'], $newRefund );
			}
        }
        Response::instance()->sendMessage( 'Successfully refunded.', 'success' );
    }

    private function shippingAddress( $o_id ){
		if( ! $this->user->can( 'orderEdit' ) ) {
            Response::instance()->sendMessage( 'Your account does not have order edit capabilities.');
        }
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }

        $s_address = ( isset( $_POST['s_address'] ) && is_array($_POST['s_address']) ) ? $_POST['s_address']: [];

		if ( ! $s_address || ! ( $location = Functions::isLocationValid( $s_address['division'] ?? '', $s_address['district'] ?? '', $s_address['area'] ?? '' ) ) ){
			Response::instance()->sendMessage( 'invalid location.');
		}
        if ( ! $location->l_status ){
			Response::instance()->sendMessage( 'Location is not active');
		}
		
		$s_address['location'] = sprintf( '%s, %s, %s, %s', $s_address['homeAddress']??'', $s_address['area'], $s_address['district'], $s_address['division'] );
		$data = [
			'o_gps_address' => $s_address['location'],
			'o_de_id' => $location->l_de_id,
            'o_ph_id' => $location->l_ph_id,
			'o_l_id' => $location->l_id,
		];
		$change = "No Changes";
		if( $order->o_gps_address != $s_address['location'] ){
			$order->addHistory( 'Address Check', $order->o_gps_address, $s_address['location'] );
			$change = sprintf( '%s TO %s', $order->o_gps_address, $s_address['location'] );
		} else {
			$order->addHistory( 'Address Check', 'No Changes' );
		}
        if( 'Dhaka City' != $location->l_district && 'cod' == $order->o_payment_method ){
            $data['o_payment_method'] = 'online';
        }
		
		$order->update( $data );
		$order->setMeta( 'addressChecked', 1 );
		$order->setMeta( 's_address', $s_address );
		$order->appendMeta( 'o_admin_note', sprintf( '%s: Address checked by %s (%s)', \date( 'Y-m-d H:i:s' ), $this->user->u_name, $change ) );

		Response::instance()->sendMessage( 'Successfully checked address.', 'success' );
    }

    function userPostAction( $action, $id ){
        switch ($action) {
            case 'sendUserSMS':
                $this->sendUserSMS( $id );
                break;
            default:
                Response::instance()->sendMessage( 'No actions provided.' );
                break;
        }
    }

    private function sendUserSMS( $u_id ){
        if( ! ( $user = User::getUser( $u_id ) ) ){
            Response::instance()->sendMessage( 'No user found.' );
        }

        $message = $_POST['message'] ?? '';
        if ( $message ) {
            Functions::sendSMS( $user->u_mobile, $message );
            Response::instance()->sendMessage( 'SMS Sent', 'success' );
        }
        Response::instance()->sendMessage( 'No Message to sent' );
    }

	function zones(){
        if( 'pharmacy' == $this->user->u_role ) {
            $ph_id = $this->user->u_id;
        } else {
            Response::instance()->sendMessage( 'You cannot access zones' );
        }
        $zones = Functions::getPharmacyZones( $ph_id );
        Response::instance()->sendData( ['zones' => $zones], 'success' );
    }

    private function assignToDeliveryMan( $o_de_id ){
		$l_zone = isset( $_POST['zone'] ) ? $_POST['zone'] : '';
		$bag = isset( $_POST['bag'] ) ? $_POST['bag'] : '';
		if ( !$o_de_id || !$l_zone || !$bag ){
			Response::instance()->sendMessage( 'invalid information.');
		}

		$deliveryman = User::getUser( $o_de_id );
		if ( 'delivery' !== $deliveryman->u_role ){
			Response::instance()->sendMessage( 'invalid delivery man.');
		}

		$db = new DB();
		$db->add( 'SELECT tr.* FROM t_orders tr' );
		$db->add( ' INNER JOIN t_locations tl ON tr.o_l_id = tl.l_id INNER JOIN t_order_meta tom ON tr.o_id = tom.o_id AND tom.meta_key = ? WHERE tl.l_zone = ?', 'bag', $l_zone );
		$db->add( ' AND ( tr.o_status = ? OR tr.o_is_status = ? )', 'confirmed', 'packed' );
		$db->add( ' AND tr.o_i_status IN ( ?,? )', 'confirmed', 'paid' );
		$db->add( ' AND tom.meta_value + 0 = ?',  $bag );
		$query = $db->execute();
		$query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Order');
		$o_ids = [];
		while( $order = $query->fetch() ){
			$data = [
				'o_de_id' => $o_de_id,
				'o_status' => 'delivering',
			];
			if( $order->update( $data ) ){
				$o_ids[] = $order->o_id;
			}
		}
		Response::instance()->addData( 'o_ids', $o_ids );
		Response::instance()->sendMessage( 'Successfully delivery man assigned.', 'success');
	}

	public function bags() {
        $ids = isset( $_GET['ids'] ) ? $_GET['ids'] : '';
		$l_id = isset( $_GET['_l_id'] ) ? $_GET['_l_id'] : '';
        $zone = isset( $_GET['_zone'] ) ? $_GET['_zone'] : '';
        $ph_id = isset( $_GET['_ph_id'] ) ? $_GET['_ph_id'] : 0;
        $not_assigned = isset( $_GET['_not_assigned'] ) ? filter_var( $_GET['_not_assigned'], FILTER_VALIDATE_BOOLEAN ) : false;
		$hide_empty = isset( $_GET['_hide_empty'] ) ? filter_var( $_GET['_hide_empty'], FILTER_VALIDATE_BOOLEAN ) : false;
        $orderBy = isset( $_GET['_orderBy'] ) ? $_GET['_orderBy'] : '';
        $order = ( isset( $_GET['_order'] ) && 'DESC' == $_GET['_order'] ) ? 'DESC' : 'ASC';
        $page = isset( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = isset( $_GET['_perPage'] ) ? (int)$_GET['_perPage'] : 25;

        $db = new DB;

        $db->add( 'SELECT SQL_CALC_FOUND_ROWS * FROM t_bags WHERE 1=1' );
        if( $zone ) {
            $db->add( ' AND b_zone = ?', $zone );
        } elseif( $l_id && ( $zone = Location::getValueByLocationId( $l_id, 'zone' ) ) ){
			$db->add( ' AND b_zone = ?', $zone );
		}
        if( $ph_id || 'pharmacy' == $this->user->u_role ) {
            $db->add( ' AND b_ph_id = ?', $ph_id ?: $this->user->u_id );
        }
        if( $ph_id ) {
            $db->add( ' AND b_ph_id = ?', $ph_id );
        }
		if( $not_assigned ){
			$db->add( ' AND b_de_id = ?', 0 );
		}
		if( $hide_empty ){
			$db->add( ' AND o_count > ?', 0 );
		}
        if( $ids ) {
            $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
            $in  = str_repeat('?,', count($ids) - 1) . '?';
            $db->add( " AND b_id IN ($in)", ...$ids );

            $perPage = count($ids);
        }

        if( $orderBy ) {
            $db->add( " ORDER BY $orderBy $order" );
        }

        $limit    = $perPage * ( $page - 1 );
        $db->add( ' LIMIT ?, ?', $limit, $perPage );
        
        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();
		$query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Bag');

        while( $bag = $query->fetch() ) {
			$data = $bag->toArray();
            $data['id'] = $data['b_id'];
            $data['assign_name'] = User::getName( $data['b_de_id'] );
			$data['invoiceUrl'] = \sprintf( SITE_URL . '/v1/invoice/bag/%d/%s/', $data['b_id'], Functions::jwtEncode( ['b_id' => $data['b_id'], 'exp' => time() + 60 * 60 ] ) );

            Response::instance()->appendData( '', $data );
        }

        if ( ! Response::instance()->getData() ) {
            Response::instance()->sendMessage( 'No bags Found' );
        } else {
            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        }
    }

    function bagSingle( $b_id ) {
        if( $bag = Bag::getBag( $b_id ) ){
			$data = $bag->toArray();
            $data['id'] = $data['b_id'];

            Response::instance()->setStatus( 'success' );
            Response::instance()->setData( $data );
        } else {
            Response::instance()->sendMessage( 'No order medicine Found' );
        }

        Response::instance()->send();
    }

	public function bagCreate() {
		if( 'administrator' !== $this->user->u_role ) {
			Response::instance()->sendMessage( 'You cannot add new bag' );
		}

		$b_ph_id = isset( $_POST['b_ph_id'] ) ? (int)$_POST['b_ph_id'] : 0;
		$b_zone = isset( $_POST['b_zone'] ) ? $_POST['b_zone'] : '';

		if( !$b_ph_id || !$b_zone ) {
            Response::instance()->sendMessage( 'All Fields Required' );
        }
		$zones = Functions::getPharmacyZones( $b_ph_id );
		if( ! in_array( $b_zone, $zones ) ){
			Response::instance()->sendMessage( 'Zone not exists.' );
		}
		$b_no = (int)DB::instance()->select( 't_bags', [ 'b_ph_id' => $b_ph_id, 'b_zone' => $b_zone ], 'MAX(b_no)' )->fetchColumn();

		$data = $_POST;
		$data['b_no'] = $b_no + 1;
		$bag = new Bag;
		$b_id = $bag->insert( $data );

        if( ! $b_id ){
			Response::instance()->sendMessage( 'Something wrong, Please try again.' );
		}
        
        $this->bagSingle( $b_id );
    }

    public function bagUpdate( $b_id ) {
		if( ! in_array( $this->user->u_role, [ 'administrator', 'pharmacy' ] ) ){
            Response::instance()->sendMessage( 'You cannot assign deliveryman' );
        }
		$bag = Bag::getBag( $b_id );
		if( !$bag ){
			Response::instance()->sendMessage( 'No bag found.' );
		}

		$b_de_id = isset( $_POST['b_de_id'] ) ? (int)$_POST['b_de_id'] : 0;
		$move_ids = $_POST['move_ids'] ?? [];
		$move_zone = $_POST['move_zone'] ?? '';
		$move_bag = $_POST['move_bag'] ?? 0;
		$is_move_checked = isset( $_POST['is_move_checked'] ) ? filter_var( $_POST['is_move_checked'], FILTER_VALIDATE_BOOLEAN ) : false;
		if( $is_move_checked ){
			$move_ids = \array_filter( \array_map( 'intval', \array_map( 'trim', $move_ids ) ) );
			if( !$move_ids || !is_array( $move_ids ) ){
				Response::instance()->sendMessage( 'No ids selected.' );
			}
			if( !$move_zone || !$move_bag ){
				Response::instance()->sendMessage( 'No zone/bag selected.' );
			}
			$zones = Functions::getPharmacyZones( $bag->b_ph_id );
			if( ! in_array( $move_zone, $zones ) ){
				Response::instance()->sendMessage( 'Invalid zone.' );
			}
			$query = DB::instance()->select( 't_bags', [ 'b_ph_id' => $bag->b_ph_id, 'b_zone' => $move_zone, 'b_no' => $move_bag ] );
			$query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Bag');
			$newBag = $query->fetch();
			if( !$newBag ){
				Response::instance()->sendMessage( 'No destination bag found.' );
			}
			if( $newBag->b_id === $bag->b_id ){
				Response::instance()->sendMessage( 'You cannot move to same bag.' );
			}
			$all_o_ids = array_values( array_unique( array_merge( $newBag->o_ids, $move_ids ) ) );
			$newBag->update( [ 'o_ids' => $all_o_ids, 'o_count' => count( $all_o_ids ) ] );

			$diff_o_ids = array_values( array_diff( $bag->o_ids, $move_ids ) );
			if( $diff_o_ids ){
				$bag->update( [ 'o_ids' => $diff_o_ids, 'o_count' => count( $diff_o_ids ) ] );
			} else {
				$bag->release();
			}
			CacheUpdate::instance()->add_to_queue( $move_ids, 'order_meta');
			CacheUpdate::instance()->add_to_queue( $move_ids, 'order');
			CacheUpdate::instance()->update_cache( [], 'order_meta' );
			CacheUpdate::instance()->update_cache( [], 'order' );

			DB::instance()->update( 't_order_meta', [ 'meta_value' => $newBag->b_id ], [ 'o_id' => $move_ids, 'meta_key' => 'bag' ] );
			foreach ( $move_ids as $o_id ) {
				if( $order = Order::getOrder( $o_id ) ){
					Cache::instance()->delete( $o_id, 'order_meta' );

					$data = [];
					if( $newBag->b_de_id ){
						$data['o_de_id'] = $newBag->b_de_id;
					}
					if( $newBag->b_de_id && 'confirmed' == $order->o_status  ){
						$data['o_status'] = 'delivering';
					}
					if( $newBag->b_de_id && 'packed' == $order->o_is_status  ){
						$data['o_is_status'] = 'delivering';
					}
					$order->update( $data );
				}
			}
			$this->bagSingle( $b_id );
		}

		if( ! $b_de_id ){
			Response::instance()->sendMessage( 'No deliveryman selected.' );
		}
		
		if( $bag->b_de_id ){
			Response::instance()->sendMessage( 'Deliveryman is already assigned to this bag' );
		}
		$o_ids = $bag->o_ids;
		if( !$bag->o_count || !$o_ids ){
			Response::instance()->sendMessage( 'No orders in this bag' );
		}
		if( $de_bag = Bag::deliveryBag( $bag->b_ph_id, $b_de_id ) ){
			Response::instance()->sendMessage( 'This deliveryman is already assigned' );
		}
		$in  = str_repeat('?,', count($o_ids) - 1) . '?';
		$order_check = DB::db()->prepare( "SELECT COUNT(*) FROM t_orders WHERE ( o_i_status = ? OR o_is_status = ? ) AND o_id IN ($in)" );
        $order_check->execute( [ 'checking', 'checking', ...$o_ids ] );
        if( $order_check->fetchColumn() ){
            Response::instance()->sendMessage( 'Some orders are in checking status, check them first.' );
        }

		if( $bag->update( [ 'b_de_id' => $b_de_id ] ) ){
			CacheUpdate::instance()->add_to_queue( $o_ids, 'order');
			CacheUpdate::instance()->add_to_queue( $o_ids, 'order_meta');
			CacheUpdate::instance()->update_cache( [], 'order' );
			CacheUpdate::instance()->update_cache( [], 'order_meta' );

			foreach ( $o_ids as $o_id ) {
				if( $order = Order::getOrder( $o_id ) ){
					$data = [
						'o_de_id' => $b_de_id,
					];
					if( 'confirmed' == $order->o_status  ){
						$data['o_status'] = 'delivering';
					}
					if( 'packed' == $order->o_is_status  ){
						$data['o_is_status'] = 'delivering';
					}
					$order->update( $data );
				}
			}
			$this->bagSingle( $b_id );
		}
		Response::instance()->sendMessage( 'Something wrong, Please try again.' );
    }

    public function bagDelete( $b_id ) {
        Response::instance()->sendMessage( 'Deleting not alowed.');
    }

    function orderGetType( $type, $id ){
        switch ($type) {
            case 'history':
                $this->history( $id );
                break;
            default:
                Response::instance()->sendMessage( 'No types provided.' );
                break;
        }
    }

    private function history( $o_id ){
        $orderBy = isset( $_GET['_orderBy'] ) ? $_GET['_orderBy'] : 'h_id';
        $order = ( isset( $_GET['_order'] ) && 'DESC' == $_GET['_order'] ) ? 'DESC' : 'ASC';
        $page = isset( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = isset( $_GET['_perPage'] ) ? (int)$_GET['_perPage'] : 20;

        $limit    = $perPage * ( $page - 1 );

        $db = new DB;
        $db->add( 'SELECT SQL_CALC_FOUND_ROWS * FROM t_histories WHERE h_obj = ? AND obj_id = ?', 'order', $o_id );

        $db->add( " ORDER BY $orderBy $order" );
        $db->add( ' LIMIT ?, ?', $limit, $perPage );

        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();
        //$query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\History');

        while( $data = $query->fetch() ){
            $data['id'] = $data['h_id'];
            $data['u_name'] = $data['u_id'] ? User::getName( $data['u_id'] ) : 'System';

            Response::instance()->appendData( '', $data );
        }

        if ( ! Response::instance()->getData() ) {
            Response::instance()->sendMessage( 'No history Found' );
        } else {
            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        }
    }

    public function requestStock(){
        $m_id = isset( $_POST['m_id'] ) ? $_POST['m_id'] : 0;
        $u_id = isset( $_POST['u_id'] ) ? $_POST['u_id'] : 0;
        $page = isset( $_GET['_page'] ) ? (int)$_GET['_page'] : 1;
        $perPage = isset( $_GET['_perPage'] ) ? (int)$_GET['_perPage'] : 25;

        $db = new DB;
        $db->add( 'SELECT SQL_CALC_FOUND_ROWS trs.*, tu.u_name, tu.u_mobile, tm.m_name, tm.m_strength, tm.m_form FROM t_request_stock AS trs INNER JOIN t_users AS tu ON trs.r_u_id = tu.u_id INNER JOIN t_medicines AS tm ON trs.r_m_id = tm.m_id WHERE 1=1' );
        if ( $m_id ){
            $db->add( ' AND trs.r_m_id = ?', $m_id );
        }
        if ( $u_id ){
            $db->add( ' AND trs.r_u_id = ?', $u_id );
        }

        $limit    = $perPage * ( $page - 1 );
        $db->add( ' LIMIT ?, ?', $limit, $perPage );

        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();

        while( $requestStock = $query->fetch() ) {
            Response::instance()->appendData( '', $requestStock );
        }

        if ( ! Response::instance()->getData() ) {
            Response::instance()->sendMessage( 'No Request Stock Found' );
        } else {
            Response::instance()->setResponse( 'total', $total );
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        }
    }

    public function optionsMultipleGet(){
        $data = [];
        $stringKeys = [ 'top_notice', 'yt_video_key', 'yt_video_title', 'prescription_percent', 'call_percent', 'healthcare_percent', 'call_time' ];
        $fileKeys = [ 'attachedFilesApp', 'attachedFilesWeb', 'attachedFilesHomepageBanner', 'attachedFilesUnderProductBanner' ]; 
        foreach ( $stringKeys as $key ) {
            $data[ $key ] = (string)Option::get( $key );
        }
        foreach ( $fileKeys as $key ) {
            $images = Option::get( $key );
            if( $images && is_array( $images ) ){
                foreach( $images as &$image ){
                    $image['src'] = Functions::getS3Url( $image['s3key']??'' );
                }
                unset( $image );
            } else {
                $images = [];
            }
            $data[ $key ] = $images;
        }
        $categories = Functions::getCategories();
        foreach ( $categories as $c_id => $c_name ) {
            $return = [];
            if( ( $cat_m_ids = Option::get( "categories_sidescroll-{$c_id}" ) ) && is_array( $cat_m_ids ) ){
                foreach ( $cat_m_ids as $m_id ) {
                    $return[] = [
                        'm_id' => $m_id,
                    ];
                }
            }
            $data[ "categories_sidescroll-{$c_id}" ] = $return;
        }

        Response::instance()->sendData( $data, 'success' );
    }

    public function optionsMultipleSet(){
        if( 'administrator' !== $this->user->u_role ) {
			Response::instance()->sendMessage( 'You cannot set options' );
		}
        $post = is_array( $_POST ) ? $_POST : [];
        foreach ( $post as $key => $value ) {
            switch ($key) {
                case 'top_notice':
                case 'yt_video_key':
                case 'yt_video_title':
                case 'prescription_percent':
                case 'call_percent':
                case 'healthcare_percent':
                case 'call_time':
                    Option::set( $key, $value );
                break;
                case 'attachedFilesApp':
                case 'attachedFilesWeb':
                case 'attachedFilesHomepageBanner':
                case 'attachedFilesUnderProductBanner':
                    Functions::miscOptionModifyFiles( $key, $value );
                break;
                case strpos( $key, 'categories_sidescroll-' ) === 0 :
                    $return = [];
                    if( $value && is_array( $value ) ){
                        foreach ( $value as $array ) {
                            $return[] = intval( $array['m_id'] ?? 0 );
                        }
                    }
                    Option::set( $key, array_unique( array_filter( $return ) ) );
                break;
                default:
                    //Response::instance()->sendMessage( 'No actions provided.' );
                break;
            }
        }

        Response::instance()->sendMessage( 'Successfully Save.', 'success');
    }
}