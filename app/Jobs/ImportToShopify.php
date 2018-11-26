<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class ImportToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $data;
    private $shopify;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($shopify, $data)
    {
        $this->data = $data;
        $this->shopify = $shopify;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $limit = 1;
        $result = [];
        $x = 0;

        foreach( $this->data['data']['data'] as  $key => $value ){

            if( $x == $limit){
                break;
            }

            $fields = [
                "title"=> $value['title'],
                "handle"=> $value['key'],
                "body_html"=> $value['productDescription'],
                "vendor" => $value['vendorUniqueId'],
                "imagePrefixPath" => $value['imagePrefixPath'],
                "price" => $this->getPrice($value),
                "product_type"=> $value['viewTemplatePrefix'],
            ];


            if( $value['productType'] == 'configurable'  ){
                $options = [];
                $fields['options'] = [];
                $variants = [];
                $images = [];
                $additionalImages = [];
                foreach ($value['productTypeConfig']['options'] as $k => $v){
                    $options[] = $v['name'];
                    $fields['options'][] = ["name" => $v['name']];
                }

                $simples = $datalink->simples($collection_id, $value['id']);
                $i = 0;
                foreach( $simples['data']['data'] as $kk => $vv ){
                    // variants
                    $j = 1;
                    $variants[$i] = [
                        'title' => $vv['title'],
                        'price' => $this->getPrice($vv),
                        'inventory_quantity' => $vv['quantity'],
                        'sku' => $vv['sku'],
                        'weight' => $vv['weight'],
                    ];
                    foreach ( $options as $option ){
                        $variants[$i]["option$j"] = $vv[$option];
                        $j++;
                    }
                    //images
                    if( count($vv['images']) > 0 ){
                        foreach($vv['images'] as $img){
                            $images[] = ['src' => "https://rmimages2.blob.core.windows.net/{$fields['imagePrefixPath']}/{$img['name']}.jpg"];


                        }
                    }
                    if( count($vv['additionalImages']) > 0 ){
                        foreach($vv['additionalImages'] as $img){
                         //   $additionalImages[] = ['src' => "https://ecatalog.rminno.net/{$fields['vendor']}/{$img['name']}.jpg"];

                            $additionalImages[] = ['src' => "https://rmimages2.blob.core.windows.net/{$fields['imagePrefixPath']}/{$img['name']}.jpg"];

                            $myfile = fopen("newfile.txt", "w") or die("Unable to open file!");
                            $txt = "1111s";
                            fwrite($myfile, $txt);
                            $txt = "Jane Doe\n";
                            fwrite($myfile, $txt);
                            fclose($myfile);
                        }
                    }

                    $i++;
                }

                $newImages = array_merge_recursive($additionalImages, $images);

//                dd($images, $additionalImages,$newImages, $value['images'], $value['additionalImages']);

                $fields['images'] = $newImages;
                $fields['variants'] = $variants;
//                print_r($c);
//                print_r($options);
//                print_r($fields);
//                exit;
            }

            $result[] = $this->shopify->addProduct($fields);

//            dd($result);
            $x++;

            sleep(1);
        }
    }

    private function getPrice( &$data )
    {
        $keys = ['mapPrice', 'salePrice'];
        foreach($keys as $key){
            if( isset($data[$key]) && $data[$key] > 0 ){
                return $data[$key];
            }
        }
    }
}
