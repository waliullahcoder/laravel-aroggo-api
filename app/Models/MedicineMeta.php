<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineMeta extends Model
{
    use HasFactory;

    protected $primaryKey = 'meta_id';

    protected $fillable = [
        'm_id',
        'meta_id',
        'meta_key',
        'meta_value',
    ];

    protected $table = 't_medicine_meta';

    public $timestamps = false;


}
