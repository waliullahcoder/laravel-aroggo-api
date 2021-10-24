<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OMedicine extends Model
{
    use HasFactory;

    protected $fillable = [
        'om_id',
        'o_id',
        'm_id',
        'sku',
        'm_qty',
        'm_unit',
        'm_price',
        'm_d_price',
        's_price',
        'om_status',
    ];

    protected $primaryKey = 'om_id';

    public $timestamps = false;
    
    protected $table = "t_o_medicines";


}
