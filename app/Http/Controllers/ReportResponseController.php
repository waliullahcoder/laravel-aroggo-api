<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Cache\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Bag;
use App\Models\Company;
use App\Models\Option;
use App\Models\GenericV2;
use App\Models\Medicine;
use App\Models\Token;
use App\Models\Discount;
use App\Models\Order;
use App\Models\OMedicine;
use App\Models\Log;
use App\Models\CacheUpdate;
use App\Models\GenericV1;
use App\Models\Collection;
class ReportResponseController extends Controller
{


    public function report(Request $request){
        $dateFrom = $request->dateFrom ? $this->validateDate($request->dateFrom) : '';
        $dateTo = $request->dateTo ? $this->validateDate($request->dateTo) : '';
        $limit = $request->limit && $request->limit <= 1000 ? $request->limit : 10;

        if( ! $dateFrom || ! $dateTo ){
            return response()->json([
                'status'=>'error',
                'message'=>'Invalid date'
            ]);
        }
        $data = [
            'orders' => $this->orders($dateFrom, $dateTo),
            'deOrders' => $this->deliveryOrders($dateFrom, $dateTo),
            'users' => $this->users($dateFrom, $dateTo),
            'summary' => $this->summary($dateFrom, $dateTo),
            'popularMedicines' => $this->popularMedicines($dateFrom, $dateTo, $limit),
        ];

        return response()->json([
            'status'=>'success',
            'data'=>$data
        ]);

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
        $orders= DB::table('t_orders') ->whereBetween('o_created', [$dateFrom, $end->format('Y-m-d')])
        ->where('o_created', $dateFormat)
        ->where('o_status', '=', 'pending')
        ->get();

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
        $orders= DB::table('t_orders') ->whereBetween('o_created', [$dateFrom, $end->format('Y-m-d')])
        ->where('o_created', $dateFormat)
        ->where('o_status', '=', 'delivered')
        ->get();

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

        $registeredUsers= DB::table('t_users') ->whereBetween('u_created', [$dateFrom, $end->format('Y-m-d')])
        ->where('o_created', $dateFormat)
        ->get();
        
        $orderedUsers= DB::table('t_users') ->whereBetween('u_created', [$dateFrom, $end->format('Y-m-d')])
        ->where('u_o_count', 0)
        ->where('o_created', $dateFormat)
        ->get();

        $repeatedUsers= DB::table('t_users') ->whereBetween('u_created', [$dateFrom, $end->format('Y-m-d')])
        ->where('u_o_count', '>', 1)
        ->where('o_created', $dateFormat)
        ->get();

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


        $usersCount= DB::table('t_users') ->whereBetween('u_created', [$dateFrom, $end->format('Y-m-d')])
        ->get()->count();

        $ordersCount= DB::table('t_orders') ->whereBetween('o_created', [$dateFrom, $end->format('Y-m-d')])
        ->get()->count();

        $result = DB::table('t_orders')
        ->whereBetween('t_orders.o_created', [$dateFrom, $end->format('Y-m-d')])
        ->leftJoin('t_order_meta', 't_orders.o_id', '=', 't_order_meta.o_id')
        ->where('meta_key', '=', 'supplierPrice')
        ->where('o_status', '=', 'delivered')
        ->get();

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

        $popular_medicines_quantity_wise = DB::table('t_o_medicines')
        ->whereBetween('t_orders.o_created', [$dateFrom, $end->format('Y-m-d')])
        ->leftJoin('t_orders', 't_orders.o_id', '=', 't_o_medicines.o_id')
        ->leftJoin('t_medicines', 't_medicines.m_id', '=', 't_o_medicines.m_id')
        ->where('t_orders.o_status', '=', 'delivered')
        ->where('o_status', '=', 'delivered')
        ->orderBy('total_qty', 'desc')
        ->limit($limit)
        ->get()
        ->sum('t_o_medicines.m_qty');

        $popular_medicines_revenue_wise = DB::table('t_o_medicines')
        ->whereBetween('t_orders.o_created', [$dateFrom, $end->format('Y-m-d')])
        ->leftJoin('t_orders', 't_orders.o_id', '=', 't_o_medicines.o_id')
        ->leftJoin('t_medicines', 't_medicines.m_id', '=', 't_o_medicines.m_id')
        ->where('t_orders.o_status', '=', 'delivered')
        ->where('o_status', '=', 'delivered')
        ->orderBy('total_qty', 'desc')
        ->limit($limit)
        ->get()
        ->sum('t_o_medicines.m_qty * t_o_medicines.m_d_price');

        $data = [
            'popular_medicines_quantity_wise' => $popular_medicines_quantity_wise,
            'popular_medicines_revenue_wise' => $popular_medicines_revenue_wise,
        ];

        return $data;
    }









}
