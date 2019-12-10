<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MmidAlias extends Model
{
    protected $connection = 'mmid';
    protected $table = 'mmid_alias';
    public $timestamps = false;
    protected $primaryKey = 'mid';
    public $incrementing = false;
}
