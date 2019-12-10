<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Inventory;

class ImportInventory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:inventory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'import inventory postgre to mysql';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $minId = 1700000;
        $maxId = 1800000;

        $successCounter = 0;
        $failCounter = 0;
        $failIds = [];
        $this->info('start process...');

        $inv = Inventory::where('id', '<=', $maxId)
        ->where('id', '>', $minId)
        ->get();
        foreach ($inv as $key => $value) {
            $exist = DB::connection('mysql')->table('inventory')->where(['id' => $value->id])->orWhere(['sn' => $value->serialnumber])->count();
            if ($exist == 0) {
                $data = [
                    'id' => $value->id,
                    'sn' => $value->serialnumber,
                    'brand_id' => $value->brand_id,
                    'model_id' => $value->partnumber_id,
                    'created_at' => strtotime('now')
                ];
                $insert = DB::connection('mysql')->table('inventory')->insert($data);
                $successCounter += 1;
                $this->info('id '.$value->id.' inserted');
            } else {
                $failCounter += 1;
                $failIds[] = $value->id;
                $this->info('id '.$value->id.' skipped');
            }
        }
        $this->info('success '.$successCounter.', failed '.$failCounter);
        // $this->info(json_encode($failIds));
    }
}
