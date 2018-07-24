<?php

namespace App\Console\Commands;

use App\Record;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ImportReceitaWs extends Command
{
    protected $signature = 'import:receita-ws';
    protected $description = 'Consult Receita WS Database';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $key = 'receita-ws:last-id';

        $counter = 0;
        $error = 0;

        while ($counter < 3) {

            if (Cache::has($key)) {
                $lastId = Cache::get($key);
            } else {
                $lastId = 0;
                Cache::forever($key, $lastId);
            }

            $entity = DB::connection('isocell')->table('clients')->where('id', '>', $lastId)->first();
            if (!$entity) {
                echo 'BREAK 1 (ENTITY DONT EXIST)'.PHP_EOL;
                break;
            }

            if (!isset($entity->cnpj)) {
                echo 'BREAK 2 (ISSET)'.PHP_EOL;
                break;
            }

            $record = Record::where('cnpj', $entity->cnpj)->first();
            if ($record) {
                echo 'COTINUE 1 (HAS REACORD)'.PHP_EOL;
                Cache::increment($key);
                continue;
            }

            if ($counter > 0) {
                sleep(10);
            }

            $data = $this->getData($entity->cnpj);
            if (!$data) {
                if ($error > 10) {
                    echo 'BREAK 3 (ERRORS TIMEOUT)'.PHP_EOL;
                    break;
                }
                $error++;
                echo 'COTINUE 2 (EXCEPTION)'.PHP_EOL;
                continue;
            }

            Record::create($data);

            echo 'INSERTED, last_id: '.$lastId.PHP_EOL;

            $counter++;

            Cache::increment($key);
        }

    }

    public function getData($cnpj)
    {
        try {
            $data = json_decode(file_get_contents('https://www.receitaws.com.br/v1/cnpj/' . $cnpj), true);
        } catch (Exception $exception) {
            dd($exception->getMessage());
            return false;
        }

        return [
            'cnpj' => $cnpj,
            'data' => json_encode($data),
        ];
    }
}
