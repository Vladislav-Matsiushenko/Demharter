<?php

namespace MagediaDemharter\Service;

class GetYamahaProductsService
{
// Local
    private $yamahaProductDataFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/YamahaProductDataStaging.csv';
//    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $yamahaProductDataFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/YamahaProductData.csv';
    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $yamahaProductDataFilePath = '/usr/home/mipzhm/public_html/files/demharter/YamahaProductData.csv';
//    private $endpointUrl = 'https://www.quad-ersatzteile.com/api';
    private $manufacturerName = 'Yamaha';
    private $userName = 'schwab';
    private $apiKey = 'pdw4kVus56U9IcFaKuHKv7QFQABtKeG20ub5rAh3';
    private $helper;

    public function __construct()
    {
        $this->helper = Shopware()->Container()->get('magedia_demharter.helper');
        ini_set('memory_limit', '-1');
    }

    public function execute()
    {
        $startTime = microtime(true);

        $csvFile = fopen($this->yamahaProductDataFilePath, 'w');
        fputcsv($csvFile, ['Article Number', 'Name', 'Stock Count'], ';');

        $manufacturerIds = [];
        $manufacturers = $this->helper->getManufacturers($this->endpointUrl, $this->userName, $this->apiKey);
        foreach ($manufacturers as $manufacturer){
            if (strtolower($this->manufacturerName) === strtolower($manufacturer->name)){
                $manufacturerIds[] = $manufacturer->id;
            }
        }
        unset($manufacturers);

        $start = 0;
        $products = $this->getProducts($start);
        while (count($products) > 0) {
            foreach ($products as $product) {
                if (in_array($product->supplierId, $manufacturerIds) ||
                    strpos($product->mainDetail->number, 'Y') === 0
                ) {
                    fputcsv($csvFile, [$product->mainDetail->number, $product->name, $product->mainDetail->inStock], ';');
                }
            }

            echo '.';
            $start += 10000;
            $products = $this->getProducts($start);
        }

        fclose($csvFile);

        $executionTime = (microtime(true) - $startTime);
        echo 'Getting yamaha products completed in ' . $executionTime . " seconds\n";
    }

    private function getProducts($start)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpointUrl . '/articles?limit=10000&start=' . $start);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->userName . ':' . $this->apiKey);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        $response = curl_exec($ch);

        if(curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
            curl_close($ch);

            return [];
        }

        curl_close($ch);

        return json_decode($response)->data ?? [];
    }
}
