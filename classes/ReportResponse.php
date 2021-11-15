<?php

namespace OA;
use OA\Factory\{User, Medicine, Discount, Order, Option, CacheUpdate, Inventory};
use GuzzleHttp\Client;

class ReportResponse {
    private $user;

    function __construct() {
        \header("Access-Control-Allow-Origin: *");
        //\header("Access-Control-Request-Headers: *");
        \define( 'ADMIN', true );

        if ( ! ( $user = User::getUser( Auth::id() ) ) ) {
            Response::instance()->setCode( 403 );
            Response::instance()->loginRequired( true );
            Response::instance()->sendMessage( 'You are not logged in' );
        }
        if( ! $user->can( 'backendAccess' ) ) {
            Response::instance()->setCode( 401 );
            Response::instance()->sendMessage( 'Your account does not have admin access.');
        }
        $httpMethod = $_SERVER['REQUEST_METHOD'];
        if( $user->can( 'onlyGET' ) && $httpMethod != 'GET' ) {
            Response::instance()->sendMessage( 'Your account does not have permission to do this.');
        }

        $this->user = $user;
    }

    public function report(){
        $dateFrom = (isset( $_GET['dateFrom'] ) && $this->validateDate( $_GET['dateFrom'] ) ) ? $_GET['dateFrom'] : '';
        $dateTo = (isset( $_GET['dateTo'] ) && $this->validateDate( $_GET['dateTo'] ) ) ? $_GET['dateTo'] : '';
        $limit = (isset( $_GET['limit'] ) && (int)$_GET['limit'] <= 1000 ) ? (int)$_GET['limit'] : 10;

        if( ! $dateFrom || ! $dateTo ){
            Response::instance()->sendMessage( 'Invalid date', 'error' );
        }
        $data = [
            'orders' => $this->orders($dateFrom, $dateTo),
            'deOrders' => $this->deliveryOrders($dateFrom, $dateTo),
            'users' => $this->users($dateFrom, $dateTo),
            'summary' => $this->summary($dateFrom, $dateTo),
            'popularMedicines' => $this->popularMedicines($dateFrom, $dateTo, $limit),
        ];
        Response::instance()->sendData( $data, 'success');
    }

