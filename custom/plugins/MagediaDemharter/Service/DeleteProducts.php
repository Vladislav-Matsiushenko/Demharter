<?php

namespace MagediaDemharter\Service;

class DeleteProducts
{
    private $logFilePath = '/var/www/quad-ersatzteile.loc/NotDeletedProducts.txt';
    private $ebayPricesFilePath = '/var/www/quad-ersatzteile.loc/EbayPrices.txt';
    private $csvFilePath = '/var/www/quad-ersatzteile.loc/Teileexport-grp-3.csv';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';
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

    public function deleteProductsFromCsv()
    {
        file_put_contents($this->logFilePath, '');

        $orderNumbers = [];
        $ebayPrices = [];
        $csvFile = fopen($this->csvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if (strlen($rowData['external_id']) < 4) {
                $logMessage = 'Product with ID = ' . $rowData['products_id'] . '; External ID = ' . $rowData['external_id'] . '; Name = ' . $rowData['products_name'] . '; Tax ID = ' . $rowData['products_tax_class_id'] . " was not deleted!\n";
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

        $productData = [];
        $result = Shopware()->Db()->query("SELECT articleID FROM s_articles_details WHERE ordernumber IN ('" . implode("','", $orderNumbers) . "')");
        foreach ($result as $row) {
            array_push($productData, array('id' => $row['articleID']));

            if (count($productData) >= 500) {
                $time_start = microtime(true);

                $this->deleteProducts($productData);
                $productData = [];

                $time_end = microtime(true);
                $execution_time = ($time_end - $time_start);

                echo 'Total execution time for remove 500 products: ' . $execution_time . ' sec';
                echo "\n";
            }
        }

        if (count($productData) > 0) {
            $this->deleteProducts($productData);
        }
    }

    /**
     * @param array $productData
     * @return void
     */
    function deleteProducts(array $productData)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpointUrl . '/articles');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($productData));
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->userName:$this->apiKey");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Error: ' . curl_error($ch);
        } else {
            echo '500 product deleted successfully!';
            echo "\n";
        }

        curl_close($ch);
    }
}
