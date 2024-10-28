<?php

namespace MagediaDemharter\Service;

class CreateManufacturersService
{
// Local
    private $productsDataCsvFilePath = '/var/www/quad-ersatzteile.loc/ProductsData.csv';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $productsDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/ProductsData.csv';
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $productsDataCsvFilePath = '/usr/home/mipzhm/public_html/ProductsData.csv';
//    private $endpointUrl = 'https://www.quad-ersatzteile.com/api';
    private $userName = 'schwab';
    private $apiKey = 'pdw4kVus56U9IcFaKuHKv7QFQABtKeG20ub5rAh3';
    private $helper;
    private $modelManager;
    private $dbalConnection;

    public function __construct()
    {
        $this->helper = Shopware()->Container()->get('magedia_demharter.helper');
        ini_set('memory_limit', '-1');
        $this->modelManager = Shopware()->Container()->get('models');
        $this->dbalConnection = Shopware()->Container()->get('dbal_connection');
    }

    public function execute()
    {
        $startTime = microtime(true);

        $manufacturersData = [];
        $csvFile = fopen($this->productsDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if ($rowData['cat_manufacturer'] != '' && $rowData['cat_manufacturer'] != "Artikel noch nicht zugewiesen") {
                $manufacturersData[] = $rowData['cat_manufacturer'];
            }
        }
        fclose($csvFile);
        $manufacturersData = array_unique($manufacturersData);

        $manufacturers = $this->helper->getManufacturers($this->endpointUrl, $this->userName, $this->apiKey);
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
                $this->helper->createManufacturer($this->endpointUrl, $this->userName, $this->apiKey,
                    json_encode(array('name' => $name, 'image' => ''))
                );

                $createdManufacturersCount++;
                if ($createdManufacturersCount % 100 == 0) {
                    echo 'Created ' . $createdManufacturersCount . ' manufacturers. ' . ($manufacturersCount - $createdManufacturersCount) . " left\n";
                }
            }
        }

        $executionTime = (microtime(true) - $startTime);
        echo 'Creating manufacturers completed in ' . $executionTime . " seconds\n";
    }
}
