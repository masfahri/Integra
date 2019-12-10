<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Reused;
use App\Inventorys;
use App\ClientWarranty;
use App\Movement;
use App\Clients;
use App\Profiles;
use App\LogData;
use App\Pics;
use App\Warehouses;
use App\SnImport;
use App\Users;
use App\ConsoleTask;
use App\UserAccess;
use App\Brand;
use App\Models;
use App\StockOpname;

class InventoryController extends Controller
{
    private function getBrandName($brand_id)
    {
        $brand = Brand::where(['id' => $brand_id])->first();
        if ($brand) {
            return $brand->name;
        }
        return 'unknown';
    }

    private function getModelName($model_id)
    {
        $model = Models::where(['id' => $model_id])->first();
        if ($model) {
            return $model->name;
        }
        return 'unknown';
    }

    private function getClientName($client_id)
    {
        $client = Clients::where(['id' => $client_id])->first();
        if ($client) {
            return $client->name;
        }
        return 'unknown';
    }

    private function getSnBrandModel($inventory_id)
    {
        $inventory = Inventorys::join('brand', 'brand.id', '=', 'inventory.brand_id')
        ->join('model', 'model.id', 'inventory.model_id')
        ->where(['inventory.id' => $inventory_id])
        ->select('inventory.sn', 'brand.name as brand', 'model.name as model')
        ->first();

        if (!$inventory) {
            return false;
        }
        return $inventory;
    }

    private function getStatusWord($s)
    {
        $status = 'Unknown';
        if (strtolower($s) == 'sold') {
            $status = 'Sold';
        } elseif (strtolower($s) == 'rent') {
            $status = 'Rent';
        }
        return $status;
    }

    private function getWarrantyRange($start, $end)
    {
        $warranty = date('d M Y', $start).' - '.date('d M Y', $end);
        return $warranty;
    }

    private function getProfileName($profile_id)
    {
        $profile = Profiles::where(['id' => $profile_id])->first();
        if (!$profile) {
            return false;
        }
        return ucwords(strtolower($profile->fullname));
    }

    private function checkSnTitles($titles)
    {
        $valid = true;
        if (empty($titles)) {
            $valid = false;
        }
        $titles = explode(';', $titles);

        $keys = ['serialnumber', 'partnumber', 'brand'];
        foreach ($keys as $value) {
            if ($valid) {
                if (!in_array($value, $titles)) {
                    $valid = false;
                    break;
                }
            }
        }
        
        return $valid;
    }

    private function checkClientTitles($titles)
    {
        $valid = true;
        if (empty($titles)) {
            $valid = false;
        }
        $titles = explode(';', $titles);

        $keys = ['Inventory(serial number)', 'Client(code)', 'PartNumber', 'TID', 'MID', 'STATUS (sold/rent/repair)', 'Start warranty (Date)', 'End warranty (Date)'];
        foreach ($keys as $value) {
            if ($valid) {
                if (!in_array($value, $titles)) {
                    $valid = false;
                    break;
                }
            }
        }
        
        return $valid;
    }

    // delete soon
    public function testing(Request $request)
    {
        $token = $request->segment(2);
        $params = $request->all();
        $data = [$token, $params];
        return response()->json($data, 403);
    }

