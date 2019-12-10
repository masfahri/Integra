<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'inventory_inventory';
    public $timestamps = false;

    // public function getLogoAttribute($value){
    //     if (empty($value)) {
    //         return '';
    //     } else {
    //         return $value;
    //     }
    // }
}
