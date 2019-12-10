<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Suggestion extends Model
{
    protected $connection = 'mmid';
    protected $table = 'mmid_suggestion';
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
}
