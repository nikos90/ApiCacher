<?php
/**
 * Created by PhpStorm.
 * User: macbook51
 * Date: 25/03/15
 * Time: 13:09
 */

namespace HSpace\ApiCacher\Listener;

class EraseCacheListener {

    protected $root_cache;
    /**
     * Constuctor
     */
    public function __construct(){
        $this->root_cache = __DIR__.'/../Cache';
    }

    /**
     * Listen for the command to erase the cache
     */
    public function listen(){
        if(isset($_GET['eraseCache']) && $_GET['eraseCache'] == 'true'){
            $this->_clear();
        }
    }

    /**
     * Erase all cache files
     */
    protected function _clear(){
        array_map('unlink', glob($this->root_cache.'/*'));
    }
}