<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Inventory;
use App\InventoryClient;

class ImportClientInv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:clientinv';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Client Inventory';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function getSn($inventory_id)
    {
        $inventory = Inventory::where(['id' => $inventory_id])->first();
        if (!$inventory) {
            return false;
        }
        return $inventory->serialnumber;
    }

    private function getOwning($owner_status)
    {
        $owning = 'unknown';
        if (strtolower($owner_status) == 's') {
            $owning = 'sold';
        } else if (strtolower($owner_status) == 'r') {
            $owning = 'rent';
        } else if (strtolower($owner_status) == 'i') {
            $owning = 'idle';
        }
        return $owning;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $minId = 1800000;
        $maxId = 1900000;

        $successCounter = 0;
        $failCounter = 0;
        $failIds = [];
        $this->info('start process...');

        $inv = InventoryClient::where('id', '<=', $maxId)
        ->where('id', '>', $minId)
        ->get();

        foreach ($inv as $key => $value) {
            $exist = DB::connection('mysql')->table('client_warranty')->where(['id' => $value->id])->count();
            if ($exist == 0) {
                $sn = self::getSn($value->inventory_id);
                if ($sn) {
                    $start = strtotime($value->start_warranty);
                    $end = strtotime($value->end_warranty);
                    $data = [
                        'id' => $value->id,
                        'client_id' => $value->client_id,
                        'inventory_id' => $value->inventory_id,
                        'sn' => $sn,
                        'owning' => self::getOwning($value->owner_status),
                        'tid' => $value->tid,
                        'mid' => $value->mid,
                        'start' => $start,
                        'end' => $end,
                        'state' => 'active',
                        'created_at' => strtotime('now')
                    ];
                    $insert = DB::connection('mysql')->table('client_warranty')->insert($data);
                    $successCounter += 1;
                    $this->info('id '.$value->id.' inserted');
                } else {
                    $this->info('id '.$value->id.' skipped s/n n/a');
                }
            } else {
                $failCounter += 1;
                $failIds[] = $value->id;
                $this->info('id '.$value->id.' skipped id exist');
            }
        }
        $this->info('success '.$successCounter.', failed '.$failCounter);
    }
}
