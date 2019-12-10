<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Ixudra\Curl\Facades\Curl;
use Reused;
use App\Client;
use App\Profile;
use App\Pic;
use App\Warehouse;
use App\Inventory;
use App\Users;
use App\SnImport;
use App\JoDetail;
use App\MmidAlias;
use App\Mmid;
use App\Suggestion;
use App\Warehouses;

class BackdoorController extends Controller
{

    public function migrateclient(Request $request)
    {
    	$requester = Reused::getRequester();
    	$ip = Reused::getIpAddr();
    	if ($ip != Config::get('app.trusted_ip')) {
    		$response = ['requester' => $requester, 'error' => true, 'msg' => 'get out!'];
        	return response()->json($response, 403);
    	}
    	$report = [];

    	$clients = Client::whereNotNull('code')->whereNotNull('name')->get();
    	foreach ($clients as $key => $value) {
    		$existClient = DB::connection('mysql')->table('client')->where(['id' => $value->id])->count();
    		if ($existClient == 0) {
    			$data = [
    				'id' => $value->id,
    				'code' => $value->code,
    				'name' => $value->name,
    				'address' => $value->address,
    				'phone' => $value->phone_number,
    				'parent_id' => $value->parent_client_id
    			];
    			$insert = DB::connection('mysql')->table('client')->insert($data);
    			$report[] = $insert;
    		}
    	}
    	$response = ['requester' => $requester, 'error' => false, 'data' => $report];
        return response()->json($response, 200);
    }

    public function migrateprofile(Request $request)
    {
    	$requester = Reused::getRequester();
    	$ip = Reused::getIpAddr();
    	if ($ip != Config::get('app.trusted_ip')) {
    		$response = ['requester' => $requester, 'error' => true, 'msg' => 'get out!'];
        	return response()->json($response, 403);
    	}
    	$report = [];

    	$profile = Profile::get();
    	// $profile = Profile::whereNotNull('phone_number')->where('phone_number', '!=', '')->get();
    	// return response()->json($profile, 403);
    	foreach ($profile as $key => $value) {
    		$existProfile = DB::connection('mysql')->table('profile')->where(['id' => $value->id])->count();
    		if ($existProfile == 0) {
    			$gender = $value->gender;
	    		if (empty($gender)) {
	    			$gender = 'L';
	    		}

	    		$data = [
	    			'id' => $value->id,
	    			'user_id' => $value->user_id,
	    			'email' => $value->email_address,
	    			'fullname' => $value->full_name,
	    			'phone' => str_replace('`', '', $value->phone_number),
	    			'birthdate' => $value->birthdate,
	    			'gender' => $gender,
	    			'created_at' => strtotime('now')
	    		];
	    		$insert = DB::connection('mysql')->table('profile')->insert($data);
    			$report[] = $insert;
    		}
    	}
    	return response()->json($report, 403);
    }

    public function migratepic(Request $request)
    {
    	$requester = Reused::getRequester();
    	$ip = Reused::getIpAddr();
    	if ($ip != Config::get('app.trusted_ip')) {
    		$response = ['requester' => $requester, 'error' => true, 'msg' => 'get out!'];
        	return response()->json($response, 403);
    	}
    	$report = [];

    	$pic = Pic::get();
    	foreach ($pic as $key => $value) {
    		$existPic = DB::connection('mysql')->table('pic')->where(['id' => $value->id])->orWhere(['profile_id' => $value->profile_id])->count();
    		if ($existPic == 0) {
    			$is_active = $value->is_active;
    			$active = 1;
	    		if (!$is_active) {
	    			$active = 0;
	    		}

	    		$data = [
	    			'id' => $value->id,
	    			'is_active' => $active,
	    			'client_id' => $value->client_id,
	    			'profile_id' => $value->profile_id,
	    			'warehouse_id' => $value->warehouse_id,
	    			'created_at' => strtotime('now')
	    		];
	    		$insert = DB::connection('mysql')->table('pic')->insert($data);
    			$report[] = $insert;
    		}
    	}
    	return response()->json($report, 403);
    }

