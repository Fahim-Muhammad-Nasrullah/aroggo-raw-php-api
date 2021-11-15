<?php

namespace OA;
use OA\Factory\Log;


class Route {
	/**
	 * List of roles and capabilities.
	 *
	 * @since 2.0.0
	 * @access public
	 * @var array
	 */
	public $routes;
	
	public $route_collector;
	public $route_dispatcher;

	
	private static $instance;

	/**
	 * Constructor
	 *
	 * @since 2.0.0
	 */
	public function __construct() {		
		try{
			
			$this->route_dispatcher = \FastRoute\cachedDispatcher(function(\FastRoute\RouteCollector $r) {
				$this->route_collector = $r;
				$this->addRoutes( $this->route_collector );
			}, [
				'cacheFile' => STATIC_DIR . '/froute.cache', /* required */
				'cacheDisabled' => !MAIN,     /* optional, enabled by default */
			]);
		} catch( \Exception $e  ){
			//echo $e;
			Response::instance()->sendMessage( 'ROUTE error.', 'error' );
			die;
		}
	}
	
	public static function instance() {
		if( ! self::$instance instanceof self ) {
			self::$instance = new self;
		}
		return self::$instance;
	}
	
	protected function addRoutes( $rc ) {

		$rc->post( '/v1/auth/sms/send/', [ '\OA\Auth', 'SMSSend' ] );
		$rc->post( '/v1/auth/sms/verify/', [ '\OA\Auth', 'SMSVerify' ] );
		$rc->post( '/v1/auth/logout/', [ '\OA\Auth', 'logout' ] );
		$rc->post( '/admin/v1/auth/sms/send/', [ '\OA\Auth', 'adminSMSSend' ] );
		$rc->post( '/admin/v1/auth/sms/verify/', [ '\OA\Auth', 'adminSMSVerify' ] );

		$rc->get( '/v1/data/initial/[{table}/{page:\d+}/]', [ '\OA\RouteResponse', 'dataInitial_v1' ] );
		$rc->get( '/v2/data/initial/[{table}/{page:\d+}/]', [ '\OA\RouteResponse', 'dataInitial_v2' ] );
		$rc->get( '/v1/data/check/{dbVersion:\d+}/', [ '\OA\RouteResponse', 'dataCheck' ] );
		$rc->get( '/v1/home/', [ '\OA\RouteResponse', 'home' ] );
		$rc->get( '/v2/home/', [ '\OA\RouteResponse', 'home_v2' ] );
		$rc->get( '/v1/medicines/[{search}/[{page:\d+}/]]', [ '\OA\RouteResponse', 'medicines' ] );
		$rc->get( '/v1/sameGeneric/{g_id:\d+}/{page:\d+}/', [ '\OA\RouteResponse', 'sameGeneric' ] );
		$rc->get( '/v1/medicine/{m_id:\d+}/', [ '\OA\RouteResponse', 'medicineSingle_v1' ] );
		$rc->get( '/v2/medicine/{m_id:\d+}/', [ '\OA\RouteResponse', 'medicineSingle_v2' ] );
		$rc->get( '/v3/medicine/{m_id:\d+}/', [ '\OA\RouteResponse', 'medicineSingle_v3' ] );
		$rc->get( '/v1/medicine/extra/{m_id:\d+}/', [ '\OA\RouteResponse', 'medicineSingleExtra_v1' ] );
		$rc->get( '/v2/medicine/extra/{m_id:\d+}/', [ '\OA\RouteResponse', 'medicineSingleExtra_v2' ] );

		$rc->get( '/v1/medicine/price/{m_id:[0-9,]+}/', [ '\OA\RouteResponse', 'medicinePrice' ] );
		$rc->post( '/v1/medicine/suggest/', [ '\OA\RouteResponse', 'medicineSuggest' ] );
		$rc->post( '/v1/token/', [ '\OA\RouteResponse', 'token' ] );
		$rc->get( '/v1/categories/', [ '\OA\RouteResponse', 'categories' ] );

		$rc->post( '/v1/cart/details/', [ '\OA\RouteResponse', 'cartDetails' ] );
		$rc->post( '/v1/discount/check/', [ '\OA\RouteResponse', 'dicountCheck' ] );

		$rc->get( '/v1/checkout/initiated/', [ '\OA\RouteResponse', 'checkoutInitiated' ] );

		$rc->post( '/v1/order/add/', [ '\OA\RouteResponse', 'orderAdd' ] );
		$rc->get( '/v1/order/{o_id:\d+}/', [ '\OA\RouteResponse', 'orderSingle' ] );
		$rc->post( '/v1/order/{o_id:\d+}/', [ '\OA\RouteResponse', 'orderUpdate' ] );
		$rc->get( '/v1/invoice/{o_id:\d+}/{secret}/', [ '\OA\RouteResponse', 'invoice' ] );
		$rc->get( '/v1/invoice/bag/{o_id:\d+}/{secret}/', [ '\OA\RouteResponse', 'invoiceBag' ] );
		$rc->get( '/v1/orders/{status}/[{page:\d+}/]', [ '\OA\RouteResponse', 'orders' ] );
		$rc->get(    '/v1/cashBalance/', [ '\OA\RouteResponse', 'cashBalance' ] );
		$rc->get( '/v1/location/', [ '\OA\RouteResponse', 'location' ] );
		$rc->get( '/v1/profile/', [ '\OA\RouteResponse', 'profile' ] );
		$rc->post( '/v1/profile/', [ '\OA\RouteResponse', 'profileUpdate' ] );
		$rc->get( '/v1/profile/prescriptions/', [ '\OA\RouteResponse', 'prescriptions' ] );
		$rc->get( '/v1/offers/', [ '\OA\RouteResponse', 'offers' ] );
		$rc->get( '/v1/faqsHeaders/', [ '\OA\RouteResponse', 'FAQsHeaders' ] );
		$rc->get( '/v1/faqs/{slug}/', [ '\OA\RouteResponse', 'FAQsReturn' ] );
		$rc->get( '/v1/locationData/', [ '\OA\RouteResponse', 'locationData' ] );
		$rc->get( '/v1/allLocations/', [ '\OA\RouteResponse', 'allLocations' ] );
		$rc->post( '/v1/webhook/redx/', [ '\OA\Factory\Redx', 'webhook' ] );
		$rc->post( '/v1/requestStock/', [ '\OA\RouteResponse', 'requestStockCreate' ] );

		$rc->addGroup('/payment/v1', function ( $rc ) {
			$rc->get( '/{o_id:\d+}/{secret}/[{method}/]', [ '\OA\PaymentResponse', 'home' ] );
			$rc->get( '/callback/{method}/', [ '\OA\PaymentResponse', 'callback' ] );
			$rc->addRoute( ['GET', 'POST'], '/success/{method}/{o_id:\d+}/{secret}/', [ '\OA\PaymentResponse', 'success' ] );
			$rc->post( '/error/{o_id:\d+}/{method}/', [ '\OA\PaymentResponse', 'error' ] );
			$rc->post( '/ipn/[{method}/]', [ '\OA\PaymentResponse', 'ipn' ] );

			$rc->get( '/bKash/create/{o_id:\d+}/{secret}/', [ '\OA\Payment\Bkash', 'create' ] );
			$rc->get( '/bKash/execute/{o_id:\d+}/{secret}/', [ '\OA\Payment\Bkash', 'execute' ] );
			$rc->get( '/bKash/manualSuccess/{o_id:\d+}/', [ '\OA\Payment\Bkash', 'manualSuccess' ] );
			$rc->post( '/bKash/refund/{o_id:\d+}/', [ '\OA\Payment\Bkash', 'refund' ] );
			$rc->post( '/bKash/refundStatus/{o_id:\d+}/', [ '\OA\Payment\Bkash', 'refundStatus' ] );
		});

		$rc->addGroup('/cache/v1', function ( $rc ) {
			$rc->get(    '/flush/', [ '\OA\CacheResponse', 'cacheFlush' ] );
			$rc->get(    '/stats/', [ '\OA\CacheResponse', 'cacheStats' ] );

			$rc->get(    '/set/{key}/{value}/[{group}/]', [ '\OA\CacheResponse', 'set' ] );
			$rc->get(    '/get/{key}/[{group}/]', [ '\OA\CacheResponse', 'get' ] );
			$rc->get(    '/delete/{key}/[{group}/]', [ '\OA\CacheResponse', 'delete' ] );
		});
		$rc->addGroup('/adminApp/v1', function ( $rc ) {
			$rc->get( '/orders/', [ '\OA\AdminAppResponse', 'orders' ] );
			$rc->get( '/later/', [ '\OA\AdminAppResponse', 'later' ] );
			$rc->get( '/purchaseRequest/', [ '\OA\AdminAppResponse', 'purchaseRequest' ] );
			$rc->get( '/collections/', [ '\OA\AdminAppResponse', 'collections' ] );
			$rc->get( '/pendingCollection/', [ '\OA\AdminAppResponse', 'pendingCollection' ] );
			$rc->get( '/zones/', [ '\OA\AdminAppResponse', 'zones' ] );
			$rc->post( '/sendCollection/', [ '\OA\AdminAppResponse', 'sendCollection' ] );
			$rc->post( '/receivedCollection/{co_id:\d+}/', [ '\OA\AdminAppResponse', 'receivedCollection' ] );
			$rc->post(  '/statusTo/{o_id:\d+}/{status}/', [ '\OA\AdminAppResponse', 'statusTo' ] );
			$rc->post(  '/internalStatusTo/{o_id:\d+}/{status}/', [ '\OA\AdminAppResponse', 'internalStatusTo' ] );
			$rc->post( '/issueStatusTo/{o_id:\d+}/{status}/', [ '\OA\AdminAppResponse', 'issueStatusTo' ] );
			$rc->post( '/saveInternalNote/{o_id:\d+}/', [ '\OA\AdminAppResponse', 'saveInternalNote' ] );
			$rc->post( '/sendDeSMS/{o_id:\d+}/', [ '\OA\AdminAppResponse', 'sendDeSMS' ] );
		});
		$rc->addGroup('/admin/v1', function ( $rc ) {
			$rc->get( '/allLocations/', [ '\OA\AdminResponse', 'allLocations' ] );
			$rc->get( '/report/', [ '\OA\ReportResponse', 'report' ] );
			$rc->get( '/optionsMultiple/', [ '\OA\AdminResponse', 'optionsMultipleGet' ] );
			$rc->post( '/optionsMultiple/', [ '\OA\AdminResponse', 'optionsMultipleSet' ] );

			$rc->addGroup('/medicines', function ( $rc ) {
				$rc->get(    '/', [ '\OA\AdminResponse', 'medicines' ] );
				$rc->post(   '/', [ '\OA\AdminResponse', 'medicineCreate' ] );
				$rc->get(    '/{m_id:\d+}/', [ '\OA\AdminResponse', 'medicineSingle' ] );
				$rc->post(   '/{m_id:\d+}/', [ '\OA\AdminResponse', 'medicineUpdate' ] );
				$rc->delete( '/{m_id:\d+}/', [ '\OA\AdminResponse', 'medicineDelete' ] );
				$rc->delete( '/image/{m_id:\d+}/', [ '\OA\AdminResponse', 'medicineImageDelete' ] );
			});
			$rc->addGroup('/requestStock', function ( $rc ) {
				$rc->get(    '/', [ '\OA\AdminResponse', 'requestStock' ] );
			});
			$rc->addGroup('/users', function ( $rc ) {
				$rc->get(    '/', [ '\OA\AdminResponse', 'users' ] );
				$rc->post(   '/', [ '\OA\AdminResponse', 'userCreate' ] );
				$rc->get(    '/{u_id:\d+}/', [ '\OA\AdminResponse', 'userSingle' ] );
				$rc->post(   '/{u_id:\d+}/', [ '\OA\AdminResponse', 'userUpdate' ] );
				$rc->delete( '/{u_id:\d+}/', [ '\OA\AdminResponse', 'userDelete' ] );
				$rc->post(   '/{action}/{u_id:\d+}/', [ '\OA\AdminResponse', 'userPostAction' ] );
			});
			$rc->addGroup('/orders', function ( $rc ) {
				$rc->get(    '/', [ '\OA\AdminResponse', 'orders' ] );
				$rc->post(   '/', [ '\OA\AdminResponse', 'orderCreate' ] );
				$rc->get(    '/{o_id:\d+}/', [ '\OA\AdminResponse', 'orderSingle' ] );
				$rc->post(   '/{o_id:\d+}/', [ '\OA\AdminResponse', 'orderUpdate' ] );
				$rc->delete( '/{o_id:\d+}/', [ '\OA\AdminResponse', 'orderDelete' ] );
				$rc->get(   '/{type}/{o_id:\d+}/', [ '\OA\AdminResponse', 'orderGetType' ] );
				$rc->post(   '/{action}/{o_id:\d+}/', [ '\OA\AdminResponse', 'orderPostAction' ] );
				$rc->post(   '/updateMany/', [ '\OA\AdminResponse', 'orderUpdateMany' ] );
			});
			$rc->addGroup('/offlineOrders', function ( $rc ) {
				$rc->get(    '/', [ '\OA\AdminResponse', 'offlineOrders' ] );
				$rc->post(   '/', [ '\OA\AdminResponse', 'offlineOrderCreate' ] );
				$rc->get(    '/{o_id:\d+}/', [ '\OA\AdminResponse', 'orderSingle' ] );
				$rc->post(   '/{o_id:\d+}/', [ '\OA\AdminResponse', 'offlineOrderUpdate' ] );
				$rc->delete( '/{o_id:\d+}/', [ '\OA\AdminResponse', 'orderDelete' ] );
			});
			$rc->addGroup('/orderMedicines', function ( $rc ) {
				$rc->get(    '/', [ '\OA\AdminResponse', 'orderMedicines' ] );
				//$rc->post(   '/', [ '\OA\AdminResponse', 'orderCreate' ] );
				$rc->get(    '/{om_id:\d+}/', [ '\OA\AdminResponse', 'orderMedicineSingle' ] );
				$rc->post(   '/{om_id:\d+}/', [ '\OA\AdminResponse', 'orderMedicineUpdate' ] );
				$rc->delete( '/{om_id:\d+}/', [ '\OA\AdminResponse', 'orderMedicineDelete' ] );
			});
			$rc->addGroup('/laterMedicines', function ( $rc ) {
				$rc->get(    '/', [ '\OA\AdminResponse', 'laterMedicines' ] );
				$rc->post(   '/savePurchaseRequest/', [ '\OA\AdminResponse', 'savePurchaseRequest' ] );
			});
			$rc->addGroup('/inventory', function ( $rc ) {
				$rc->get(    '/', [ '\OA\AdminResponse', 'inventory' ] );
				$rc->get(    '/{i_id:\d+}/', [ '\OA\AdminResponse', 'inventorySingle' ] );
				$rc->post(   '/{i_id:\d+}/', [ '\OA\AdminResponse', 'inventoryUpdate' ] );
				$rc->delete( '/{i_id:\d+}/', [ '\OA\AdminResponse', 'inventoryDelete' ] );
				$rc->get(    '/balance/', [ '\OA\AdminResponse', 'inventoryBalance' ] );
			});
			$rc->addGroup('/purchases', function ( $rc ) {
				$rc->get(    '/', [ '\OA\AdminResponse', 'purchases' ] );
				$rc->post(   '/', [ '\OA\AdminResponse', 'purchaseCreate' ] );
				$rc->get(    '/{pu_id:\d+}/', [ '\OA\AdminResponse', 'purchaseSingle' ] );
				$rc->post(   '/{pu_id:\d+}/', [ '\OA\AdminResponse', 'purchaseUpdate' ] );
				$rc->delete( '/{pu_id:\d+}/', [ '\OA\AdminResponse', 'purchaseDelete' ] );
				$rc->get(    '/pendingTotal/', [ '\OA\AdminResponse', 'purchasesPendingTotal' ] );
				$rc->post(    '/sync/', [ '\OA\AdminResponse', 'purchasesSync' ] );
			});
			$rc->addGroup('/collections', function ( $rc ) {
				$rc->get(    '/', [ '\OA\AdminResponse', 'collections' ] );
				$rc->get(    '/{co_id:\d+}/', [ '\OA\AdminResponse', 'collectionSingle' ] );
			});
			$rc->addGroup('/ledger', function ( $rc ) {
				$rc->get(    '/', [ '\OA\AdminResponse', 'ledger' ] );
				$rc->post(   '/', [ '\OA\AdminResponse', 'ledgerCreate' ] );
				$rc->get(    '/{l_id:\d+}/', [ '\OA\AdminResponse', 'ledgerSingle' ] );
				$rc->post(   '/{l_id:\d+}/', [ '\OA\AdminResponse', 'ledgerUpdate' ] );
				$rc->delete( '/{l_id:\d+}/', [ '\OA\AdminResponse', 'ledgerDelete' ] );
				$rc->get(    '/balance/', [ '\OA\AdminResponse', 'ledgerBalance' ] );
			});
			$rc->addGroup('/companies', function ( $rc ) {
				$rc->get(    '/', [ '\OA\AdminResponse', 'companies' ] );
				$rc->get(    '/{c_id:\d+}/', [ '\OA\AdminResponse', 'companySingle' ] );
				$rc->post(   '/', [ '\OA\AdminResponse', 'companyCreate' ] );
                $rc->post(   '/{c_id:\d+}/', [ '\OA\AdminResponse', 'companyUpdate' ] );
			});
			$rc->addGroup('/generics', function ( $rc ) {
				$rc->get(    '/', [ '\OA\AdminResponse', 'generics' ] );
				$rc->get(    '/{g_id:\d+}/', [ '\OA\AdminResponse', 'genericSingle' ] );
                $rc->post(   '/', [ '\OA\AdminResponse', 'genericCreate' ] );
                $rc->post(   '/{g_id:\d+}/', [ '\OA\AdminResponse', 'genericUpdate' ] );
			});
			$rc->addGroup('/locations', function ( $rc ) {
				$rc->get(    '/', [ '\OA\AdminResponse', 'locations' ] );
				$rc->get(    '/{l_id:\d+}/', [ '\OA\AdminResponse', 'locationSingle' ] );
			});
			$rc->addGroup('/bags', function ( $rc ) {
				$rc->get(    '/', [ '\OA\AdminResponse', 'bags' ] );
				$rc->post(   '/', [ '\OA\AdminResponse', 'bagCreate' ] );
				$rc->get(    '/{b_id:\d+}/', [ '\OA\AdminResponse', 'bagSingle' ] );
				$rc->post(   '/{b_id:\d+}/', [ '\OA\AdminResponse', 'bagUpdate' ] );
				$rc->delete( '/{b_id:\d+}/', [ '\OA\AdminResponse', 'bagDelete' ] );
			});
		});

		$rc->addGroup( '/partner/v1', function ( $rc ) {
			$rc->get( '/locationData/', [ '\OA\PartnerResponse', 'locationData' ] );

			$rc->addGroup('/orders', function ( $rc ) {
				$rc->get(    '/', [ '\OA\PartnerResponse', 'orders' ] );
				$rc->post(   '/', [ '\OA\PartnerResponse', 'orderCreate' ] );
				$rc->get(    '/{o_id:\d+}/', [ '\OA\PartnerResponse', 'orderSingle' ] );
			});
			$rc->addGroup('/users', function ( $rc ) {
				//$rc->get(    '/{u_id:\d+}/', [ '\OA\PartnerResponse', 'userSingle' ] );
				$rc->get(    '/{u_mobile}/', [ '\OA\PartnerResponse', 'userSingle' ] );
			});
		});

		$rc->addGroup('/cron/v1', function ( $rc ) {
			$rc->get(    '/daily/{type}/', [ '\OA\CronResponse', 'daily' ] );
			$rc->get(    '/hourly/{type}/', [ '\OA\CronResponse', 'hourly' ] );
			$rc->get(    '/halfhourly/{type}/', [ '\OA\CronResponse', 'halfhourly' ] );
		});

		$rc->addGroup('/onetime/v1', function ( $rc ) {
			//$rc->get(    '/addImageLinkInMeta/', [ '\OA\OnetimeResponse', 'addImageLinkInMeta' ] );
			//$rc->get(    '/addImageLinkInMetaS3/', [ '\OA\OnetimeResponse', 'addImageLinkInMetaS3' ] );
			//$rc->get(    '/addPrescriptionToS3/', [ '\OA\OnetimeResponse', 'addPrescriptionToS3' ] );
			//$rc->get(    '/addLedgerFilesToS3/', [ '\OA\OnetimeResponse', 'addLedgerFilesToS3' ] );
			//$rc->get(    '/bulkCreateOrder/{count:\d+}/', [ '\OA\OnetimeResponse', 'bulkCreateOrder' ] );
			//$rc->get(    '/orderDuplicate/', [ '\OA\OnetimeResponse', 'orderDuplicate' ] );
			//$rc->get(    '/sendOTPSMS/{number}/{count:\d+}/', [ '\OA\OnetimeResponse', 'sendOTPSMS' ] );
			//$rc->get(    '/sendNotificationToAllUsers/', [ '\OA\OnetimeResponse', 'sendNotificationToAllUsers' ] );
			$rc->get(    '/updateLocationsTable/', [ '\OA\OnetimeResponse', 'updateLocationsTable' ] );
			$rc->get(    '/sitemap/', [ '\OA\OnetimeResponse', 'sitemap' ] );
			$rc->get(    '/genericsMerge/', [ '\OA\OnetimeResponse', 'genericsMerge' ] );
			$rc->post(    '/updateFosterGatewayFee/', [ '\OA\OnetimeResponse', 'updateFosterGatewayFee' ] );
			$rc->post(    '/updateFosterGatewayFee/{o_id:\d+}/', [ '\OA\OnetimeResponse', 'updateFosterGatewayFeeSingle' ] );

			$rc->get(    '/stockUpdate/{rob:\d+}/{by}/{id:\d+}/', [ '\OA\OnetimeResponse', 'stockUpdate' ] );
			$rc->get(    '/deliverymanUpdate/{prev:\d+}/{curr:\d+}/', [ '\OA\OnetimeResponse', 'deliverymanUpdate' ] );
			$rc->get(    '/priceUpdate/{discountPercent}/{by}/{id:\d+}/[{prevDiscountPercent}/]', [ '\OA\OnetimeResponse', 'priceUpdate' ] );
			$rc->get(    '/medicineCSVImport/{number:\d+}/', [ '\OA\OnetimeResponse', 'medicineCSVImport' ] );

			$rc->get(    '/search/medicine/indices/delete/', [ '\OA\Search\Medicine', 'indicesDelete' ] );
			$rc->get(    '/search/medicine/indices/create/', [ '\OA\Search\Medicine', 'indicesCreate' ] );
			$rc->get(    '/search/medicine/bulkIndex/', [ '\OA\Search\Medicine', 'bulkIndex' ] );
		});
	}
	
