<?php

namespace MagediaDemharter\Service;

class DeleteProductsService
{
// Local
    private $ebayPricesFilePath = '/var/www/quad-ersatzteile.loc/EbayPrices.txt';
    private $productsDataCsvFilePath = '/var/www/quad-ersatzteile.loc/ProductsData.csv';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $ebayPricesFilePath = '/usr/home/mipzhm/public_html/staging/EbayPrices.txt';
//    private $productsDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/ProductsData.csv';
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $ebayPricesFilePath = '/usr/home/mipzhm/public_html/EbayPrices.txt';
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

        $orderNumbers = [];
        $ebayPrices = [];
        $csvFile = fopen($this->productsDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if (strlen($rowData['external_id']) < 4) {
                echo 'Product with ID = ' . $rowData['products_id'] . ' and External ID = ' . $rowData['external_id'] . " was not deleted!\n";
                continue;
            }
            $orderNumbers[] = $rowData['external_id'];

            $result = Shopware()->Db()->query("SELECT * FROM s_articles_prices WHERE pricegroup = 'Ebay' AND articleID = 
                (SELECT articleID FROM s_articles_details WHERE ordernumber = '" . $rowData['external_id'] . "')");
            foreach ($result as $rowEbayPrice) {
                $ebayPrices[$rowData['external_id']] = [
                    'price' => $rowEbayPrice['price'],
                    'pseudoprice' => $rowEbayPrice['pseudoprice'],
                    'percent' => $rowEbayPrice['percent']
                ];
            }
        }
        fclose($csvFile);

        file_put_contents($this->ebayPricesFilePath, json_encode($ebayPrices));

        $productIds = [];
        $result = Shopware()->Db()->query("SELECT articleID FROM s_articles_details WHERE ordernumber IN ('" . implode("','", $orderNumbers) . "')");
        foreach ($result as $row) {
            $productIds[] = array('id' => $row['articleID']);

            if (count($productIds) >= 500) {
                $this->helper->deleteProduct($this->endpointUrl, $this->userName, $this->apiKey,
                    json_encode($productIds)
                );
                $productIds = [];
            }
        }

        if (count($productIds) > 0) {
            $this->helper->deleteProduct($this->endpointUrl, $this->userName, $this->apiKey,
                json_encode($productIds)
            );
        }

        $executionTime = (microtime(true) - $startTime);
        echo 'Deleting products completed in ' . $executionTime . " seconds\n";
    }
}
