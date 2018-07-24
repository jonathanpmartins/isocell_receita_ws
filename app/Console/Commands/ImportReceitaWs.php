<?php

namespace App\Console\Commands;

use App\Record;
use Exception;
use Illuminate\Console\Command;
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
        $counter = 0;
        $error = 0;
        while ($counter < 3) {

            //$client = DB::connection('isocell')->table('clients')->inRandomOrder()->first();
            $cnpjs = Record::pluck('cnpj');
            $client = DB::connection('isocell')->table('clients')->whereNotIn('cnpj', $cnpjs)->inRandomOrder()->first();

            $record = Record::where('cnpj', $client->cnpj)->first();
            if ($record) {
                continue;
            }

            $data = $this->getData($client->cnpj);
            if (!$data) {
                if ($error > 10) {
                    break;
                }
                $error++;
                continue;
            }

            Record::create($data);

            echo 'INSERTED'.PHP_EOL;

            $counter++;

            sleep(10);
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
