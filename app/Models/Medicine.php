<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Cache\Cache;
use phpDocumentor\Reflection\Types\Boolean;

class Medicine extends Model
{
    use HasFactory;

    protected $fillable = [
        'm_id',
        'm_name',
        'm_g_id',
        'm_strength',
        'm_unit',
        'm_price',
        'm_d_price',
        'm_c_id',
        'm_form',
        'm_rob',
        'm_status',
        'm_category',
        'm_comment',
        'm_i_comment',
        'm_u_id',
        'm_cat_id',
        'm_min',
        'm_max',
    ];

    protected $primaryKey = 'm_id';

    public $timestamps = false;
    
    protected $table = "t_medicines";

    protected $casts = [
        'm_rob' => 'boolean',
        'm_cat_id' => 'integer',
    ];

    public function medicineMeta(){
        return $this->hasMany(MedicineMeta::class, 'm_id', 'm_id');
    }

    public function genericV1(){
        return $this->belongsTo(GenericV1::class, 'm_g_id','g_id');
    }
    public function genericV2(){
        return $this->belongsTo(GenericV2::class, 'm_g_id', 'g_id');
    }
    public function company(){
        return $this->belongsTo(Company::class, 'm_c_id', 'c_id');
    }

    public function getMeta($key)
    {
        return $this->medicineMeta()->where('meta_key', '=', $key)->first()->meta_value ?? false;
    }

    public function setMeta($key, $value)
    {
        $medicineMeta =  $this->medicineMeta()->where('meta_key', '=', $key)->first();
        if($medicineMeta){
            $medicineMeta->update([
                'meta_value' => $value
            ]);
        }else{
            MedicineMeta::create([
                'm_id' => $this->m_id,
                'meta_key' => $key,
                'meta_value' => $value
            ]);
        }
    }
    public function insertMetas( $keyValues ) {
        $data = [];
        foreach ( $keyValues as $key => $value ) {
            if( ! $key || ! \is_string( $key ) ){
                continue;
            }
            $data[] = [
                'meta_key' => $key,
                'meta_value' => maybeJsonEncode( $value ),
            ];
        }
        return $this->medicineMeta()->createMany($data);
    }

    public function getCount( $key ) {
        //We are using Memcached, So use it for count performance
        $found = false;
        $cache = new Cache();
        $count = (int)$cache->get( $this->m_id, "medicineCount{$key}", false, $found );
        if( ! $found ){
            $count = (int)$this->getMeta( "medicineCount{$key}" );
            $cache->set( $this->m_id, $count, "medicineCount{$key}" );
        }
        return $count;
    }

    public function incrCount( $key, $offset = 1, $update_count = 10 ) {
        $count = $this->getCount( $key );
        $cache = new Cache();
        $count = $cache->incr( $this->m_id, $offset, "medicineCount{$key}" );
        if( $count && ( $count % $update_count == 0 ) ){
            //Update every "$update_count"th count
            $this->setMeta( "medicineCount{$key}", $count );

//            \OA\Search\Medicine::init()->update( $this->m_id, ["medicineCount{$key}" => $count] );
        }
        return $count;
    }

    public function isCold(){
        $m_g_id = $this->m_g_id;
        return isCold( $m_g_id );
    }

    public function getMRxReqAttribute($value){
        return (11 == $this->m_cat_id);
    }

    public function getMMinAttribute(){
        return 1;
        //m_min
        // m_more_something
        // getMMoreSomethingAttribue()
    }

    public function getMMaxAttribute(){
        return 200;
    }

    public function getMDescriptionAttribute(){
        $generic = $this->genericV1()->first();
        if(!$generic)
            return false;
        $value = $generic->toArray();
        unset( $value['g_id'], $value['g_name'] );
        return $value;
    }
    public function getMDescriptionV2Attribute(){ //m_description_v2
        $generic = $this->genericV2()->select(['g_overview','g_quick_tips','g_safety_advices'])->first();
        if(!$generic)
            return false;
        $value = $generic->toArray();
        if( $value ){
            foreach ( $value as &$v ) {
                $v = maybeJsonDecode($v);
            }
            unset( $v );
        } else {
            $value = [];
        }
        return $value;
    }
    public function getMDescriptionDimsAttribute(){
        $generic = $this->genericV1()->first();
        if(!$generic)
            return false;
        $row = $generic->toArray();
        $val = [];
        if( $row ){
            if( !empty( $row['indication'] ) ){
                $val[] = [
                    'title' => 'Indication',
                    'content' => $row['indication'],
                ];
            }
            if( !empty( $row['administration'] ) ){
                $val[] = [
                    'title' => 'Administration',
                    'content' => $row['administration'],
                ];
            }
            if( !empty( $row['adult_dose'] ) ){
                $val[] = [
                    'title' => 'Adult Dose',
                    'content' => $row['adult_dose'],
                ];
            }
            if( !empty( $row['child_dose'] ) ){
                $val[] = [
                    'title' => 'Child Dose',
                    'content' => $row['child_dose'],
                ];
            }
            if( !empty( $row['renal_dose'] ) ){
                $val[] = [
                    'title' => 'Renal Dose',
                    'content' => $row['renal_dose'],
                ];
            }
            if( !empty( $row['contra_indication'] ) ){
                $val[] = [
                    'title' => 'Contraindication',
                    'content' => $row['contra_indication'],
                ];
            }
            if( !empty( $row['mode_of_action'] ) ){
                $val[] = [
                    'title' => 'Mode of Action',
                    'content' => $row['mode_of_action'],
                ];
            }
            if( !empty( $row['precaution'] ) ){
                $val[] = [
                    'title' => 'Precaution',
                    'content' => $row['precaution'],
                ];
            }
            if( !empty( $row['side_effect'] ) ){
                $val[] = [
                    'title' => 'Side Effect',
                    'content' => $row['side_effect'],
                ];
            }
            if( !empty( $row['pregnancy_category_note'] ) ){
                $val[] = [
                    'title' => 'Pregnancy Category Note',
                    'content' => $row['pregnancy_category_note'],
                ];
            }
            if( !empty( $row['interaction'] ) ){
                $val[] = [
                    'title' => 'Interaction',
                    'content' => $row['interaction'],
                ];
            }
        }
        return $val;
    }

    public function getMPicUrlAttribute(){
        return $value = getPicUrl( maybeJsonDecode($this->getMeta( 'images' )) );
    }
    public function getMGenericAttribute(){
        return $this->genericV2->g_name ?? '';
    }
    public function getMCompanyAttribute(){
        return $this->company->c_name ?? '';
    }
}
