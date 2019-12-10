<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ConsoleTask extends Model
{
    protected $connection = 'mysql';
    protected $table = 'console_task';
    public $timestamps = false;

    public function getPathAttribute($value){
        if (empty($value)) {
            return '';
        } else {
            return url('/').str_replace('/var/www/amsapi/public', '', $value);
        }
    }

    public function getCreatedAtAttribute($value){
        if (empty($value)) {
            return 'n/a';
        } else {
            return date('d M Y H:i', $value);
        }
    }

    public function getProcessAtAttribute($value){
        if (empty($value)) {
            return 'n/a';
        } else {
            return date('d M Y H:i', $value);
        }
    }

    public function getUpdatedAtAttribute($value){
        if (empty($value)) {
            return 'n/a';
        } else {
            return date('d M Y H:i', $value);
        }
    }

    public function getFileNameAttribute($value){
        if (empty($value)) {
            return 'n/a';
        } else {
            return $value;
        }
    }
}
