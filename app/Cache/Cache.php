<?php
namespace App\Cache;

use Illuminate\Support\Facades\Cache as CacheParent;

class Cache{

    CONST CACHE_KEY = "AROGGO";
    /**
     * The amount of times the cache data was already stored in the cache.
     *
     * @since 2.5.0
     * @access private
     * @var int
     */
    private $cache_hits = 0;

    /**
     * Amount of times the cache did not have the request in cache
     *
     * @var int
     * @access public
     * @since 2.0.0
     */
    public $cache_misses = 0;

    /**
     * Retrieves the cache contents, if it exist
     *
     * The contents will be first attempted to be retrieved by searching by the
     * key in the cache group. If the cache is hit (success) then the contents
     * are returned.
     *
     * On failure, the number of cache misses will be incremented.
     *
     * @since 2.0.0
     *
     * @param int|string $key What the contents in the cache are called
     * @param string $group Where the cache contents are grouped
     * @param string $force Whether to force a refetch rather than relying on the local cache (default is false)
     * @return false|mixed False on failure to retrieve contents or the cache
     *		               contents on success
     */
    public function get( $key, $group = 'default', $force = false, &$found = null ) {
        if ( empty( $group ) )
            $group = 'default';

        $key = "{$group}.{$key}";
        $cacheKey = $this->getCacheKey($key);

        if(CacheParent::has($cacheKey)){
            $found = true;
            $obj = CacheParent::get($cacheKey);
            if ( is_object($obj))
                return clone $obj;
            else
                return $obj;
        }
        $found = false;
        $this->cache_misses += 1;
        return false;
    }


    public function add( $key, $data, $group = 'default', $expire = null ) {
        if ( empty( $group ) )
            $group = 'default';
        $key = "{$group}.{$key}";
        $cacheKey = $this->getCacheKey($key);
        if ( is_object( $data ) )
            $data = clone $data;
        if(CacheParent::has($cacheKey))
            return false;
        CacheParent::put($cacheKey, $data, $expire);
        return true;
    }

    public function set( $key, $data, $group = 'default', $expire = null ) {
        if ( empty( $group ) )
            $group = 'default';

        $key = "{$group}.{$key}";
        $cacheKey = $this->getCacheKey($key);

        if ( is_object( $data ) )
            $data = clone $data;

        CacheParent::put($cacheKey, $data, $expire);
        return true;
    }

    public function incr( $key, $offset = 1, $group = 'default' ) {
        if ( empty( $group ) )
            $group = 'default';
        $key = "{$group}.{$key}";
        $cacheKey = $this->getCacheKey($key);

        if ( ! CacheParent::has($cacheKey) )
            return false;

        $count = CacheParent::get($cacheKey);

        if ( ! is_numeric( $count ) )
            $count = 0;

        $offset = (int) $offset;

        $count = CacheParent::increment($cacheKey, $offset);
        return $count;
        /*if ( $this->cache[ $group ][ $key ] < 0 )
            $this->cache[ $group ][ $key ] = 0;

        return $this->cache[ $group ][ $key ];*/
    }

    public function getCacheKey($group){
        $group = strtoupper($group);
        return self::CACHE_KEY . ".$group";
    }

    public function delete( $key, $group = 'default' ) {
        if ( empty( $group ) )
            $group = 'default';
        $key = "{$group}.{$key}";
        $cacheKey = $this->getCacheKey($key);
        if( !CacheParent::has($cacheKey) )
            return false;

        CacheParent::forget($cacheKey);
        return true;
    }

    public function flush() {

        CacheParent::flush();
        return true;
    }

}