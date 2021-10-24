<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GenericV2 extends Model
{
    use HasFactory;

    protected $fillable = [
        'g_id',
        'g_name',
        'g_overview',
        'g_quick_tips',
        'g_safety_advices',
        'g_question_answer',

    ];

    protected $primaryKey = 'g_id';

    public $timestamps = false;

    protected $table = "t_generics_v2";
}
