<?php
/**
 * Created by PhpStorm.
 * User: naggelakis
 * Date: 24/3/15
 * Time: 10:28
 */
namespace HSpace\ApiCacher\Library;
use HSpace\ApiCacher\Helpers\Curl;

class ApiCacher {

    protected $root_cache;
    protected $queue;

    /**
     * Constuctor
     */
    public function __construct(){
        $this->root_cache = __DIR__.'/../Cache';
    }

    /**
     * The endpoint that uses the cacher
     * @param $endpoint
     * @param string $type
     * @param null $fields
     * @param bool $json_decode
     * @param bool $rebuild
     * @return mixed|\stdClass
     */
    public function request($endpoint, $type = 'GET',$fields = null, $json_decode = false, $rebuild = false){
        $cached_data = $this->get_cache($endpoint,$type);

        if($cached_data){

            /** Rebuild cache if you need */
            if($rebuild == true){
                /** Add Endpoint to queue for rebuild */
                $this->queue[$endpoint] = $type;
            }

            /** Get cached data */
            return $this->output_cache($cached_data,$json_decode);

        }else{
            /** Execute Request and build file */
            $response = $this->fetch_data($endpoint, $type, $fields);
            if(isset($response->error)) { return $this->error($response); }
            /** build the cache file */
            $this->build_cache($response,$endpoint,$type);
            /** return response data */
            return $this->output_cache($response,$json_decode);
        }
    }

    /**
     * Store the data into cache
     * @param $data
     * @param $endpoint
     * @param $type
     * @return int
     */
    protected function build_cache($data,$endpoint,$type){
        $cache = $this->get_file_path($endpoint,$type);
        $handle = fopen($cache, 'w');
        return fwrite($handle, serialize($data));

    }

    /**
     * Get cache file
     * @param $endpoint
     * @param $type
     * @return mixed
     */
    protected function get_cache($endpoint,$type){
        $file = $this->get_file_path($endpoint,$type);
        if(file_exists($file)){
            return $this->get_file_data($file);
        }
    }

    /**
     * Get cached data
     * @param $file
     * @return mixed
     */
    protected function get_file_data($file){
        $data = file_get_contents($file);
        return unserialize($data);
    }

    /**
     * Get absolute path of file
     * @param $endpoint
     * @param $type
     * @return string
     */
    protected function get_file_path($endpoint,$type){
        $name = $this->hash_file($endpoint,$type);
        $file = $this->root_cache.'/'.$name;
        return $file;
    }

    /**
     * Hash file name
     * @param $endpoint
     * @param $type
     * @return string
     */
    protected function hash_file($endpoint,$type){
        $string = $endpoint.'-'.$type;
        $hash = md5($string);
        return $file = $hash.'.txt';
    }

    /**
     * Curl the endpoint
     * @param $endpoint
     * @param string $type
     * @param null $fields
     * @return mixed|\stdClass
     */
    protected function fetch_data($endpoint, $type = 'GET',$fields=null){
        $curl = new Curl();
        if($type=='GET') {
            $response = $curl->_get($endpoint);
        }elseif($type=='POST' && is_array($fields)){
            $response = $curl->_post($endpoint,$fields);
        }
        return $response;
    }

    /**
     * Return final data for the endpoint
     * @param $data
     * @param bool $json_decode
     * @return mixed
     */
    protected function output_cache($data,$json_decode=false){

        if($json_decode==true){
            return json_decode($data);
        }else{
            return $data;
        }
    }

    /**
     * Return error message
     * @param $error
     * @return \stdClass
     */
    protected function error($error){
        if(is_object($error)){
            return $error;
        }else{
            $object = new \stdClass();
            $object->status = 'error';
            $object->message = $error;
            return $object;
        }
    }

    /**
     * Finalize the cacher, rebuild caches if need.
     */
    public function __destruct(){

        if(is_array($this->queue) && count($this->queue)>0){
            foreach($this->queue as $endpoint=>$type){
                if($type=='GET'){
                    $response = $this->fetch_data($endpoint, $type);
                }elseif(is_array($type)){
                    $response = $this->fetch_data($endpoint, 'POST',$type);
                }
                if(isset($response->error)) { return $this->error($response); }
                /** build the cache file */
                $this->build_cache($response,$endpoint,$type);
                unset($this->queue[$endpoint]);
            }
        }

    }
} 