    public function loginuser(Request $request)
    {
        $log = new LogData;
        $log->log_data = json_encode($request->all());
        $log->save();

        $requester = Reused::getRequester();

        $username = $request->input('email');
        $pass = $request->input('password');

        $user = Users::where(['username' => $username])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid user!'];
            return response()->json($response, 403);
        }
        $salt = $user->salt;
        $hash = sha1($pass.$salt);
        if ($hash != $user->hash) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'wrong password!'];
            return response()->json($response, 403);
        }
        $token = Reused::generateToken($user->id);

        $data = [
            'name' => $user->username, 
            'email' => $user->email, 
            'cred' => strtoupper(hash('sha256', $user->level)), 
            'token' => $token
        ];
        // $response = ['data' => ['token' => base64_encode(json_encode($data))]];
        $response = ['data' => ['token' => $token]];
        return response()->json($response, 200);
    }

    public function validateuser(Request $request)
    {
        $requester = Reused::getRequester();

        $headers = $request->server();
        $token = $headers['HTTP_TOKEN'];
        $access = UserAccess::where(['token' => $token])->first();
        $userId = 0;
        if ($access) {
            $userId = $access->uid;
        }
        $user = Users::join('profile', 'profile.user_id', 'users.id')
        ->where(['users.id' => $userId])
        ->select('users.*', 'profile.email', 'profile.fullname', 'profile.phone', 'profile.gender', 'profile.photo')
        ->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'unknown user!'];
            return response()->json($response, 403);
        }
        $credential = strtoupper(hash('sha256', $user->level));
        unset($user->level);
        unset($user->id);
        unset($user->salt);
        unset($user->hash);
        unset($user->created_at);
        unset($user->updated_at);
        $user->credential = $credential;
        $user->token = $token;

        return response()->json($user, 200);
    }

    public function inventorydevices(Request $request)
    {
        $requester = $request->segment(2);
        $token = $request->segment(3);

        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid token!'];
            return response()->json($response, 403);
        }
        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid user!'];
            return response()->json($response, 403);
        }

        $page = $request->input('page');
        $limit = $request->input('limit');
        $offset = ($page - 1) * $limit;
        if (empty($page)) {
            $page = 1;
            $offset = 0;
            $limit = 10;
        }

        $serialnumber_like = $request->input('serialnumber_like');
        $brand_like = $request->input('brand_like');

        $mode = 'normal';
        $snLike = false;
        $brLike = false;
        if (!empty($serialnumber_like) || !empty($brand_like)) {
            $mode = 'filter';
            if (!empty($serialnumber_like)) {
                $snLike = true;
            }
            if (!empty($brand_like)) {
                $brLike = true;
            }
        }

        $data = [];
        $total = 0;
        if ($mode == 'normal') {
            $inventory = Inventorys::select('*', 'sn as serialnumber')
            ->orderBy('id', 'asc')
            ->orderBy('sn', 'asc')
            ->offset($offset)
            ->limit($limit)
            ->get();

            foreach ($inventory as $key => $value) {
                $value->brand = self::getBrandName($value->brand_id);
                $value->model = self::getModelName($value->model_id);
            }

            $total = Inventorys::count();
        } elseif ($mode == 'filter') {
            $inventory = null;
            if ($snLike && $brLike) {
                $brands = [];
                $brand = Brand::where('name', 'like', '%'.$brand_like.'%')
                ->limit(100)
                ->get();
                if (@count($brand) > 0) {
                    foreach ($brand as $key => $value) {
                        $brands[] = $value->id;
                    }
                }

                $inventory = Inventorys::where('sn', 'like', '%'.$serialnumber_like.'%')
                ->whereIn('brand_id', $brands)
                ->select('*', 'sn as serialnumber')
                ->orderBy('id', 'asc')
                ->orderBy('sn', 'asc')
                ->offset($offset)
                ->limit($limit)
                ->get();

                foreach ($inventory as $key => $value) {
                    $value->brand = self::getBrandName($value->brand_id);
                    $value->model = self::getModelName($value->model_id);
                }

                $total = Inventorys::where('sn', 'like', '%'.$serialnumber_like.'%')
                ->whereIn('brand_id', $brands)
                ->count();
            } else if ($snLike) {
                $inventory = Inventorys::where('sn', 'like', '%'.$serialnumber_like.'%')
                ->select('*', 'sn as serialnumber')
                ->orderBy('id', 'asc')
                ->orderBy('sn', 'asc')
                ->offset($offset)
                ->limit($limit)
                ->get();

                foreach ($inventory as $key => $value) {
                    $value->brand = self::getBrandName($value->brand_id);
                    $value->model = self::getModelName($value->model_id);
                }

                $total = Inventorys::where('sn', 'like', '%'.$serialnumber_like.'%')->count();
            } else {
                $brands = [];
                $brand = Brand::where('name', 'like', '%'.$brand_like.'%')
                ->limit(100)
                ->get();
                if (@count($brand) > 0) {
                    foreach ($brand as $key => $value) {
                        $brands[] = $value->id;
                    }
                }

                $inventory = Inventorys::whereIn('brand_id', $brands)
                ->select('*', 'sn as serialnumber')
                ->orderBy('id', 'asc')
                ->orderBy('sn', 'asc')
                ->offset($offset)
                ->limit($limit)
                ->get();

                foreach ($inventory as $key => $value) {
                    $value->brand = self::getBrandName($value->brand_id);
                    $value->model = self::getModelName($value->model_id);
                }

                $total = Inventorys::whereIn('brand_id', $brands)
                ->count();
            }
        }

        $response = ['requester' => $requester, 'error' => false, 'data' => $inventory, 'total' => $total, 'mode' => $mode];
        return response()->json($response, 200);
    }

    public function inventoryclient(Request $request)
    {
        $requester = $request->segment(2);
        $token = $request->segment(3);

        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid token!'];
            return response()->json($response, 403);
        }
        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid user!'];
            return response()->json($response, 403);
        }

        $page = $request->input('page');
        $limit = $request->input('limit');
        $offset = ($page - 1) * $limit;
        if (empty($page)) {
            $page = 1;
            $offset = 0;
            $limit = 10;
        }

        $serialnumber_like = $request->input('serialnumber_like');
        $client_like = $request->input('client_like');

        $mode = 'normal';
        $snLike = false;
        $cLike = false;
        if (!empty($serialnumber_like) || !empty($client_like)) {
            $mode = 'filter';
            if (!empty($serialnumber_like)) {
                $snLike = true;
            }
            if (!empty($client_like)) {
                $cLike = true;
            }
        }

        $data = [];
        $total = 0;
        if ($mode == 'normal') {
            $inventory = ClientWarranty::orderBy('id', 'asc')
            ->select('*', 'sn as serialnumber')
            ->offset($offset)
            ->limit($limit)
            ->get();

            if (@count($inventory) > 0) {
                foreach ($inventory as $key => $value) {
                    $value->ownership = self::getStatusWord($value->owning);
                    $attribute = self::getSnBrandModel($value->inventory_id);
                    $value->model = $attribute->model;
                    $value->brand = $attribute->brand;
                    $value->client = self::getClientName($value->client_id);
                    $value->warranty = self::getWarrantyRange($value->start, $value->end);
                }
            }

            $total = ClientWarranty::count();
        } elseif ($mode == 'filter') {
            $client_ids = [];
            if ($cLike) {
                $client = Clients::where('name', 'like', '%'.$client_like.'%')->limit(100)->get();
                if (@count($client) > 0) {
                    foreach ($client as $key => $value) {
                        $cId = $value->id;
                        if (!in_array($cId, $client_ids)) {
                            $client_ids[] = $value->id;
                        }
                    }
                }
            }

            $mode = 0;
            $inventory = null;
            if ($snLike && $cLike) {
                $inventory = ClientWarranty::where('sn', 'like', '%'.$serialnumber_like.'%')
                ->whereIn('client_id', $client_ids)
                ->select('*', 'sn as serialnumber')
                ->orderBy('id', 'asc')
                ->offset($offset)
                ->limit($limit)
                ->get();

                $total = ClientWarranty::orderBy('id', 'asc')
                ->where('sn', 'like', '%'.$serialnumber_like.'%')
                ->whereIn('client_id', $client_ids)
                ->count();
            } else if ($snLike) {
                $inventory = ClientWarranty::orderBy('id', 'asc')
                ->where('sn', 'like', '%'.$serialnumber_like.'%')
                ->select('*', 'sn as serialnumber')
                ->offset($offset)
                ->limit($limit)
                ->get();

                $total = ClientWarranty::orderBy('id', 'asc')
                ->where('sn', 'like', '%'.$serialnumber_like.'%')
                ->count();
            } else {
                $inventory = ClientWarranty::orderBy('id', 'asc')
                ->whereIn('client_id', $client_ids)
                ->select('*', 'sn as serialnumber')
                ->offset($offset)
                ->limit($limit)
                ->get();

                $total = ClientWarranty::orderBy('id', 'asc')
                ->whereIn('client_id', $client_ids)
                ->count();
            }

            if (@count($inventory) > 0) {
                foreach ($inventory as $key => $value) {
                    $value->ownership = self::getStatusWord($value->owning);
                    $attribute = self::getSnBrandModel($value->inventory_id);
                    $value->model = $attribute->model;
                    $value->brand = $attribute->brand;
                    $value->client = self::getClientName($value->client_id);
                    $value->warranty = self::getWarrantyRange($value->start, $value->end);
                }
            }
        }

        $response = ['requester' => $requester, 'error' => false, 'data' => $inventory, 'total' => $total];
        return response()->json($response, 200);
    }

    public function inventorymovement(Request $request)
    {
        $requester = $request->segment(2);
        $token = $request->segment(3);

        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid token!'];
            return response()->json($response, 403);
        }
        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid user!'];
            return response()->json($response, 403);
        }

        $response = ['requester' => $requester, 'error' => true, 'msg' => 'Temporary disable for migration'];
        return response()->json($response, 403);
    }

    public function snreport(Request $request)
    {
        $requester = Reused::getRequester();
        $token = $request->server()['HTTP_TOKEN'];

        $cTask = ConsoleTask::join('users', 'users.id', '=', 'console_task.uploader')
        ->select('console_task.*', 'users.username')
        ->orderBy('created_at', 'desc')
        ->get();
        
        if (@count($cTask) > 0) {
            $response = ['requester' => $requester, 'error' => false, 'data' => $cTask];
            return response()->json($response, 200);
        }
        $response = ['requester' => $requester, 'error' => true, 'msg' => 'Report not available!'];
        return response()->json($response, 403);
    }

    public function uploadsnpn(Request $request)
    {
        $requester = Reused::getRequester();
        $token = $request->server()['HTTP_TOKEN'];

        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid token!'];
            return response()->json($response, 403);
        }
        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid user!'];
            return response()->json($response, 403);
        }
        $userId = $user->id;

        if ($userId > 3) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'Import failed, you not permitted to upload!'];
            return response()->json($response, 403);
        }

        $enableUpload = true;
        $fileName = '';
        if(!$request->hasFile('sn_pn')) {
            $enableUpload = false;
        }

        if ($enableUpload) {
            $file = $request->file('sn_pn');
            if(!$file->isValid()) {
                $response = ['requester' => $requester, 'error' => true, 'msg' => 'Import failed, invalid file!'];
                return response()->json($response, 403);
            }
            $path = public_path() . '/uploads/';
            $file_extension = $file->getClientOriginalExtension();
            $ori_name = $file->getClientOriginalName();
            if ($file_extension != 'csv') {
                $response = ['requester' => $requester, 'error' => true, 'msg' => 'Import failed, wrong extention file!'];
                return response()->json($response, 403);
            }

            $fileName = strtotime('now').'_sn_'.$userId.'.'.$file_extension;
            $file->move($path, $fileName);

            $array = Excel::toArray(new SnImport, $path.$fileName);
            $sheet1 = $array[0];
            $titles = $sheet1[0][0];
            $validTitle = self::checkSnTitles($titles);
            if (!$validTitle) {
                $response = ['requester' => $requester, 'error' => true, 'msg' => 'Import failed, invalid csv titles!'];
                return response()->json($response, 403);
            }

            //store as console task
            $data = [
                'uploader' => $userId, 
                'name' => 'sn',
                'file_name' => $ori_name,
                'path' => $path.$fileName, 
                'status' => 'waiting',
                'created_at' => strtotime('now')
            ];
            DB::table('console_task')->insert($data);

            $msg = 'import will process 5000 row per minute on cosole layer, please check at import status at upload task menu';
            $response = ['requester' => $requester, 'error' => false, 'data' => $msg];
            return response()->json($response, 200);
        }
        $response = ['requester' => $requester, 'error' => true, 'msg' => 'Import failed, wrong file!'];
        return response()->json($response, 403);
    }

    public function uploadwarranty(Request $request)
    {
        $requester = Reused::getRequester();
        $token = $request->server()['HTTP_TOKEN'];

        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid token!'];
            return response()->json($response, 403);
        }
        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid user!'];
            return response()->json($response, 403);
        }
        $userId = $user->id;
        if ($userId > 3) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'Import failed, you not permitted to upload!'];
            return response()->json($response, 403);
        }
        
        $enableUpload = true;
        $fileName = '';
        if(!$request->hasFile('warranty')) {
            $enableUpload = false;
        }
        if ($enableUpload) {
            $file = $request->file('warranty');
            if(!$file->isValid()) {
                $response = ['requester' => $requester, 'error' => true, 'msg' => 'Import failed, invalid file!'];
                return response()->json($response, 403);
            }
            $path = public_path() . '/uploads/';
            $file_extension = $file->getClientOriginalExtension();
            $ori_name = $file->getClientOriginalName();
            if ($file_extension != 'csv') {
                $response = ['requester' => $requester, 'error' => true, 'msg' => 'Import failed, wrong extention file!'];
                return response()->json($response, 403);
            }

            $fileName = strtotime('now').'_client_'.$userId.'.'.$file_extension;
            $file->move($path, $fileName);

            $array = Excel::toArray(new SnImport, $path.$fileName);
            $sheet1 = $array[0];
            $titles = $sheet1[0][0];
            $validTitle = self::checkClientTitles($titles);
            if (!$validTitle) {
                $response = ['requester' => $requester, 'error' => true, 'msg' => 'Import failed, invalid csv titles!'];
                return response()->json($response, 403);
            }

            //store as console task
            $data = [
                'uploader' => $userId, 
                'name' => 'client',
                'file_name' => $ori_name,
                'path' => $path.$fileName, 
                'status' => 'waiting',
                'created_at' => strtotime('now')
            ];
            DB::table('console_task')->insert($data);

            $msg = 'import will process 5000 row per minute on cosole layer, please check at import status at upload task menu';
            $response = ['requester' => $requester, 'error' => false, 'data' => $msg];
            return response()->json($response, 200);
        }
        $response = ['requester' => $requester, 'error' => true, 'msg' => 'Import failed, wrong file!'];
        return response()->json($response, 403);
    }

    public function ecoclient(Request $request)
    {
        $requester = $request->segment(2);
        $token = $request->segment(3);

        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid token!'];
            return response()->json($response, 403);
        }
        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid user!'];
            return response()->json($response, 403);
        }

        $page = $request->input('page');
        $limit = $request->input('limit');
        $offset = ($page - 1) * $limit;
        if (empty($page)) {
            $page = 1;
            $offset = 0;
            $limit = 10;
        }

        $id_like = $request->input('id_like');
        $name_like = $request->input('name_like');
        $code_like = $request->input('code_like');
        $address_like = $request->input('address_like');
        $phone_number_like = $request->input('phone_number_like');

        $mode = 'normal';
        $idLike = false;
        $nameLike = false;
        $codeLike = false;
        $addrLike = false;
        $phoneLike = false;
        if (!empty($id_like) || !empty($name_like) || !empty($code_like) || !empty($address_like) || !empty($phone_number_like)) {
            $mode = 'filter';
            if (!empty($id_like)) {
                $idLike = true;
            }
            if (!empty($name_like)) {
                $nameLike = true;
            }
            if (!empty($code_like)) {
                $codeLike = true;
            }
            if (!empty($address_like)) {
                $addrLike = true;
            }
            if (!empty($phone_number_like)) {
                $phoneLike = true;
            }
        }

        $data = null;
        $total = 0;
        if ($mode == 'normal') {
            $data = Clients::select('*', 'phone as phone_number')->limit($limit)->offset($offset)->get();
            $total = Clients::count();
        } else {
            $likes = [
                'id' => $id_like, 
                'name' => $name_like, 
                'code' => $code_like, 
                'address' => $address_like, 
                'phone' => $phone_number_like
            ];

            $data = Clients::where(function($query) use ($likes) {
                foreach ($likes as $key => $value) {
                    if (!empty($value)) {
                        $query->where($key, 'like', '%'.$value.'%');
                    }
                }
            })
            ->select('*', 'phone as phone_number')
            ->offset($offset)
            ->limit($limit)
            ->get();

            $total = Clients::where(function($query) use ($likes) {
                foreach ($likes as $key => $value) {
                    if (!empty($value)) {
                        $query->where($key, 'like', '%'.$value.'%');
                    }
                }
            })
            ->count();
        }

        $response = ['requester' => $requester, 'error' => false, 'data' => $data, 'total' => $total];
        return response()->json($response, 200);
    }

    public function ecopic(Request $request)
    {
        $requester = $request->segment(2);
        $token = $request->segment(3);

        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid token!'];
            return response()->json($response, 403);
        }
        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid user!'];
            return response()->json($response, 403);
        }

        $page = $request->input('page');
        $limit = $request->input('limit');
        $offset = ($page - 1) * $limit;
        if (empty($page)) {
            $page = 1;
            $offset = 0;
            $limit = 10;
        }

        $pname_like = $request->input('pname_like');
        $clname_like = $request->input('clname_like');
        $whname_like = $request->input('whname_like');

        $mode = 'normal';
        $pname = false;
        $clname = false;
        $whname = false;
        if (!empty($pname_like) || !empty($clname_like) || !empty($whname_like)) {
            $mode = 'filter';
            if (!empty($pname_like)) {
                $pname = true;
            }
            if (!empty($clname_like)) {
                $clname = true;
            }
            if (!empty($whname_like)) {
                $whname = true;
            }
        }

        $data = null;
        $total = 0;
        if ($mode == 'normal') {
            $data = Pics::join('profile', 'profile.id', '=', 'pic.profile_id')
            ->join('warehouse', 'warehouse.id', '=', 'pic.warehouse_id')
            ->leftjoin('client', 'client.id', '=', 'pic.client_id')
            ->select('pic.*', 'profile.fullname as pname', 'warehouse.name as whname', 'warehouse.address as whaddress', 'warehouse.lat as whlat', 'warehouse.lng as whlng', 'client.name as clname')
            ->offset($offset)
            ->limit($limit)
            ->get();

            $total = Pics::count();
        } else {
            $profileIds = [];
            if ($pname) {
                $profiles = Profiles::where('fullname', 'like', '%'.$pname_like.'%')->limit(100)->get();
                foreach ($profiles as $key => $value) {
                    $id = $value->id;
                    if (!in_array($id, $profileIds)) {
                        $profileIds[] = $id;
                    }
                }
            }
            
            $clientIds = [];
            if ($clname) {
                $clients = Clients::where('name', 'like', '%'.$clname_like.'%')->limit(100)->get();
                foreach ($clients as $key => $value) {
                    $id = $value->id;
                    if (!in_array($id, $clientIds)) {
                        $clientIds[] = $id;
                    }
                }
            }

            $whIds = [];
            if ($whname) {
                $wh = Warehouses::where('name', 'like', '%'.$whname_like.'%')->limit(100)->get();
                foreach ($wh as $key => $value) {
                    $id = $value->id;
                    if (!in_array($id, $whIds)) {
                        $whIds[] = $id;
                    }
                }
            }

            $likes = ['pic.profile_id' => $profileIds, 'pic.client_id' => $clientIds, 'pic.warehouse_id' => $whIds];
            $data = Pics::join('profile', 'profile.id', '=', 'pic.profile_id')
            ->join('warehouse', 'warehouse.id', '=', 'pic.warehouse_id')
            ->leftjoin('client', 'client.id', '=', 'pic.client_id')
            ->where(function($query) use ($likes) {
                foreach ($likes as $key => $value) {
                    if (!empty($value)) {
                        $query->whereIn($key, $value);
                    }
                }
            })
            ->select('pic.*', 'profile.fullname as pname', 'warehouse.name as whname', 'warehouse.address as whaddress', 'warehouse.lat as whlat', 'warehouse.lng as whlng', 'client.name as clname')
            ->offset($offset)
            ->limit($limit)
            ->get();

            $total = Pics::where(function($query) use ($likes) {
                foreach ($likes as $key => $value) {
                    if (!empty($value)) {
                        $query->whereIn($key, $value);
                    }
                }
            })
            ->count();
        }

        $response = ['requester' => $requester, 'error' => false, 'data' => $data, 'total' => $total];
        return response()->json($response, 200);
    }

    public function warehouse(Request $request)
    {
        $requester = $request->segment(2);
        $token = $request->segment(3);

        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid token!'];
            return response()->json($response, 403);
        }
        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid user!'];
            return response()->json($response, 403);
        }

        $page = $request->input('page');
        $limit = $request->input('limit');
        $offset = ($page - 1) * $limit;
        if (empty($page)) {
            $page = 1;
            $offset = 0;
            $limit = 10;
        }

        $code_like = $request->input('code_like');
        $name_like = $request->input('name_like');
        $address_like = $request->input('address_like');
        $phone_number_like = $request->input('phone_number_like');

        $cLike = false;
        $nLike = false;
        $aLike = false;
        $pLike = false;

        $mode = 'normal';
        if (!empty($code_like) || !empty($name_like) || !empty($address_like) || !empty($phone_number_like)) {
            $mode = 'filter';
            if (!empty($code_like)) {
                $cLike = true;
            }
            if (!empty($name_like)) {
                $nLike = true;
            }
            if (!empty($address_like)) {
                $aLike = true;
            }
            if (!empty($phone_number_like)) {
                $pLike = true;
            }
        }

        $data = null;
        $total = 0;
        if ($mode == 'normal') {
            $data = Warehouses::offset($offset)->limit($limit)->get();
            $total = Warehouses::count();
        } else {
            $likes = ['code' => $code_like, 'name' => $name_like, 'address' => $address_like, 'phone' => $phone_number_like];
            $data = Warehouses::where(function($query) use ($likes) {
                foreach ($likes as $key => $value) {
                    if (!empty($value)) {
                        $query->where($key, 'like', '%'.$value.'%');
                    }
                }
            })
            ->offset($offset)
            ->limit($limit)
            ->get();

            $total = Warehouses::where(function($query) use ($likes) {
                foreach ($likes as $key => $value) {
                    if (!empty($value)) {
                        $query->where($key, 'like', '%'.$value.'%');
                    }
                }
            })
            ->count();
        }

        $response = ['requester' => $requester, 'error' => false, 'data' => $data, 'total' => $total];
        return response()->json($response, 200);
    }

    public function sndetails(Request $request)
    {
        $requester = Reused::getRequester();

        $sn = $request->input('sn');
        $inventory = Inventorys::where(['sn' => $sn])->first();
        if (!$inventory) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'Data not available!'];
            return response()->json($response, 403);
        }

        $client_code = '';
        $client_name = '';
        $client_address = '';
        $client_phone = '';
        $owner_status = '';
        $warranty = '';
        $cw = ClientWarranty::where(['sn' => $sn])->first();
        if ($cw) {
            $client = Clients::where(['id' => $cw->client_id])->first();
            if ($client) {
                $client_code = $client->code;
                $client_name = $client->name;
                $client_address = $client->address;
                $client_phone = $client->phone;
            }
            $owner_status = self::getStatusWord($cw->owning);
            $warranty = self::getWarrantyRange($cw->start, $cw->end);
        }

        $brand = '';
        $model = '';
        $parts = self::getSnBrandModel($inventory->id);
        if ($parts) {
            $brand = $parts['brand'];
            $model = $parts['model'];
        }
        $latitude = 0;
        $longitude = 0;

        $inventory->client_code = $client_code;
        $inventory->client_name = $client_name;
        $inventory->client_address = $client_address;
        $inventory->client_phone = $client_phone;
        $inventory->owner_status = $owner_status;
        $inventory->warranty = $warranty;
        $inventory->brand = $brand;
        $inventory->model = $model;
        $inventory->latitude = $latitude;
        $inventory->longitude = $longitude;

        $response = ['requester' => $requester, 'error' => false, 'data' => $inventory];
        return response()->json($response, 200);
    }

    public function brands(Request $request)
    {
        $requester = $request->segment(2);
        $token = $request->segment(3);

        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid token!'];
            return response()->json($response, 403);
        }
        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid user!'];
            return response()->json($response, 403);
        }

        $page = $request->input('page');
        $limit = $request->input('limit');
        $offset = ($page - 1) * $limit;
        if (empty($page)) {
            $page = 1;
            $offset = 0;
            $limit = 10;
        }
        
        $mode = 'normal';
        $data = null;
        $total = 0;
        if ($mode == 'normal') {
            $data = Brand::offset($offset)
            ->limit($limit)
            ->get();

            $total = Brand::count();
        }

        $response = ['requester' => $requester, 'error' => false, 'data' => $data, 'total' => $total];
        return response()->json($response, 200);
    }

    public function models(Request $request)
    {
        $requester = $request->segment(2);
        $token = $request->segment(3);

        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid token!'];
            return response()->json($response, 403);
        }
        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid user!'];
            return response()->json($response, 403);
        }

        $page = $request->input('page');
        $limit = $request->input('limit');
        $offset = ($page - 1) * $limit;
        if (empty($page)) {
            $page = 1;
            $offset = 0;
            $limit = 10;
        }
        
        $mode = 'normal';
        $data = null;
        $total = 0;
        if ($mode == 'normal') {
            $data = Models::offset($offset)
            ->limit($limit)
            ->get();

            $total = Models::count();
        }

        $response = ['requester' => $requester, 'error' => false, 'data' => $data, 'total' => $total];
        return response()->json($response, 200);
    }

    public function stockopname(Request $request)
    {
        $requester = Reused::getRequester();

        $page = $request->input('page');
        $limit = $request->input('limit');
        $offset = ($page - 1) * $limit;
        if (empty($page)) {
            $page = 1;
            $offset = 0;
            $limit = 10;
        }
        
        $mode = 'normal';
        $data = null;
        $total = 0;
        if ($mode == 'normal') {
            $data = StockOpname::offset($offset)
            ->limit($limit)
            ->get();

            $total = StockOpname::count();
        }

        $response = ['requester' => $requester, 'error' => false, 'data' => $data, 'total' => $total];
        return response()->json($response, 200);
    }

    public function updatepass(Request $request)
    {
        $requester = Reused::getRequester();
        $token = $request->server()['HTTP_TOKEN'];

        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid token!'];
            return response()->json($response, 403);
        }
        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid user!'];
            return response()->json($response, 403);
        }
        $userId = $user->id;

        $old_pass = $request->input('old_pass');
        $new_pass = $request->input('new_pass');
        if (empty($old_pass) || empty($new_pass)) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'old pass & new pass cannot empty!'];
            return response()->json($response, 403);
        }

        $length = strlen($new_pass);
        if ($length < 6) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'minimum 6 char!'];
            return response()->json($response, 403);
        }

        $salt = $user->salt;
        $hash = sha1($old_pass.$salt);
        if ($hash != $user->hash) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'wrong old password!'];
            return response()->json($response, 403);
        }

        $newSalt = Reused::generateSalt();
        $newHash = sha1($new_pass.$newSalt);
        // update data
        $user->salt = $newSalt;
        $user->hash = $newHash;
        $user->updated_at = strtotime('now');
        if ($user->save()) {
            $response = ['requester' => $requester, 'error' => false, 'data' => 'succes change password'];
            return response()->json($response, 200);
        }
        $response = ['requester' => $requester, 'error' => true, 'msg' => 'failed update password!'];
        return response()->json($request->all(), 403);
    }

    private function getGroupLevel($level)
    {
        $name = '';
        switch ($level) {
            case 'wh':
                $name = 'Warehouse';
                break;

            case 'inv':
                $name = 'Inventory';
                break;

            case 'mmid':
                $name = 'MMID';
                break;

            case 'dev':
                $name = 'Developer';
                break;
            
            default:
                $name = 'Guest';
                break;
        }
        return $name;
    }

    public function allusers(Request $request)
    {
        $requester = Reused::getRequester();
        $token = $request->server()['HTTP_TOKEN'];

        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid token!'];
            return response()->json($response, 403);
        }
        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid user!'];
            return response()->json($response, 403);
        }
        $userId = $user->id;

        $userAll = Users::join('profile', 'profile.user_id', 'users.id')
        ->whereNotIn('users.id', [1, 2])
        ->select('users.*', 'profile.email', 'profile.fullname', 'profile.phone', 'profile.gender', 'profile.photo')
        ->orderBy('profile.fullname', 'asc')
        ->get();

        $filtered = [];
        if (@count($userAll) > 0) {
            foreach ($userAll as $key => $value) {
                $created = date('d M Y H:i', $value->created_at);
                $updated = date('d M Y H:i', $value->updated_at);
                if ($value->updated_at == 0) {
                    $updated = $created;
                }
                
                unset($value->salt);
                unset($value->hash);
                $value->level = self::getGroupLevel($value->level);
                $value->created_at = $created;
                $value->updated_at = $updated;
                
                $filtered[] = $value;
            }
        }

        $response = ['requester' => $requester, 'error' => false, 'data' => $filtered];
        return response()->json($response, 200);
    }

    private function getLevelCode($group)
    {
        $code = '';
        switch ($group) {
            case 'Dev':
                $code = 'dev';
                break;

            case 'Warehouse':
                $code = 'wh';
                break;

            case 'Inventory':
                $code = 'inv';
                break;

            case 'MMID':
                $code = 'mmid';
                break;
            
            default:
                $code = 'wh';
                break;
        }
        return $code;
    }

    public function insertuser(Request $request)
    {
        $requester = Reused::getRequester();
        $token = $request->server()['HTTP_TOKEN'];

        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();

        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid token!'];
            return response()->json($response, 403);
        }
        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid user!'];
            return response()->json($response, 403);
        }
        $userId = $user->id;

        $username = $request->input('username');
        $group = $request->input('group');
        $handphone = $request->input('handphone');
        $email = $request->input('email');
        $fullname = $request->input('fullname');
        $gender = $request->input('gender');
        $clientId = $request->input('client_id');
        if (empty($username) || empty($group) || empty($handphone) || empty($email) || empty($fullname) || empty($gender)) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'please fill all fields!'];
            return response()->json($response, 403);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|regex:/^[A-Za-z.]+$/',
            'group' => 'required',
            'handphone' => 'required|numeric|min:10',
            'email' => 'required|email',
            'fullname' => 'required',
            'gender' => 'required'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $response = ['requester' => $requester, 'error' => true, 'msg' => $errors->first()];
            return response()->json($response, 403);
        }
        $mails = explode('@', $email);
        if (count($mails) < 2) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid email address!'];
            return response()->json($response, 403);
        }
        $mailDot = explode('.', $mails[1]);
        if (count($mailDot) < 2) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid email address!'];
            return response()->json($response, 403);
        }

        $existClient = Clients::where(['id' => $clientId])->count();
        if ($existClient == 0) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'Client Not Found!'];
            return response()->json($response, 403);
        }

        $existUser = Users::where(['username' => strtolower($username)])->count();
        if ($existUser > 0) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'username already used!'];
            return response()->json($response, 403);
        }

        $existEmail = Profiles::where(['email' => $email])->count();
        if ($existEmail > 0) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'email already used!'];
            return response()->json($response, 403);
        }
        $levelCode = self::getLevelCode($group);
        $default_pass = 'qwerty09876';
        $salt = Reused::generateSalt();
        $gen = 'M';
        if (strtolower($gender) == 'female') {
            $gen = 'F';
        }

        $nUser = new Users;
        $nUser->username = $username;
        $nUser->level = $levelCode;
        $nUser->status = 'active';
        $nUser->salt = $salt;
        $nUser->hash = sha1($default_pass.$salt);
        $nUser->created_at = strtotime('now');
        $nUser->updated_at = strtotime('now');
        $nUser->created_by = $userId;
        if ($group == 'Dev') {
            $clientId = 0;
        }
        $nUser->client_id = $clientId;
        if ($nUser->save()) {
            $pr = new Profiles;
            $pr->user_id = $nUser->id;
            $pr->email = $email;
            $pr->fullname = $fullname;
            $pr->phone = $handphone;
            $pr->gender = $gen;
            $pr->created_at = strtotime('now');
            $pr->created_by = $userId;
            if ($pr->save()) {
                $response = ['requester' => $requester, 'error' => false, 'data' => 'success add user!'];
                return response()->json($response, 200);
            }
        }
        $response = ['requester' => $requester, 'error' => true, 'msg' => 'failed add user!'];
        return response()->json($request->all(), 403);
    }

    public function insertwarehouse(Request $request)
    {
        $requester = Reused::getRequester();
        $token = $request->server()['HTTP_TOKEN'];

        $access = UserAccess::where(['token' => $token])->whereNull('removed_at')->first();
        if (!$access) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid token!'];
            return response()->json($response, 403);
        }
        $user = Users::where(['id' => $access->uid])->first();
        if (!$user) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'invalid user!'];
            return response()->json($response, 403);
        }
        $userId = $user->id;

        $code = $request->input('code');
        $name = $request->input('name');
        $address = $request->input('address');
        $phone = $request->input('phone');
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $clientId = $request->input('client_id');
        $status = 'active';
        if (empty($code) || empty($name) || empty($address) || empty($phone) || empty($lat) || empty($lng) || empty($clientId)) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'please fill all fields!'];
            return response()->json($response, 403);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required',
            'name' => 'required',
            'address' => 'required',
            'phone' => 'required|numeric|min:10',
            'lat' => 'required',
            'lng' => 'required',
            'client_id' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $response = ['requester' => $requester, 'error' => true, 'msg' => $errors->first()];
            return response()->json($response, 403);
        }

        $existClient = Clients::where(['id' => $clientId])->count();
        if ($existClient == 0) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'Client Not Found!'];
            return response()->json($response, 403);
        }

        $existsWarehouse = Warehouses::where(['code' => $code])->count();
        if ($existsWarehouse > 0) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'Code already used!'];
            return response()->json($response, 403);
        }

        $existPhone = Warehouses::where(['phone' => $phone])->count();
        if ($existPhone > 0) {
            $response = ['requester' => $requester, 'error' => true, 'msg' => 'Phone already used!'];
            return response()->json($response, 403);
        }

        $nWherehouses = new Warehouses;
        $nWherehouses->code = $code;
        $nWherehouses->name = $name;
        $nWherehouses->address = $address;
        $nWherehouses->phone = $phone;
        $nWherehouses->lat = $lat;
        $nWherehouses->lng = $lng;
        $nWherehouses->status = $status;
        $nWherehouses->client_id = $clientId;
        $nWherehouses->created_at = strtotime('now');
        if ($nWherehouses->save()) {
            $response = ['requester' => $requester, 'error' => false, 'data' => 'success add Warehouse!'];
            return response()->json($response, 200);
        }
        $response = ['requester' => $requester, 'error' => true, 'msg' => 'failed add Warehouse!'];
        return response()->json($request->all(), 403);
    }
}
