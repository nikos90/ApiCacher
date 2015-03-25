<?php
/**
 * Created by PhpStorm.
 * User: naggelakis
 * Date: 24/3/15
 * Time: 10:28
 */
namespace HSpace\ApiCacher\Library;
use HSpace\ApiCacher\Helpers\Curl;
use HSpace\ApiCacher\Helpers\MultiCurl;

class ApiCacher {

    protected $root_cache;
    protected $queue;
    public $multi;
    protected $multiCurl=null;

    /**
     * Constuctor
     */
    public function __construct(){
        ob_start();
        $this->root_cache = __DIR__.'/../Cache';
    }

    /**
     * The endpoint that uses the cacher
     * @param $endpoint
     * @param null $fields
     * @param bool $json_decode
     * @param bool $rebuild
     * @return mixed|\stdClass
     */
    public function request($endpoint,$fields = null, $json_decode = false, $rebuild = false, $run = false){
        $cached_data = $this->get_cache($endpoint);

        if($cached_data && $run == false){

            /** Rebuild cache if you need */
            if($rebuild == true){
                /** Add Endpoint to queue for rebuild */
                $this->queue[$endpoint] = ($fields == null)? $endpoint : $fields;
            }

            /** Get cached data */
            return $this->output_cache($cached_data,$json_decode);

        }else{
            /** Execute Request and build file */
            $response = $this->fetch_data($endpoint, $fields);
            if(isset($response->error)) { return $this->error($response); }
            /** build the cache file */
            $this->build_cache($response,$endpoint);
            /** return response data */
            return $this->output_cache($response,$json_decode);
        }
    }


    public function request_multi($endpoint,$fields = null, $json_decode = false, $rebuild = false, $run = false){
        $cached_data = $this->get_cache($endpoint);
        if($cached_data && $run == false){
            /** Rebuild cache if you need */
            if($rebuild == true){
                /** Add Endpoint to queue for rebuild */
                $this->queue[$endpoint] = ($fields == null)? $endpoint : $fields;
            }
            /** Get cached data */
            $this->multi[$endpoint] = $this->output_cache($cached_data,$json_decode);
        }else{
            /** Execute Request and build file */
            $this->multi_build($endpoint, $fields);
        }
    }

    protected function multi_build($endpoint,$fields=null){
        if($this->multiCurl == null){
            $this->multiCurl = new MultiCurl();
        }
        $this->multiCurl->_add($endpoint,$fields);

    }

    public function execute($json_decode=false){
        if($this->multiCurl == null ){ return false; }
        $output = $this->multiCurl->run($json_decode);
        if(is_array($output) && count($output)>0){
            foreach($output as $endpoint=>$data){
                $this->build_cache($data,$endpoint);
                $this->multi[$endpoint] = $data;
            }
        }
    }

    public function multi_output(){
        return $this->multi;
    }
    /**
     * Store the data into cache
     * @param $data
     * @param $endpoint
     * @return int
     */
    protected function build_cache($data,$endpoint){
        $cache = $this->get_file_path($endpoint);
        $handle = fopen($cache, 'w');
        return fwrite($handle, serialize($data));

    }

    /**
     * Get cache file
     * @param $endpoint
     * @return mixed
     */
    protected function get_cache($endpoint){
        $file = $this->get_file_path($endpoint);
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
     * @return string
     */
    protected function get_file_path($endpoint){
        $name = $this->hash_file($endpoint);
        $file = $this->root_cache.'/'.$name;
        return $file;
    }

    /**
     * Hash file name
     * @param $endpoint
     * @return string
     */
    protected function hash_file($endpoint){
        $hash = md5($endpoint);
        return $file = $hash.'.txt';
    }

    /**
     * Curl the endpoint
     * @param $endpoint
     * @param null $fields
     * @return mixed|\stdClass
     */
    protected function fetch_data($endpoint,$fields=null){
        $curl = new Curl();
        if($fields==null) {
            $response = $curl->_get($endpoint);
        }elseif(is_array($fields)){
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

        if($json_decode==true && !is_object($data) && !is_array($data)){
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
        ob_end_flush();
        if(is_array($this->queue) && count($this->queue)>0){

            foreach($this->queue as $endpoint=>$data){
                if(is_array($data)){ $fields = $data; }else{ $fields = null; }
                $this->request_multi($endpoint,$fields,false,false,true);
                unset($this->queue[$endpoint]);
            }

            flush();
            call_user_func(array($this, 'execute'));

            //$run = $this->execute();
        }

    }
} 