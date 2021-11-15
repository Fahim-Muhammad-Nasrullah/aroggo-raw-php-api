<?php

namespace OA\Factory;
use OA\{Functions, Response};
use GuzzleHttp\Client;


class Redx {

	public static $instance;
	private $client;

	public static function instance() {
		if( ! self::$instance instanceof self ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	function __construct() {
	}

	private function client(){

		return new Client([
			'http_errors' => false,
			'headers' => [
				'API-ACCESS-TOKEN' => 'Bearer '. REDX_TOKEN,
				'Content-Type' => 'application/json',
			],
		]);
	}

	public function getRedxArea( $post_code ){

		if ( !$post_code ) {
			return false;
		}

		$res = $this->client()->get( REDX_URL . '/areas?post_code='. $post_code);
		if( 200 !== $res->getStatusCode() ){
			return false;
		}
		$body = Functions::maybeJsonDecode( $res->getBody()->getContents() );

		if( $body || \is_array( $body['areas'] ) ){
			return $body['areas'][0];
		}
		return false;
	}

	public function createRedxParcel( $o_id ){
		if ( !$o_id ) {
			return false;
		}

		if( $order = Order::getOrder( $o_id ) ){
			$location = Location::getLocation( $order->o_l_id );
			if ($location){
				if( !$location->l_redx_area_id ) {
					$RedxArea = $this->getRedxArea( $location->l_postcode );
					if ( $RedxArea && isset( $RedxArea['id'] ) ){
						$location->l_redx_area_id = $RedxArea['id'];
						$location->update();
					}else{
						$order->setMeta('redx_error', 'Location data failed');
						$order->appendMeta( 'o_i_note', 'Redx parcel create failed, Create manually' );
						$order->addHistory( 'RedxParcel Failed', 'Failed to create percel' );
						return false;
					}
				}
				$pickup_store_id = (int)Meta::get( 'user', $order->o_ph_id, 'redx_pickup_store_id' );

				$amount = $order->isPaid() ? 0 : $order->o_total;
				$s_address = $order->getMeta('s_address');
				$mobile = $name = $area = '';
				if( is_array( $s_address ) && ! empty( $s_address['mobile'] ) ){
					$mobile = Functions::checkMobile( $s_address['mobile'] );
					$name = $s_address['name'];
					$area = $s_address['area'];
				}
				$mobile = $mobile ?: $order->u_mobile;
				$name = $name ?: $order->u_name;
				$parcel = [
						"customer_name"  => $name,
						"customer_phone" => $mobile,
						"delivery_area"  => $area,
						"delivery_area_id" => $location->l_redx_area_id,
						"customer_address" => $order->o_gps_address,
						"cash_collection_amount" => $amount,
						"parcel_weight" => 500,
						"merchant_invoice_id" => "{$order->o_id}",
						"value" => $order->o_subtotal,
						"pickup_store_id" => $pickup_store_id,
				];
				/*
				 ## need to set parcel_weight
				 */

				$res = $this->client()->post( REDX_URL . '/parcel', ['json' => $parcel] );
				if( 201 !== $res->getStatusCode() ){
					$order->setMeta('redx_error', sprintf( '%s response code received', $res->getStatusCode() ) );
					$order->appendMeta( 'o_i_note', 'Redx parcel create failed, Create manually' );
					$order->addHistory( 'RedxParcel Failed', 'Failed to create percel' );
					return false;
				}

				$response = Functions::maybeJsonDecode( $res->getBody()->getContents() );

			   if ( $response && isset( $response['tracking_id'] ) ){
					//$order->deleteMeta( 'redx_error' );
					$order->setMeta('redx_tracking_id', $response['tracking_id']);
					$order->addHistory( 'Redx Parcel', 'Redx Parcel Created. Tracking id = '.$response['tracking_id'] );
			   } else {
					$order->setMeta('redx_error', $response);
					$order->appendMeta( 'o_i_note', 'Redx parcel create failed, Create manually' );
					$order->addHistory( 'RedxParcel Failed', 'Failed to create percel' );
			   }
			}
		}
	}

    public function webhook(){
		$api_key = $_GET['api_key'] ?? '';
        $payload  = file_get_contents('php://input');
		$payload = Functions::maybeJsonDecode( $payload );

		$tokenDecoded = Functions::jwtDecode( $api_key );
        if( !$tokenDecoded || empty( $tokenDecoded['u_id'] ) || 143 !== $tokenDecoded['u_id'] ){
            Response::instance()->sendMessage( 'Invalid token' );
        }

        $invoice_number = isset( $payload['invoice_number'] ) ? (int)$payload['invoice_number'] : 0;
        $status = isset( $payload['status'] ) ? $payload['status'] : '';
        $current_tracking_id = isset( $payload['tracking_number'] ) ? $payload['tracking_number'] : '';
        $message_en = isset( $payload['message_en'] ) ? $payload['message_en'] : '';

        if ( $invoice_number ){
            if ( $order = Order::getOrder( $invoice_number ) ){
                $tracking_id = $order->getMeta( 'redx_tracking_id' );
                if ( $tracking_id && $tracking_id === $current_tracking_id ){
                    if ( 'ready-for-delivery' === $status || 'delivery-in-progress' === $status ){
						if( 'confirmed' === $order->o_status && 'confirmed' === $order->o_i_status ){
							$order->o_status = 'delivering';
                        	$order->update();
						}
                    } elseif ( 'delivered' === $status ){
						if( 'delivering' === $order->o_status ){
							$order->o_status = 'delivered';
                        	$order->update();
						}
                    }
                    $order->addHistory( 'Redx Response', $message_en );

					Response::instance()->sendMessage( 'Webhook received and updated.', 'success' );
                }
            }
        }
		Response::instance()->sendMessage( 'Something wrong, Please try again' );
    }
}