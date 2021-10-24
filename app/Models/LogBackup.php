<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogBackup extends Model
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

    protected $primaryKey = 'log_id';

    public $timestamps = false;
    
    protected $table = "t_logs_backup";

    
}
