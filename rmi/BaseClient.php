<?php
/**
 * Created by PhpStorm.
 * User: meysam
 * Date: 8/14/17
 * Time: 3:39 PM
 */

namespace RMI;

use GuzzleHttp\Client;
use Illuminate\Contracts\Logging\Log;
use Mockery\Exception;


class BaseClient
{
    protected $client = null;
    protected $url;
    protected $response;

    public function __construct($config = null)
    {
        if( !is_null($config) && is_array($config) ){
            $this->setConfig($config);
        }
    }

    public function setConfig($config)
    {
        foreach($config as $key => $value){
            $this->{$key} = $value;
        }
    }


    public function post($fields)
    {
        return $this->response =  $this->client->post(
            $this->url,
            [
                'json' => [
                    json_encode($fields)
                ]
            ]
        );
    }



    public function get($query_string = [])
    {
        return $this->response =  $this->client->request(
            'GET',
            $this->url,
            [
                'query' => $query_string
            ]
        );
    }

//$this->response = $this->curl('POST', $url, $fields, $keyId, $bucket_id);

    public function curl($method, $url, $fields, $headers = "",$bucket_id="")
    {


//        $string = file_get_contents("test.json");
//        $json_a = json_decode($string, true);
//
//        return $json_a;






        //curl('POST', $url, $fields,$keyId);
        $curl = curl_init();
        $keyId="";

        if( $headers != "" )
            $keyId='apikey:'.$headers;

        $default_headers = [
            "cache-control:no-cache",
            "content-type:application/json",
            // "bucketId:$bucket_id",
            //'token:eyJ0b2tlbiI6ImV5SmhiR2NpT2lKU1V6STFOaUlzSW5SNWNDSTZJa3BYVkNKOS5leUoxYzJWeVNXUWlPaUkxWVdFek9UZ3lOV1ExWmpBd01qUTVZVEV5TlRNek1UWWlMQ0ptYVhKemRHNWhiV1VpT2lKVFlXVmxaQ0lzSW14aGMzUnVZVzFsSWpvaVQzSmhhbWtpTENKeWIyeGxJam9pWVdSdGFXNGlMQ0poY0hBaU9pSmtZWFJoYkdsdWF5SXNJbVZ0WVdsc0lqb2ljMkZsWldSdmNtRnFhVUJuYldGcGJDNWpiMjBpTENKdmQyNWxja2xrSWpwdWRXeHNMQ0pwYzBGa2JXbHVJanAwY25WbExDSnJaWGxKWkNJNklqVmlOalZpTUdFME5qSTVOV0ZrTW1OaVlqQXlPV1JtWkNJc0ltbGhkQ0k2TVRVek5qYzBPREV5TjMwLmtBdnAwR2xDdWkwU1hxSmdTc0ZkYUdFUUh0YVZzay1lLTlJZFhIc0FseUxYSmxqR0FrZzN6eW1EZXFHRnEtaGdlekQ5RHFyd0pHQzBscmg3VGxNTzV4NUVtazZBUC11c3V1OVBCSkVVZmhzdzhybm1FaFBCYUdZQnFPQTdPRXBLazJSNUpLSkdFNXVHUDdZUGg4UWRmeXB6RmxVc3lQa19xYlNsejZySzFJRnowWGVlRE5vN2xjUm1OMFBsQWhiWXMyTUM1TGRtTmdoMElfQmFrQUdCaXAzRFlJdnRlWk1xYUJZMld6cFRKYkNyM3ZQaGdQdEVmSXltVV9yMnBEQkU2dGdvLTlPUzVOODIyZldROUZ5QUVfejgydVNTUWx4eFpSc0I5eldCVnpNSHNsWkJTMWRITzZBbERnek1LTHgtak95d0todktHbkNnS2FFa0RxNmxyUSIsImVtYWlsIjoic2FlZWRvcmFqaUBnbWFpbC5jb20iLCJhcHAiOiJkYXRhbGluayIsImtleUlkIjoiNWI2NWIwYTQ2Mjk1YWQyY2JiMDI5ZGZkIn0',
            $keyId
        ];


        //Log :: info($default_headers,$url,$method,$fields);
        //$url = 'http://apps.rminno.com:80/customApi/vendorproduct/getbucketproduct';

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($fields),
            CURLOPT_HTTPHEADER => $default_headers,
        ]);



        $response = curl_exec($curl);
        $err = curl_error($curl);
        $info = curl_getinfo($curl);

        $this->response_info = $info;

        curl_close($curl);
        //dd($url,$method,$fields,$default_headers,'erropr=',$err,$response);

        if ($err) {
            return $err;
        }
        else {
            return json_decode($response, true);
        }
    }

}