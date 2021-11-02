<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Log extends Model
{
    use HasFactory;

    protected $fillable = [
        'log_id',
        'u_id',
        'log_ip',
        'log_ua',
        'log_created',
        'log_http_method',
        'log_uri',
        'log_get',
        'log_post',
        'log_response_code',
        'log_response',

    ];

    protected $casts = [
        'log_response' => 'json',
    ];

    protected static function boot() {
        parent::boot();

        static::creating(function ($log) {
            $log->u_id = Auth::id();
            $log->log_ip = $_SERVER['REMOTE_ADDR'];
            $log->log_ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ?  filter_var( $_SERVER['HTTP_USER_AGENT'], FILTER_SANITIZE_STRING ) : '';
            $log->log_http_method = $_SERVER['REQUEST_METHOD'];
            $log->log_get = json_encode(is_array( $_GET ) ? $_GET : []);
            $log->log_post = json_encode(is_array( $_POST ) ? $_POST : []);
            $log->log_created = Carbon::now();
            $log->log_uri = request()->getRequestUri();
            if( 'POST' !== $log->log_http_method && strpos( $log->log_uri, '/api/admin/' ) === 0 && '/api/admin/v1/laterMedicines/' != $log->log_uri && fnmatch( '/api/admin/v1/*/', $log->log_uri, FNM_PATHNAME ) ){
                return false;
            }
        });
    }

    protected $primaryKey = 'log_id';

    public $timestamps = false;
    
    protected $table = "t_logs";

    protected $with = ['user'];

    public function user()
    {
        return $this->belongsTo(User::class,'u_id');
    }

    public function insert( $data = array() ){
        if( $this->exist() ){
            return false;
        }
        if( is_array( $data ) && $data ){
            foreach( $data as $k => $v ){
                if( property_exists( $this, $k ) ) {
                    $this->set( $k, $v );
                }
            }
        }

        if( 'POST' === $this->log_http_method ) {

        } elseif( strpos( $this->log_uri, '/admin/' ) === 0 && '/admin/v1/laterMedicines/' != $this->log_uri && fnmatch( '/admin/v1/*/', $this->log_uri, FNM_PATHNAME ) ){
            return false;
        }
        $data_array = $this->toArray();
        unset( $data_array['log_id'] );

        //  $this->log_id = DB::instance()->insert( 't_logs', $data_array );

        if( $this->log_id ){
            //No need to cache, Its the log
            //Cache::instance()->add( $this->log_id, $this, 'log' );
        }
        return $this->log_id;
    }
}
