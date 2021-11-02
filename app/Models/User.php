<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Cache\Cache;
use Tymon\JWTAuth\Facades\JWTAuth;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'u_id_prev',
        'u_id',
        'u_name',
        'u_mobile',
        'u_token',
        'u_email',
        'fcm_token',
        'u_lat',
        'u_long',
        'u_role',
        'u_status',
        'u_cash',
        'u_p_cash',
        'u_otp',
        'u_otp_time',
        'u_referrer',
        'u_r_uid',
        'u_o_count'
    ];

    protected $primaryKey = 'u_id';

    const CREATED_AT = 'u_created';

    const UPDATED_AT = 'u_updated';

    protected $casts = [
        'u_otp_time' => 'datetime',
        'u_created' => 'datetime',
        'u_updated' => 'datetime'
    ];

    protected $table = 't_users';

    public function authToken(){
        return JWTAuth::fromUser($this);
    }
    public function userMeta(){
        return $this->hasMany(UserMeta::class, 'u_id', 'u_id');
    }

    public function orders(){
        return $this->hasMany(Order::class, 'u_id', 'u_id');
    }

    public function getMeta($key)
    {
        $meta = $this->userMeta()->where('meta_key', '=', $key)->first();
        return $meta ? $meta->meta_value : false;
    }

    public function setMeta($key, $value)
    {
        $userMeta =  $this->userMeta()->where('meta_key', '=', $key)->first();
        if($userMeta){
            $userMeta->update([
                'meta_value' => $value
            ]);
        }else{
            UserMeta::create([
                'u_id' => $this->u_id,
                'meta_key' => $key,
                'meta_value' => $value
            ]);
        }
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    private function capabilities( $role = '' ) {
        if( ! $role ) {
            $role = $this->u_role;
        }
        $roles = [
            'administrator' => [
                'role:administrator',
                'backendAccess',
                'orderCreate',
                'orderEdit',
                'orderDelete',
                //'offlineOrderCreate',
                'medicineCreate',
                'medicineEdit',
                'medicineDelete',
                'userCreate',
                'userEdit',
                'userDelete',
                'userChangeRole',
                'collectionsView',
                'ledgerView',
                'ledgerCreate',
                'ledgerEdit',
                'inventoryView',
                'inventoryEdit',
                'purchasesView',
            ],
            'operator' => [
                'role:operator',
                'backendAccess',
                'orderCreate',
                'orderEdit',
                'userEdit',
                'inventoryView',
                'purchasesView',
            ],
            'pharmacy' => [
                'role:pharmacy',
                'backendAccess',
                'medicineCreate',
                'medicineEdit',
                'orderCreate',
                'offlineOrderCreate',
                'orderEdit',
                'inventoryView',
                'purchasesView',
                'collectionsView',
            ],
            'investor' => [
                'role:investor',
                'backendAccess',
                'onlyGET', //only GET request allowed in backend
                'collectionsView',
                'ledgerView',
                'inventoryView',
                'purchasesView',
            ],
        ];
        if ( isset( $roles[ $role ] ) ) {
            return  $roles[ $role ];
        } else {
            return [];
        }
    }

    public function canDo( $cap ) {
        if(! $cap ){
            return false;
        }
        $caps = $this->capabilities();
        return in_array($cap, $caps);
    }
}