    public function migratewh(Request $request)
    {
    	$requester = Reused::getRequester();
    	$ip = Reused::getIpAddr();
    	if ($ip != Config::get('app.trusted_ip')) {
    		$response = ['requester' => $requester, 'error' => true, 'msg' => 'get out!'];
        	return response()->json($response, 403);
    	}
    	$report = [];

        $wh = Warehouses::get();
    	foreach ($wh as $key => $value) {
    		$exist = DB::connection('mysql')->table('warehouse')->where(['id' => $value->id])->count();
    		if ($exist == 0) {
	    		$data = [
	    			'id' => $value->id,
	    			'code' => $value->code,
	    			'name' => $value->name,
	    			'address' => $value->address,
	    			'phone' => $value->phone_number,
	    			'lat' => $value->map_lat,
	    			'lng' => $value->map_lng,
	    			'client_id' => $value->client_id,
	    			'created_at' => strtotime('now')
	    		];
	    		$insert = DB::connection('mysql')->table('warehouse')->insert($data);
    			$report[] = $insert;
    		}
    	}
    	return response()->json($report, 403);
    }

    public function migratemodel(Request $request)
    {
    	$requester = Reused::getRequester();
    	$ip = Reused::getIpAddr();
    	if ($ip != Config::get('app.trusted_ip')) {
    		$response = ['requester' => $requester, 'error' => true, 'msg' => 'get out!'];
        	return response()->json($response, 403);
    	}
    	$report = [];

    	$parts = DB::table('inventory_partnumber')->get();
    	foreach ($parts as $key => $value) {
    		$exist = DB::connection('mysql')->table('model')->where(['id' => $value->id])->count();
    		if ($exist == 0) {
	    		$data = [
	    			'id' => $value->id,
	    			'code' => $value->code,
	    			'name' => $value->name,
	    			'status' => 'active',
	    			'created_at' => strtotime('now')
	    		];
	    		$insert = DB::connection('mysql')->table('model')->insert($data);
    			$report[] = $insert;
    		}
    	}
    	return response()->json($report, 403);
    }

    private function checkUserNameExist($username)
    {
        $user = Users::where(['username' => $username])->count();
        if ($user > 0) {
            return true;
        }
        return false;
    }

    private function getUserName($full_name)
    {
        $names = explode('.', $full_name);
        if (isset($names[1])) {
            $first = strtolower($names[0]);
            $last = strtolower($names[1]);
            if (strpos($last, '@') !== false) {
                $lasts = explode('@', $last);
                $last = $lasts[0];
            }

            $username = $first.'.'.$last;
            $exist = self::checkUserNameExist($username);
            if (!$exist) {
                return $username;
            }

            $username = $username.'2';
            $exist = self::checkUserNameExist($username);
            if (!$exist) {
                return $username;
            }
        }

        $names = explode(' ', $full_name);
        if (isset($names[1])) {
            $first = strtolower($names[0]);
            $last = strtolower($names[1]);

            $username = $first.'.'.$last;
            $exist = self::checkUserNameExist($username);
            if (!$exist) {
                return $username;
            }

            $username = $username.'2';
            $exist = self::checkUserNameExist($username);
            if (!$exist) {
                return $username;
            }
        }

        $name = str_replace(' ', '', $full_name);
        $username = strtolower($name).'.'.strtolower($name);
        $exist = self::checkUserNameExist($username);
        if (!$exist) {
            return $username;
        }

        $username = $username.'2';
        $exist = self::checkUserNameExist($username);
        if (!$exist) {
            return $username;
        }
    }

