<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InventoryClient extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'inventory_inventoryclient';
    public $timestamps = false;
}
