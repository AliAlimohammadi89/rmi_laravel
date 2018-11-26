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

class DatalinkClient extends BaseClient
{

    private $api_key;
    private $base_url;
    protected $response;
    private $error;


    public function __construct($config = null)
    {
        parent::__construct();
        if (!is_null($config) && is_array($config)) {
            $this->setConfig($config);
        }
    }

    public function setConfig($config)
    {
        $keys = ['api_key', 'base_url'];
        foreach ($config as $key => $value) {
            if (in_array($key, $keys)) {
                $this->$key = $value;
            }
        }
        if (isset($config['api_key'])) {
            $this->client = new Client(['headers' => ['api_key' => $config['api_key'], 'ip' => $config['ip']]]);
        }
    }


    public function getResponse()
    {
        return $this->response;
    }


    public function getResponseBody()
    {
        return json_decode($this->response->getBody()->getContents(), true);
    }


    public function getResponseHead()
    {

    }


    public function getError()
    {
        return $this->error;
    }


    public function getBucket($bucket_id, $keyId, $limit = 10, $offset)
    {







        //[2018-11-02 17:31:32] local.INFO: 5bd5fa382bad7d4f56d3760f,c7518dbf088e35957fad0fd3b54411d0, 90, 0


        $url = "http://apps.rminno.com:80/customApi/vendorproduct/getbucketproduct?bucketId=$bucket_id&limit=$limit&skip=$offset";

        try {
            $fields = [
                'bucketId' => $bucket_id,
                //'apikey' => $keyId,
                //  'token' => 'eyJ0b2tlbiI6ImV5SmhiR2NpT2lKU1V6STFOaUlzSW5SNWNDSTZJa3BYVkNKOS5leUoxYzJWeVNXUWlPaUkxWVdFek9UZ3lOV1ExWmpBd01qUTVZVEV5TlRNek1UWWlMQ0ptYVhKemRHNWhiV1VpT2lKVFlXVmxaQ0lzSW14aGMzUnVZVzFsSWpvaVQzSmhhbWtpTENKeWIyeGxJam9pWVdSdGFXNGlMQ0poY0hBaU9pSmtZWFJoYkdsdWF5SXNJbVZ0WVdsc0lqb2ljMkZsWldSdmNtRnFhVUJuYldGcGJDNWpiMjBpTENKdmQyNWxja2xrSWpwdWRXeHNMQ0pwYzBGa2JXbHVJanAwY25WbExDSnJaWGxKWkNJNklqVmlOalZpTUdFME5qSTVOV0ZrTW1OaVlqQXlPV1JtWkNJc0ltbGhkQ0k2TVRVek5qYzBPREV5TjMwLmtBdnAwR2xDdWkwU1hxSmdTc0ZkYUdFUUh0YVZzay1lLTlJZFhIc0FseUxYSmxqR0FrZzN6eW1EZXFHRnEtaGdlekQ5RHFyd0pHQzBscmg3VGxNTzV4NUVtazZBUC11c3V1OVBCSkVVZmhzdzhybm1FaFBCYUdZQnFPQTdPRXBLazJSNUpLSkdFNXVHUDdZUGg4UWRmeXB6RmxVc3lQa19xYlNsejZySzFJRnowWGVlRE5vN2xjUm1OMFBsQWhiWXMyTUM1TGRtTmdoMElfQmFrQUdCaXAzRFlJdnRlWk1xYUJZMld6cFRKYkNyM3ZQaGdQdEVmSXltVV9yMnBEQkU2dGdvLTlPUzVOODIyZldROUZ5QUVfejgydVNTUWx4eFpSc0I5eldCVnpNSHNsWkJTMWRITzZBbERnek1LTHgtak95d0todktHbkNnS2FFa0RxNmxyUSIsImVtYWlsIjoic2FlZWRvcmFqaUBnbWFpbC5jb20iLCJhcHAiOiJkYXRhbGluayIsImtleUlkIjoiNWI2NWIwYTQ2Mjk1YWQyY2JiMDI5ZGZkIn0',
                //'limit' => $limit,
                // 'isVendorWebsite' => 1,
                //'where' => new \stdClass()
            ];
            $status = true;
            $this->response = $this->curl('POST', $url, $fields, $keyId, $bucket_id);

        } catch (\Exception $e) {
            $status = false;
            $this->error = $e->getMessage();
        }
        return $status;
    }


    public function getAllProducts($bucket_id, $limit = 100, $offset = 1)
    {
        $this->url = $this->base_url . "/buckets/$bucket_id/products.json";

        try {
            $status = true;
            $this->response = $this->get(['limit' => $limit, 'skip' => $offset]);
        } catch (\Exception $e) {
            $status = false;
            $this->error = $e->getMessage();
        }
        return $status;

     }


    public function getSimpleProducts($bucket_id, $product_id)
    {
        $this->url = $this->base_url . "/buckets/$bucket_id/products/$product_id/simples.json";
        try {
            $status = true;
            $this->response = $this->get();
        } catch (\Exception $e) {
            $status = false;
            $this->error = $e->getMessage();
        }
        return $status;
    }


    public function checkuserpassddatalink($username, $password)
    {

        $url = "http://apps.rminno.com/customApi/magento/getRSAKey";


        $curl = curl_init();
        $keyId = "";


        $fields = [
            'email' => $username,
            'password' => $password
        ];


        $default_headers = [
            "cache-control:no-cache",
            "content-type:application/json",
        ];

        //$url = 'http://apps.rminno.com:80/customApi/vendorproduct/getbucketproduct';


        $header = [
            "cache-control: no-cache",
            "content-type: application/json",
        ];

        $curl = curl_init();
        $config = [
            CURLOPT_PORT => '80',
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => $header

        ];
        $params['email'] = $username;
        $params['password'] = $password;
        $params['apiPath'] = 'http://rmi.alialimohammadi.ir/public/';
        curl_setopt_array($curl, $config);
        if ($params) {

            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        }

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        $response = json_decode($response, true);

        return $response;

    }
}