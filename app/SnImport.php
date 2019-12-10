<?php

namespace App;

use App\Inventory;
use Maatwebsite\Excel\Concerns\ToModel;

class SnImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new Inventory([
            //
        ]);
    }
}
