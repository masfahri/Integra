<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class JoDetail extends Model
{
    protected $connection = 'mmid';
    protected $table = 'jo_detail';
    public $timestamps = false;
}
