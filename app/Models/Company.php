<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Cache\Cache;
class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'c_id',
        'c_name',
    ];

    protected $primaryKey = 'c_id';

    public $timestamps = false;

    protected $table = "t_companies";

    public static function getName( $id ) {
        $company = static::getCompany( $id );
        if( $company ) {
            return $company->c_name;
        }
        return '';
    }

    public static function getCompany( $id ) {
        if ( ! \is_numeric( $id ) ){
            return false;
        }
        $id = \intval( $id );
        if ( $id < 1 ){
            return false;
        }
        $cache= new Cache();

        if ( $company = $cache->get( $id, 'company' ) ){
            return $company;
        }
        $company=Company::where('c_id', $id)->get();
        if( $company ){     
            $cache->add( $company->c_id, $company, 'company' );
            return $company;
        } else {
            return false;
        }
    }
    
}
