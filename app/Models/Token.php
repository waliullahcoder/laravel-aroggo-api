<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    use HasFactory;

    protected $fillable = [
        't_id',
        't_uid',
        't_created',
        't_token',
        't_ip',
    ];

    protected $primaryKey = 't_id';

    public $timestamps = false;

    protected $table = "t_tokens";

    // protected $with = ['user'];

    // public function user()
    // {
    //     return $this->belongsTO(User::class,'u_id');
    // }
}
