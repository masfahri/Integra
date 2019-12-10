<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Pics extends Model
{
    protected $connection = 'mysql';
    protected $table = 'pic';
    public $timestamps = false;
}
