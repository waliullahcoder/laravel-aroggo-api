<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class History extends Model
{
    use HasFactory;

    protected $fillable = [
        'h_id',
        'h_obj',
        'obj_id',
        'u_id',
        'h_created',
        'h_action',
        'h_from',
        'h_to',

    ];

    protected $primaryKey = 'h_id';

    public $timestamps = false;

    protected $table = "t_histories"; 

    protected $with = ['user'];

    public function user()
    {
        return $this->belongsTO(User::class,'u_id');
    }
}
