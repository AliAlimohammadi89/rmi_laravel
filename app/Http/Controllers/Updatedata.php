<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Updatedata extends Controller
{
    //
    public function update(){
        print 'is ok';
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
       $array_fildes= array('5a3ae841785fb07629900e24','5a3ae841785fb07629900e25','5a3ae841785fb07629900e26','5a3ae890785fb07629900e43','5a3ae890785fb07629900e4e');
       //echo $q->find('5a3ae841785fb07629900e24');
        foreach ($users as $value){

            //if (strstr($value->varyants_id, '5a3ae841785fb07629900e20')) {
              //  echo 'found a zero';
                $p=\GuzzleHttp\json_decode($value->varyants_id);
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
        dd(($users ) );
    }
}
