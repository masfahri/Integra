<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $connection = 'mysql';
    protected $table = 'brand';
    public $timestamps = false;
}
