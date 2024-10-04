<?php

namespace MagediaDemharter\Service;

class UpdateProductsQuantityService
{
// Local
    private $logFilePath = '/var/www/quad-ersatzteile.loc/NotUpdatedProductsQuantity.txt';
    private $productsDataCsvFilePath = '/var/www/quad-ersatzteile.loc/ProductsData.csv';

// Staging
//    private $logFilePath = '/usr/home/mipzhm/public_html/staging/NotUpdatedProductsQuantity.txt';
//    private $productsDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/ProductsData.csv';

// Live
//    private $logFilePath = '/usr/home/mipzhm/public_html/NotUpdatedProductsQuantity.txt';
//    private $productsDataCsvFilePath = '/usr/home/mipzhm/public_html/ProductsData.csv';
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

        $csvFile = fopen($this->productsDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if (!$rowData['products_name']) {
                $logMessage = 'Product with ID = ' . $rowData['products_id'] . " has no name\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            if ($rowData['products_category_tree'] == '' || $rowData['products_category_tree'] == "Artikel noch nicht zugewiesen") {
                $logMessage = 'Product with ID = ' . $rowData['products_id'] . " has no category\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            if (strlen($rowData['external_id']) < 4) {
                $logMessage = 'Product with ID = ' . $rowData['products_id'] . " has no external ID\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            for ($i = 0; $i < strlen($rowData['external_id']); $i++) {
                if (!preg_match('/^[a-zA-Z0-9-_.]+$/', $rowData['external_id'][$i])){
                    $rowData['external_id'][$i] = '_';
                }
            }

            $productDetails = $this->modelManager->getRepository('Shopware\Models\Article\Detail')->findOneBy(['number' => $rowData['external_id']]);
            if (!$productDetails) {
                $logMessage = 'Product with ID = ' . $rowData['products_id'] . " does not exist\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            $productDetails->setInStock($rowData['stock_count']);
            $this->modelManager->persist($productDetails);
        }
        fclose($csvFile);

        $this->modelManager->flush();

        echo "Updating products quantity completed\n";
    }
}
