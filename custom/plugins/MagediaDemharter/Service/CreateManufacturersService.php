<?php

namespace MagediaDemharter\Service;

class CreateManufacturersService
{
// Local
    private $csvFilePath = '/var/www/quad-ersatzteile.loc/ProductsData.csv';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $csvFilePath = '/usr/home/mipzhm/public_html/staging/ProductsData.csv';
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $csvFilePath = '/usr/home/mipzhm/public_html/ProductsData.csv';
//    private $endpointUrl = 'https://www.quad-ersatzteile.com/api';
    private $userName = 'schwab';
    private $apiKey = 'pdw4kVus56U9IcFaKuHKv7QFQABtKeG20ub5rAh3';
    private $modelManager;
    private $dbalConnection;

    public function __construct()
    {
        ini_set('memory_limit', '-1');
        $this->modelManager = Shopware()->Container()->get('models');
        $this->dbalConnection = Shopware()->Container()->get('dbal_connection');
    }

    public function execute()
    {
        $manufacturersData = [];
        $csvFile = fopen($this->csvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if ($rowData['cat_manufacturer'] != '' && $rowData['cat_manufacturer'] != "Artikel noch nicht zugewiesen") {
                $manufacturersData[] = $rowData['cat_manufacturer'];
            }
        }
        fclose($csvFile);
        $manufacturersData = array_unique($manufacturersData);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpointUrl . '/manufacturers?limit=50000');
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->userName:$this->apiKey");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        $response = curl_exec($ch);

        if(curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);

            return;
        }

        curl_close($ch);

        $manufacturers = json_decode($response)->data;
        $manufacturersCount = count($manufacturersData);
        $createdManufacturersCount = 0;
        foreach ($manufacturersData as $name) {
            $manufacturerId = 0;
            foreach ($manufacturers as $item){
                if ($name == $item->name){
                    $manufacturerId = $item->id;
                    break;
                }
            }

            if ($manufacturerId == 0) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->endpointUrl . '/manufacturers');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('name' => $name, 'image' => '')));
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_USERPWD, $this->userName . ':' . $this->apiKey);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                $response = curl_exec($ch);

                if (curl_errno($ch)) {
                    echo 'Curl error: ' . curl_error($ch);
                }

                curl_close($ch);

                $createdManufacturersCount++;
                if ($createdManufacturersCount % 10 == 0) {
                    echo 'Created ' . $createdManufacturersCount . ' manufacturers. ' . ($manufacturersCount - $createdManufacturersCount) . " left\n";
                }
            }
        }

        echo "Creating manufacturers completed\n";
    }
}
