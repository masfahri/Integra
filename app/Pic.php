<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Pic extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'ecosystem_pic';
    public $timestamps = false;

    public function getPnameAttribute($value){
        if (empty($value)) {
            return '';
        } else {
            return ucwords(strtolower($value));
        }
    }

    public function getWhnameAttribute($value){
        if (empty($value)) {
            return '';
        } else {
            //return ucwords(strtolower($value));
            return $value;
        }
    }
}
