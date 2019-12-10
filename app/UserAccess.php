<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserAccess extends Model
{
    protected $connection = 'mysql';
    protected $table = 'user_access';
    public $timestamps = false;
}
