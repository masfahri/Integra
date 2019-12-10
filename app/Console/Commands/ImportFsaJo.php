<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Ixudra\Curl\Facades\Curl;
use App\SnImport;
use App\JoDetail;
use App\MmidAlias;
use App\Mmid;
use App\Suggestion;
use App\ConsoleFsaTask;

class ImportFsaJo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:fsajo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Data From FSA JO Excel';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
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
                $this->info($mmid->name.' registered with id '.$mmid->mmid);
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
                    $existSuggest = Suggestion::where(['mid' => $mAlias->mid])->first();
                    if (!$existSuggest) {
                        $suggest = new Suggestion;
                        $suggest->mid = $mAlias->mid;
                        $suggest->suggest_mmid = $suggestion[0]->mmid;
                        $suggest->created_at = strtotime('now');
                        $suggest->save();

                        $this->info($mAlias->nama_merchant.' insert to suggest');
                    }
                    return ''; //need to add to task possible to parent mmid
                }
            }
            $mmid = self::generateMmid($mAlias->latitude, $mAlias->longitude, $mAlias->nama_merchant);
        }
        return $mmid;
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

    private function executeImportFsaJo($id, $path)
    {
        $array = Excel::toArray(new SnImport, $path);
        $sheet1 = $array[0];
        $names = $array[0][0];
        $titles = [];
        foreach ($names as $key => $value) {
            $titles[$key] = self::normalizeKeyName($value);
        }

        $validTitle = self::checkTitles($titles);
        if (!$validTitle) {
            $note = 'wrong format file titles not match';
            $this->info($note);
            ConsoleFsaTask::where(['id' => $id])->update(['status' => 'reject', 'notes' => $note]);
            exit();
        }

        $jo_insert = 0;
        $alias_insert = 0;
        $alias_update = 0;

        $dateKey = [0, 1, 25, 27, 29, 31, 66];
        foreach ($sheet1 as $key => $value) {
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

                                if (empty($mmid)) {
                                    $this->info($mAlias->nama_merchant.' suggest to merge');
                                }
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

                            if (empty($mmid)) {
                                $this->info($mAlias->nama_merchant.' suggest to merge');
                            }
                        }
                    }
                }
            }
        }

        $msg = $jo_insert.' jo imported and '.$alias_insert.' alias created, and '.$alias_update.' alias updated from '.(count($sheet1) -1).' row';
        ConsoleFsaTask::where(['id' => $id])->update(['status' => 'done', 'updated_at' => strtotime('now'), 'notes' => $msg]);
        $this->info($msg);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('start process...');
        $inProcess = ConsoleFsaTask::where(['status' => 'process'])->where('created_at', '>', strtotime('-1 day'))->count();
        if ($inProcess <= 0) {
            $this->info('no process common...');
            $task = ConsoleFsaTask::where(['status' => 'waiting', 'process_at' => 0, 'updated_at' => 0])
            ->where('created_at', '>', strtotime('-3 day'))
            ->select('*', 'path as realpath')
            ->orderBy('id', 'asc')
            ->first();

            if ($task) {
                $this->info('executing task...'.$task->id);
                //set flag to process
                ConsoleFsaTask::where(['id' => $task->id])->update(['status' => 'process', 'process_at' => strtotime('now')]);
                $name = $task->name;
                if ($name == 'fsa_jo') {
                    self::executeImportFsaJo($task->id, $task->realpath);
                }
            } else {
                $this->info('no task to execute...');
            }
        }
    }
}
