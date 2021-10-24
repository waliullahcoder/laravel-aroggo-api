<?php

namespace App\Models;

use App\Cache\Cache;
use Illuminate\Support\Facades\DB;

class CacheUpdate
{
    private static $instance;

    private $queue = array();

    public static function instance() {
        if ( ! self::$instance instanceof self ) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function add_to_queue( $ids, $type ){
        $ids = (array) $ids;
        $type = (string) $type;

        if( ! $ids || ! is_array( $ids ) || ! $type ){
            return false;
        }
        if( ! isset( $this->queue[ $type ] ) || ! is_array( $this->queue[ $type ] ) ){
            $this->queue[ $type ] = array();
        }
        $this->queue[ $type ] = array_merge( $this->queue[ $type ], $ids );
    }

    public function update_cache( $provided_ids, $type ){
        $provided_ids = (array) $provided_ids;
        $type = (string) $type;

        if( ! is_array( $provided_ids ) || ! $type ){
            return array();
        }
        if( ! isset( $this->queue[ $type ] ) || ! is_array( $this->queue[ $type ] ) ){
            $this->queue[ $type ] = array();
        }
        $ids = array_merge( $this->queue[ $type ], $provided_ids );
        $this->queue[ $type ] = array();

        $ids = array_unique( array_filter( array_map( 'intval', $ids ) ) );
        if( ! $ids ){
            return array();
        }

        $callback = array( $this, "update_cache_{$type}" );

        return $callback( $ids, $provided_ids );
    }

    function update_main_cache( $ids, $provided_ids, $cache_group, $db_table, $id_column, $class ){
        $need_query = array();
        $return = array();

        foreach( $ids as $id ){
            $cached = (new Cache())->get( $id, $cache_group );
            if( false === $cached ){
                $need_query[] = $id;
            } elseif( in_array( $id, $provided_ids ) ) {
                $return[ $id ] = $cached;
            }
        }

        if( $need_query ){
            $data = array();

            $dbDatas = DB::table($db_table)->whereIn($id_column, $provided_ids)->get();

            foreach ($dbDatas as $returnClass) {
                $returnClass->updateCache();
                $data[ $returnClass->$id_column ] = $returnClass;
            }

            foreach( $need_query as $id ){
                if( ! isset( $data[ $id ] ) ){
                    $data[ $id ] = new \stdClass();
                }
            }

            foreach( $data as $id => $returnClass ){
                if( in_array( $id, $provided_ids ) ){
                    $return[ $id ] = $returnClass;
                }
            }
        }
        return $return;
    }

    function update_meta_cache( $ids, $provided_ids, $cache_group, $db_table, $id_column ) {
        $need_query = [];
        $return = [];

        foreach( $ids as $id ){
            $cached = (new Cache())->get( $id, $cache_group );
            if( false === $cached ){
                $need_query[] = $id;
            } elseif( in_array( $id, $provided_ids ) ) {
                $return[ $id ] = $cached;
            }
        }

        if( $need_query ){
            $metas = array();

            $dbMetas = DB::table($db_table)->whereIn($id_column, $provided_ids)->get();

            foreach ($dbMetas as $meta) {
                $meta = (array) $meta;
                $metas[ (int)$meta[ $id_column ] ][ $meta['meta_key' ] ] = maybeJsonDecode( $meta['meta_value'] );
            }
            foreach( $need_query as $id ){
                if( ! isset( $metas[ $id ] ) ){
                    $metas[ $id ] = array();
                }
            }

            foreach( $metas as $id => $meta ){
                (new Cache())->add( $id, $meta, $cache_group );

                if( in_array( $id, $provided_ids ) ){
                    $return[ $id ] = $meta;
                }
            }
        }
        return $return;
    }

    function update_cache_user( $ids, $provided_ids ){
        return $this->update_main_cache( $ids, $provided_ids, 'user', 't_users', 'u_id', User::class );
    }

    function update_cache_medicine( $ids, $provided_ids ){
        return $this->update_main_cache( $ids, $provided_ids, 'medicine', 't_medicines', 'm_id', Medicine::class );
    }

    function update_cache_order( $ids, $provided_ids ){
        return $this->update_main_cache( $ids, $provided_ids, 'order', 't_orders', 'o_id', Order::class );
    }

    function update_cache_order_meta( $ids, $provided_ids ){
        return $this->update_meta_cache( $ids, $provided_ids, 'order_meta', 't_order_meta', 'o_id' );
    }

    function update_cache_user_meta( $ids, $provided_ids ){
        return $this->update_meta_cache( $ids, $provided_ids, 'user_meta', 't_user_meta', 'u_id' );
    }

    function update_cache_medicine_meta( $ids, $provided_ids ){
        return $this->update_meta_cache( $ids, $provided_ids, 'medicine_meta', 't_medicine_meta', 'm_id' );
    }
    function update_cache_inventory_meta( $ids, $provided_ids ){
        return $this->update_meta_cache( $ids, $provided_ids, 'inventory_meta', 't_inventory_meta', 'i_id' );
    }
}