	public function dispatch(){

		$httpMethod = $_SERVER['REQUEST_METHOD'];
		$uri = $_SERVER['REQUEST_URI'];
		if( 'OPTIONS' === $httpMethod ) {
			http_response_code( 200 );
			header("Access-Control-Allow-Origin: *");
			header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
			header("Access-Control-Allow-Headers: Authorization, Content-Type, Accept, Origin");
			header("Access-Control-Max-Age: 86400");
			die;
		}

		//$uri = str_replace( '/arogga', '', $uri);

		// Strip query string (?foo=bar) and decode URI
		if (false !== $pos = strpos($uri, '?')) {
			$uri = substr($uri, 0, $pos);
		}
		$uri = rtrim( rawurldecode($uri), '/' ) . '/';

		Log::instance()->set( 'log_uri', $uri );
		
		if( '/' == $uri ){
			Response::instance()->setCode( 404 );
			Response::instance()->sendMessage( 'Nothing Found' );
		}
		/*
		if( 0 !== strpos( $uri, '/onetime/v1/' ) ){
			Response::instance()->setCode( 503 );
			Response::instance()->sendMessage( 'Maintenance' );
		}
		*/
		
		$routeInfo = $this->route_dispatcher->dispatch($httpMethod, $uri);
		
		switch ($routeInfo[0]) {
			case \FastRoute\Dispatcher::NOT_FOUND:
				Response::instance()->setCode( 404 );
				Response::instance()->sendMessage( 'Nothing Found' );
				break;
			case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
				$allowedMethods = $routeInfo[1];
				Response::instance()->setCode( 405 );
				Response::instance()->sendMessage( 'Not Allowed' );
				break;
			case \FastRoute\Dispatcher::FOUND:

				$callback = $routeInfo[1];
				$vars = $routeInfo[2];

				if( $callback && is_callable( $callback ) ){
					if ( is_array( $callback ) ) {
						call_user_func_array( [ new $callback[0], $callback[1] ], $vars );
					} else {
						call_user_func_array( $callback, $vars );
					}
				}
				break;
		}
		Response::instance()->setCode( 404 );
		Response::instance()->sendMessage( 'Nothing Found' );
	}
}
