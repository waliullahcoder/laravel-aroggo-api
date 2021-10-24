<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Cache\Cache;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'l_id',
        'l_division',
        'l_district',
        'l_area',
        'l_postcode',
        'l_de_id',
        'l_ph_id',
        'l_zone',
        'l_lat',
        'l_lon',
    ];

    protected $primaryKey = 'l_id';

    public $timestamps = false;

    protected $table = "t_locations";
    
}
