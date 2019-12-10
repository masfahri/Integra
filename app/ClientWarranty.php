<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ClientWarranty extends Model
{
    protected $connection = 'mysql';
    protected $table = 'client_warranty';
    public $timestamps = false;

    protected $fillable = ['client_id', 'inventory_id', 'sn', 'owning', 'tid', 'mid', 'start', 'end', 'state', 'created_at', 'updated_at'];
}
