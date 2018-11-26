<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use phpDocumentor\Reflection\Types\String_;
use RMI\ShopifyClient;
use RMI\DatalinkClient;
use App\Client as ClientModel;
use GuzzleHttp\Client;
use Carbon\Carbon;
use App\Process;
use App\Subprocess;
use Validator;
use Illuminate\Support\Facades\Log;
use App\Mail\ProcessStarted;
use App\Mail\ProcessCompleted;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;


class AppController extends Controller
{
    private $shopify;
    private $client;
    private $client_id;
    private $model;
    private $session_key;
    public function __construct()
    {
        $this->session_key = 'client_id';
        $this->middleware('verifyclient', ['except' => ['index','insert_auto', 'store', 'auth', 'process', 'result', 'logout']]);
        $this->client_id = session('client_id');
        $this->model = new ClientModel;
        $this->client = $this->model->getClient($this->client_id);
        if( !is_null($this->client) && !is_null($this->client_id) ){
            $this->shopify = new ShopifyClient([
                'access_token' => $this->client->shopify_access_token,
                'store_url' => $this->client->shopify_store
            ]);
        }
        // if exists session but doesnt exist client id in table then clear sessions
        if( is_null($this->client) && !is_null($this->client_id) ){
            session()->flush();
        }
    }
    public function index()
    {
        if( !is_null($this->client) && !is_null($this->client->shopify_store) ){
            return redirect('import?'.time());
        }
        return view('app.index');
    }
    public function store(Request $request)
    {
        $this->validate($request, [
            'store' => 'required|regex:/[a-zAz0-9]+\.myshopify.com/iu',
            'Usernamedatalink' => 'required',
            'UserpassDatalink' => 'required',
            'password' => 'required|min:8|regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[\d\X]).*$/'
        ]);
        $store = $request->store;
        $password = $request->password;
        $Usernamedatalink= $request->Usernamedatalink;
        $UserpassDatalink = $request->UserpassDatalink;
        $api_key = env('SHOPIFY_APIKEY');
        $scopes = env('SHOPIFY_SCOPES');
        $redirect_uri = urlencode(env('SHOPIFY_REDIRECT_URI'));
        $getRow = $this->model->whereShopifyStore($store)->first();
        $datalink = new DatalinkClient([
            'api_key' =>"",
            'base_url' => "",
            'ip' => env('RMI_IP')
        ]);
        $IsOKuser=$datalink->checkuserpassddatalink($Usernamedatalink,$UserpassDatalink);
        if($IsOKuser['result']!='true'){
            $redirect_to="/?error=datalink";
            // return redirect($redirect_to);
        }
        if( is_null($getRow) ){

            $unique_key = bcrypt(time());
            $row = $this->model->create([
                'shopify_store' => $store,
                'unique_key' => $unique_key,
                'password' =>  $password ,
               // 'datalink_public_key' =>  $IsOKuser['data'] ,
                'datalik_public_set' => time() ,
                'username_datalink' =>  $Usernamedatalink ,
                'userpass_datalink' =>  $UserpassDatalink
            ]);

        }
        else{

            if($password!=$getRow->password){
                $redirect_to="/?error=password";


                return redirect($redirect_to);
            }
            $unique_key = $getRow->unique_key;

        }
        $redirect_to = "https://{$store}/admin/oauth/authorize?client_id={$api_key}&scope={$scopes}&redirect_uri={$redirect_uri}&state={$unique_key}";
        return redirect($redirect_to);
    }
    public function auth(Request $request)
    {
        $api_key = env('SHOPIFY_APIKEY');
        $secret_key = env('SHOPIFY_SECRET');
        $query = $_GET;
        if (!isset($query['code'], $query['hmac'], $query['shop'], $query['state'], $query['timestamp'])) {
            return dd('parameters are invalid!');
        }
        $one_minute_ago = Carbon::now()->subSeconds(60)->timestamp;
        if ($query['timestamp'] < $one_minute_ago) {
            return dd('request time has been expired!');
        }
        $hmac = $query['hmac'];
        $store = $query['shop'];
        unset($query['hmac']);
        foreach ($query as $key => $val) {
            $params[] = "$key=$val";
        }
        asort($params);
        $params = implode('&', $params);
        $calculated_hmac = hash_hmac('sha256', $params, $secret_key);
        if($hmac == $calculated_hmac){

            $client = new Client();

            $response = $client->request(
                'POST',
                "https://{$store}/admin/oauth/access_token",
                [
                    'form_params' => [
                        'client_id' => $api_key,
                        'client_secret' => $secret_key,
                        'code' => $query['code']
                    ]
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            $access_token = $data['access_token'];

            $nonce = $query['state'];

            $row = $this->model->where(['shopify_store' => $store, 'unique_key' => $nonce])->first();


            if( $row ){
                $row->shopify_access_token = $access_token;
                $row->save();
                session([$this->session_key => $row->id]);
                return redirect('import');
            }

            return 'error';
        }
    }
    public function import(Request $request)
    {
        $client = $this->getClient();
        $process_id = $tracking_code= '';
        $isProcessRunning = false;
        if( !is_null($client) && $client->datalink_api != '' && !is_null($client->datalink_api) ){
            $api = $client->datalink_api;
        }
        else{
            $api = '';
        }
        $validator = Validator::make($request->all(), [
            'api' => 'required',
            'bucket_id' => 'required',
            'keyId' => 'required',
            'email' => 'nullable|email'
        ]);
        if( $validator->passes() ){
            $openProcess= Process::where(['client_id' => $this->client_id])->whereIn('status', [1,2])->get();
            if( $openProcess->count() > 0 ){
                return view('app.import', ['api' => $api, 'error'=> 'You have been submit a request before. please wait until completed it']);
            }

            $datalink_api = $request->input('api');
            $bucket_id = $request->input('bucket_id');
            $keyId = $request->input('keyId');
            $email = $request->input('email');

            session(["api" => $datalink_api]);
            session(["bucket_id" => $bucket_id]);
            session(["keyId" => $keyId]);
            $base_url = env('RMI_BASE_URL');

            $datalink = new DatalinkClient([
                'api_key' => $datalink_api,
                'base_url' => $base_url,
                'ip' => env('RMI_IP')
            ]);
            $getBucket = $datalink->getBucket($bucket_id,$keyId, 10,0);
            if($getBucket){
                $data = $datalink->getResponse();
            }
            else{
                return view('app.import', ['api' => $api, 'error'=> 'Datalink API is not correct. '.$datalink->getError()]);
            }
            // update new datalink api
            $client = ClientModel::find($this->client_id);
            $client->datalink_api = $datalink_api;
            $client->save();
            $process_id = $this->makeProcess($bucket_id, $data, $email);
            $process_row = Process::find($process_id);

            // cache data and remove after 2 minutes
            Cache::put($process_id, $data, 2);

            if($email){
                $this->emailStarted($email, url("result/?id=$process_id&code=$process_row->tracking_code"), $process_id);
            }

            $tracking_code = $process_row->tracking_code;

            $isProcessRunning = true;

            session(['process_id' => $process_id]);
        }
        return view('app.import', [
            'api' => $api,
            'errors' => $validator->errors(),
            'submit' => $request->has('x'),
            'process_id' => $process_id,
            'tracking_code' => $tracking_code,
            'process' => $isProcessRunning
        ]);
    }
    public function result(Request $request)
    {
        $process_id = $request->input('id');
        $code = $request->input('code');
        if( is_null($process_id) || $process_id == '' || is_null($code) || $code == ''  ){
            return $this->response('parameters are invalid');
        }
        $process = Process::where(['id' => $process_id, 'tracking_code' => $code])->first();
        // var_dump($code);
        // die;
        if( $process->count() == 0 ){
            return $this->response('process not found');
        }
        $total_processes = $process->count_products;
        return view('app.handle', [
            'process_id' => $process_id,
            'total_processes' => $total_processes,
            'process_status' => $process->status
        ]);
    }
    public function progress(Request $request)
    {
        // $this->run($request);
        if( is_null($request->input('id')) || $request->input('id') == '' || intval($request->input('id')) == 0  ){
            return "-1";
        }
        $process = Process::find($request['id']);
        if( !$process ){
            return "-1";
        }
        if( $process->status == 3 ){
            return $process->count_products;
        }
        return Subprocess::where(['process_id' => $request['id']])->get()->count();
    }
    public function run(Request $request){
        $process_id = session("process_id");
        $api = session("api");
        $bucket_id = session("bucket_id");
        $keyId = session("keyId");
        if(  is_null($process_id) ){
            return 'Your request is invalid';
        }
        $process = Process::find($process_id);
        if( !$process ){
            return 'Your request is invalid';
        }
        elseif($process->status == 2){
            return 'You can not submit new request until you have a open request';
        }
        elseif($process->status == 3){
            return 'Your request has been closed';
        }
        //dd(1);
        // set status process to running
        $this->setProcessStatus($process_id, 2);
        //commend for test
        $datalink = new DatalinkClient([
            'api_key' => $api,
            'base_url' => env('RMI_BASE_URL'),
            'ip' => env('RMI_IP')
        ]);
        $limit  = 10;
        $offset = 0;

        Log :: info("$bucket_id,$keyId, $limit, $offset ");
        //[2018-11-02 17:31:32] local.INFO: 5bd5fa382bad7d4f56d3760f,c7518dbf088e35957fad0fd3b54411d0, 90, 0

        $getBucket = $datalink->getBucket($bucket_id,$keyId, $limit, $offset);
        Log :: info("$bucket_id");


        $data = $datalink->getResponse();
        if(!$getBucket || !isset($data['data'])){
            Log::info( $data );
            return;
        }
        // Log::info( $data );
        $variants_arr = [[]];
        $count = $data['data']['total'];
        //$count = $data['metadata']['count'];
        // $options_list = ['colorGroupName', 'shapeName', 'sizeName'];


        $options_list = ['shapeSize', 'colorGroupName', 'sizeName'];

        $tagKeys = ['brandName', 'collor', 'constructionName', 'collectionName', 'fieldDesignName', 'vendorUniqueId', 'originCountryName', 'primaryMaterialName','primaryStyleName', 'sizeName' ,'sku','shapeName','size'];
        $speciKeys = ['brandName', 'collor', 'collectionName', 'designName', 'primaryStyleName', 'colorGroupName', 'originCountryName', 'primaryMaterialName', 'constructionName', 'fieldDesignName','sku','shapeName','size'];
        $x = 0;
//        $myfile = fopen("newfile.txt", "wb") or die("Unable to open file!");
//        $txt = $count;
//        fwrite($myfile, $txt);
//        $txt = "$process_id \n";
//        fwrite($myfile, $txt);
//        fclose($myfile);
        foreach(array_chunk(range(1, $count), $limit) as $z){
//            break;
            if( !isset($data['data']['data']) ){

                $row = new Subprocess();
                $row->process_id = $process_id;
                $row->product_id = 0;
                $row->shopify_product_id = null;
                $row->error = json_encode($this->shopify->getResponse());
                $row->status = 3;
                $row->created_at = date("Y-m-d H:i:s");
                $row->save();
                $x++;
                $this->setProcessStatus($process_id, 3);
                continue;
            }
            //Log :: info ()
            foreach( $data['data']['data'] as  $key => $value ){

                $options_list=array();
                foreach ($value['productTypeConfig']['options'] as $option_S){
                    $options_list[]=$option_S['name'];
                }


                $options = [];
                $variants = [];
                $tags = $moreTags = $fullTags = [];
                $metafields = $fullMetafields = [];
                $images = [];
                $additionalImages = [];
                $newImages = [];
                $fields = [
                    'product' => [
                        //   "title"=> $value['title'],
//                        "handle"=> $value['key'],
                        "title" => $value['vendorUniqueId']." " . $value['collectionName']. " ". $value['designName'],
                        "body_html"=> $this->getDesc($value),
                        "vendor" => $value['vendorUniqueId'],
                        "imagePrefixPath" => $value['imagePrefixPath'],
                        "price" => $this->_getPrices($value),
                        "product_type"=> (isset($value['viewTemplatePrefix']))?$value['viewTemplatePrefix']:'rug',
                    ]
                ];
                //  5b7144e3a1300c88575207c6
                if(($value['productType'] == 'configurable' && count($value['simpleList'])>0 ) || $value['productType'] != 'configurable' ) {

                    if( $value['productType'] == 'configurable'   ){

                        Log::info("390!!!!!value");
                        Log::info($value);
                        Log::info("2!!!!!!!!!! ");
                        $fields['product']['options'] = [];
                        foreach ($value['productTypeConfig']['options'] as $k => $v) {
                            $key = array_search($v['name'], $options_list);
                            $options[$key] = $v['name'];
                            $fields['product']['options'][$key] = ["name" => $v['name']];
                        }
                        // sort arrays by key to asc
                        ksort($options, 1);
                        ksort($fields['product']['options'], 1);
                        $simples = ['data' => $value['simpleList']];
                        $i = 0;
                        foreach ($simples['data'] as $kk => $vv) {
                            // variants v
                            $j = 1;
                            $quantity = (isset($vv['quantity']) ? $vv['quantity'] : 0);//ALI!@
                            $weight = (isset($vv['weight']) ? $vv['weight'] : 0);//ALI!@
                            $price=$this->_getPrices($vv);
//                            if($price['special_price'] <= 10 || $price['old_price'] <= 11 )
//                                break;

                            $variants[$i] = [
                                //'title' => $vv['title'],
                                //'price' => $this->getPrice($vv),
                                'compare_at_price' => $price['old_price'],
                                'price' => $price['special_price'],
                                'inventory_quantity' => $quantity,
                                "sku" => $vv['id'],
                                //"sku" => $vv['sku'],
                                "barcode" => $vv['gs1'],
                                'weight' => $weight,
                            ];
                            $List_metafides_variant = [


                                'collectionName',
                                'size',
                                'Availability',
                                'image',
                                'color',
                                'gender',
                                'deliveryType',
                                'Primarymaterial',
                                'Secondarymaterial',
                                'originCountryName',
                                'productDescription',
                                'collectionName',
                                'vendorColorGroupName',
                                'shapeName',
                                'primaryMaterialName',
                                'designName',
                                'primaryStyleName',
                                'colorGroupName',
                                'constructionName',
                                'actualSize',
                                'standardSize',
                                // 'images',
                                // 'additionalImages',
                                'secondaryColorName',
                                'shapeSize',
                                'sku',
                                'title',
                                //   'imagePrefixPath'
                            ];
                            $fullMetafields1=$images1=$additionalImages1=array();
                            foreach ($List_metafides_variant as  $varants) {
                                if (isset($vv[$varants]) && $vv[$varants] != '' && !empty($vv[$varants]))  {
                                    $fullMetafields1 [] = [
                                        'namespace' => 'specifications',
                                        'key' => $varants,
                                        'value' => $vv[$varants],
                                        'value_type' => 'string'
                                    ];
                                }
                            }

                            //////////////////////////

                            if (count($vv['images']) > 0) {
                                foreach ($vv['images'] as $img) {
                                    // $images[] = $img['name'].'jpg';//ALI!@
                                    $images1[] = "https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg";
                                    //  Log::info("https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg");

                                }
                            }
                            if (count($vv['additionalImages']) > 0) {
                                foreach ($vv['additionalImages'] as $img) {
                                    // $additionalImages[] = $img['name'].'jpg';
                                    $additionalImages1[] = "https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg";
                                    //  Log::info("https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg");


                                }
                            }

                            $fullMetafields1 [] = [
                                'namespace' => 'images',
                                'key' => 'all',
                                'value' => implode(',', array_unique(array_merge_recursive($additionalImages1, $images1))),
                                'value_type' => 'string'
                            ];

                            ///////////////
                            $variants[$i]['metafields'] = $fullMetafields1;
                            Log::info("1!!!!!!!!!variants");
                            //  Log::info($vv);
                            Log::info("2!!!!!!!!!!!variants2");
                            // $variants[$i]['metafields'] =$metafield;
                            // add options to tags
                            foreach ($options as $option) {
                                if (isset($variants_arr[$x][$i])) {
                                    $variants_arr[$x][$i] .= "-" . $vv[$option];
                                } else {
                                    $variants_arr[$x][$i] = $vv[$option];
                                }
                                $variants[$i]["option$j"] = $vv[$option];
                                if ($option != 'vendorSizeName' || $option != 'shapeSize') {
//
                                    $tags[] = "{$option}_{$vv[$option]}";
                                    //}
                                }
                                $j++;
                            }
                            //add tags to filters
                            foreach ($tagKeys as $tk) {
                                if (isset($vv[$tk]) && $vv[$tk] != '') {
                                    $moreTags[] = "{$tk}_{$vv[$tk]}";
                                }
                            }
                            //add price tag

                            $price=$this->_getPrices($vv);
//                            if($price['special_price'] <= 10 || $price['old_price'] <= 11 )
//                                break;




                            $moreTags[] = 'price_' . $price['special_price'];

                            //add specification
                            foreach ($speciKeys as $speciKey) {
                                if (isset($vv[$speciKey]) && $vv[$speciKey] != '') {
                                    $key = array_search($speciKey, $speciKeys);
                                    $metafields[$key] = "{$speciKey}::{$vv[$speciKey]}";
                                }
                            }
                            ksort($metafields, 1); // sort $metafields by key ASC
                            $i++;

                            $ii = 0;


                            //   $variants[$i]['metafields']=$fullMetafields1;

                            foreach (array_count_values($variants_arr[$x]) as $kkk => $vvv) {
                                if ($vvv > 1) {
                                    for ($y = 0; $y < $vvv; $y++) {
                                        $duplicate_key = array_search($kkk, $variants_arr[$x]);
                                        //                            echo "$kkk - $vvv - $duplicate_key <hr />";
                                        //unset($variants[$duplicate_key]);
                                        if (isset($variants_arr[$x][$duplicate_key])) {
                                            //   unset($variants_arr[$x][$duplicate_key]);
                                        }

                                    }
                                    //                        $duplicate_key = array_search($kkk, $variants_arr[$x]);
                                    //                        unset($variants[$duplicate_key]);
                                    //                        unset($variants_arr[$x][$kkk]);
                                }
                                //                    echo "$kkk => $vvv";
                                //                    echo "<hr />";
                                $ii++;
                            }


                            // end if product is configurable
                        }
                        //images
//                        if (count($vv['images']) > 0) {
//                            foreach ($vv['images'] as $img) {
//                                // $images[] = $img['name'].'jpg';//ALI!@
//                                $images[] = "https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg";
//                                //  Log::info("https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg");
//                            }
//                        }
//                        if (count($vv['additionalImages']) > 0) {
//                            foreach ($vv['additionalImages'] as $img) {
//                                // $additionalImages[] = $img['name'].'jpg';
//                                $additionalImages[] = "https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg";
//                                //  Log::info("https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg");
//
//
//                            }
//                        }
                        // if (count($value['images'])!= 0){
                        $i=1;
                        $shop_images=array();
                        foreach ($value['images'] as $image ){

                            if($i < 3){
                                // $newImages[] = $nnn= ['src' => "https://ecatalog.rminno.net/{$fields['product']['vendor']}/{$value['images'][0]['name']}.jpg"];
                                //  $p=explode(" ",$image['shapeSize']);
                                //   $newImages[$p[0]][] =  env('RMI_SERVER')."{$image['imagePrefixPath']}/{$image['name']}.jpg";
                                //  $newImages_all [] =  env('RMI_SERVER')."{$image['imagePrefixPath']}/{$image['name']}.jpg";
                                $shop_images [] =  "https://rmimages2.blob.core.windows.net/{$image['imagePrefixPath']}/{$image['name']}.jpg";
                            }

                            $i++;
                        }

                        if(isset($shop_images)){
                            //  $shop_images=$newImages_all;
                            // Log :: info ($newImages,$newImages_all);

//                                if(isset ($newImages['Rectangle']) ){
//                                    $shop_images=$newImages['Rectangle'];
//                                }
//                                elseif(isset ($newImages['Runner']) ){
//                                    $shop_images=$newImages['Runner'];
//                                }
//                                else{
//                                    $shop_images=$newImages_all;
//                                }
                            //  }
                            // Log :: info ("value= ",$shop_images);

                            $fullMetafields [] = [
                                'namespace' => 'images',
                                'key' => 'all',
                                'value' => $shop_images[0],
                                'value_type' => 'string'
                            ];
                        }
                        $fields['product']['metafields'] = $fullMetafields;
                        //  $fields['product'][]['metafields'] = $fullMetafields;33fs

                        Log::info("1!!!!!!!!!metafields");
                        // Log::info($metafields);
                        Log::info("2!!!!!!!!!!!metafields2");

                        foreach (array_unique($metafields) as $metafield) {
                            $metafield_arr = explode('::', $metafield);
                            $fullMetafields [] = [
                                'namespace' => 'specifications',
                                'key' => $metafield_arr[0],
                                'value' => $metafield_arr[1],
                                'value_type' => 'string'
                            ];
                        }

                        $fullTags = array_merge_recursive(array_unique($moreTags), array_unique($tags));
                        //$variants[0]['metafields']=$fullMetafields;

                        $fields['product']['metafields'] = $fullMetafields;
                        $fields['product']['tags'] = implode(', ', $fullTags);
                        $fields['product']['variants'] = array_values($variants);
                    } // simple products
                    else {
                        // add price & quantity
                        $quantity1 = (isset($fields['product']['quantity']) ? $fields['product']['quantity'] : 0);//ALI!@


                        $fields['product']['variants'] = [
                            [
                                'price' => $this->_getPrices($value),
                                'inventory_quantity' => $quantity1
                            ]
                        ];

                        //add specification
                        foreach ($speciKeys as $speciKey) {
                            if (isset($value[$speciKey]) && $value[$speciKey] != '') {
                                $metafields[] = "{$speciKey}::{$value[$speciKey]}";
                            }
                        }

                        foreach (array_unique($metafields) as $metafield) {
                            $metafield_arr = explode('::', $metafield);
                            $fullMetafields [] = [
                                'namespace' => 'specifications',
                                'key' => $metafield_arr[0],
                                'value' => $metafield_arr[1],
                                'value_type' => 'string'
                            ];
                        }
                        $fields['product']['metafields'] = $fullMetafields;
                    }

                    // end simple products

                    $fields['product']['images'] = $newImages;

                    // if product inserted in shopify then updated it
                    $product_exists = Subprocess::where(['client_id' => $this->client_id, 'product_id' => $value['_id'] ])->whereNotNull('shopify_product_id')->first();
                    if( !is_null($product_exists) && $product_exists->count() > 0 ){


                        unset($fields['product']['metafields']);
                        $result = $this->shopify->productUpdate($product_exists->shopify_product_id, $fields);
                        $mode = 2; // update shopify product
                        Log::info("1!!!!!!!!!UPDATE");

                        // Log::info($fields);

                        Log::info("2!!!!!!!!!!!2");

                        Log::info("product id: {$value['_id']}, shopify id: $product_exists->shopify_product_id");
                    }
                    else{


                        $result = $this->shopify->productAdd($fields);//ALI!@ add to Shopify Products
                        $mode = 1; // insert shopify product


                    }

                    //insert a new product
                    try{
                        if($result){ // added product to shopify successfully
                            $status = 2;
                            $error = null;
                            $product = $this->shopify->getResponse();
                            $shopify_product_id = (isset($product['product']['id']))?$product['product']['id']:null;
                            log::info('#SSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSS#');
                            Log::info($shopify_product_id);
                            $p=array();
                            // Log::info('$product');
                            // Log:: info($product['product']);
                            foreach ($product['product']['variants'] as $v){
                                $p[]=array('datalinkId'=>$v['sku'],'shopifyId'=>$v['id']);
                                // $p[$v['sku']]=$v['id'];
                            }
                        }
                        else{
                            $status = 3;
                            $error = json_encode($this->shopify->getResponse());
                            $shopify_product_id = null;
//                        Log::info("product id = {$value['_id']}");
                            Log::info($error);
                            // Log::info(json_encode($fields));
                            log::info('########################################');
                        }
                        $row = new Subprocess();
                        $row->client_id = $this->client_id;
                        $row->process_id = $process_id;
                        $row->product_id = $value['_id'];
                        $row->shopify_product_id ="$shopify_product_id";
                        $row->error = $error;
                        $row->mode = $mode;
                        $row->status = $status;
                        $row->varyants_id = json_encode($p);
                        $row->save();
                    }
                    catch (\Exception $e){
                        $rows_err = "$this->client_id, $process_id, {$value['_id']}, $shopify_product_id, $error, $status";
                        Log::info("SAVE ERROR: ".$e->getMessage());
                        Log::info("FIELDS: $rows_err");
                        break;
                    }
                }
                else{
                    try{
                        $row = new Subprocess();
                        $row->client_id = $this->client_id;
                        $row->process_id = $process_id;
                        $row->product_id = $value['_id'];
                        $row->shopify_product_id = 0;
                        $row->error = "no list fund";
                        $row->mode = 1;
                        $row->status = 3;
                        $row->save();
                    }
                    catch (\Exception $e){
                        $rows_err = "$this->client_id, $process_id, {$value['_id']},item is not list product ";
                        Log::info("SAVE ERROR: ".$e->getMessage());
                        Log::info("FIELDS: $rows_err");
                        break;
                    }
                }
                $x++;
                $offset++;

            }

            // Log::info('getbuket!!!!!');
            //  Log::info($bucket_id,'limit=', $limit, 'offset = ',$offset);
            $getBucket = $datalink->getBucket($bucket_id,$keyId, $limit, $offset);
            $data = $datalink->getResponse();
            if(!$getBucket || !isset($data['data'])){
                for( $i=1 ;$i<=10 ;$i++){
                    Log :: info ('datalink_no');
                    sleep(10);
                    $getBucket = $datalink->getBucket($bucket_id,$keyId, $limit, $offset);
                    $data = $datalink->getResponse();
                    if(isset($data['data']['data'])){
                        $i=11;
                    }
                }
            }
        }
        // remove data
        session()->forget('api');
        session()->forget('bucket_id');
        session()->forget('process_id');
        session()->flush();
        // close process
        $this->setProcessStatus($process_id, 3);
        if( $process->email && !is_null($process->email) ){
            Log::info("email: true ");
            $success = Subprocess::where(['process_id' => $process_id, 'status' => 2])->get()->count();
            $fail = Subprocess::where(['process_id' => $process_id, 'status' => 3])->get()->count();
            $this->emailCompleted($process->email, $process->count_products, $success, $fail);
        }
    }
    public function history()
    {
        $data = [
            'page_title' => 'List of your requests',
            'processes' => Process::where(['client_id' => $this->client_id])->get()
        ];
        return view('app.history', $data);
    }
    public function logout(Request $request)
    {
        $request->session()->flush();
        return redirect('/');
    }
    private function emailStarted($email, $url)
    {
        $content = [
            'subject'=> 'Datalink shopify - Starting an import of products',
            'body'=> 'Your data is importing to shopify. You can see processing status on below link',
            'button' => 'Show status of progress',
            'button_url' => $url
        ];
        Mail::to($email)->send(new ProcessStarted($content));
    }
    private function emailCompleted($email, $total, $success, $fail)
    {
        $content = [
            'subject'=> 'Datalink shopify - import completed ',
            'body'=> "Your data imported to shopify.",
            'total' => $total,
            'success' => $success,
            'fail' => $fail
        ];
        Mail::to($email)->send(new ProcessCompleted($content));
    }
    private function getClient()
    {
        return $this->model->whereId($this->client_id)->first();
    }
    private function getPrice_olde( &$data )
    {
        $keys = ['mapPrice', 'salePrice'];
        foreach($keys as $key){
            if( isset($data[$key]) && $data[$key] > 0 ){
                return $data[$key];
            }
        }
    }
    protected function _getPrices($datalinkProduct)
    {
        $oldPrice = 9999.987;
        $findOld=false;
        $findspe=false;
        if (isset($datalinkProduct['p1']) && !empty($datalinkProduct['p1']) && $datalinkProduct['p1'] >=1 ) {
            $oldPrice = $datalinkProduct['p1'];
            $findOld=true;
        }
        else if (isset($datalinkProduct['marketPrice']) && !empty($datalinkProduct['marketPrice'])) {
            $oldPrice = $datalinkProduct['marketPrice'];
            $findOld=true;
        }
        else if (isset($datalinkProduct['msrpPrice']) && !empty($datalinkProduct['msrpPrice'])) {
            $oldPrice = $datalinkProduct['msrpPrice'];
            $findOld=true;
        }
        $specialPrice = 1.1234;
        if (isset($datalinkProduct['p2']) && !empty($datalinkProduct['p2']) && $datalinkProduct['p2'] >=1) {
            $specialPrice = $datalinkProduct['p2'];
            $findspe=true;
        }
        else if (isset($datalinkProduct['salePrice']) && !empty($datalinkProduct['salePrice'])) {
            $specialPrice = $datalinkProduct['salePrice'];
            $findspe=true;
        }
        else if (isset($datalinkProduct['mapPrice']) && !empty($datalinkProduct['mapPrice'])) {
            $specialPrice = $datalinkProduct['mapPrice'];
            $findspe=true;
        }
        if ($oldPrice == 9999999.9876 && $specialPrice != 1.1234) {
            $oldPrice = $specialPrice;
            $specialPrice = null;
            $findOld=true;
        }
        return ['old_price' => $oldPrice, 'special_price' => $specialPrice , 'findspe' => $findspe ,'findOld' => $findOld];
    }
    //get edward
    private function getDesc( &$data )
    {
        $description  = isset($data['collectionDescription']) ? trim($data['collectionDescription']) . "\r\n" : '';
        $description .= isset($data['productDescription']) ? $data['productDescription'] : '';
        //$description .= (isset($data['cares']) && is_array($data['cares']) && count($data['cares']) > 0) ? "Cares: \r\n" . implode("\r\n", $data['cares']) : '';
        $description = utf8_encode(trim($description));
        return trim($description);
    }
    private function setProcessStatus($process_id, $status)
    {
        $process = Process::find($process_id);
        $process->status = $status; // 1:open,2:running,3:close
        $process->save();
    }
    private function Skip_number($process_id, $count_product_insert)
    {
        $process = Process::find($process_id);
        $process->Skip_number = $count_product_insert; // 1:open,2:running,3:close
        $process->save();
    }
    private function setProcesscount($process_id, $count)
    {
        $process = Process::find($process_id);
        $process->count_products = $count; // 1:open,2:running,3:close
        $process->save();
    }
    private function makeProcess($bucket_id, &$data, $email = null)
    {
        if( is_null($this->session_key) ){
            throw new Exception('Session key not set');
        }
        //   Log :: info('$data[ data ][ total ] =',$data['data'] );
        $process = new Process;
        $process->client_id = session($this->session_key);
        $process->bucket_id = $bucket_id;
        $process->tracking_code = str_random(40);
        $process->email = $email;
        $process->count_products = ( isset($data['data']) && isset($data['data']['total']) )?$data['data']['total']:0;
        $process->status = 1; //open
        $process->save();

        return $process->id;
    }
    public function update_data(){
        //print 'is ok';
        $users = DB::table('clients')
            ->join('processes', 'clients.id', '=','processes.client_id')
            ->join('subprocesses', 'subprocesses.process_id', '=', 'processes.id')
            ->select('subprocesses.varyants_id' )
            ->where('clients.shopify_store','=','rmi6.myshopify.com')
            ->where('subprocesses.mode','=','1')
            ->where('processes.bucket_id','=','5ba401eccc0c27bf779f5790')
            //->groupBy('subprocesses.product_id')
            ->get();
        $pp= $users[0]->varyants_id ;

        // [{"datalinkId":"","shopifyId":14061963018313},{"datalinkId":"","shopifyId":14061963411529},{"datalinkId":"5a3ae841785fb07629900e26","shopifyId":14061964001353},{"datalinkId":"5a3ae841785fb07629900e27","shopifyId":14061964460105},{"datalinkId":"5a3ae841785fb07629900e28","shopifyId":14061964689481},{"datalinkId":"5a3ae841785fb07629900e29","shopifyId":14061964820553},{"datalinkId":"5a3ae841785fb07629900e2a","shopifyId":14061964951625},{"datalinkId":"5a3ae841785fb07629900e2b","shopifyId":14061965082697}
        // $array_fildes= array('5a3ae841785fb07629900e24','5a3ae841785fb07629900e25','5a3ae841785fb07629900e26','5a3ae890785fb07629900e43','5a3ae890785fb07629900e4e');
        //echo $q->find('5a3ae841785fb07629900e24');
        foreach ($users as $value){
            //if (strstr($value->varyants_id, '5a3ae841785fb07629900e20')) {
            //  echo 'found a zero';
            $p=\GuzzleHttp\json_decode($value->varyants_id);
            //  dd($p);
            foreach ($p as $parentIndex => $items){
                if ($items->datalinkId =='5a3ae841785fb07629900e20')
                {
                    print "shopify_id = ".$items->shopifyId;
                    break;
                }
                print 1;

            }
            //  break;
            //} else {
            //  echo 'did not find a zero ';
            //}
            print "->". 2 ;
        }
        $process_id = session("process_id");
        $api = session("api");
        $bucket_id = session("bucket_id");
        $keyId = session("keyId");
        if(  is_null($process_id) ){
            return 'Your request is invalid';
        }
        $process = Process::find($process_id);
        if( !$process ){
            return 'Your request is invalid';
        }
        elseif($process->status == 2){
            return 'You can not submit new request until you have a open request';
        }
        elseif($process->status == 3){
            return 'Your request has been closed';
        }
        //dd(1);
        // set status process to running
        $this->setProcessStatus($process_id, 2);
        //commend for test

        $datalink = new DatalinkClient([
            'api_key' => $api,
            'base_url' => env('RMI_BASE_URL'),
            'ip' => env('RMI_IP')
        ]);
        $limit  = 10;
        $offset = 0;
        $getBucket = $datalink->getBucket($bucket_id,$keyId, $limit, $offset);
        $data = $datalink->getResponse();
        if(!$getBucket || !isset($data['data'])){
            Log::info( $data );
            return;
        }
        // Log::info( $data );
        $variants_arr = [[]];
        $count = $data['data']['total'];
        //$count = $data['metadata']['count'];
        // $options_list = ['colorGroupName', 'shapeName', 'sizeName'];
        $options_list = ['shapeSize', 'colorGroupName', 'sizeName'];
        $tagKeys = ['brandName', 'colorGroupName', 'constructionName', 'collectionName', 'fieldDesignName', 'vendorUniqueId', 'originCountryName', 'primaryMaterialName','primaryStyleName', 'sizeName','sku','shapeName','standardSize'];
        $speciKeys = ['brandName', 'designer', 'collectionName', 'designName', 'primaryStyleName', 'colorGroupName', 'originCountryName', 'primaryMaterialName', 'constructionName', 'fieldDesignName','sku','shapeName','standardSize'];
        $x = 0;
//        $myfile = fopen("newfile.txt", "wb") or die("Unable to open file!");
//        $txt = $count;
//        fwrite($myfile, $txt);
//        $txt = "$process_id \n";
//        fwrite($myfile, $txt);
//        fclose($myfile);

        foreach(array_chunk(range(1, $count), $limit) as $z){
//            break;
            if( !isset($data['data']['data'] )){
                $row = new Subprocess();
                $row->process_id = $process_id;
                $row->product_id = 0;
                $row->shopify_product_id = null;
                $row->error = json_encode($this->shopify->getResponse());
                $row->status = 3;
                $row->created_at = date("Y-m-d H:i:s");
                $row->save();
                $x++;
                continue;
            }





            foreach( $data['data']['data'] as  $key => $value )
            {
                try{
                    $options = [];
                    $variants = [];
                    $tags = $moreTags = $fullTags = [];
                    $metafields = $fullMetafields = [];
                    $images = [];
                    $additionalImages = [];
                    // $newImages = [];
                    $fields = [
                        'product' => [
                            // "title"=> $value['title'],
//                        "handle"=> $value['key'],
                            "body_html"=> $this->getDesc($value),
                            "vendor" => $value['vendorUniqueId'],
                            "title" => $value['vendorUniqueId']." " . $value['collectionName']. " ". $value['designName'],
                            "imagePrefixPath" => $value['imagePrefixPath'],
                            "price" => $this->_getPrices($value),
                            "product_type"=> (isset($value['viewTemplatePrefix']))?$value['viewTemplatePrefix']:'rug',
                        ]
                    ];
                    $shop_images=$newImages_all=array();
                    if( isset($value['images']) && isset($value['images'][0]) ){


                        $i_img=1;
                        foreach ($value['images'] as $image ){
                            if($i_img < 2){
                                ;
                                // $newImages[] = $nnn= ['src' => "https://ecatalog.rminno.net/{$fields['product']['vendor']}/{$value['images'][0]['name']}.jpg"];
                                $p=explode(" ",$image['shapeSize']);
                                // $newImages[$p[0]][] =  ['src' =>env('RMI_SERVER')."{$fields['product']['imagePrefixPath']}/{$value['images'][0]['name']}.jpg"];
                                // $newImages_all [] =  ['src' =>env('RMI_SERVER')."{$fields['product']['imagePrefixPath']}/{$value['images'][0]['name']}.jpg"];
                                $shop_images [] =  ['src' =>"https://rmimages2.blob.core.windows.net/{$fields['product']['imagePrefixPath']}/{$value['images'][0]['name']}.jpg"];
                                $i_img++;
                            }
                            break;

                        }
//                    if(isset ($newImages['Rectangle']) ){
//                        $shop_images=$newImages['Rectangle'];
//                    }
//                    elseif(isset ($newImages['Runner']) ){
//                        $shop_images=$newImages['Runner'];
//                    }
//                    else{
//                        $shop_images=$newImages_all;
//                    }
                    }

//                if( isset($value['images']) && isset($value['images'][0]) ){
//                    //   $newImages[] = $nnn= ['src' => "https://ecatalog.rminno.net/{$fields['product']['vendor']}/{$value['images'][0]['name']}.jpg"];
//                    $newImages[] =  ['src' => "https://rmimages2.blob.core.windows.net/{$fields['product']['imagePrefixPath']}/{$value['images'][0]['name']}.jpg"];
//                }
                    //  5b7144e3a1300c88575207c6

                    if( $value['productType'] == 'configurable' ){

                        $fields['product']['options'] = [];
                        foreach ($value['productTypeConfig']['options'] as $k => $v){
                            $key = array_search($v['name'], $options_list);
                            $options[$key] = $v['name'];
                            $fields['product']['options'][$key] = ["name" => $v['name']];
                        }
                        // sort arrays by key to asc
                        ksort($options, 1);
                        ksort($fields['product']['options'], 1);

                        $simples = ['data' => $value['simpleList']];
                        $i = 0;
                        foreach( $simples['data'] as $kk => $vv ){
                            // variants
                            $j = 1;

                            $quantity = (isset($vv['quantity']) ? $vv['quantity'] : 0);//ALI!@
                            $weight = (isset($vv['weight']) ? $vv['weight'] : 0);//ALI!@
                            $variants[$i] = [
                                //'title' => $vv['title'],
                                'price' => $this->_getPrices($vv),
                                'inventory_quantity' => $quantity,
                                "sku" => $vv['id'],
                                // "sku" => $vv['sku'],
                                "barcode" => $vv['gs1'],
                                'weight' =>$weight,


                            ];
                            $List_metafides_variant=array(
                                'originCountryName',
                                'productDescription',
                                'collectionName',
                                'vendorColorGroupName',
                                'shapeName',
                                'primaryMaterialName',
                                'designName',
                                'primaryStyleName',
                                'colorGroupName',
                                'constructionName',
                                'actualSize',
                                'standardSize',
                                // 'images',
                                // 'additionalImages',
                                'secondaryColorName',
                                'shapeSize',
                                'title',
                                //   'imagePrefixPath'
                            );
                            $fullMetafields1=$images1=$additionalImages1=array();


                            foreach ($List_metafides_variant as  $varants) {
                                if (isset($vv[$varants])){
                                    $fullMetafields1 [] = [
                                        'namespace' => 'specifications',
                                        'key' => $varants,
                                        'value' => $vv[$varants],
                                        'value_type' => 'string'
                                    ];
                                }
                            }

                            //////////////////////////

                            if( count($vv['images']) > 0 ){
                                foreach($vv['images'] as $img){
                                    // $images[] = $img['name'].'jpg';//ALI!@
                                    $images1[] ="https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg";
                                    //  Log::info("https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg");

                                }
                            }
                            if( count($vv['additionalImages']) > 0 ){
                                foreach($vv['additionalImages'] as $img){
                                    // $additionalImages[] = $img['name'].'jpg';
                                    $additionalImages1[] ="https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg";
                                    //  Log::info("https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg");


                                }
                            }

                            $fullMetafields1 [] = [
                                'namespace' => 'images',
                                'key' => 'all',
                                'value' => implode( ',', array_unique(array_merge_recursive($additionalImages1, $images1)) ),
                                'value_type' => 'string'
                            ];

                            ///////////////







                            $variants[$i]['metafields']=$fullMetafields1;



                            Log::info("1!!!!!!!!!variants");

                            Log::info($vv);

                            Log::info("2!!!!!!!!!!!variants2");


                            // $variants[$i]['metafields'] =$metafield;



                            // add options to tags
                            foreach ( $options as $option ){
                                if( isset($variants_arr[$x][$i]) ){
                                    $variants_arr[$x][$i] .= "-".$vv[$option];
                                }
                                else{
                                    $variants_arr[$x][$i] = $vv[$option];
                                }
                                $variants[$i]["option$j"] = $vv[$option];
                                if( $option != 'vendorSizeName'  || $option != 'shapeSize'){
//
                                    $tags[] = "{$option}_{$vv[$option]}";
                                    //}
                                }
                                $j++;
                            }
                            //add tags to filters
                            foreach( $tagKeys as $tk ){
                                if( isset($vv[$tk]) && $vv[$tk] != '' ){
                                    $moreTags[] = "{$tk}_{$vv[$tk]}";
                                }
                            }
                            //add price tag
                            //  $moreTags[] = 'price_'.$this->_getPrices($vv);
                            $price=$this->_getPrices($vv);
//                            if($price['special_price'] <= 10 || $price['old_price'] <= 11 )
//                                break;




                            $moreTags[] = 'price_' . $price['special_price'];

                            //add specification
                            foreach( $speciKeys as $speciKey ){
                                if( isset($vv[$speciKey]) && $vv[$speciKey] != '' ){
                                    $key = array_search($speciKey, $speciKeys);
                                    $metafields[$key] = "{$speciKey}::{$vv[$speciKey]}";
                                }
                            }
                            ksort($metafields, 1); // sort $metafields by key ASC
                            $i++;

                            $ii = 0;


                            //   $variants[$i]['metafields']=$fullMetafields1;

                            foreach( array_count_values($variants_arr[$x]) as $kkk => $vvv ){
                                if( $vvv > 1 ){
                                    for($y = 0; $y < $vvv; $y++){
                                        $duplicate_key = array_search($kkk, $variants_arr[$x]);
                                        //                            echo "$kkk - $vvv - $duplicate_key <hr />";
                                        unset($variants[$duplicate_key]);
                                        if( isset($variants_arr[$x][$duplicate_key]) ){
                                            unset($variants_arr[$x][$duplicate_key]);
                                        }

                                    }
                                    //                        $duplicate_key = array_search($kkk, $variants_arr[$x]);
                                    //                        unset($variants[$duplicate_key]);
                                    //                        unset($variants_arr[$x][$kkk]);
                                }
                                //                    echo "$kkk => $vvv";
                                //                    echo "<hr />";
                                $ii++;
                            }


                            // end if product is configurable
                        }

                        //images
                        if( count($vv['images']) > 0 ){
                            foreach($vv['images'] as $img){
                                // $images[] = $img['name'].'jpg';//ALI!@
                                $images[] ="https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg";
                                //  Log::info("https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg");

                            }
                        }
                        if( count($vv['additionalImages']) > 0 ){
                            foreach($vv['additionalImages'] as $img){
                                // $additionalImages[] = $img['name'].'jpg';
                                $additionalImages[] ="https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg";
                                //  Log::info("https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg");


                            }
                        }

                        $fullMetafields [] = [
                            'namespace' => 'images',
                            'key' => 'all',
                            'value' => implode( ',', array_unique(array_merge_recursive($additionalImages, $images)) ),
                            'value_type' => 'string'
                        ];
                        $fields['product']['metafields'] = $fullMetafields;
                        //  $fields['product'][]['metafields'] = $fullMetafields;33fs


                        Log::info("1!!!!!!!!!metafields");

                        //  Log::info($metafields);

                        Log::info("2!!!!!!!!!!!metafields2");

                        foreach(array_unique($metafields) as $metafield ){
                            $metafield_arr = explode('::', $metafield);
                            $fullMetafields [] = [
                                'namespace' => 'specifications',
                                'key' => $metafield_arr[0],
                                'value' => $metafield_arr[1],
                                'value_type' => 'string'
                            ];
                        }

                        $fullTags = array_merge_recursive(array_unique($moreTags), array_unique($tags));
                        //$variants[0]['metafields']=$fullMetafields;

                        $fields['product']['metafields'] = $fullMetafields;
                        $fields['product']['tags'] = implode(', ', $fullTags);
                        $fields['product']['variants'] = array_values($variants);
                        $quantity1 = isset($vv['quantity']) ? $vv['quantity'] : 0;//ALI!@
                        // add price & quantity


                        $fields['product']['variants'] = [
                            [
                                // 'price' => $this->_getPrices($value),
                                'inventory_quantity' => $quantity1
                            ]
                        ];

                    } // simple products
                    else{


                        //add specification
                        foreach( $speciKeys as $speciKey ){
                            if( isset($value[$speciKey]) && $value[$speciKey] != '' ){
                                $metafields[] = "{$speciKey}::{$value[$speciKey]}";
                            }
                        }

                        foreach(array_unique($metafields) as $metafield ){
                            $metafield_arr = explode('::', $metafield);
                            $fullMetafields [] = [
                                'namespace' => 'specifications',
                                'key' => $metafield_arr[0],
                                'value' => $metafield_arr[1],
                                'value_type' => 'string'
                            ];
                        }
                        $fields['product']['metafields'] = $fullMetafields;
                    }
                    // end simple products
                    $fields['product']['images'] = array_unique($shop_images);
                    // if product inserted in shopify then updated it
                    $product_exists = Subprocess::where(['client_id' => $this->client_id, 'product_id' => $value['_id'] ])->whereNotNull('shopify_product_id')->first();
                    if( !is_null($product_exists) && $product_exists->count() > 0 ){
                        unset($fields['product']['metafields']);
                        $result = $this->shopify->productUpdate($product_exists->shopify_product_id, $fields);
                        $mode = 2; // update shopify product
                        Log::info("1!!!!!!!!!UPDATE");

                        Log::info($fields);

                        Log::info("2!!!!!!!!!!!2");

                        Log::info("product id: {$value['_id']}, shopify id: $product_exists->shopify_product_id");
                    }
                    else{


                        $result = $this->shopify->productAdd($fields);//ALI!@ add to Shopify Products
                        $mode = 1; // insert shopify product
                        Log::info("1!!!!!!!!!1");

                        Log::info(json_encode($fields));

                        Log::info("2!!!!!!!!!!!2");

                    }

                    //insert a new product

                    if($result){ // added product to shopify successfully
                        $status = 2;
                        $error = null;
                        $product = $this->shopify->getResponse();
                        $shopify_product_id = (isset($product['product']['id']))?$product['product']['id']:null;
                        log::info('#SSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSS#');
                        Log::info(json_encode($product));
                        $p=array();


                        foreach ($product['product']['variants'] as $v){

                            $p[]=array('datalinkId'=>$v['sku'],'shopifyId'=>$v['id']);

                            // $p[$v['sku']]=$v['id'];
                        }
                    }
                    else{
                        $status = 3;
                        $error = json_encode($this->shopify->getResponse());
                        $shopify_product_id = null;
                        //                        Log::info("product id = {$value['_id']}");
                        Log::info($error);
                        Log::info(json_encode($fields));
                        log::info('########################################');
                    }

                    // log6::info('#PPPPPPPPPPPPPPPPPPPPPPPPPP#');
                    //  Log::info(json_encode($p));


                    $row = new Subprocess();
                    $row->client_id = $this->client_id;
                    $row->process_id = $process_id;
                    $row->product_id = $value['_id'];
                    $row->shopify_product_id = "$shopify_product_id";
                    $row->error = $error;
                    $row->mode = $mode;
                    $row->status = $status;
                    $row->varyants_id = json_encode($p);

                    $row->save();
                }
                catch (Exception $e) {
                    $row = new Subprocess();
                    $row->client_id = $this->client_id;
                    $row->process_id = $process_id;
                    $row->product_id = $value['_id'];
                    $row->shopify_product_id = 0;
                    $row->error = 'error';
                    $row->mode = 0;
                    $row->status = 3;
                    // $row->varyants_id = json_encode($p);

                    $row->save();

                    // echo 'Caught exception: ',  $e->getMessage(), "\n";
                }
                $x++;
                $offset++;

            }

            // $getBucket = $datalink->getBucket($bucket_id, $limit, $offset);
            $getBucket = $datalink->getBucket($bucket_id,$keyId, $limit, $offset);
            $data = $datalink->getResponse();
            if(!isset($data['data']['data']) ){
                for( $i=1 ;$i<=10 ;$i++){
                    Log :: info ('datalink no');
                    sleep(10);
                    $getBucket = $datalink->getBucket($bucket_id,$keyId, $limit, $offset);
                    $data = $datalink->getResponse();
                    if(isset($data['data']['data'])){
                        $i=11;
                    }

                }
            }
        }

        // remove data
        session()->forget('api');
        session()->forget('bucket_id');
        session()->forget('process_id');
        session()->flush();

        // close process
        $this->setProcessStatus($process_id, 3);

        if( $process->email && !is_null($process->email) ){
            Log::info("email: true ");
            $success = Subprocess::where(['process_id' => $process_id, 'status' => 2])->get()->count();
            $fail = Subprocess::where(['process_id' => $process_id, 'status' => 3])->get()->count();
            $this->emailCompleted($process->email, $process->count_products, $success, $fail);
        }
        dd(($users ) );
    }
    public function insert_auto( Request $request ){
        // $pe=$
        Log ::info ('$request');
        Log:: info($request);
        $bucketList=\GuzzleHttp\json_decode($request['bucketList'],true);
        Log :: info ('request');
        Log :: info ($bucketList);
        Log :: info ('request_end');
        //return $bucketList;
        if( !isset ( $bucketList[0]['email']) ){

            $ret['type'] = true;
            $a[0]['status'] = 'Parameter key is not valid';
            $a[0]['name'] = 'shopify';
            $a[0]['states'] = 'no list';
            //$a[$KEY]['id_clients'] = $clients[0]->id;
            $ret['message'] = $a;
            $state_return= '"' . str_replace('"', '\"', json_encode($ret)) . '"';
            // return   $state_return;
        }
        foreach ($bucketList as $KEY=>$bucket) {
            // $id=$bucket['id'];
            Log :: info ('$bucket[\'state\']');
            Log :: info ($bucket['state']);
            Log :: info ('request_end');
            switch ($bucket['state']){
                case  'getStatus' :
                    $state_return=$this->state_getStatus($bucket,$KEY);
                    break;
                case 'created' :
                    $state_return=$this->state_created($bucket,$KEY);
                    break;
                case 'stop' :
                    $state_return=$this->status_stop($bucket,$KEY);
                    break;
                case 'deleted' :
                    $state_return=$this->status_deleted($bucket,$KEY);
                    break;
                default :
                    $ret['type'] = true;
                    $a[$KEY]['status'] = 'Parameter key is not valid';
                    $a[$KEY]['name'] = 'shopify';
                    $a[$KEY]['states'] = $bucket['state'];
                    //$a[$KEY]['id_clients'] = $clients[0]->id;
                    $ret['message'] = $a;
                    $state_return= '"' . str_replace('"', '\"', json_encode($ret)) . '"';
                    break;
            }
            return $state_return;
//            if ($bucket['state'] == 'getStatus') {
//                /////////////////
//            }
//            elseif ($bucket['state'] == 'created') {
//
//            }
//            elseif ($bucket['state'] == 'stop') {
//
//            }
        }
    }
    //$run = $this->run_auto($api_key, $backet_id, $api_key, $this->client_id);
    //[2018-11-03 16:13:38] local.INFO: c7518dbf088e35957fad0fd3b54411d0 , 5bddae94b66d6bae056f9530, c7518dbf088e35957fad0fd3b54411d0 , 39
    public function run_auto ($api,$bucket_id,$keyId,$client_id){
        $process = new Process;
        $process->client_id = $client_id;
        $process->bucket_id =  $bucket_id;
        $process->tracking_code = str_random(40);
        // $process->email = $email;
        $process->count_products = 0;
        $process->status = 1; //open
        $process->save();
        $process_id = $process->id;
        if(  is_null($process_id) ){
            return 'Your request is invalid';
        }
        $process = Process::find($process_id);
        if( !$process ){
            return 'Your request is invalid';
        }
        elseif($process->status == 2){
            return 'You can not submit new request until you have a open request';
        }
        elseif($process->status == 3){
            return 'Your request has been closed';
        }
        //dd(1);
        // set status process to running
        $this->setProcessStatus($process_id, 2);
        //commend for test
        $datalink = new DatalinkClient([
            'api_key' => $api,
            'base_url' => env('RMI_BASE_URL'),
            'ip' => env('RMI_IP')
        ]);
        $limit  = 10;
        $offset = 0;
        log :: info ("inrrrrrrrrrrrrrrrrrrrr");
        Log :: info ( "$bucket_id,$keyId, $limit, $offset ");
        $getBucket = $datalink->getBucket($bucket_id,$keyId, $limit, $offset);
        $data = $datalink->getResponse();

        //Log :: info ( $data);

        Log :: info ('$data');
        Log::info( $data );

        $variants_arr = [[]];
        if(!isset($data['data'])){
            for( $i=1 ;$i<=10 ;$i++){
                sleep(10);
                $getBucket = $datalink->getBucket($bucket_id,$keyId, $limit, $offset);
                $data = $datalink->getResponse();
                if(isset($data['data']['data'])){
                    $i=11;
                }
            }
            if(!isset($data['data']['data'])) {
                return 'no get items!';
                $this->setProcessStatus($process_id, 3);
            }
        }
        $count = $data['data']['total'];
        $this->setProcesscount($process_id,$count);

        //$count = $data['metadata']['count'];
        // $options_list = ['colorGroupName', 'shapeName', 'sizeName'];
        $options_list = ['shapeSize', 'colorGroupName', 'sizeName'];
        $tagKeys = ['brandName', 'colorGroupName', 'constructionName', 'collectionName', 'fieldDesignName', 'vendorUniqueId', 'originCountryName', 'primaryMaterialName','primaryStyleName', 'sizeName','sku','shapeName','standardSize'];
        $speciKeys = ['brandName', 'designer', 'collectionName', 'designName', 'primaryStyleName', 'colorGroupName', 'originCountryName', 'primaryMaterialName', 'constructionName', 'fieldDesignName','sku','shapeName','standardSize'];
        $x = 0;
//        $myfile = fopen("newfile.txt", "wb") or die("Unable to open file!");
//        $txt = $count;
//        fwrite($myfile, $txt);
//        $txt = "$process_id \n";
//        fwrite($myfile, $txt);
//        fclose($myfile);
        foreach(array_chunk(range(1, $count), $limit) as $z){
//            break;

            if( !$getBucket ){
                $row = new Subprocess();
                $row->process_id = $process_id;
                $row->product_id = 0;
                $row->shopify_product_id = null;
                $row->error = "not get bucket";
                $row->status = 3;
                $row->created_at = date("Y-m-d H:i:s");
                $row->save();
                $x++;

                $this->setProcessStatus($process_id, 3);



                continue;
            }
            $count_product_insert=0;
            foreach( $data['data']['data'] as  $key => $value )
            {
                try{
                    $this->Skip_number($process_id,$count_product_insert);
                    // $this->setProcesscount($process_id,$count);
                    $process = Process::find($process_id);
                    if( $process->status == 4 ||   $process->status == 3 ){
                        return false  ;
                    }
                    $count_product_insert++;
                    $options = [];
                    $variants = [];
                    $tags = $moreTags = $fullTags = [];
                    $metafields = $fullMetafields = [];
                    $images = [];
                    $additionalImages = [];
                    $newImages = [];
                    $fields = [
                        'product' => [
                            //   "title"=> $value['title'],
//                        "handle"=> $value['key'],
                            "title" => $value['vendorUniqueId']." " . $value['collectionName']. " ". $value['designName'],
                            "body_html"=> $this->getDesc($value),
                            "vendor" => $value['vendorUniqueId'],
                            "imagePrefixPath" => $value['imagePrefixPath'],
                            "price" => $this->_getPrices($value),
                            "product_type"=> (isset($value['viewTemplatePrefix']))?$value['viewTemplatePrefix']:'rug',
                        ]
                    ];
                    //  5b7144e3a1300c88575207c6
                    if(($value['productType'] == 'configurable' && count($value['simpleList'])>0 ) || $value['productType'] != 'configurable' ) {

                        if( $value['productType'] == 'configurable'   ){

                            Log::info("390!!!!!value");
                            Log::info($value);
                            Log::info("2!!!!!!!!!! ");
                            $fields['product']['options'] = [];
                            foreach ($value['productTypeConfig']['options'] as $k => $v) {
                                $key = array_search($v['name'], $options_list);
                                $options[$key] = $v['name'];
                                $fields['product']['options'][$key] = ["name" => $v['name']];
                            }
                            // sort arrays by key to asc
                            ksort($options, 1);
                            ksort($fields['product']['options'], 1);
                            $simples = ['data' => $value['simpleList']];
                            $i = 0;
                            foreach ($simples['data'] as $kk => $vv) {
                                // variants v
                                $j = 1;
                                $quantity = (isset($vv['quantity']) ? $vv['quantity'] : 0);//ALI!@
                                $weight = (isset($vv['weight']) ? $vv['weight'] : 0);//ALI!@
                                $price=$this->_getPrices($vv);
//                            if($price['special_price'] <= 10 || $price['old_price'] <= 11 )
//                                break;

                                $variants[$i] = [
                                    //'title' => $vv['title'],
                                    //'price' => $this->_getPrices($vv),
                                    'compare_at_price' => $price['old_price'],
                                    'price' => $price['special_price'],
                                    'inventory_quantity' => $quantity,
                                    "sku" => $vv['id'],
                                    //"sku" => $vv['sku'],
                                    "barcode" => $vv['gs1'],
                                    'weight' => $weight,
                                ];
                                $List_metafides_variant = array(
                                    'originCountryName',
                                    'productDescription',
                                    'collectionName',
                                    'vendorColorGroupName',
                                    'shapeName',
                                    'primaryMaterialName',
                                    'designName',
                                    'primaryStyleName',
                                    'colorGroupName',
                                    'constructionName',
                                    'actualSize',
                                    'standardSize',
                                    // 'images',
                                    // 'additionalImages',
                                    'secondaryColorName',
                                    'shapeSize',
                                    'sku',
                                    'title',
                                    //   'imagePrefixPath'
                                );
                                $fullMetafields1=$images1=$additionalImages1=array();
                                foreach ($List_metafides_variant as  $varants) {
                                    if (isset($vv[$varants]) && $vv[$varants] != '' && !empty($vv[$varants]))  {
                                        $fullMetafields1 [] = [
                                            'namespace' => 'specifications',
                                            'key' => $varants,
                                            'value' => $vv[$varants],
                                            'value_type' => 'string'
                                        ];
                                    }
                                }

                                //////////////////////////

                                if (count($vv['images']) > 0) {
                                    foreach ($vv['images'] as $img) {
                                        // $images[] = $img['name'].'jpg';//ALI!@
                                        $images1[] = "https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg";
                                        //  Log::info("https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg");

                                    }
                                }
                                if (count($vv['additionalImages']) > 0) {
                                    foreach ($vv['additionalImages'] as $img) {
                                        // $additionalImages[] = $img['name'].'jpg';
                                        $additionalImages1[] = "https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg";
                                        //  Log::info("https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg");


                                    }
                                }

                                $fullMetafields1 [] = [
                                    'namespace' => 'images',
                                    'key' => 'all',
                                    'value' => implode(',', array_unique(array_merge_recursive($additionalImages1, $images1))),
                                    'value_type' => 'string'
                                ];

                                ///////////////
                                $variants[$i]['metafields'] = $fullMetafields1;
                                Log::info("1!!!!!!!!!variants");
                                //  Log::info($vv);
                                Log::info("2!!!!!!!!!!!variants2");
                                // $variants[$i]['metafields'] =$metafield;
                                // add options to tags
                                foreach ($options as $option) {
                                    if (isset($variants_arr[$x][$i])) {
                                        $variants_arr[$x][$i] .= "-" . $vv[$option];
                                    } else {
                                        $variants_arr[$x][$i] = $vv[$option];
                                    }
                                    $variants[$i]["option$j"] = $vv[$option];
                                    if ($option != 'vendorSizeName' || $option != 'shapeSize') {
//
                                        $tags[] = "{$option}_{$vv[$option]}";
                                        //}
                                    }
                                    $j++;
                                }
                                //add tags to filters
                                foreach ($tagKeys as $tk) {
                                    if (isset($vv[$tk]) && $vv[$tk] != '') {
                                        $moreTags[] = "{$tk}_{$vv[$tk]}";
                                    }
                                }
                                //add price tag
                                // $moreTags[] = 'price_' . $this->__getPrices($vv);
                                $price=$this->_getPrices($vv);
//                            if($price['special_price'] <= 10 || $price['old_price'] <= 11 )
//                                break;




                                $moreTags[] = 'price_' . $price['special_price'];

                                //add specification
                                foreach ($speciKeys as $speciKey) {
                                    if (isset($vv[$speciKey]) && $vv[$speciKey] != '') {
                                        $key = array_search($speciKey, $speciKeys);
                                        $metafields[$key] = "{$speciKey}::{$vv[$speciKey]}";
                                    }
                                }
                                ksort($metafields, 1); // sort $metafields by key ASC
                                $i++;

                                $ii = 0;


                                //   $variants[$i]['metafields']=$fullMetafields1;

                                foreach (array_count_values($variants_arr[$x]) as $kkk => $vvv) {
                                    if ($vvv > 1) {
                                        for ($y = 0; $y < $vvv; $y++) {
                                            $duplicate_key = array_search($kkk, $variants_arr[$x]);
                                            //                            echo "$kkk - $vvv - $duplicate_key <hr />";
                                            //unset($variants[$duplicate_key]);
                                            if (isset($variants_arr[$x][$duplicate_key])) {
                                                //   unset($variants_arr[$x][$duplicate_key]);
                                            }

                                        }
                                        //                        $duplicate_key = array_search($kkk, $variants_arr[$x]);
                                        //                        unset($variants[$duplicate_key]);
                                        //                        unset($variants_arr[$x][$kkk]);
                                    }
                                    //                    echo "$kkk => $vvv";
                                    //                    echo "<hr />";
                                    $ii++;
                                }


                                // end if product is configurable
                            }
                            //images
//                        if (count($vv['images']) > 0) {
//                            foreach ($vv['images'] as $img) {
//                                // $images[] = $img['name'].'jpg';//ALI!@
//                                $images[] = "https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg";
//                                //  Log::info("https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg");
//                            }
//                        }
//                        if (count($vv['additionalImages']) > 0) {
//                            foreach ($vv['additionalImages'] as $img) {
//                                // $additionalImages[] = $img['name'].'jpg';
//                                $additionalImages[] = "https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg";
//                                //  Log::info("https://rmimages2.blob.core.windows.net/{$vv['imagePrefixPath']}/{$img['name']}.jpg");
//
//
//                            }
//                        }
                            // if (count($value['images'])!= 0){
                            $i=1;
                            $shop_images=array();
                            foreach ($value['images'] as $image ){

                                if($i < 3){
                                    // $newImages[] = $nnn= ['src' => "https://ecatalog.rminno.net/{$fields['product']['vendor']}/{$value['images'][0]['name']}.jpg"];
                                    $p=explode(" ",$image['shapeSize']);
                                    //   $newImages[$p[0]][] =  env('RMI_SERVER')."{$image['imagePrefixPath']}/{$image['name']}.jpg";
                                    //  $newImages_all [] =  env('RMI_SERVER')."{$image['imagePrefixPath']}/{$image['name']}.jpg";
                                    $shop_images [] =   "https://rmimages2.blob.core.windows.net/{$image['imagePrefixPath']}/{$image['name']}.jpg";
                                }

                                $i++;
                            }

                            if(isset($shop_images)){
                                //  $shop_images=$newImages_all;
                                // Log :: info ($newImages,$newImages_all);

//                                if(isset ($newImages['Rectangle']) ){
//                                    $shop_images=$newImages['Rectangle'];
//                                }
//                                elseif(isset ($newImages['Runner']) ){
//                                    $shop_images=$newImages['Runner'];
//                                }
//                                else{
//                                    $shop_images=$newImages_all;
//                                }
                                //  }
                                // Log :: info ("value= ",$shop_images);

                                $fullMetafields [] = [
                                    'namespace' => 'images',
                                    'key' => 'all',
                                    'value' => $shop_images[0],
                                    'value_type' => 'string'
                                ];
                            }
                            $fields['product']['metafields'] = $fullMetafields;
                            //  $fields['product'][]['metafields'] = $fullMetafields;33fs

                            Log::info("1!!!!!!!!!metafields");
                            // Log::info($metafields);
                            Log::info("2!!!!!!!!!!!metafields2");

                            foreach (array_unique($metafields) as $metafield) {
                                $metafield_arr = explode('::', $metafield);
                                $fullMetafields [] = [
                                    'namespace' => 'specifications',
                                    'key' => $metafield_arr[0],
                                    'value' => $metafield_arr[1],
                                    'value_type' => 'string'
                                ];
                            }

                            $fullTags = array_merge_recursive(array_unique($moreTags), array_unique($tags));
                            //$variants[0]['metafields']=$fullMetafields;

                            $fields['product']['metafields'] = $fullMetafields;
                            $fields['product']['tags'] = implode(', ', $fullTags);
                            $fields['product']['variants'] = array_values($variants);
                        } // simple products
                        else {
                            // add price & quantity
                            $quantity1 = (isset($fields['product']['quantity']) ? $fields['product']['quantity'] : 0);//ALI!@


                            $fields['product']['variants'] = [
                                [
                                    'price' => $this->__getPrices($value),
                                    'inventory_quantity' => $quantity1
                                ]
                            ];

                            //add specification
                            foreach ($speciKeys as $speciKey) {
                                if (isset($value[$speciKey]) && $value[$speciKey] != '') {
                                    $metafields[] = "{$speciKey}::{$value[$speciKey]}";
                                }
                            }

                            foreach (array_unique($metafields) as $metafield) {
                                $metafield_arr = explode('::', $metafield);
                                $fullMetafields [] = [
                                    'namespace' => 'specifications',
                                    'key' => $metafield_arr[0],
                                    'value' => $metafield_arr[1],
                                    'value_type' => 'string'
                                ];
                            }
                            $fields['product']['metafields'] = $fullMetafields;
                        }

                        // end simple products

                        $fields['product']['images'] = $newImages;

                        // if product inserted in shopify then updated it
                        $product_exists = Subprocess::where(['client_id' => $this->client_id, 'product_id' => $value['_id'] ])->whereNotNull('shopify_product_id')->first();
                        if( !is_null($product_exists) && $product_exists->count() > 0 ){
                            unset($fields['product']['metafields']);
                            $result = $this->shopify->productUpdate($product_exists->shopify_product_id, $fields);
                            $mode = 2; // update shopify product
                            Log::info("1!!!!!!!!!UPDATE");
                            // Log::info($fields);
                            Log::info("2!!!!!!!!!!!2");
                            Log::info("product id: {$value['_id']}, shopify id: $product_exists->shopify_product_id");
                        }
                        else{
                            $result = $this->shopify->productAdd($fields);//ALI!@ add to Shopify Products
                            $mode = 1; // insert shopify product
                        }
                        //insert a new product
                        try{
                            if($result){ // added product to shopify successfully
                                $status = 2;
                                $error = null;
                                $product = $this->shopify->getResponse();
                                $shopify_product_id = (isset($product['product']['id']))?$product['product']['id']:null;
                                log::info('#SSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSS#');
                                Log::info($shopify_product_id);
                                $p=array();
                                // Log::info('$product');
                                // Log:: info($product['product']);
                                foreach ($product['product']['variants'] as $v){
                                    $p[]=array('datalinkId'=>$v['sku'],'shopifyId'=>$v['id']);
                                    // $p[$v['sku']]=$v['id'];
                                }
                            }
                            else{
                                $status = 3;
                                $error = json_encode($this->shopify->getResponse());
                                $shopify_product_id = null;
//                        Log::info("product id = {$value['_id']}");
                                Log::info($error);
                                // Log::info(json_encode($fields));
                                log::info('########################################');
                            }
                            $row = new Subprocess();
                            $row->client_id = $this->client_id;
                            $row->process_id = $process_id;
                            $row->product_id = $value['_id'];
                            $row->shopify_product_id ="$shopify_product_id";
                            $row->error = $error;
                            $row->mode = $mode;
                            $row->status = $status;
                            $row->varyants_id = json_encode($p);
                            $row->save();
                        }
                        catch (\Exception $e){
                            $rows_err = "$this->client_id, $process_id, {$value['_id']}, $shopify_product_id, $error, $status";
                            Log::info("SAVE ERROR: ".$e->getMessage());
                            Log::info("FIELDS: $rows_err");
                            break;
                        }
                    }
                    else{
                        try{
                            $row = new Subprocess();
                            $row->client_id = $this->client_id;
                            $row->process_id = $process_id;
                            $row->product_id = $value['_id'];
                            $row->shopify_product_id = 0;
                            $row->error = "no list fund";
                            $row->mode = 1;
                            $row->status = 3;
                            $row->save();
                        }
                        catch (\Exception $e){
                            $rows_err = "$this->client_id, $process_id, {$value['_id']},item is not list product ";
                            Log::info("SAVE ERROR: ".$e->getMessage());
                            Log::info("FIELDS: $rows_err");
                            break;
                        }
                    }
                    $x++;
                    $offset++;
                }

                catch (\Exception $e){
                    $row = new Subprocess();
                    $row->process_id = $process_id;
                    $row->product_id = 0;
                    $row->shopify_product_id = null;
                    $row->error = "error not find";
                    $row->status = 3;
                    $row->created_at = date("Y-m-d H:i:s");
                    $row->save();
                    $x++;
                    $offset++;


                }
            }
            // Log::info('getbuket!!!!!');
            //  Log::info($bucket_id,'limit=', $limit, 'offset = ',$offset);
            $getBucket = $datalink->getBucket($bucket_id,$keyId, $limit, $offset);
            $data = $datalink->getResponse();
            if(!$getBucket || !isset($data['data']))
            {
                for( $i=1 ;$i<=10 ;$i++){
                    sleep(10);
                    $getBucket = $datalink->getBucket($bucket_id,$keyId, $limit, $offset);
                    $data = $datalink->getResponse();
                    if(isset($data['data']['data'])){
                        $i=11;
                    }
                }
            }
        }
        $this->setProcessStatus($process_id, 3);
        if( $process->email && !is_null($process->email) ){
            Log::info("email: true ");
            $success = Subprocess::where(['process_id' => $process_id, 'status' => 2])->get()->count();
            $fail = Subprocess::where(['process_id' => $process_id, 'status' => 3])->get()->count();
            $this->emailCompleted($process->email, $process->count_products, $success, $fail);
        }

    }
    public function setKey(Request $request){
        //   Log::info(   $request );
        $magento_user=$request->magento_user;
        $magento_pass=$request->magento_pass;
        $users = DB::table('clients')
            ->select('*' )
            ->where('shopify_store','=',$magento_user)
            ->where('password','=',$magento_pass)
            // ->where('processes.bucket_id','=','5ba401eccc0c27bf779f5790')
            //->groupBy('subprocesses.product_id')
            ->get();
        if(!isset($users[0]->id)){
            $p=[
                'type'=>'false',
                'message'=>'User is not found'
            ];
            return  json_encode($p);

        }
        $config = array(
            "digest_alg" => "sha512",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        );
        $res = openssl_pkey_new($config);
        $priKey=openssl_pkey_export($res, $privKey);
        $pubKey = openssl_pkey_get_details($res);




        $result=base64_encode($pubKey["key"]);
//        $return['type']=>true;
//        $return['salt']=>"dfikdsjfsdnsdfvjdrtsdfjcgdsleoi";
//        $return['key']=>$result;

        $return=array(
            'type'=>true,
            'salt'=>'dfikdsjfsdnsdfvjdrtsdfjcgdsleoi',
            'key'=>$result
        );


        $f='"{\"type\":true,\"salt\":\"rminno_wordpress_plugin_configed_for_woocommerce\",\"key\":\"'.$result.'\"}"';




        $id=$users[0]->id;
        $time=time();
        $privKey = base64_encode($privKey);
        $pubKey1 =  base64_encode($pubKey["key"]);

//        openssl_sign("RSAKEY", $signature, $pubKey["key"], OPENSSL_ALGO_SHA256);
//
//        $signature1= base64_encode($signature);
        DB::table('clients')->where('id', $id)->update([
            'my_private_key'      => $privKey,
            'my_public_key'             =>$pubKey1,
            'my_settime_key' => $time,
        ]);
        $p=json_encode($return);

        return ($f);


    }
    public function test(){
        $config = array(
            "digest_alg" => "sha512",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        );
        $res = openssl_pkey_new($config);
        //$privKey=openssl_pkey_export($res, $privKey);
        $pubKey = openssl_pkey_get_details($res);
        $result=base64_encode($pubKey["key"]);
    }
    private function state_getStatus ($bucket ,$KEY){
        //$client_store = $bucket['apiUsername'];
        //$client_store = $bucket['api_username'];
        $client_store='rug-gallery-outlet.myshopify.com';
        $client_store='rmi-test2.myshopify.com';
        //$client_store = $bucket['email'];
        //////////////
        $processes = DB::table('clients')
            ->select('clients.id as client_id')
            ->select('processes.*')
            ->join('processes', 'processes.client_id', '=', 'clients.id')
            ->where('processes.status', '!=',3)
            ->where('processes.status', '!=',4)
            //->whereNotIn('processes.status', [3,4])
            ->where('clients.shopify_store', '=', $client_store)
            //->groupBy('subprocesses.product_id')
            ->get();
        if ( !isset($processes[0])) {
            $ret['type'] = false;
            $a[$KEY]['imported'] = 0;
            $a[$KEY]['id'] = $client_store;
            $a[$KEY]['status'] = 'imported';
            $a[$KEY]['name'] = 'Shopify';
            $a[$KEY]['states'] = 'getStatus_empty';
            $a[$KEY]['data'] = 'getStatus_empty';
            $ret['message'] = $a;
            $p = '"{\"type\":true,\"message\":[{\"id\":\"5b8ffd95f6210dc106df135e\",\"imported\":50,\"status\":\"imported\",\"name\":\"bucket\"}]}"';
            //return $p;
            return '"' . str_replace('"', '\"', json_encode($ret)) . '"';
        }
        else {
            // dd($processes);
            $processes_id = $processes[count($processes) - 1]->id;
            $count = Subprocess::where(['process_id' => $processes_id])->get()->count();
            $imported = ($count * 100) / $processes[count($processes) - 1]->count_products;
            $ret['type'] = true;
            $a[$KEY]['imported'] =intval($imported);
            $a[$KEY]['id'] = $processes_id;
            $a[$KEY]['status'] = 'importing';
            $a[$KEY]['name'] = 'Shopify';
            $a[$KEY]['states'] = 'getStatus _no empty imported='.$imported;
            $ret['message'] = $a;
            $p = '"{\"type\":true,\"message\":[{\"id\":\"5b8ffd95f6210dc106df135e\",\"imported\":50,\"status\":\"imported\",\"name\":\"bucket\"}]}"';
            return '"' . str_replace('"', '\"', json_encode($ret)) . '"';
        }

    }
    private function state_created ($bucket ,$KEY){
        //return $request;
        // $client_store = $bucket['apiUsername'];
        $client_store = 'rug-gallery-outlet.myshopify.com';  //For Test
        $client_store = 'rmi-test2.myshopify.com';  //For Test
        $clients = DB::table('clients')
            ->select('*')
            ->where('shopify_store', '=', $client_store)
            //->groupBy('subprocesses.product_id')
            ->get();
        //rint_r($clients[0]->id);
        Log:: info('$bucket = created');
        Log:: info($clients);
        Log:: info('$bucket = created_end');
        $this->session_key = 'client_id';
        $this->client_id = $clients[0]->id;
        $this->model = new ClientModel;
        $this->client = $this->model->getClient($this->client_id);
        if (!is_null($this->client) && !is_null($this->client_id)) {
            $this->shopify = new ShopifyClient([
                'access_token' => $this->client->shopify_access_token,
                'store_url' => $this->client->shopify_store
            ]);
        }
        $api_key = $clients[0]->api_key;
        // $list_buckets=\GuzzleHttp\json_decode($request->input('bucketList'));
        Log:: info('request');
        //Log:: info($request);
        Log:: info('request_end');
        // dd($list_buckets);
        $backet_id = $bucket['id'];

        Log :: info("$api_key, $backet_id, $api_key, $this->client_id");
        //  return 1;
        $run = $this->run_auto($api_key, $backet_id, $api_key, $this->client_id);
        $ret['type'] = false;
        $a[$KEY]['imported'] = 0;
        $a[$KEY]['id'] = $client_store;
        $a[$KEY]['status'] = 'Send_data_To_Shopify';
        $a[$KEY]['name'] = 'Importing Data';
        $a[$KEY]['states'] = 'getStatus_empty';
        $a[$KEY]['data'] = 'getStatus_empty';
        $ret['message'] = $a;
        return '"' . str_replace('"', '\"', json_encode($ret)) . '"';
        //  $p = '"{\"type\":true,\"message\":[{\"id\":\"5b8ffd95f6210dc106df135e\",\"imported\":50,\"status\":\"imported\",\"name\":\"bucket\"}]}"';
        // return $p;

    }
    private function status_stop ($bucket ,$KEY){
        //return $request;
        // $client_store=$bucket['api_username'];
        //$client_store = 'rug-gallery-outlet.myshopify.com';  //For Test
        //$client_store = $bucket['email'];
        $client_store = $bucket['apiUsername'];
        $client_store = 'rmi-test2.myshopify.com';
        $clients = DB::table('clients')
            ->select('id')
            ->where('shopify_store', '=', $client_store)
            //->where('status','=', '2')
            // ->where('status','=', '1')
            // ->whereIn('status', [1, 2])
            //->groupBy('subprocesses.product_id')
            ->get();
        DB::table('processes')
            ->where('client_id', $clients[0]->id)
            ->where('status','=', '2')
            ->update([
                'status' => 4,     //stop process
                'Stop_Report' => 'Datalink Stoped!'
            ]);
        $ret['type'] = true;
        $a[$KEY]['status'] = 'importing';
        $a[$KEY]['name'] = 'shopify';
        $a[$KEY]['states'] = 'stop';
        $a[$KEY]['id_clients'] = $clients[0]->id;
        $ret['message'] = $a;
        return '"' . str_replace('"', '\"', json_encode($ret)) . '"';


    }
    private function status_deleted ($bucket ,$KEY){
        //return $request;
        // $client_store=$bucket['api_username'];
        $client_store = 'rug-gallery-outlet.myshopify.com';  //For Test
        $client_store = 'rmi-test2.myshopify.com';  //For Test
        // $client_store = $bucket['email'];
        // $client_store = $bucket['apiUsername'];
        $clients = DB::table('clients')
            ->select('id')
            ->where('shopify_store', '=', $client_store)
            //->where('status','=', '2')
            // ->where('status','=', '1')
            // ->whereIn('status', [1, 2])
            //->groupBy('subprocesses.product_id')
            ->get();
        $list_product = DB::table('processes')
            ->select('subprocesses.product_id')
            ->where('client_id', $clients[0]->id)
            ->where('processes.bucket_id', $bucket['id'])
            ->join('subprocesses', 'processes.id', '=', 'sunprocesses.process_id')
            ->where('status','=', '2')
            ->update([
                'status' => 4,     //stop process
                'Stop_Report' => 'Datalink Stoped!'
            ]);
        if(is_array($list_product)) {
            foreach ($list_product as $product){
                //$this->shopify->productDelete($this->emailCompleted());

            }
        }
        $ret['type'] = true;
        $a[$KEY]['status'] = 'importing';
        $a[$KEY]['name'] = 'shopify';
        $a[$KEY]['states'] = 'stop';
        $a[$KEY]['id_clients'] = $clients[0]->id;
        $ret['message'] = $a;
        return '"' . str_replace('"', '\"', json_encode($ret)) . '"';
    }
    public function fild_map_seting(Request $request){
        $list = $request;
        $aa = new Subprocess;
        $aa->ali2(1);
//        DB::table('clients')->where('id', $id)->update([
//            'my_private_key'      => $privKey,
//            'my_public_key'             =>$pubKey1,
//            'my_settime_key' => $time,
//        ]);
//        $p=json_encode($return);
    }
}
