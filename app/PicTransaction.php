<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PicTransaction extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'inventory_pictransaction';
    public $timestamps = false;
}
