<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Warehouses extends Model
{
    protected $connection = 'mysql';
    protected $table = 'warehouse';
    public $timestamps = false;
}
