<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Mmid extends Model
{
    protected $connection = 'mmid';
    protected $table = 'mmid';
    public $timestamps = false;

    public function getCreatedAtAttribute($value){
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

    public function getLatitudeAttribute($value){
        if (empty($value)) {
            return 0;
        } else {
            return (double) $value;
        }
    }

    public function getLongitudeAttribute($value){
        if (empty($value)) {
            return 0;
        } else {
            return (double) $value;
        }
    }
}
