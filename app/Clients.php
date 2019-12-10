<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Clients extends Model
{
    protected $connection = 'mysql';
    protected $table = 'client';
    public $timestamps = false;
}
