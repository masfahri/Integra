<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Movements extends Model
{
    protected $connection = 'mysql';
    protected $table = 'movement';
    public $timestamps = false;

    public function getDestinationAttribute($value){
        if (empty($value)) {
            return '';
        } else {
            return ucwords($value);
        }
    }
}
