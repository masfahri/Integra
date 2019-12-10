<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });


$prefixApi = 'dashboard_v1';
Route::group(['prefix' => $prefixApi], function(){
    Route::get('testing/{token}/', [
        'uses' => 'InventoryController@testing'
    ]);
    Route::post('loginuser', [
        'uses' => 'InventoryController@loginuser'
    ]);
    Route::get('validateuser', [
        'uses' => 'InventoryController@validateuser'
    ]);
    Route::get('inventorydevices/{token}/', [
        'uses' => 'InventoryController@inventorydevices'
    ]);
    Route::get('inventoryclient/{token}/', [
        'uses' => 'InventoryController@inventoryclient'
    ]);
    Route::get('inventorymovement/{token}/', [
        'uses' => 'InventoryController@inventorymovement'
    ]);
    Route::get('ecoclient/{token}/', [
        'uses' => 'InventoryController@ecoclient'
    ]);
    Route::get('ecopic/{token}/', [
        'uses' => 'InventoryController@ecopic'
    ]);
    Route::get('warehouse/{token}/', [
        'uses' => 'InventoryController@warehouse'
    ]);
    Route::post('uploadsnpn', [
        'uses' => 'InventoryController@uploadsnpn'
    ]);
    Route::get('snreport', [
        'uses' => 'InventoryController@snreport'
    ]);
    Route::post('sndetails', [
        'uses' => 'InventoryController@sndetails'
    ]);
    Route::post('uploadwarranty', [
        'uses' => 'InventoryController@uploadwarranty'
    ]);
    Route::get('brands/{token}/', [
        'uses' => 'InventoryController@brands'
    ]);
    Route::get('models/{token}/', [
        'uses' => 'InventoryController@models'
    ]);
    Route::get('stockopname', [
        'uses' => 'InventoryController@stockopname'
    ]);
    //update pass
    Route::post('updatepass', [
        'uses' => 'InventoryController@updatepass'
    ]);
    Route::get('allusers', [
        'uses' => 'InventoryController@allusers'
    ]);
    Route::post('insertuser', [
        'uses' => 'InventoryController@insertuser'
    ]);

    /** update warehouse **/
    Route::post('insertwarehouse', [
        'uses' => 'InventoryController@insertwarehouse'
    ]);
    /*mmid menu*/
    Route::get('merchantdata', [
        'uses' => 'MmidController@merchantdata'
    ]);
    Route::get('merchantalias', [
        'uses' => 'MmidController@merchantalias'
    ]);
    Route::get('merchantsuggestion', [
        'uses' => 'MmidController@merchantsuggestion'
    ]);
    Route::post('uploadfsajo', [
        'uses' => 'MmidController@uploadfsajo'
    ]);
    Route::get('fsajoreport', [
        'uses' => 'MmidController@fsajoreport'
    ]);
});

$prefixBd = 'bd';
Route::group(['prefix' => $prefixBd], function(){
    Route::get('migrateclient', [
        'uses' => 'BackdoorController@migrateclient'
    ]);
    Route::get('migrateprofile', [
        'uses' => 'BackdoorController@migrateprofile'
    ]);
    Route::get('migratepic', [
        'uses' => 'BackdoorController@migratepic'
    ]);
    Route::get('migratewh', [
        'uses' => 'BackdoorController@migratewh'
    ]);
    Route::get('migratemodel', [
        'uses' => 'BackdoorController@migratemodel'
    ]);
    Route::get('migrateuser', [
        'uses' => 'BackdoorController@migrateuser'
    ]);
    Route::post('previewmmid', [
        'uses' => 'BackdoorController@previewmmid'
    ]);
});

$prefixWh = 'wh_v1';
Route::group(['prefix' => $prefixWh], function(){
    Route::get('checkupdate', [
        'uses' => 'WarehouseController@checkupdate'
    ]);
    Route::post('login', [
        'uses' => 'WarehouseController@login'
    ]);
    Route::get('validateuser', [
        'uses' => 'WarehouseController@validateuser'
    ]);
    Route::post('stockopname', [
        'uses' => 'WarehouseController@stockopname'
    ]);
    Route::get('warehouselist', [
        'uses' => 'WarehouseController@warehouselist'
    ]);
    Route::get('clientlist', [
        'uses' => 'WarehouseController@clientlist'
    ]);
    Route::get('technicianlist', [
        'uses' => 'WarehouseController@technicianlist'
    ]);
    Route::post('sndetail', [
        'uses' => 'WarehouseController@sndetail'
    ]);
    Route::post('stockinventoryopname', [
        'uses' => 'WarehouseController@stockinventoryopname'
    ]);
    Route::post('sendinventory', [
        'uses' => 'WarehouseController@sendinventory'
    ]);
    Route::post('receiveinventory', [
        'uses' => 'WarehouseController@receiveinventory'
    ]);
    Route::get('mystockdata', [
        'uses' => 'WarehouseController@mystockdata'
    ]);
    Route::get('mysenddata', [
        'uses' => 'WarehouseController@mysenddata'
    ]);
    Route::get('myreceivedata', [
        'uses' => 'WarehouseController@myreceivedata'
    ]);
});