    private function validateDate( $date, $format = 'Y-m-d' ){
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    private function getFormate( $difference ){
        if( $difference->y > 1 ){
            $interval = \DateInterval::createFromDateString('1 year');
            $dayFormat = 'Y';
            $dateFormat = '%Y';
        } else if( $difference->y || $difference->m > 1 ){
            $interval = \DateInterval::createFromDateString('1 month');
            $dayFormat = 'Y-m';
            $dateFormat = '%Y-%m';
        } else if( $difference->m || $difference->d > 1 ) {
            $interval = \DateInterval::createFromDateString('1 day');
            $dayFormat = 'Y-m-d';
            $dateFormat = '%Y-%m-%d';
        } else {
            $interval = \DateInterval::createFromDateString('1 hour');
            $dayFormat = 'H';
            $dateFormat = '%H';
        }
        return [
            'interval' => $interval,
            'dayFormat' => $dayFormat,
            'dateFormat' => $dateFormat,
        ];
    }

    public function orders($dateFrom, $dateTo){

        $begin = new \DateTime($dateFrom);
        $end = new \DateTime($dateTo);
        $end->add(\DateInterval::createFromDateString('1 day'));
        $difference = $begin->diff($end);

        $getFormat = $this->getFormate( $difference );
        $interval = $getFormat['interval'];
        $dayFormat = $getFormat['dayFormat'];
        $dateFormat = $getFormat['dateFormat'];

        $period = new \DatePeriod($begin, $interval, $end);

        $query = DB::db()->prepare("SELECT DATE_FORMAT(o_created, ?) as orderDate, count(o_id) as orderCount, sum(o_total) as orderValue, `o_status` as orderStatus FROM `t_orders` WHERE `o_created` BETWEEN ? AND ? GROUP BY DATE_FORMAT(o_created, ?) , `o_status`");
        $query->execute([ $dateFormat, $dateFrom, $end->format('Y-m-d'), $dateFormat ]);
        $orders = $query->fetchAll();

        $orderReport = [];
        $orderReport['orderCount'] = [];
        $orderReport['orderValue'] = [];
        $legend = [ 'total' ];

        foreach ( $period as $date ) {
            $totalCount = $totalValue = 0;
            $orderCount = $orderValue = [
                'date' => $date->format($dayFormat),
            ];
            $orderCount['date'] = $orderValue['date'] = $date->format($dayFormat);

            foreach ( $orders as $order ) {
                if ( $date->format($dayFormat) == $order['orderDate'] ) {
                    $orderCount[ $order['orderStatus'] ] = round( $order['orderCount'] );
                    $orderValue[ $order['orderStatus'] ] = round( $order['orderValue'] );
                    $totalCount += $order['orderCount'];
                    $totalValue += $order['orderValue'];

                    if( !in_array( $order['orderStatus'], $legend ) ){
                        array_push( $legend, $order['orderStatus'] );
                    }
                }
            }
            $orderCount['total'] = $totalCount;
            $orderValue['total'] = $totalValue;

            $default = array_fill_keys( $legend, 0 );

            array_push($orderReport['orderCount'], array_merge( $default, $orderCount ) );
            array_push($orderReport['orderValue'], array_merge( $default, $orderValue ) );
        }
        $orderReport['legend'] = $legend;

        return $orderReport;
    }

    public function deliveryOrders($dateFrom, $dateTo){

        $begin = new \DateTime($dateFrom);
        $end = new \DateTime($dateTo);
        $end->add(\DateInterval::createFromDateString('1 day'));
        $difference = $begin->diff($end);

        $getFormat = $this->getFormate( $difference );
        $interval = $getFormat['interval'];
        $dayFormat = $getFormat['dayFormat'];
        $dateFormat = $getFormat['dateFormat'];

        $period = new \DatePeriod($begin, $interval, $end);

        $query = DB::db()->prepare("SELECT DATE_FORMAT(o_delivered, ?) as orderDate, count(o_id) as orderCount, o_de_id FROM `t_orders` WHERE `o_delivered` BETWEEN ? AND ? AND o_status = ? GROUP BY DATE_FORMAT(o_delivered, ?), o_de_id");
        $query->execute([ $dateFormat, $dateFrom, $end->format('Y-m-d'), 'delivered', $dateFormat ]);
        $orders = $query->fetchAll();

        $orderReport = [];
        $legend = [ 'total' ];

        foreach ( $period as $date ) {
            $totalCount = 0;
            $orderCount = [
                'date' => $date->format($dayFormat),
            ];

            foreach ( $orders as $order ) {
                if ( $date->format($dayFormat) == $order['orderDate'] && ($de_name = User::getName( $order['o_de_id'] )) ) {
                    $orderCount[ $de_name ] = round( $order['orderCount'] );
                    $totalCount += $order['orderCount'];

                    if( !in_array( $de_name, $legend ) ){
                        array_push( $legend, $de_name );
                    }
                }
            }
            $orderCount['total'] = $totalCount;

            $default = array_fill_keys( $legend, 0 );
            array_push($orderReport, array_merge( $default, $orderCount ));
        }
        $data = [
            'report' => $orderReport,
            'legend' => $legend,
        ];

        return $data;
    }

    public function users($dateFrom, $dateTo){
        $begin = new \DateTime($dateFrom);
        $end = new \DateTime($dateTo);
        $end->add(\DateInterval::createFromDateString('1 day'));
        $difference = $begin->diff($end);
        
        $getFormat = $this->getFormate( $difference );
        $interval = $getFormat['interval'];
        $dayFormat = $getFormat['dayFormat'];
        $dateFormat = $getFormat['dateFormat'];
        
        $period = new \DatePeriod($begin, $interval, $end);

        $registeredUsersQuery = DB::db()->prepare('SELECT DATE_FORMAT(u_created, ?), count(u_id) FROM `t_users` WHERE `u_created` BETWEEN ? AND ? GROUP BY DATE_FORMAT(u_created, ?)');
        $registeredUsersQuery->execute([ $dateFormat, $dateFrom, $end->format('Y-m-d'), $dateFormat ]);
        $registeredUsers = $registeredUsersQuery->fetchAll( \PDO::FETCH_KEY_PAIR );

        $orderedUsersQuery = DB::db()->prepare('SELECT DATE_FORMAT(u_created, ?), count(u_id) FROM `t_users` WHERE `u_o_count` > ?  AND `u_created` BETWEEN ? AND ? GROUP BY DATE_FORMAT(u_created, ?)');
        $orderedUsersQuery->execute([ $dateFormat, 0, $dateFrom, $end->format('Y-m-d'), $dateFormat ]);
        $orderedUsers = $orderedUsersQuery->fetchAll( \PDO::FETCH_KEY_PAIR );

        $repeatedUsersQuery = DB::db()->prepare('SELECT DATE_FORMAT(u_created, ?), count(u_id) FROM `t_users` WHERE `u_o_count` > ?  AND `u_created` BETWEEN ? AND ? GROUP BY DATE_FORMAT(u_created, ?)');
        $repeatedUsersQuery->execute([ $dateFormat, 1, $dateFrom, $end->format('Y-m-d'), $dateFormat ]);
        $repeatedUsers = $repeatedUsersQuery->fetchAll( \PDO::FETCH_KEY_PAIR );

        $userReport = [];
        foreach ($period as $date) {
            $userCount = [
                'date' => '',
                'total' => 0,
                'ordered' => 0,
                'repeated' => 0,
            ];
            $userCount['date'] = $date->format($dayFormat);
            if( isset( $registeredUsers[ $userCount['date'] ] ) ){
                $userCount['total'] = $registeredUsers[ $userCount['date'] ];
            }
            if( isset( $orderedUsers[ $userCount['date'] ] ) ){
                $userCount['ordered'] = $orderedUsers[ $userCount['date'] ];
            }
            if( isset( $repeatedUsers[ $userCount['date'] ] ) ){
                $userCount['repeated'] = $repeatedUsers[ $userCount['date'] ];
            }

            array_push($userReport, $userCount);
        }

        return $userReport;
    }

    public function summary($dateFrom, $dateTo){
        //summary calculation
        $begin = new \DateTime($dateFrom);
        $end = new \DateTime($dateTo);
        $end->add(\DateInterval::createFromDateString('1 day'));
        $difference = $begin->diff($end);

        $query = DB::db()->prepare('SELECT count(*) FROM `t_users` WHERE `u_created` BETWEEN ? AND ?');
        $query->execute([ $dateFrom, $end->format('Y-m-d') ]);
        $usersCount = $query->fetchColumn();

        $query = DB::db()->prepare('SELECT count(*) FROM `t_orders` WHERE `o_created` BETWEEN ? AND ?');
        $query->execute([ $dateFrom, $end->format('Y-m-d') ]);
        $ordersCount = $query->fetchColumn();

        $query = DB::db()->prepare('SELECT count(*) as total, sum(tr.o_total) as reveneue, sum(tm1.meta_value) as price, sum(tm2.meta_value) as fee FROM `t_orders` as tr LEFT JOIN `t_order_meta` as tm1 on tr.o_id = tm1.o_id AND tm1.meta_key = ? LEFT JOIN `t_order_meta` as tm2 on tr.o_id = tm2.o_id AND tm2.meta_key = ? WHERE tr.o_status = ? AND tr.o_created BETWEEN ? AND ?');
        $query->execute([ 'supplierPrice', 'paymentGatewayFee', 'delivered', $dateFrom, $end->format('Y-m-d')]);
        $result = $query->fetch() ?? [];

        $total = $result['total'] ?? 0;
        $price = $result['price'] ?? 0;
        $fee = $result['fee'] ?? 0;

        $summary = [];
        $summary['users'] = $usersCount;
        $summary['orders'] = $ordersCount;
        $summary['revenue'] = $result['reveneue'] ?? 0;
        $summary['profit'] = $summary['revenue'] - $price - $fee;
        $summary['avg_basket_size'] = $total ? round($summary['revenue']/$total, 2) : 0;

        //previous summary calculation
        $prevStartingDate = new \DateTime($dateFrom);
        $DateString = [];
        if( $difference->y ){
            $DateString[] = $difference->y . ' ' . ($difference->y > 1 ? 'years' : 'year');
        }
        if( $difference->m ){
            $DateString[] = $difference->m . ' ' . ($difference->m > 1 ? 'months' : 'month');
        }
        if( $difference->d ){
            $DateString[] = $difference->d . ' ' . ($difference->d > 1 ? 'days' : 'day');
        }
        $prevStartingDate->sub(\DateInterval::createFromDateString( implode( ' + ', $DateString ) ));

        $query = DB::db()->prepare('SELECT count(*) FROM `t_users` WHERE `u_created` BETWEEN ? AND ?');
        $query->execute([ $prevStartingDate->format('Y-m-d'), $dateFrom ]);
        $usersCount = $query->fetchColumn();

        $query = DB::db()->prepare('SELECT count(*) FROM `t_orders` WHERE `o_created` BETWEEN ? AND ?');
        $query->execute([ $prevStartingDate->format('Y-m-d'), $dateFrom ]);
        $ordersCount = $query->fetchColumn();

        $query = DB::db()->prepare('SELECT count(*) as total, sum(tr.o_total) as reveneue, sum(tm1.meta_value) as price, sum(tm2.meta_value) as fee FROM `t_orders` as tr LEFT JOIN `t_order_meta` as tm1 on tr.o_id = tm1.o_id AND tm1.meta_key = ? LEFT JOIN `t_order_meta` as tm2 on tr.o_id = tm2.o_id AND tm2.meta_key = ? WHERE tr.o_status = ? AND tr.o_created BETWEEN ? AND ?');
        $query->execute([ 'supplierPrice', 'paymentGatewayFee', 'delivered', $prevStartingDate->format('Y-m-d'), $dateFrom ]);
        $result = $query->fetch() ?? [];

        $total = $result['total'] ?? 0;
        $price = $result['price'] ?? 0;
        $fee = $result['fee'] ?? 0;

        $summary['prev_users'] = $usersCount;
        $summary['prev_orders'] = $ordersCount;
        $summary['prev_revenue'] = $result['reveneue'] ?? 0;
        $summary['prev_profit'] = $summary['prev_revenue'] - $price - $fee;
        $summary['prev_avg_basket_size'] = $total ? round($summary['prev_revenue']/$total, 2) : 0;

        return $summary;
    }

    public function popularMedicines($dateFrom, $dateTo, $limit){
        $begin = new \DateTime($dateFrom);
        $end = new \DateTime($dateTo);
        $end->add(\DateInterval::createFromDateString('1 day'));
        $difference = $begin->diff($end);
        $query = DB::db()->prepare('SELECT sum(t_o_medicines.m_qty) as total_qty, t_o_medicines.m_id, t_medicines.* FROM `t_o_medicines` JOIN `t_orders` ON t_orders.o_id = t_o_medicines.o_id JOIN `t_medicines` ON t_medicines.m_id = t_o_medicines.m_id WHERE t_orders.o_status = ? AND t_orders.o_created BETWEEN ? AND ? GROUP BY t_o_medicines.m_id ORDER BY total_qty desc limit ?');
        $query->execute([ 'delivered', $dateFrom, $end->format('Y-m-d'), $limit ]);
        $popular_medicines_quantity_wise = $query->fetchAll();

        $query = DB::db()->prepare('SELECT sum(t_o_medicines.m_qty * t_o_medicines.m_d_price) as total_revenue, t_o_medicines.m_id, t_medicines.* FROM `t_o_medicines` JOIN `t_orders` ON t_orders.o_id = t_o_medicines.o_id JOIN `t_medicines` ON t_medicines.m_id = t_o_medicines.m_id WHERE t_orders.o_status = ? AND t_orders.o_created BETWEEN ? AND ? GROUP BY t_o_medicines.m_id ORDER BY total_revenue desc limit ?');
        $query->execute([ 'delivered', $dateFrom, $end->format('Y-m-d'), $limit ]);
        $popular_medicines_revenue_wise = $query->fetchAll();

        $data = [
            'popular_medicines_quantity_wise' => $popular_medicines_quantity_wise,
            'popular_medicines_revenue_wise' => $popular_medicines_revenue_wise,
        ];

        return $data;
    }


}
