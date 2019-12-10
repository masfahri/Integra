<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Models extends Model
{
    protected $connection = 'mysql';
    protected $table = 'model';
    public $timestamps = false;
}
