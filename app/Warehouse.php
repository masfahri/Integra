<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'ecosystem_warehouse';
    public $timestamps = false;
}
