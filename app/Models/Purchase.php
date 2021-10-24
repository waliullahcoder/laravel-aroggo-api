<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'pu_id',
        'pu_inv_id',
        'pu_ph_id',
        'pu_m_id',
        'pu_price',
        'pu_qty',
        'm_unit',
        'pu_status',
        'pu_created',
        'm_expiry',
        'm_batch',
    ];

    protected $primaryKey = 'pu_id';

    public $timestamps = false;
    
    protected $table = "t_purchases";


}
