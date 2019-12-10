<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LogData extends Model
{
    protected $connection = 'mysql';
    protected $table = 'log_data';
    public $timestamps = false;
}
