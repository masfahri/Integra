<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Users extends Model
{
    protected $connection = 'mysql';
    protected $table = 'users';
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
