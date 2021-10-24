<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Cache\Cache;

class Ledger extends Model
{
    use HasFactory;

    protected $fillable = [
        'l_id',
        'l_uid',
        'l_created',
        'l_reason',
        'l_type',
        'l_amount',
    ];

    protected $primaryKey = 'l_id';

    public $timestamps = false;

    protected $table = "t_ledger";
    
}
