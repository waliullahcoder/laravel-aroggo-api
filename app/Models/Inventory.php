<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Cache\Cache;
class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'i_id',
        'i_ph_id',
        'i_m_id',
        'i_price',
        'i_qty',

    ];

    protected $primaryKey = 'i_id';

    public $timestamps = false;

    protected $table = "t_inventory";

    public static function qtyUpdateByPhMid( $ph_id, $m_id, $qty ) {
        $inv = static::getBy( 'ph_m_id', $ph_id, $m_id );
        if( $inv ){
            return $inv->qtyUpdate( $qty );
        }
        return false;
    }
    public static function getInventory( $id ) {
        return static::getBy( 'i_id', $id );
    }

    public static function getByPhMid( $ph_id, $m_id ) {
        return static::getBy( 'ph_m_id', $ph_id, $m_id );
    }

    public static function getBy( $field, $iid_or_phid, $m_id = 0 ) {
        $cache= new Cache();
        $value = $iid_or_phid;

    	if ( 'i_id' == $field ) {
    		// Make sure the value is numeric to avoid casting objects, for example,
    		// to int 1.
    		if ( ! is_numeric( $value ) )
    			return false;
    		$value = intval( $value );
    		if ( $value < 1 )
    			return false;
    	} else {
    		$value = trim( $value );
    	}

    	if ( !$value )
    		return false;

    	switch ( $field ) {
    		case 'i_id':
    			$id = $value;
                break;
            case 'ph_m_id':

    			$id = $cache->get( "{$value}_{$m_id}", 'ph_m_id_to_iid' );
    			break;
    		default:
    			return false;
    	}

    	if ( false !== $id ) {
    		if ( $inventory = $cache->get( $id, 'inventory' ) ){
                return $inventory;
            }
        }
        if ( 'i_id' == $field ) {
            $inventory =Inventory::where('i_id', $field)->first();
        } else {
            $inventory =Inventory::where('i_ph_id', $value)->where('i_m_id', $m_id)->first();
        }

        if( $inventory){
            $inventory->updateCache();
            return $inventory;
        } else {
            return false;
        }
    }

}
