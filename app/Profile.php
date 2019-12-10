<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'ecosystem_profile';
    public $timestamps = false;
}
