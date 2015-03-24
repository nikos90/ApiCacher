<?php
/**
 * Created by PhpStorm.
 * User: naggelakis
 * Date: 24/3/15
 * Time: 9:55
 */

namespace HSpace\ApiCacher\Helpers;


class Curl
{
    protected $curl;
    public $ssl_verify = false;
    public $curl_timeout= 5;
    public $curl_return_transfer = true;
    public $curl_encoding = 'gzip';
    public $curl_header = false;
    public $debug;

    /**
     * Initialize Curl
     */
    public function __construct(){
        $this->curl = curl_init();
        $this->setup();
    }

    /**
     * Default Config For Curl Resource
     */
    protected function setup(){
        curl_setopt($this->curl,CURLOPT_ENCODING,$this->curl_encoding);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, $this->curl_return_transfer);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->curl_timeout);
        curl_setopt($this->curl, CURLOPT_HEADER,$this->curl_header);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, $this->ssl_verify);
    }

    /**
     *  Request Builder
     * @param $url
     * @param null $fields
     * @return mixed
     */
    protected function base_request($url,$fields=null){

        curl_setopt($this->curl,CURLOPT_URL, $url);
        if(is_array($fields)){
            $fields_string='';
            if(is_array($fields) && count($fields)>0) {
                foreach ($fields as $key => $value) {
                    $fields_string .= $key . '=' . $value . '&';
                }
            }
            rtrim($fields_string, '&');
            curl_setopt($this->curl,CURLOPT_POST, count($fields));
            curl_setopt($this->curl,CURLOPT_POSTFIELDS, $fields_string);
        }

        $response = curl_exec($this->curl);
        return $response;
    }

    /**
     * @param $url
     * @param bool $json_decode
     * @return mixed
     */
    public function _get($url,$json_decode = false){
        $response = $this->base_request($url);

        if(!$response){ return $this->error('Response could not be acquired'); }
        return $this->_output($response,$json_decode);

    }

    /**
     * Execute a POST request
     * @param $url
     * @param $fields
     * @param bool $json_decode
     * @return mixed|\stdClass
     */
    public function _post($url,$fields,$json_decode=false){

        if(!is_array($fields)){ return $this->error('POST request needs an array with fields to be posted'); }
        $response = $this->base_request($url,$fields);
        if(!$response){ return $this->error('Response could not be acquired'); }

        return $this->_output($response,$json_decode);
    }

    /**
     * Manipulate Response
     * @param $response
     * @param bool $json_decode
     * @return mixed
     */
    protected function _output($response,$json_decode=false){
        $this->debug = $response;
        if($json_decode) {
            return json_decode($response);
        }else{
            return $response;
        }
    }

    /**
     * Error Output
     * @param $msg
     * @return \stdClass
     */
    public function error($msg){
        $error = new \stdClass();
        $error->status = 'error';
        $error->message = $msg;
        $this->debug = $error;
        return $error;
    }

    /**
     * Close Session
     */
    public function __destruct(){
        curl_close($this->curl);
    }


}