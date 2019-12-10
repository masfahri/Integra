<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    protected $connection = 'mysql';
    protected $table = 'stock_opname';
    public $timestamps = false;
}
