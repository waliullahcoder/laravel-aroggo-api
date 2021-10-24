<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Cache\Cache;

class Discount extends Model
{
    use HasFactory;

    protected $fillable = [
        'd_id',
        'd_code',
        'd_type',
        'd_amount',
        'd_max',
        'd_max_use',
        'd_status',
        'd_expiry',
        'u_id',

    ];

    protected $primaryKey = 'd_id';

    public $timestamps = false;

    protected $table = "t_discounts";

    protected $with = ['user'];

    public function user()
    {
        return $this->belongsTO(User::class,'u_id');
    }

    public function canUserUse( $u_id ) {
        $u_id = (int) $u_id;
        if ( ! $u_id || 'active' !== $this->d_status ) {
            return false;
        }
        $user = User::find($u_id);
        //Local time
        if ( $this->d_expiry !== '0000-00-00 00:00:00' && Carbon::now() > Carbon::parse( $this->d_expiry )  ) {
            $this->d_status = 'expired';
            $this->update();
            return false;
        }
        $allow = 0;
        if(env( 'ADMIN' )) {
            $allow++;
        }
        if( \in_array( $this->d_type, [ 'firstPercent', 'firstFixed' ] ) ) {
            $orders = $user->orders()->take(2)->get();
        } else {
            $orders = Order::join('t_order_meta', 't_order_meta.o_id', '=', 't_orders.o_id')->where([['t_orders.u_id', $u_id], ['t_order_meta.meta_key', 'd_code'], ['t_order_meta.meta_value', $this->d_code] ])->take(2)->get();
        }

        if ( count( $orders ) > $allow ) {
            return false;
        }
        return true;
    }


    public static function getDiscount( $d_code ) {
        if ( ! $d_code ){
            return false;
        }
         $cache= new Cache();
        if ( $discount = $cache->get( $d_code, 'discount' ) ){
            return $discount;
        }
        $discount= Discount::where('d_code', $d_code)->first();
        if( $discount){     
            $cache->add( $discount->d_code, $discount, 'discount' );
            return $discount;
        } else {
            return false;
        }
    }



}
