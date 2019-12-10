<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Reused;
use App\Users;
use App\UserAccess;
use App\Profiles;
use App\Pics;
use App\Warehouses;
use App\Inventorys;
use App\ClientWarranty;
use App\Models;
use App\Brand;
use App\Clients;
use App\StockOpname;
use App\Movements;

class WarehouseController extends Controller
{
	private function getModelName($id)
	{
		$model = Models::where(['id' => $id])->first();
		if ($model) {
			return $model->name;
		}
		return 'unknown';
	}

	private function getBrandName($id)
	{
		$brand = Brand::where(['id' => $id])->first();
		if ($brand) {
			return $brand->name;
		}
		return 'unknown';
	}

	private function getClientName($id)
	{
		$client = Clients::where(['id' => $id])->first();
		if ($client) {
			return $client->name;
		}
		return 'unknown';
	}

    private function getUserName($id)
    {
        $name = '';
        $user = Users::where(['id' => $id])->first();
        if ($user) {
            $name = $user->username;
        }
        return $name;
    }

    public function checkupdate(Request $request)
    {
    	$requester = Reused::getRequester();

    	$headers = $request->server();
    	$validHeader = Reused::validateHeader($headers);
    	if (!$validHeader) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid header'];
    		return response()->json($response, 403);
    	}
    	$validApi = Reused::validateApiKey($headers);
    	if (!$validApi) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid api key'];
    		return response()->json($response, 403);
    	}
    	
    	$response = ['requester' => $requester, 'error' => false, 'data' => $validApi];
    	return response()->json($response, 200);
    }

    public function login(Request $request)
    {
    	$requester = Reused::getRequester();

    	$headers = $request->server();
    	$validHeader = Reused::validateHeader($headers);
    	if (!$validHeader) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid header'];
    		return response()->json($response, 403);
    	}
    	$validApi = Reused::validateApiKey($headers);
    	if (!$validApi) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid api key'];
    		return response()->json($response, 403);
    	}

    	$username = $request->input('username');
    	$password = $request->input('password');

    	$user = Users::where(['username' => $username])->first();
    	if (!$user) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'user not registered'];
    		return response()->json($response, 403);
    	}
    	$salt = $user->salt;
    	$hash = sha1($password.$salt);
        if ($hash != $user->hash) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'wrong password!'];
            return response()->json($response, 403);
        }
        $token = Reused::generateToken($user->id);
        $user->token = $token;
        unset($user->salt);
        unset($user->hash);

        $profile = Profiles::where(['user_id' => $user->id])->first();
        if ($profile) {
        	$user->fullname = $profile->fullname;
        	$user->email = $profile->email;
        	$user->phone = $profile->phone;

        	$pic = Pics::where(['profile_id' => $profile->id])->first();
        	if ($pic) {
        		$warehouse = Warehouses::where(['id' => $pic->warehouse_id])->first();
        		if ($warehouse) {
        			$user->wh_id = $warehouse->id;
        			$user->wh_code = $warehouse->code;
        			$user->wh_name = $warehouse->name;
        			$user->wh_address = $warehouse->address;
        		}
        	}
        }
    	
    	$response = ['requester' => $requester, 'error' => false, 'data' => $user];
    	return response()->json($response, 200);
    }

    public function validateuser(Request $request)
    {
    	$requester = Reused::getRequester();

    	$headers = $request->server();
    	$validHeader = Reused::validateHeader($headers);
    	if (!$validHeader) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid header'];
    		return response()->json($response, 403);
    	}
    	$validApi = Reused::validateApiKey($headers);
    	if (!$validApi) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid api key'];
    		return response()->json($response, 403);
    	}

    	$token = $headers['HTTP_TOKEN'];
    	$access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
    	if (!$access) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid token'];
    		return response()->json($response, 403);
    	}

        $user = Users::where(['id' => $access->uid])->first();
    	if (!$user) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'user not registered'];
    		return response()->json($response, 403);
    	}
    	$user->token = $token;
        unset($user->salt);
        unset($user->hash);

        $profile = Profiles::where(['user_id' => $user->id])->first();
        if ($profile) {
        	$user->fullname = $profile->fullname;
        	$user->email = $profile->email;
        	$user->phone = $profile->phone;

        	$pic = Pics::where(['profile_id' => $profile->id])->first();
        	if ($pic) {
        		$warehouse = Warehouses::where(['id' => $pic->warehouse_id])->first();
        		if ($warehouse) {
        			$user->wh_id = $warehouse->id;
        			$user->wh_code = $warehouse->code;
        			$user->wh_name = $warehouse->name;
        			$user->wh_address = $warehouse->address;
        		}
        	}
        }
    	
    	
    	$response = ['requester' => $requester, 'error' => false, 'data' => $user];
    	return response()->json($response, 200);
    }

    public function warehouselist(Request $request)
    {
    	$requester = Reused::getRequester();

    	$headers = $request->server();
    	$validHeader = Reused::validateHeader($headers);
    	if (!$validHeader) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid header'];
    		return response()->json($response, 403);
    	}
    	$validApi = Reused::validateApiKey($headers);
    	if (!$validApi) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid api key'];
    		return response()->json($response, 403);
    	}

    	$token = $headers['HTTP_TOKEN'];
    	$access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
    	if (!$access) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid token'];
    		return response()->json($response, 403);
    	}

        $user = Users::where(['id' => $access->uid])->first();
    	if (!$user) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'user not registered'];
    		return response()->json($response, 403);
    	}
        if ($user->level != 'mmid') {
            switch ($user->level) {
                case 'wh':
                    $where = array('status' => 'active', 'id' => $user->client_id);
                    break;
                default:
                    $where = array('status' => 'active');
                    break;
            }
        }

        $whList = DB::connection('mysql')->table('warehouse')->where($where)->orderBy('name', 'asc')->get();
    	
    	$response = ['requester' => $requester, 'error' => false, 'data' => $whList];
    	return response()->json($response, 200);
    }

    public function clientlist(Request $request)
    {
    	$requester = Reused::getRequester();

    	$headers = $request->server();
    	$validHeader = Reused::validateHeader($headers);
    	if (!$validHeader) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid header'];
    		return response()->json($response, 403);
    	}
    	$validApi = Reused::validateApiKey($headers);
    	if (!$validApi) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid api key'];
    		return response()->json($response, 403);
    	}

    	$token = $headers['HTTP_TOKEN'];
    	$access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
    	if (!$access) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid token'];
    		return response()->json($response, 403);
    	}

    	$user = Users::where(['id' => $access->uid])->first();
    	if (!$user) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'user not registered'];
    		return response()->json($response, 403);
        }
        
        if ($user->level != 'mmid') {
            switch ($user->level) {
                case 'wh':
                    $where = array('status' => 'active', 'id' => $user->client_id);
                    break;
                
                default:
                    $where = array('status' => 'active');
                    break;
            }
        }

    	$clientList = DB::connection('mysql')->table('client')->where($where)->orderBy('name', 'asc')->get();
    	
    	$response = ['requester' => $requester, 'error' => false, 'data' => $clientList];
    	return response()->json($response, 200);
    }

    public function technicianlist(Request $request)
    {
    	$requester = Reused::getRequester();

    	$headers = $request->server();
    	$validHeader = Reused::validateHeader($headers);
    	if (!$validHeader) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid header'];
    		return response()->json($response, 403);
    	}
    	$validApi = Reused::validateApiKey($headers);
    	if (!$validApi) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid api key'];
    		return response()->json($response, 403);
    	}

    	$token = $headers['HTTP_TOKEN'];
    	$access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
    	if (!$access) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid token'];
    		return response()->json($response, 403);
    	}

    	$user = Users::where(['id' => $access->uid])->first();
    	if (!$user) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'user not registered'];
    		return response()->json($response, 403);
        }
        
        if ($user->level != 'mmid') {
            switch ($user->level) {
                case 'wh':
                    $where = array('status' => 'active', 'client_id' => $user->client_id, 'level' => 'mmid');
                    break;
                
                default:
                    $where = array('status' => 'active');
                    break;
            }
        }

    	$clientList = DB::connection('mysql')->table('users')->where($where)->orderBy('username', 'asc')->get();
    	
    	$response = ['requester' => $requester, 'error' => false, 'data' => $clientList];
    	return response()->json($response, 200);
    }

    public function sndetail(Request $request)
    {
    	$requester = Reused::getRequester();

    	$headers = $request->server();
    	$validHeader = Reused::validateHeader($headers);
    	if (!$validHeader) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid header'];
    		return response()->json($response, 403);
    	}
    	$validApi = Reused::validateApiKey($headers);
    	if (!$validApi) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid api key'];
    		return response()->json($response, 403);
    	}

    	$token = $headers['HTTP_TOKEN'];
    	$access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
    	if (!$access) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid token'];
    		return response()->json($response, 403);
    	}

    	$user = Users::where(['id' => $access->uid])->first();
    	if (!$user) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'user not registered'];
    		return response()->json($response, 403);
    	}

    	$sn = $request->input('sn');
        // $inventory = Inventorys::where(['sn' => $sn])->first();
        $inventory = Inventorys::join('stock_opname', 'stock_opname.sn', '=', 'inventory.sn')
        ->where(['inventory.sn' => $sn])
        ->first();
    	if (!$inventory) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'inventory not found'];
    		return response()->json($response, 403);
    	}
    	$inventory->model = self::getModelName($inventory->model_id);
    	$inventory->brand = self::getBrandName($inventory->brand_id);

    	$warranty = ClientWarranty::where(['sn' => $sn])->first();
    	if ($warranty) {
    		$inventory->client = self::getClientName($warranty->client_id);
    		$inventory->owning = ucwords($warranty->owning);
    		$inventory->tid = $warranty->tid;
    		$inventory->mid = $warranty->mid;
    		$inventory->warranty_start = $warranty->start;
    		$inventory->warranty_end = $warranty->end;
    	}

    	$response = ['requester' => $requester, 'error' => false, 'data' => $inventory];
    	return response()->json($response, 200);
    }
    
    public function stockinventoryopname(Request $request)
    {
    	$requester = Reused::getRequester();

    	$headers = $request->server();
    	$validHeader = Reused::validateHeader($headers);
    	if (!$validHeader) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid header'];
    		return response()->json($response, 403);
    	}
    	$validApi = Reused::validateApiKey($headers);
    	if (!$validApi) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid api key'];
    		return response()->json($response, 403);
    	}

    	$token = $headers['HTTP_TOKEN'];
    	$access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
    	if (!$access) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid token'];
    		return response()->json($response, 403);
    	}

    	$user = Users::where(['id' => $access->uid])->first();
    	if (!$user) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'user not registered'];
    		return response()->json($response, 403);
    	}

    	$sn = $request->input('sn');
        $inventory = Inventorys::where(['sn' => $sn])->first();
    	if (!$inventory) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'inventory opname not found please note SN to Admin!'];
            return response()->json($response, 403);
        }
    	$inventory->model = self::getModelName($inventory->model_id);
    	$inventory->brand = self::getBrandName($inventory->brand_id);

    	$warranty = ClientWarranty::where(['sn' => $sn])->first();
    	if ($warranty) {
    		$inventory->client = self::getClientName($warranty->client_id);
    		$inventory->owning = ucwords($warranty->owning);
    		$inventory->tid = $warranty->tid;
    		$inventory->mid = $warranty->mid;
    		$inventory->warranty_start = $warranty->start;
    		$inventory->warranty_end = $warranty->end;
    	}

    	$response = ['requester' => $requester, 'error' => false, 'data' => $inventory];
    	return response()->json($response, 200);
    }

    public function stockopname(Request $request)
    {
    	$requester = Reused::getRequester();

    	$headers = $request->server();
    	$validHeader = Reused::validateHeader($headers);
    	if (!$validHeader) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid header'];
    		return response()->json($response, 403);
    	}
    	$validApi = Reused::validateApiKey($headers);
    	if (!$validApi) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid api key'];
    		return response()->json($response, 403);
    	}

    	$token = $headers['HTTP_TOKEN'];
    	$access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
    	if (!$access) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'invalid token'];
    		return response()->json($response, 403);
    	}

        $user = Users::where(['id' => $access->uid])->first();
    	if (!$user) {
    		$response = ['requester' => $requester, 'error' => true, 'data' => 'user not registered'];
    		return response()->json($response, 403);
    	}

        $sn = $request->input('sn');
    	$inv_id = 0;
        $inventory = Inventorys::where(['sn' => $sn])->first();
        
    	if ($inventory) {
    		$inv_id = $inventory->id;
        }else{
            $inventory = new Inventorys();
            $inventory->sn = $sn;
            $inventory->brand_id = 1;
            $inventory->model_id = 88;
            $inventory->created_at = strtotime('now');
            $inventory->save();
            $inv_id = $inventory->id;
        }
        
    	$wh_id = $user->client_id;
    	$lat = $request->input('lat');
        $lng = $request->input('lng');
        $status_inventory = $request->input('status_inventory');
        $status_production = $request->input('status_production');
    	

        $so = StockOpname::where(['sn' => $sn])->first();
        if ($so) {
            $response = ['requester' => $requester, 'error' => true, 'data' => $sn.' Reported Before'];
            return response()->json($response, 403);
        }else{
            $so = new StockOpname();
            $so->sn = $sn;
            $so->inventory_id = $inv_id;
            $so->wh_id = $wh_id;
            $so->status = 'submit';
            $so->submit_by = $user->id;
            $so->lat = $lat;
            $so->lng = $lng;
            $so->status_inventory = $status_inventory;
            $so->status_production = $status_production;
            $so->created_at = strtotime('now');
            if ($so->save()) {
                $response = ['requester' => $requester, 'error' => false, 'data' => $sn.' report succeed'];
                return response()->json($response, 200);
            }
        }
    	$response = ['requester' => $requester, 'error' => true, 'data' => $sn.' report failed'];
    	return response()->json($response, 403);
    }

    public function sendinventory(Request $request)
    {
        $requester = Reused::getRequester();

        $headers = $request->server();
        $validHeader = Reused::validateHeader($headers);
        if (!$validHeader) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'invalid header'];
            return response()->json($response, 403);
        }
        $validApi = Reused::validateApiKey($headers);
        if (!$validApi) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'invalid api key'];
            return response()->json($response, 403);
        }

        $token = $headers['HTTP_TOKEN'];
        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'invalid token'];
            return response()->json($response, 403);
        }

        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'user not registered'];
            return response()->json($response, 403);
        }

        $sn = $request->input('sn');
        $destination = $request->input('destination');
        $technician = $request->input('receiver_id');
        $dest_code = $request->input('dest_code');
        $sender_lat = $request->input('sender_lat');
        $sender_lng = $request->input('sender_lng');

        //unclosed receive check
        $exist = Movements::where(['sn' => $sn])
        ->where('sender_id', '>', 0)
        ->whereNull('receiver_id')
        ->count();
        if ($exist > 0) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'failed to send, item not receive yet'];
            return response()->json($response, 403);
        }

        $inventory = Inventorys::where(['sn' => $sn])->first();
        // $inventory = Inventorys::join('stock_opname', 'stock_opname.sn', '=', 'inventory.sn')
        // ->where(['inventory.sn' => $sn])
        // ->first();
        if (!$inventory) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'failed to send, inventory not listed'];
            return response()->json($response, 403);
        }

        $move = new Movements;
        $move->inventory_id = $inventory->id;
        $move->sn = $inventory->sn;
        $move->sender_id = $user->id;
        $move->created_at = strtotime('now');
        $move->send_at = strtotime('now');
        $move->destination = strtolower($destination);
        $move->receiver_id = $technician;
        $move->dest_code = $dest_code;
        $move->sender_lat = $sender_lat;
        $move->sender_lng = $sender_lng;
        if ($move->save()) {
            $response = ['requester' => $requester, 'error' => false, 'data' => $sn.' send succeed'];
            return response()->json($response, 200);
        }
        $response = ['requester' => $requester, 'error' => true, 'data' => 'failed to send, please try again'];
        return response()->json($response, 403);
    }

    public function receiveinventory(Request $request)
    {
        $requester = Reused::getRequester();

        $headers = $request->server();
        $validHeader = Reused::validateHeader($headers);
        if (!$validHeader) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'invalid header'];
            return response()->json($response, 403);
        }
        $validApi = Reused::validateApiKey($headers);
        if (!$validApi) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'invalid api key'];
            return response()->json($response, 403);
        }

        $token = $headers['HTTP_TOKEN'];
        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'invalid token'];
            return response()->json($response, 403);
        }

        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'user not registered'];
            return response()->json($response, 403);
        }

        $sn = $request->input('sn');
        $receiver_lat = $request->input('receiver_lat');
        $receiver_lng = $request->input('receiver_lng');
        $where = array('receiver_id' => $user->id);
        $move = Movements::where(['sn' => $sn])
        ->where('sender_id', '>', 0)
        ->where($where)
        ->first();
        if (!$move) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'failed to receive, inventory not sended'];
            return response()->json($response, 403);
        }

        $move->receiver_id = $user->id;
        $move->receive_at = strtotime('now');
        $move->receiver_lat = $receiver_lat;
        $move->receiver_lng = $receiver_lng;
        $move->updated_at = strtotime('now');
        if ($move->save()) {
            $response = ['requester' => $requester, 'error' => false, 'data' => $sn.' receive succeed'];
            return response()->json($response, 200);
        }
        $response = ['requester' => $requester, 'error' => true, 'data' => 'failed to send, please try again'];
        return response()->json($response, 403);
    }

    public function mystockdata(Request $request)
    {
        $requester = Reused::getRequester();

        $headers = $request->server();
        $validHeader = Reused::validateHeader($headers);
        if (!$validHeader) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'invalid header'];
            return response()->json($response, 403);
        }
        $validApi = Reused::validateApiKey($headers);
        if (!$validApi) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'invalid api key'];
            return response()->json($response, 403);
        }

        $token = $headers['HTTP_TOKEN'];
        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'invalid token'];
            return response()->json($response, 403);
        }

        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'user not registered'];
            return response()->json($response, 403);
        }

        $stock = StockOpname::join('warehouse', 'warehouse.id', '=', 'stock_opname.wh_id')
        ->where(['stock_opname.submit_by' => $user->id])
        ->select('stock_opname.*', 'warehouse.code', 'warehouse.name', 'warehouse.address')
        ->orderBy('stock_opname.created_at', 'desc')
        ->get();
        if (@count($stock) > 0) {
            $addedSn = [];
            $filteredData = [];
            foreach ($stock as $key => $value) {
                if (!in_array($value->sn, $addedSn)) {
                    $brand = '';
                    $model = '';
                    $inv = Inventorys::where(['sn' => $value->sn])->first();
                    if ($inv) {
                        $brand = self::getBrandName($inv->brand_id);
                        $model = self::getModelName($inv->model_id);
                    }
                    $value->brand = $brand;
                    $value->model = $model;

                    $filteredData[] = $value;
                    $addedSn[] = $value->sn;
                }
            }
            if (@count($filteredData) > 0) {
                $response = ['requester' => $requester, 'error' => false, 'data' => $filteredData];
                return response()->json($response, 200);
            }
        }
        $response = ['requester' => $requester, 'error' => true, 'data' => 'stock data n/a'];
        return response()->json($response, 403);
    }

    public function mysenddata(Request $request)
    {
        $requester = Reused::getRequester();

        $headers = $request->server();
        $validHeader = Reused::validateHeader($headers);
        if (!$validHeader) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'invalid header'];
            return response()->json($response, 403);
        }
        $validApi = Reused::validateApiKey($headers);
        if (!$validApi) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'invalid api key'];
            return response()->json($response, 403);
        }

        $token = $headers['HTTP_TOKEN'];
        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'invalid token'];
            return response()->json($response, 403);
        }

        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'user not registered'];
            return response()->json($response, 403);
        }

        $sendData = Movements::where(['sender_id' => $user->id])->orderBy('created_at', 'desc')->get();
        if (@count($sendData) > 0) {
            $addedSn = [];
            $filteredData = [];
            foreach ($sendData as $key => $value) {
                $destination = $value->destination;
                $dest_code = $value->dest_code;
                $dest_name = '';
                $dest_address = '';
                $receiver = 'n/a';
                if (!empty($value->receiver_id)) {
                    $receiver = self::getUserName($value->receiver_id);
                }
                $value->receiver = $receiver;

                if (strtolower($destination) == 'client') {
                    $client = Clients::where(['code' => $dest_code])->first();
                    if ($client) {
                        $dest_name = $client->name;
                        $dest_address = $client->address;
                    }
                } else {
                    $warehouse = Warehouses::where(['code' => $dest_code])->first();
                    if ($warehouse) {
                        $dest_name = $warehouse->name;
                        $dest_address = $warehouse->address;
                    }
                }
                $value->dest_name = $dest_name;
                $value->dest_address = $dest_address;

                if (!in_array($value->sn, $addedSn)) {
                    $brand = '';
                    $model = '';
                    $inv = Inventorys::where(['sn' => $value->sn])->first();
                    if ($inv) {
                        $brand = self::getBrandName($inv->brand_id);
                        $model = self::getModelName($inv->model_id);
                    }
                    $value->brand = $brand;
                    $value->model = $model;

                    $filteredData[] = $value;
                    $addedSn[] = $value->sn;
                }
            }
            if (@count($filteredData) > 0) {
                $response = ['requester' => $requester, 'error' => false, 'data' => $filteredData];
                return response()->json($response, 200);
            }
        }
        $response = ['requester' => $requester, 'error' => true, 'data' => 'send data n/a'];
        return response()->json($response, 403);
    }

    public function myreceivedata(Request $request)
    {
        $requester = Reused::getRequester();

        $headers = $request->server();
        $validHeader = Reused::validateHeader($headers);
        if (!$validHeader) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'invalid header'];
            return response()->json($response, 403);
        }
        $validApi = Reused::validateApiKey($headers);
        if (!$validApi) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'invalid api key'];
            return response()->json($response, 403);
        }

        $token = $headers['HTTP_TOKEN'];
        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'invalid token'];
            return response()->json($response, 403);
        }

        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'data' => 'user not registered'];
            return response()->json($response, 403);
        }

        $sendData = Movements::where(['receiver_id' => $user->id])->orderBy('created_at', 'desc')->get();
        if (@count($sendData) > 0) {
            $addedSn = [];
            $filteredData = [];
            foreach ($sendData as $key => $value) {
                $destination = $value->destination;
                $dest_code = $value->dest_code;
                $dest_name = '';
                $dest_address = '';
                $sender = 'n/a';
                if (!empty($value->sender_id)) {
                    $sender = self::getUserName($value->sender_id);
                }
                $value->sender = $sender;

                if (!in_array($value->sn, $addedSn)) {
                    $brand = '';
                    $model = '';
                    $inv = Inventorys::where(['sn' => $value->sn])->first();
                    if ($inv) {
                        $brand = self::getBrandName($inv->brand_id);
                        $model = self::getModelName($inv->model_id);
                    }
                    $value->brand = $brand;
                    $value->model = $model;

                    $filteredData[] = $value;
                    $addedSn[] = $value->sn;
                }
            }
            if (@count($filteredData) > 0) {
                $response = ['requester' => $requester, 'error' => false, 'data' => $filteredData];
                return response()->json($response, 200);
            }
        }
        $response = ['requester' => $requester, 'error' => true, 'data' => 'receive data n/a'];
        return response()->json($response, 403);
    }
}
