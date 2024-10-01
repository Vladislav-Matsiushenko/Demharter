<?php

namespace MagediaDemharter\Service;

class DeleteProductsService
{
// Local
    private $logFilePath = '/var/www/quad-ersatzteile.loc/NotDeletedProducts.txt';
    private $ebayPricesFilePath = '/var/www/quad-ersatzteile.loc/EbayPrices.txt';
    private $csvFilePath = '/var/www/quad-ersatzteile.loc/Teileexport-grp-3.csv';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $logFilePath = '/usr/home/mipzhm/public_html/staging/NotDeletedProducts.txt';
//    private $ebayPricesFilePath = '/usr/home/mipzhm/public_html/staging/EbayPrices.txt';
//    private $csvFilePath = '/usr/home/mipzhm/public_html/staging/Teileexport-grp-3.csv';
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $logFilePath = '/usr/home/mipzhm/public_html/NotDeletedProducts.txt';
//    private $ebayPricesFilePath = '/usr/home/mipzhm/public_html/EbayPrices.txt';
//    private $csvFilePath = '/usr/home/mipzhm/public_html/Teileexport-grp-3.csv';
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
        file_put_contents($this->logFilePath, '');

        $orderNumbers = [];
        $ebayPrices = [];
        $csvFile = fopen($this->csvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if (strlen($rowData['external_id']) < 4) {
                $logMessage = 'Product with ID = ' . $rowData['products_id']
                    . '; External ID = ' . $rowData['external_id']
                    . '; Name = ' . $rowData['products_name']
                    . '; Tax ID = ' . $rowData['products_tax_class_id']
                    . " was not deleted!\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
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
                $time_start = microtime(true);

                $this->deleteProducts($productIds);
                $productIds = [];

                $time_end = microtime(true);
                $execution_time = ($time_end - $time_start);

                echo 'Total execution time for deleting 500 products: ' . $execution_time . " sec\n";
            }
        }

        if (count($productIds) > 0) {
            $this->deleteProducts($productIds);
        }

        echo "Deleting completed\n";
    }

    function deleteProducts(array $productIds)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpointUrl . '/articles');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($productIds));
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->userName . ':' . $this->apiKey);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Error: ' . curl_error($ch);
        }

        curl_close($ch);
    }
}
