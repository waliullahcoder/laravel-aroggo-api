<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Cache\Cache;
class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'o_id',
        'u_id',
        'u_name',
        'u_mobile',
        'o_subtotal',
        'o_addition',
        'o_deduction',
        'o_total',
        'o_created',
        'o_updated',
        'o_delivered',
        'o_status',
        'o_i_status',
        'o_is_status',
        'o_address',
        'o_lat',
        'o_long',
        'o_gps_address',
        'o_payment_method',
        'o_de_id',
        'o_ph_id',
        'o_priority',
        'o_l_id',
    ];

    protected $primaryKey = 'o_id';

    public $timestamps = false;

    protected $table = "t_orders";

    protected $with = ['user'];

    public function user()
    {
        return $this->belongsTo(User::class,'u_id', 'u_id');
    }

    public function getMeta( $key ) {
        return Meta::get( 'order', $this->o_id, $key );
    }

    public function city(){
        $city = '';
        $s_address = $this->getMeta('s_address')?:[];
        if( is_array($s_address) && ! empty( $s_address['district'] ) ){
            $city = $s_address['district'];
        }
        return $city;
    }

    public function timeline(){
        $timeline = $this->getMeta( 'timeline' );
        if( ! is_array( $timeline ) ){
            $timeline = [];
        }
        if( $timeline ){
            $default = [
                'placed' => [
                    'time' => $this->o_created,
                    'title' => 'Order Placed',
                    'body' => 'Your order is successfully placed to Arogga. Order id #' . $this->o_id,
                    'done' => true,
                ],
                'processing' => [
                    'time' => $this->o_created,
                    'title' => 'Processing',
                    'body' => 'We have received your order, our pharmacist will check and confirm shortly.',
                    'done' => true,
                ],
                'confirmed' => [
                    'title' => 'Confirmed',
                    'body' => 'We have confirmed your order.',
                ],
                'packing' => [
                    'title' => 'Packing',
                    'body' => 'We are currently packing your order.',
                ],
                'packed' => [
                    'title' => 'Packed',
                    'body' => 'Your order is packed now.',
                ],
                'payment' => [
                    'title' => 'Payment',
                    'body' => '',
                ],
                'delivering' => [
                    'title' => 'Delivering',
                    'body' => $this->city() == 'Dhaka City' ? 'Deliveryman has picked up your order for delivering.' : 'Our delivery partner has picked up your order for delivering. it generally takes 1-5 days to deliver outside dhaka.',
                ],
                'delivered' => [
                    'title' => 'Delivered',
                    'body' => 'You have received your order.',
                ],
            ];

            if( 'cancelled' == $this->o_status ){
                $default['cancelled'] = [
                    'time' => $this->o_updated,
                    'title' => 'Cancelled',
                    'body' => (string)$this->getMeta('o_note'),
                    'done' => true,
                ];
            }
            if( 'delivering' == $this->o_status ){
                if( $this->city() == 'Dhaka City' ){
                    $deliveryman = User::find( $this->o_de_id );
                    $default['delivering']['body'] = sprintf('Deliveryman (%s: %s) has picked up your order for delivering.', $deliveryman ? $deliveryman->u_name : '', $deliveryman ? $deliveryman->u_mobile : '' );
                } elseif( $redx_tracking_id = $this->getMeta( 'redx_tracking_id' ) ){
                    $default['delivering']['body'] = 'Our delivery partner REDX has picked up your order for delivering.';
                    $default['delivering']['link'] = [
                        'src' => 'https://redx.com.bd/track-global-parcel/?trackingId=' . $redx_tracking_id,
                        'title' => 'Track Order'
                    ];
                }
            }
            $merged = [];
            foreach ( $default as $key => $value ) {
                if( isset( $timeline[ $key ] ) && is_array( $timeline[ $key ] ) ){
                    $merged[ $key ] = array_merge( $value, $timeline[ $key ] );
                } else {
                    $merged[ $key ] = $value;
                }
            }
            $timeline = $merged;
        }
        return $timeline;
    }

    public function validateToken( $token ){
        $tokenDecoded = jwtDecode( $token );
        if( $tokenDecoded && !empty( $tokenDecoded['o_id'] ) && $this->o_id === $tokenDecoded['o_id']  ){
            return true;
        }
        return false;
    }

    public function signedUrl( $prefix, $suffix = '', $time = '' ){
        if( ! $time ){
            $time = time() + 60 * 60;
        }
        $url = sprintf( url('/api/') . '%s/%d/%s%s', $prefix, $this->o_id, jwtEncode( ['o_id' => $this->o_id, 'exp' => $time ] ), $suffix );

        return $url;
    }

    function updateCache() {
        if( $this->o_id ) {
            (new Cache())->set( $this->o_id, $this, 'order' );
        }
    }
}
