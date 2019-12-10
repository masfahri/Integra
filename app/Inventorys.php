<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Inventorys extends Model
{
    protected $connection = 'mysql';
    protected $table = 'inventory';
    public $timestamps = false;
}
