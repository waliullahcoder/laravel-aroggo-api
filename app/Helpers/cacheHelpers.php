<?php

use App\Cache\Cache;
use App\Models\Bag;
use App\Models\Discount;
use App\Models\Location;
use \App\Models\Medicine;
use App\Models\Option;
use App\Models\Order;

if (!function_exists('getMedicine')) {
   function getMedicine( $id ) {
        if ( ! \is_numeric( $id ) ){
            return false;
        }
        $id = \intval( $id );
        if ( $id < 1 ){
            return false;
        }

        $cache = new Cache();
        if ( $medicine = $cache->get( $id, 'medicine' ) ){
            return $medicine;
        }

        if( $medicine = Medicine::find($id) ){
            $cache->add( $medicine->m_id, $medicine, 'medicine' );
            return $medicine;
        } else {
            return false;
        }
    }
}
if (!function_exists('getLocations')) {
    function getLocations(){
        $cache = new Cache();
        /*if ( $cache_data = $cache->get('locations' ) ){
            return $cache_data;
        }*/
        $locations = Location::orderBy('l_division', 'asc')->orderBy('l_district', 'asc')->orderBy('l_area', 'asc')->get();
        $data = [];
        foreach ($locations as $l){
            $data[ $l['l_division'] ][ $l['l_district'] ][ $l['l_area'] ] = [
                'l_de_id' => $l['l_de_id' ],
                'l_postcode' => $l['l_postcode' ],
                'l_id' => $l['l_id' ],
                'l_ph_id' => $l['l_ph_id' ],
                'l_zone' => $l['l_zone' ],
            ];
        }
        $cache->set( 'locations', $data );
        return $data;
    }
}
if (!function_exists('getOrder')) {
    function getOrder( $id ) {
        if ( ! \is_numeric( $id ) ){
            return false;
        }
        $id = \intval( $id );
        if ( $id < 1 ){
            return false;
        }
        $cache = new Cache();
        if ( $order = $cache->get( $id, 'order' ) ){
            return $order;
        }
        if( $order = Order::find($id) ){
            $cache->add( $order->o_id, $order, 'order' );
            return $order;
        } else {
            return false;
        }

    }
}
if (!function_exists('getDiscount')) {
    function getDiscount( $d_code ) {
        if ( ! $d_code ){
            return false;
        }

        $cache = new Cache();
        if ( $discount = $cache->get( $d_code, 'discount' ) ){
            return $discount;
        }

        if( $discount = Discount::where('d_code', $d_code)->first() ){
            $cache->add(  $discount->d_code, $discount, 'discount' );
            return $discount;
        } else {
            return false;
        }

    }
}
if (!function_exists('getBag')) {
    function getBag($id)
    {
        if (!$id || !is_numeric($id)) {
            return false;
        }

        $cache = new Cache();
        if ($bag = $cache->get($id, 'bag')) {
            return $bag;
        }

        if ($bag = Bag::where('b_id', $id)->first()) {
            $cache->set($bag->b_id, $bag, 'bag');
            return $bag;
        } else {
            return false;
        }
    }
}
if (!function_exists('getLocation')) {
    function getLocation($id)
    {
        if (!$id || !is_numeric($id)) {
            return false;
        }

        $cache = new Cache();
        if ($location = $cache->get($id, 'location')) {
            return $location;
        }

        if ($location = Location::find($id)) {
            $cache->set($location->l_id, $location, 'location');
            return $location;
        } else {
            return false;
        }
    }
}
if (!function_exists('getValueByLocationId')) {
    function getValueByLocationId($l_id, $value): bool
    {
        if( !$l_id || !$value){
            return false;
        }
        $location = getLocation( $l_id );
        if ( $location ){
            switch ( $value ) {
                case 'district':
                    return $location->l_district;
                    break;
                case 'zone':
                    return $location->l_zone;
                    break;
            }
        }
        return false;
    }
}
if (!function_exists('getOption')) {
    function getOption($key)
    {
        $found = false;
        $value =(new Cache())->get( $key, 'option', false, $found );
        if ( $found ){
            return $value;
        }
        if( $option = Option::where('option_name', $key)->first() ){
            $value = maybeJsonDecode( $option->option_value );
            (new Cache())->set( $key, $value, 'option' );
            return $value;
        } else {
            (new Cache())->set( $key, false, 'option' );
            return false;
        }
    }
}