    public function migrateuser(Request $request)
    {
        $requester = Reused::getRequester();
        $ip = Reused::getIpAddr();
        if ($ip != Config::get('app.trusted_ip')) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'get out!'];
            return response()->json($response, 403);
        }
        $report = [];
        $profile = Profile::get();
        foreach ($profile as $key => $value) {
            $username = self::getUserName($value->full_name);
            if ($value->user_id == 2) {
                $user = new Users;
                $user->id = $value->user_id;
                $user->username = $username;
                $user->level = 'us';
                $user->status = 'active';
                $user->salt = 'abcdef';
                $user->hash = 'd63c8c0050dc7a0d55b53f68e2d956e40a7c1a6d';
                $user->created_at = strtotime('now');
                $user->updated_at = 0;
                if ($user->save()) {
                    $report[] = $username;
                }
            }
        }
        return response()->json($report, 403);
    }

    private function normalizeKeyName($name)
    {
        $name = strtolower($name);
        $find = ['.', '/', ' '];
        $replace = ['', '_', '_'];
        for ($i = 0; $i < count($find); $i++) { 
            $name = str_replace($find[$i], $replace[$i], $name);
        }
        return $name;
    }

    private function getEpochTime($date)
    {
        if (empty($date)) {
            return 0;
        }
        $dt = $date;
        $tm = '';
        if (strpos($date, ' ') !== false) {
            $date = str_replace(': ', '', $date);
            $dates = explode(' ', $date);
            $dt = $dates[0];
            $tm = $dates[1].':00';
        }
        $arrDate = explode('/', $dt);
        $mDate = $arrDate[2].'-'.$arrDate[1].'-'.$arrDate[0];
        if (!empty($tm)) {
            $mDate = $mDate.' '.$tm;
        }
        return strtotime($mDate);
    }

    private function joDetailExist($nomor_tiket)
    {
        $count = JoDetail::where(['nomor_tiket' => $nomor_tiket])->count();
        if ($count > 0) {
            return true;
        }
        return false;
    }

    private function mAliasExist($mid)
    {
        $count = MmidAlias::where(['mid' => $mid])->count();
        if ($count > 0) {
            return true;
        }
        return false;
    }

    private function geoCodeLocation($lat, $lng)
    {
        $gApiKey = 'AIzaSyAEIY8DvWKRssVQch8xR4EW-hUJETWiXPs';
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?latlng='.$lat.','.$lng.'&key='.$gApiKey;
        $response = Curl::to($url)->get();
        $resp = json_decode($response, true);
        if ($resp) {
            if (isset($resp['status'])) {
                if ($resp['status'] == 'OK') {
                    $postal_code;
                    $city;
                    $address_components = $resp['results'][0]['address_components'];
                    foreach ($address_components as $key => $value) {
                        $long_name = $value['long_name'];
                        $types = $value['types'];
                        if (in_array('postal_code', $types )) {
                            $postal_code = $long_name;
                        }
                        if (in_array('administrative_area_level_2', $types )) {
                            $city = $long_name;
                        }
                    }
                    $formatted_address = $resp['results'][0]['formatted_address'];
                    return ['postal_code' => $postal_code, 'city' => $city, 'formatted_address' => $formatted_address];
                }
            }
        }
        return false;
    }

    private function generateMmid($lat, $lng, $name)
    {
        $geoCode = self::geoCodeLocation($lat, $lng);
        if ($geoCode) {
            $postal_code = $geoCode['postal_code'];
            $city = $geoCode['city'];
            $g_address = $geoCode['formatted_address'];
            $maxId = Mmid::max('id');
            if (!$maxId) {
                $maxId = 0;
            }

            $newId = $maxId +1;
            $prefix;
            $length = strlen($newId);
            switch ($length) {
                case 8:
                    $prefix = '';
                    break;

                case 7:
                    $prefix = '0';
                    break;

                case 6:
                    $prefix = '00';
                    break;

                case 5:
                    $prefix = '000';
                    break;

                case 4:
                    $prefix = '0000';
                    break;

                case 3:
                    $prefix = '00000';
                    break;

                case 2:
                    $prefix = '000000';
                    break;

                case 1:
                    $prefix = '0000000';
                    break;

                
                default:
                    $prefix = '';
                    break;
            }

            $id = $postal_code.'.'.$prefix.$newId;

            $mmid = new Mmid;
            $mmid->mmid = $id;
            $mmid->name = $name;
            $mmid->address = $g_address;
            $mmid->postal_code = $postal_code;
            $mmid->city = $city;
            $mmid->latitude = $lat;
            $mmid->longitude = $lng;
            $mmid->g_address = $g_address;
            $mmid->created_at = strtotime('now');
            if ($mmid->save()) {
                return $mmid->mmid;
            }
            return false;
        }
        return false;
    }

    private function checkDistance($lat1, $lng1, $lat2, $lng2)
    {
        // convert latitude/longitude degrees for both coordinates
        // to radians: radian = degree * π / 180
        $lat1 = deg2rad($lat1);
        $lng1 = deg2rad($lng1);
        $lat2 = deg2rad($lat2);
        $lng2 = deg2rad($lng2);

        // calculate great-circle distance
        $distance = acos(sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($lng1 - $lng2));

        // distance in human-readable format:
        // earth's radius in km = ~6371
        $erad = 6371;
        $eradHm = 63710; //use this for get value ini hekto meter
        $calc = number_format($eradHm * $distance, 0, '', '');
        return $calc;
    }

    private function getMmid($mAlias)
    {
        if (!is_object($mAlias)) {
            return false;
        }

        //check empty
        $strictEmpty = ['mid', 'nama_merchant', 'latitude', 'longitude'];
        foreach ($mAlias as $key => $value) {
            if (in_array($key, $strictEmpty)) {
                if (empty($value)) {
                    return false;
                }
            }
        }

        $mmid = false;
        $mmids = Mmid::get();
        if (@count($mmids) == 0) {
            //insert 1st data
            $mmid = self::generateMmid($mAlias->latitude, $mAlias->longitude, $mAlias->nama_merchant);
        } else {
            $suggestion = [];
            $suggestIds = [];
            foreach ($mmids as $key => $value) {
                $distance = self::checkDistance($value->latitude, $value->longitude, $mAlias->latitude, $mAlias->longitude);
                if ($distance <= 5) {
                    //possible duplicate by distance in 500m
                    if (!in_array($value->id, $suggestIds)) {
                        $suggestion[] = $value;
                        $suggestIds[] = $value->id;
                    }
                } else {
                    $rawAddress = $mAlias->alamat_merchant.' '.$mAlias->alamat_merchant_2;
                    $jlPos = strpos(strtolower($rawAddress), 'jl');
                    $targetAddress = substr($rawAddress, $jlPos);
                    $arrTarget = explode(' ', str_replace('.', '', $targetAddress));

                    $iAddress = $value->address;
                    $jPos = strpos(strtolower($iAddress), 'jl');
                    $itemAddress = substr($iAddress, $jPos);
                    $arrItem = explode(' ', str_replace('.', '', $itemAddress));

                    if ($arrTarget && $arrItem) {
                        $targetLine = $arrTarget[0].' '.$arrTarget[1];
                        $itemLine = $arrItem[0].' '.$arrItem[1];
                        if ($targetLine == $itemLine) {
                            if (!in_array($value->id, $suggestIds)) {
                                $suggestion[] = $value;
                                $suggestIds[] = $value->id;
                            }
                        }
                    }
                }
            }
            if (@count($suggestion) > 0) {
                if (@count($suggestion) == 1) {
                    // check if mid already in suggestion
                    $existSuggest = Suggestion::where(['mid' => $suggestion[0]->mmid])->first();
                    if (!$existSuggest) {
                        $suggest = new Suggestion;
                        $suggest->mid = $mAlias->mid;
                        $suggest->suggest_mmid = $suggestion[0]->mmid;
                        $suggest->created_at = strtotime('now');
                        $suggest->save();
                    }
                    return ''; //need to add to task possible to parent mmid
                }
            }
            $mmid = self::generateMmid($mAlias->latitude, $mAlias->longitude, $mAlias->nama_merchant);
        }
        return $mmid;
    }

    public function previewmmid(Request $request)
    {
        $requester = Reused::getRequester();
        $ip = Reused::getIpAddr();
        if ($ip != Config::get('app.trusted_ip')) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'get out!'];
            return response()->json($response, 403);
        }

        $enableUpload = true;
        if(!$request->hasFile('mmid')) {
            $enableUpload = false;
        }

        if ($enableUpload) {
            $file = $request->file('mmid');
            if(!$file->isValid()) {
                $response = ['requester' => $requester, 'error' => true, 'msg' => 'Import failed, invalid file!'];
                return response()->json($response, 403);
            }
            $path = public_path() . '/uploads/';
            $file_extension = $file->getClientOriginalExtension();
            $ori_name = $file->getClientOriginalName();
            if ($file_extension != 'xlsx') {
                $response = ['requester' => $requester, 'error' => true, 'msg' => 'Import failed, wrong extention file!'];
                return response()->json($response, 403);
            }

            $fileName = strtotime('now').'_mmid.'.$file_extension;
            $file->move($path, $fileName);
            $array = Excel::toArray(new SnImport, $path.$fileName);

            $names = $array[0][0];
            $titles = [];
            foreach ($names as $key => $value) {
                $titles[$key] = self::normalizeKeyName($value);
            }

            $jo_insert = 0;
            $alias_insert = 0;
            $alias_update = 0;

            $dateKey = [0, 1, 25, 27, 29, 31, 66];
            foreach ($array[0] as $key => $value) {
                if ($key > 0) {
                    $insertData = [];
                    foreach ($value as $k => $v) {
                        if (in_array($k, $dateKey)) {
                            $v = self::getEpochTime($v);
                        }
                        if ($k == 2) {
                            $v = strtotime($v);
                        }
                        $insertData[$titles[$k]] = $v;
                    }
                    $exist = self::joDetailExist($insertData['nomor_tiket']);
                    if (!$exist) {
                        $insertData['created_at'] = strtotime('now');
                        DB::connection('mmid')->table('jo_detail')->insert($insertData);
                        $jo_insert += 1;

                        $aliasExist = self::mAliasExist($insertData['mid']);
                        if (!$aliasExist) {
                            $mAlias = new MmidAlias;
                            $mAlias->mid = $insertData['mid'];
                            $mAlias->nama_merchant = ltrim($insertData['nama_merchant'], ' ');
                            $mAlias->alamat_merchant = $insertData['alamat_merchant'];
                            $mAlias->alamat_merchant_2 = $insertData['alamat_merchant_2'];
                            $mAlias->kode_pos = $insertData['kode_pos'];
                            $mAlias->kota = $insertData['kota'];
                            $mAlias->latitude = $insertData['latitude'];
                            $mAlias->longitude = $insertData['longitude'];
                            $mAlias->created_at = strtotime('now');
                            $mAlias->save();
                            if ($mAlias->save()) {
                                $alias_insert += 1;

                                $mmid = self::getMmid($mAlias);
                                if ($mmid) {
                                    $mAlias->mmid = $mmid;
                                    $mAlias->save();
                                }
                            }
                        }
                    } else {
                        $nomor_tiket = $insertData['nomor_tiket'];
                        $insertData['updated_at'] = strtotime('now');
                        unset($insertData['nomor_tiket']);

                        $joDetail = JoDetail::where(['nomor_tiket' => $nomor_tiket])->update($insertData);
                        $mAlias = MmidAlias::where(['mid' => $insertData['mid'], 'mmid' => ''])->first();
                        if ($mAlias) {
                            $mmid = self::getMmid($mAlias);
                            if ($mmid) {
                                $mAlias->mmid = $mmid;
                                $mAlias->updated_at = strtotime('now');
                                $mAlias->save();
                                $alias_update += 1;
                            }
                        }
                    }
                }
            }

            return response()->json(['jo_insert' => $jo_insert, 'alias_insert' => $alias_insert, 'alias_update' => $alias_update], 403);
        }
        return response()->json($enableUpload, 403);
    }

}
