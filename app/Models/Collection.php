<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Cache\Cache;

class Collection extends Model
{
    use HasFactory;

    protected $fillable = [
        'co_id',
        'co_fid',
        'co_tid',
        'o_ids',
        'co_amount',
        'co_s_amount',
        'co_status',
        'co_created',

    ];

    protected $primaryKey = 'co_id';

    public $timestamps = false;

    protected $table = "t_collections";
}
