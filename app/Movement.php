<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Movement extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'inventory_inventorymovement';
    public $timestamps = false;
}
