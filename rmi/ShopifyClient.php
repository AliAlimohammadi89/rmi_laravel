<?php
/**
 * Created by PhpStorm.
 * User: meysam
 * Date: 8/14/17
 * Time: 2:57 PM
 */

namespace RMI;

use Illuminate\Contracts\Logging\Log;
use RMI\BaseClient;
use GuzzleHttp\Client;


class ShopifyClient extends BaseClient
{

    private $access_token;
    private $store_url;
    protected $response;
    private $response_info;


    public function __construct($config = null)
    {
        parent::__construct();
        if( !is_null($config) && is_array($config) ){
            $this->setConfig($config);

        }
    }


    public function setConfig($config)
    {
        $keys = ['access_token', 'store_url'];
        foreach($config as $key => $value){
            if( in_array($key, $keys) ){
                $this->{$key} = $value;
            }
        }
        if( isset($config['access_token']) ){
            $this->client = new Client(['headers' => [
                'X-Shopify-Access-Token' => $config['access_token'],
                'Content-Type' => 'application/json'
            ]]);
        }
    }


    public function productAdd(&$fields)
    {
        $submit = $this->curl("POST", "admin/products.json", $fields);
        if($submit && isset($this->response_info['http_code']) && $this->response_info['http_code'] == 201){
            return $submit;
        }
        return false;
    }
    public function productUpdate($product_id, &$fields)
    {
        $submit = $this->curl("PUT", "admin/products/{$product_id}.json", $fields);
        if ($submit && isset($this->response_info['http_code']) && $this->response_info['http_code'] == 200) {
            return true;
        }
        return false;
    }

    public function productDelete($product_id){
        $submit = $this->curl("DELETE", "admin/products/{$product_id}.json","");
        if ($submit && isset($this->response_info['http_code']) && $this->response_info['http_code'] == 200) {
            return true;
        }
        return false;

    }



    public function  getResponse()
    {
        return $this->response;
    }
    public function handle()
    {

    }
    public function curl($method, $url, $fields, $headers = [],$bucket_id="")
    {






        $curl = curl_init();
        ini_set('max_execution_time', 30000); //ALI!@
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://{$this->store_url}/$url",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($fields),
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "content-type: application/json",
                "x-shopify-access-token: $this->access_token"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $info = curl_getinfo($curl);
        $this->response_info = $info;
        curl_close($curl);
        if ($err) {
            $this->response = $err;
            return false;
        }
        else {
            $this->response = json_decode($response, true);
            return true;
        }
    }
}