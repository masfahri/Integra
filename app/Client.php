<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'ecosystem_client';
    public $timestamps = false;
}
