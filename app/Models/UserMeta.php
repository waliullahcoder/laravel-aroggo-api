<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMeta extends Model
{
    use HasFactory;

    protected $primaryKey = 'meta_id';

    protected $fillable = [
        'u_id',
        'meta_id',
        'meta_key',
        'meta_value',
    ];

    protected $table = 't_user_meta';

    public $timestamps = false;
}
