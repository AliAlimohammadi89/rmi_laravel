<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


use Illuminate\Support\Facades\Log;




Route::get('/', function () {
    return view('welcome');
});


Route::get('/access_denied', function (){
   return 'Access Denied';
});





//Route::get('/test', 'TestController@index');
//Route::get('/test/progress', 'TestController@progress');
//Route::get('/test/long_progress', 'TestController@longProgress');


//Route::get('app/import', 'AppController@import');
//Route::post('app/import', 'AppController@import');


//Route::prefix('app')->group( function () {
    Route::get('/', 'AppController@index');
    Route::post('/', 'AppController@store');
    Route::get('auth', 'AppController@auth');
    Route::get('import', 'AppController@import');
    Route::post('import', 'AppController@import');
    Route::get('run', 'AppController@run');
    Route::get('result', 'AppController@result');
    Route::get('progress', 'AppController@progress');
    Route::get('history', 'AppController@history');
    Route::get('logout', 'AppController@logout');
    Route::get('updatedata', 'Updatedata@update');
    Route::get('updatedata2', 'AppController@update_data');
    Route::post('insert_auto', 'AppController@insert_auto');
    Route::post('rest/default/V1/synch/setKey', 'AppController@setKey');
    Route::post('rest/default/V1/synch/Connection/bucket/setCategory', 'AppController@insert_auto');
    Route::get('test', 'AppController@test');
    Route::get('fild_map_seting', 'AppController@fild_map_seting');


//});
//
//Route::prefix('shopify')->group(function () {
//    Route::get('/', 'ShopifyController@index');
//    Route::get('app', 'ShopifyAppController@index');
//    Route::post('app', 'ShopifyAppController@store');
//    Route::get('app/auth', 'ShopifyAppController@auth');
//    Route::get('app/import', 'ShopifyHandleController@index')->middleware('verifyclient');
//    Route::post('app/import', 'ShopifyHandleController@handle');
//
//    Route::get('app/run', 'ShopifyHandleController@run');
//    Route::get('app/progress', 'ShopifyHandleController@progress');
//    Route::get('app/progress_count', 'ShopifyHandleController@progressCount');
//    Route::get('app/progress_result', 'ShopifyHandleController@progressResult');
//    Route::get('app/test', 'ShopifyHandleController@test');
//
//
//
//
//    Route::get('products', 'ShopifyController@products');
//    Route::get('add_product', 'ShopifyController@addProduct');
//    Route::get('configurable', 'ShopifyController@configurable');
//    Route::get('test', 'ShopifyController@test');
////    Route::get('products1', function () {
////        return 'products worked!';
////    });
//});
