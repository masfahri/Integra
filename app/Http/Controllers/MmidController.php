<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Reused;
use App\Mmid;
use App\MmidAlias;
use App\Suggestion;
use App\UserAccess;
use App\SnImport;
use App\ConsoleFsaTask;
use App\Users;

class MmidController extends Controller
{
    public function merchantdata(Request $request)
    {
        $requester = Reused::getRequester();
        $token = $request->server()['HTTP_TOKEN'];

        $data = Mmid::orderBy('name')->get();
        
        if (@count($data) > 0) {
            $response = ['requester' => $requester, 'error' => false, 'data' => $data];
            return response()->json($response, 200);
        }
        $response = ['requester' => $requester, 'error' => true, 'msg' => 'Report not available!'];
        return response()->json($response, 403);
    }

    public function merchantalias(Request $request)
    {
        $requester = Reused::getRequester();
        $token = $request->server()['HTTP_TOKEN'];
        
        $data = MmidAlias::orderBy('nama_merchant')->get();
        
        if (@count($data) > 0) {
            $response = ['requester' => $requester, 'error' => false, 'data' => $data];
            return response()->json($response, 200);
        }
        $response = ['requester' => $requester, 'error' => true, 'msg' => 'Report not available!'];
        return response()->json($response, 403);
    }

    public function merchantsuggestion(Request $request)
    {
        $requester = Reused::getRequester();
        $data = Suggestion::join('mmid_alias', 'mmid_alias.mid', '=', 'mmid_suggestion.mid')
        ->whereNull('mmid_suggestion.updated_at')
        ->select('mmid_suggestion.*', 'mmid_alias.nama_merchant', 'mmid_alias.alamat_merchant', 'mmid_alias.alamat_merchant_2', 'mmid_alias.kode_pos', 'mmid_alias.latitude', 'mmid_alias.longitude')
        ->orderBy('mmid_alias.nama_merchant')
        ->get();
        
        if (@count($data) > 0) {
            $response = ['requester' => $requester, 'error' => false, 'data' => $data];
            return response()->json($response, 200);
        }
        $response = ['requester' => $requester, 'error' => true, 'msg' => 'Report not available!'];
        return response()->json($response, 403);
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

    private function checkTitles($titles)
    {
        if (!is_array($titles)) {
            return false;
        }

        $valid = true;
        $keys = ['tanggal_import_sistem','tanggal_terima_tiket','bank_date','bank_time','bank','id_lapor_ompk_case_id','no_kontrak','nomor_tiket','no_spk','jenis_pekerjaan','tid_csi','tid_cimb','ciltap','mid','nama_merchant','alamat_merchant','alamat_merchant_2','kode_pos','kota','nama_pic','no_pic','catatan_pra_kunjungan','jenis_kerusakan','kode_init','model_edc_ots','tgl_kunjungan_ulang_1','catatan_kunjungan_ulang_1','tgl_kunjungan_ulang_2','catatan_kunjungan_ulang_2','tgl_kunjungan_ulang_3','catatan_kunjungan_ulang_3','tanggal_perubahan_status','status_pekerjaan','catatan_status','catatan_kunjungan','notel_ots','pic_ots','merek_edc','model_edc','sn_edc_sekarang','sn_edc_ots','samcard','versi_software','peripheral','edc_bank_lain','operator','koneksi','sim_card_sekarang','sim_card_pengganti','transaksi','fitur','jumlah_faktur_ots','jumlah_faktur_tambahan','edc_fitur','no_bast','callsign_id','link_foto_merchant','link_foto_sn_edc','link_foto_struk_transaksi','link_foto_fkm','link_foto_lainnya','nama_teknisi','company','team','team_leader','service_point','sla_date','sla_time','usia_jo','status_sla','latitude','longitude'];
        $diff = array_diff($titles, $keys);
        if (count($diff) > 0) {
        	return false;
        }
        return true;
    }

    public function uploadfsajo(Request $request)
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

        $enableUpload = true;
        $fileName = '';
        if(!$request->hasFile('fsa_jo')) {
            $enableUpload = false;
        }

        if ($enableUpload) {
            $file = $request->file('fsa_jo');
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

            $fileName = strtotime('now').'_fsajo_'.$userId.'.'.$file_extension;
            $file->move($path, $fileName);

            // $array = Excel::toArray(new SnImport, $path.$fileName);
            // $sheet1 = $array[0];
            // $names = $array[0][0];
            // $titles = [];
            // foreach ($names as $key => $value) {
            //     $titles[$key] = self::normalizeKeyName($value);
            // }

            // $validTitle = self::checkTitles($titles);
            // if (!$validTitle) {
            //     $response = ['requester' => $requester, 'error' => true, 'msg' => 'Import failed, invalid xlsx titles!'];
            //     return response()->json($response, 403);
            // }

            //store as console task
            $data = [
                'uploader' => $userId, 
                'name' => 'fsa_jo',
                'file_name' => $ori_name,
                'path' => $path.$fileName, 
                'status' => 'waiting',
                'created_at' => strtotime('now')
            ];
            DB::connection('mmid')->table('console_fsatask')->insert($data);

            $msg = 'import will process 5000 row per minute on cosole layer, please check at import status at upload task menu';
            $response = ['requester' => $requester, 'error' => false, 'data' => $msg];
            return response()->json($response, 200);
        }
        $response = ['requester' => $requester, 'error' => true, 'msg' => 'Import failed, wrong file!'];
        return response()->json($response, 403);
    }

    private function getUserName($id)
    {
    	$user = Users::where(['id' => $id])->first();
    	return $user;
    }

    public function fsajoreport(Request $request)
    {
        $requester = Reused::getRequester();
        $data = ConsoleFsaTask::orderBy('created_at', 'desc')->get();
        if (@count($data) > 0) {
        	foreach ($data as $key => $value) {
        		$uploader = $value->uploader;
        		$uploadBy = self::getUserName($uploader);
        		if ($uploadBy) {
        			$value->username = $uploadBy->username;
        		}
        	}
        }
        
        if (@count($data) > 0) {
            $response = ['requester' => $requester, 'error' => false, 'data' => $data];
            return response()->json($response, 200);
        }
        $response = ['requester' => $requester, 'error' => true, 'msg' => 'Report not available!'];
        return response()->json($response, 403);
    }
}
