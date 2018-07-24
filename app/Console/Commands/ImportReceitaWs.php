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
        echo 'INIT'.PHP_EOL;

        $key = 'receita-ws:last-id';

        $counter = 0;
        $error = 0;

        while ($counter < 3) {

            echo 'WHILE'.PHP_EOL;

            if (Cache::has($key)) {
                echo 'has cache'.PHP_EOL;
                $lastId = Cache::get($key);
            } else {
                $lastId = 0;
                echo 'dont has cache'.PHP_EOL;
                Cache::forever($key, $lastId);
            }

            echo '0'.PHP_EOL;

            $entity = DB::connection('isocell')->table('clients')->where('id', '>', $lastId)->first();
            if (!$entity) {
                echo 'BREAK 1 (ENTITY DONT EXIST)'.PHP_EOL;
                break;
            }

            echo '1'.PHP_EOL;

            if (!isset($entity->cnpj)) {
                echo 'BREAK 2 (ISSET)'.PHP_EOL;
                Cache::increment($key);
                break;
            }

            echo '2'.PHP_EOL;

            $record = Record::where('cnpj', $entity->cnpj)->first();
            if ($record) {
                echo 'COTINUE 1 (HAS REACORD)'.PHP_EOL;
                Cache::increment($key);
                continue;
            }

            echo '3'.PHP_EOL;

            if ($counter > 0) {

                $sleeped = 0;
                while ($sleeped < 15) {
                    sleep(1);
                    $sleeped++;
                    echo 'Sleeping...'.$sleeped.PHP_EOL;
                }
            }

            echo '4'.PHP_EOL;

            $data = $this->getData($entity->cnpj, $key);
            if (!$data) {
                if ($error > 10) {
                    echo 'BREAK 3 (ERRORS TIMEOUT)'.PHP_EOL;
                    break;
                }
                $error++;
                echo 'COTINUE 2 (EXCEPTION)'.PHP_EOL;
                Cache::increment($key);
                continue;
            }

            Record::create($data);

            echo 'INSERTED, last_id: '.$lastId.PHP_EOL;

            $counter++;

            Cache::increment($key);
        }

    }

    public function getData($cnpj, $key)
    {
        echo 'CNPJ: '.$cnpj.PHP_EOL;
        try {
            $data = json_decode(file_get_contents('https://www.receitaws.com.br/v1/cnpj/' . $cnpj), true);
        } catch (Exception $exception) {
            Cache::increment($key);
            dd($exception->getMessage());
            return false;
        }

        return [
            'cnpj' => $cnpj,
            'data' => json_encode($data),
        ];
    }
}
