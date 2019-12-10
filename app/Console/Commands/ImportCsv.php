<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\SnImport;
use App\Inventorys;
use App\Clients;
use App\ClientWarranty;
use App\ConsoleTask;
use App\Models;
use App\Brand;

class ImportCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:csv';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'import huge csv file';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function checkSnTitles($titles)
    {
        $valid = true;
        if (empty($titles)) {
            $valid = false;
        }
        $titles = explode(';', $titles);

        $keys = ['serialnumber', 'partnumber', 'brand'];
        foreach ($keys as $value) {
            if ($valid) {
                if (!in_array($value, $titles)) {
                    $valid = false;
                    break;
                }
            }
        }
        
        return $valid;
    }

    private function checkClientTitles($titles)
    {
        $valid = true;
        if (empty($titles)) {
            $valid = false;
        }
        $titles = explode(';', $titles);

        $keys = ['Inventory(serial number)', 'Client(code)', 'PartNumber', 'TID', 'MID', 'STATUS (sold/rent/repair)', 'Start warranty (Date)', 'End warranty (Date)'];
        foreach ($keys as $value) {
            if ($valid) {
                if (!in_array($value, $titles)) {
                    $valid = false;
                    break;
                }
            }
        }
        
        return $valid;
    }

    private function getPartNumberId($pn)
    {
        $id = 0;
        if (!empty($pn)) {
            $partnumber = Models::where(['code' => $pn])->first();
            if ($partnumber) {
                $id = $partnumber->id;
            }
        }
        return $id;
    }

    private function getBrandId($brand)
    {
        $id = 0;
        if (!empty($brand)) {
            $brand = Brand::where(['code' => $brand])->first();
            if ($brand) {
                $id = $brand->id;
            }
        }
        return $id;
    }

    private function checkSnExist($sn)
    {
        $exist = false;
        if (!empty($sn)) {
            $record = Inventorys::where(['sn' => $sn])->count();
            if ($record > 0) {
                $exist = true;
            }
        }
        return $exist;
    }

    private function formattingClientObject($lines)
    {
        if (!is_array($lines)) {
            return false;
        }
        if (!isset($lines[2])) {
            return false;
        }

        $sn = ltrim($lines[0]);
        $pn = ltrim($lines[2]);
        $brand_id = 0;

        $inventory = Inventorys::where(['sn' => $sn])->first();
        if (!$inventory) {
            return false;
        } else {
            $invId = $inventory->id;
        }

        $keys = ['inventory_id', 'client_id', 'pn', 'tid', 'mid', 'owning', 'start', 'end'];
        $data = [];
        for ($i = 0; $i < count($lines); $i++) { 
            $item = ltrim($lines[$i]);
            if ($i != 2) {
                if ($i == 0) {
                    $data['sn'] = $sn;
                    $data[$keys[$i]] = (int) $invId;
                } else if ($i == 1) {
                    $cid = 0;
                    $clients = Clients::where(['code' => $item])->first();
                    if ($clients) {
                        $cid = $clients->id;
                    }
                    if ($cid == 0) {
                        return false;
                    }
                    $data[$keys[$i]] = (int) $cid;
                } else if ($i == 5) {
                    // $state = str_split($item);
                    // $data[$keys[$i]] = ucfirst($state[0]);
                    $data[$keys[$i]] = $item;
                } else if ($i >= 6) {
                    $items = explode('/', $item);
                    if (count($items) < 3) {
                        return false;
                    }
                    $line = $items[2].'-'.$items[1].'-'.$items[0];
                    // $line = $items[2].'-'.self::getMonthNumber($items[1]).'-'.$items[0];
                    $data[$keys[$i]] = strtotime($line);
                } else {
                    $data[$keys[$i]] = ltrim($lines[$i]);
                }
            }
        }
        return $data;
    }

    private function getMonthNumber($name)
    {
        $month = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
        if (in_array(strtolower($name), $month)) {
            foreach ($month as $key => $value) {
                if (strtolower($name) == $value) {
                    return $key +1;
                    break;
                }
            }
        }
        return $name;
    }


    private function executeImportSn($id, $path)
    {
        $array = Excel::toArray(new SnImport, $path);
        $sheet1 = $array[0];
        $titles = $sheet1[0][0];
        $validFormat = self::checkSnTitles($titles);
        if (!$validFormat) {
            $note = 'wrong format file titles not match';
            $this->info($note);
            ConsoleTask::where(['id' => $id])->update(['status' => 'reject', 'notes' => $note]);
            exit();
        }

        $insertCounter = 0;
        $skipCounter = 0;
        foreach ($sheet1 as $key => $value) {
            if ($key > 0) {
                $this->info('process...'.$value[0]);

                $lines = explode(';', $value[0]);
                $sn = $lines[0];
                $pn = $lines[1];
                $brand = $lines[2];

                $pn_id  = self::getPartNumberId($pn);
                if ($pn_id) {
                    $brand_id = self::getBrandId($brand);
                    if ($brand_id) {
                        $existSn = self::checkSnExist($sn);
                        if ($existSn) {
                            $skipCounter += 1;
                            $this->info('skip...'.$value[0].' s/n exist');
                        } else {
                            $inventory = new Inventorys;
                            $inventory->sn = $sn;
                            $inventory->brand_id = $brand_id;
                            $inventory->model_id = $pn_id;
                            $inventory->created_at = strtotime('now');
                            if ($inventory->save()) {
                                $insertCounter += 1;
                            }
                        }
                    } else {
                        $this->info('skip...'.$value[0].' brand n/a');
                    }
                } else {
                    $this->info('skip...'.$value[0].' p/n n/a');
                }
            }
        }
        $msg = $insertCounter.' imported and '.$skipCounter.' skipped from '.(count($sheet1) -1).' row';
        ConsoleTask::where(['id' => $id])->update(['status' => 'done', 'updated_at' => strtotime('now'), 'notes' => $msg]);
        $this->info($msg);
    }

    private function executeImportClient($id, $path)
    {
        $array = Excel::toArray(new SnImport, $path);
        $sheet1 = $array[0];
        $titles = $sheet1[0][0];
        $validFormat = self::checkClientTitles($titles);
        if (!$validFormat) {
            $note = 'wrong format file titles not match';
            $this->info($note);
            ConsoleTask::where(['id' => $id])->update(['status' => 'reject', 'notes' => $note]);
            exit();
        }

        $insertCounter = 0;
        $skipCounter = 0;
        foreach ($sheet1 as $key => $value) {
            if ($key > 0) {
                $this->info('process...'.$value[0]);

                $lines = explode(';', $value[0]);
                $clientData = self::formattingClientObject($lines);
                if ($clientData) {
                    $exist = ClientWarranty::where(['inventory_id' => $clientData['inventory_id']])->count();
                    if ($exist > 0) {
                        $this->info('skip...'.$value[0].' s/n exist');
                        $skipCounter += 1;
                    } else {
                        $cwr = new ClientWarranty;
                        $cwr->owning = $clientData['owning'];
                        $cwr->tid = $clientData['tid'];
                        $cwr->mid = $clientData['mid'];
                        $cwr->start = $clientData['start'];
                        $cwr->end = $clientData['end'];
                        $cwr->client_id = $clientData['client_id'];
                        $cwr->inventory_id = $clientData['inventory_id'];
                        $cwr->sn = $clientData['sn'];
                        $cwr->state = 'active';
                        $cwr->created_at = strtotime('now');
                        if ($cwr->save()) {
                            $insertCounter += 1;
                        }
                    }
                } else {
                    $this->info('skip...'.$value[0].' inventory or client n/a yet');
                }
            }
        }
        $msg = $insertCounter.' imported/updated and '.$skipCounter.' skipped from '.(count($sheet1) -1).' row';
        ConsoleTask::where(['id' => $id])->update(['status' => 'done', 'updated_at' => strtotime('now'), 'notes' => $msg]);
        $this->info($msg);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('start process...');
        $inProcess = ConsoleTask::where(['status' => 'process'])->where('created_at', '>', strtotime('-1 day'))->count();
        if ($inProcess <= 0) {
            $this->info('no process common...');
            $task = ConsoleTask::where(['status' => 'waiting', 'process_at' => 0, 'updated_at' => 0])
            ->where('created_at', '>', strtotime('-3 day'))
            ->select('*', 'path as realpath')
            ->orderBy('id', 'asc')
            ->first();

            if ($task) {
                $this->info('executing task...'.$task->id);
                //set flag to process
                ConsoleTask::where(['id' => $task->id])->update(['status' => 'process', 'process_at' => strtotime('now')]);
                $name = $task->name;
                if ($name == 'sn') {
                    self::executeImportSn($task->id, $task->realpath);
                } else if ($name == 'client') {
                    self::executeImportClient($task->id, $task->realpath);
                }
            } else {
                $this->info('no task to execute...');
            }
        }
    }
}
