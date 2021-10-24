<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Cache\Cache;
class GenericV1 extends Model
{
    use HasFactory;
    protected $fillable = [
        'g_id',
        'g_name',
        'precaution',
        'indication',
        'contra_indication',
        'side_effect',
        'mode_of_action',
        'interaction',
        'pregnancy_category_note',
        'adult_dose',
        'child_dose',
        'renal_dose',
        'administration'

    ];

    protected $primaryKey = 'g_id';

    public $timestamps = false;

    protected $table = "t_generics";

    public static function getName( $id ) {
        $generic = static::getGeneric( $id );
        if( $generic ) {
            return $generic->g_name;
        }
        return '';
    }
    public static function getGeneric( $id ) {
        if ( ! \is_numeric( $id ) ){
            return false;
        }
        $id = \intval( $id );
        if ( $id < 1 ){
            return false;
        }
        $cache=new Cache();

        if ( $generic = $cache->get( $id, 'generic' ) ){
            return $generic;
        }
        $generic= GenericV1::where('g_id', $id)->first();
        if( $generic){     
            $cache->add( $generic->g_id, $generic, 'generic' );
            return $generic;
        } else {
            return false;
        }
    }
}
