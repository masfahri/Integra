<?php

namespace App\CustomHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Ixudra\Curl\Facades\Curl;

class Reused 
{
    // func for call cust api
    public static function getRequester()
    {
        $url = URL::current();
        $urls = explode('/', $url);
        $requester = $urls[count($urls) -1];
        return $requester;
    }
    
    public static function validateHeader($headers)
    {
        $valid = true;
        $headerNeeds = ["HTTP_API", "HTTP_AGENT", "HTTP_BUNDLE", "HTTP_TOKEN"];
        $filteredHeaders = [];
        foreach ($headers as $key => $value) {
            if (in_array($key, $headerNeeds)) {
                if (!empty($value)){
                    $filteredHeaders[] = $key;
                }
            }
        }
        $diff = array_diff($headerNeeds, $filteredHeaders);
        if (@count($diff) > 0) {
            $valid = false;
        }
        return $valid;
    }

    public static function validateApiKey($headers)
    {
        $apiKeys = DB::connection('mysql')
        ->table('api_keys')
        ->where(['api_key' => $headers['HTTP_API'], 'status' => 'active', 'bundle_name' => $headers['HTTP_BUNDLE']])
        ->orderBy('id', 'desc')
        ->first();
        if ($apiKeys) {
            $reqversion = $apiKeys->version;
            $maxVersion = DB::connection('mysql')->table('api_keys')->where(['bundle_name' => $headers['HTTP_BUNDLE']])->max('version');
            if ($maxVersion > $reqversion) {
                return ['active' => true, 'update' => false];
            }
            return ['active' => true, 'update' => true];
        }
        return false;
    }

    public static function getIpAddr()
    {
        $ip = '127.0.0.1';
        foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key){
            if (array_key_exists($key, $_SERVER) === true){
                foreach (explode(',', $_SERVER[$key]) as $ip){
                    $ip = trim($ip); // just to be safe
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false){
                        return $ip;
                    }
                }
            }
        }
        return $ip;
    }

    public static function generateSalt($n = 6)
    {
        $code = '';
        $pattern = '1234567890abcdefghijlmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $counter = strlen($pattern)-1;
        for ($i=0; $i<$n; $i++) {
            $code .= $pattern{rand(0, $counter)};
        }
        return $code;
    }

    public static function generateToken($uid = '')
    {
        $headers = apache_request_headers();
        $agent = 'n/a';
        if (isset($headers['User-Agent'])) {
            $agent = $headers['User-Agent'];
        }
        $now = strtotime('now');
        DB::connection('mysql')->table('user_access')
        ->where('uid', '=', $uid)
        ->whereNull('removed_at')
        ->update(['removed_at' => $now]);

        $token = md5(date('Ymdhis') . rand(10,99) . rand(10,99) . rand(10,99));
        $from_ip = self::getIpAddr();
        
        $data = ['uid' => $uid, 'token' => $token, 'created_at' => $now, 'from_ip' => $from_ip, 'user_agent' => $agent];
        DB::connection('mysql')->table('user_access')->insert($data);
        return $token;
    }

    public static function validateHandphone($handphone = '')
    {
        $unwanteds = ['+', ' ', '-'];
        $replacements = ['', '', ''];
        $handphone = str_replace($unwanteds, $replacements, $handphone);
        $handphone = preg_replace('/[^0-9]+/', '', $handphone);

        $length = strlen($handphone);
        if ($length < 9) {
            return false;
        }
        
        $prefix = substr($handphone, 0, 2);
        if ($prefix == '62') {
            return substr($handphone, 2);
        }
        else if ($prefix == '08') {
            return substr($handphone, 1, ($length -1));
        }
        else {
            $prefix = substr($handphone, 0, 1);
            if ($prefix == '8') {
                return $handphone;
            }
            return false;
        }
        return false;
    }

    public static function importFileContents($file_path)
    {
        $query = sprintf("LOAD DATA LOCAL INFILE '%s' INTO TABLE temp_sn 
            LINES TERMINATED BY '\\n'
            FIELDS TERMINATED BY ';' 
            IGNORE 1 LINES (`content`)", addslashes($file_path));

        return DB::connection('mysql')->getpdo()->exec($query);
    }


}