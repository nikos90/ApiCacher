<?php
/**
 * Created by PhpStorm.
 * User: naggelakis
 * Date: 24/3/15
 * Time: 11:48
 */
namespace HSpace\ApiCacher\Helpers;
use HSpace\ApiCacher\Helpers\Curl;

class MultiCurl
{
    protected $multi;
    public $ssl_verify = false;
    public $curl_timeout = 5;
    public $curl_return_transfer = true;
    public $curl_encoding = 'gzip';
    public $curl_header = false;
    public $debug;
    public $queue;
    public $output;

    /**
     * Initialize Curl
     */
    public function __construct()
    {
        $this->multi = curl_multi_init();

    }



    /**
     * Default Config For Curl Resource
     */
    protected function setup($url,$fields = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_ENCODING, $this->curl_encoding);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, $this->curl_return_transfer);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->curl_timeout);
        curl_setopt($ch, CURLOPT_HEADER, $this->curl_header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->ssl_verify);
        if (is_array($fields)) {
            $fields_string = '';
            if (is_array($fields) && count($fields) > 0) {
                foreach ($fields as $key => $value) {
                    $fields_string .= $key . '=' . $value . '&';
                }
            }
            rtrim($fields_string, '&');
            curl_setopt($ch, CURLOPT_POST, count($fields));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        }
        return $ch;
    }


    /**
     * Run the parallel system requests
     * @return mixed
     */
    public function run($json_decode=false){
        /** While we're still active, execute curl */
        if(isset($this->queue) && count($this->queue)>0) {
            $active = null;
            do {
                $mrc = curl_multi_exec($this->multi, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);

            while ($active && $mrc == CURLM_OK) {
                /** Wait for activity on any curl-connection */
                if (curl_multi_select($this->multi) == -1) {
                    continue;
                }

                /** Continue to exec until curl is ready to give us more data */
                do {
                    $mrc = curl_multi_exec($this->multi, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        /** Loop through the channels and retrieve the received content, then remove the handle from the multi-handle */
        if(isset($this->queue) && count($this->queue)>0) {
            foreach ($this->queue as $key => $channel) {
                if(is_resource($channel)) {
                    $response = curl_multi_getcontent($channel);
                    $this->output[$key] = ($json_decode == true) ? json_decode($response) : $response;
                    curl_multi_remove_handle($this->multi, $channel);
                    curl_close($channel);
                }
            }
        }
        return $this->output;
    }

    /**
     * @param $url
     * @return mixed
     */
    public function _add($url,$fields=null)
    {
        $ch = $this->setup($url,$fields);

        curl_multi_add_handle($this->multi, $ch);
        $this->queue[$url] = $ch;

    }



    /**
     * Error Output
     * @param $msg
     * @return \stdClass
     */
    public function error($msg)
    {
        $error = new \stdClass();
        $error->status = 'error';
        $error->message = $msg;
        $this->debug = $error;
        return $error;
    }

    /**
     * Close Session
     */
    public function __destruct()
    {
        curl_multi_close($this->multi);
    }


}