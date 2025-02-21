<?php

namespace MagediaDemharter\Service;

class UpdateProductsQuantityService
{
// Local
    private $productsDataCsvFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/ProductsData.csv';

// Staging
//    private $productsDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/ProductsData.csv';

// Live
//    private $productsDataCsvFilePath = '/usr/home/mipzhm/public_html/files/demharter/ProductsData.csv';
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
        $startTime = microtime(true);

        $csvFile = fopen($this->productsDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if (!$rowData['products_name']) {
                echo 'Product with ID = ' . $rowData['products_id'] . " has no name\n";
                continue;
            }

            if ($rowData['products_category_tree'] == '' || $rowData['products_category_tree'] == "Artikel noch nicht zugewiesen") {
                echo 'Product with ID = ' . $rowData['products_id'] . " has no category\n";
                continue;
            }

            if (strlen($rowData['external_id']) < 4) {
                echo 'Product with ID = ' . $rowData['products_id'] . " has no external ID\n";
                continue;
            }

            for ($i = 0; $i < strlen($rowData['external_id']); $i++) {
                if (!preg_match('/^[a-zA-Z0-9-_.]+$/', $rowData['external_id'][$i])){
                    $rowData['external_id'][$i] = '_';
                }
            }

            $productDetails = $this->modelManager->getRepository('Shopware\Models\Article\Detail')->findOneBy(['number' => $rowData['external_id']]);
            if (!$productDetails) {
                echo 'Product with ID = ' . $rowData['products_id'] . " does not exist\n";
                continue;
            }

            $productDetails->setInStock($rowData['stock_count']);
            $this->modelManager->persist($productDetails);
        }
        fclose($csvFile);

        $this->modelManager->flush();

        $executionTime = (microtime(true) - $startTime);
        echo 'Updating products quantity completed in ' . $executionTime . " seconds\n";
    }
}
