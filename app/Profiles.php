<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Profiles extends Model
{
    protected $connection = 'mysql';
    protected $table = 'profile';
    public $timestamps = false;

    public function getPhotoAttribute($value){
        if (empty($value)) {
            return '';
        } else {
            return url('/').$value;
        }
    }

    public function getPhoneAttribute($value){
        if (empty($value)) {
            return '';
        } else {
            return $value;
        }
    }
}
