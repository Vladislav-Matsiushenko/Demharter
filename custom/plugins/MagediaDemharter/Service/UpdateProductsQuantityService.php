<?php

namespace MagediaDemharter\Service;

class UpdateProductsQuantityService
{
// Local
    private $productDataCsvFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/ProductData.csv';

// Staging
//    private $productDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/ProductData.csv';

// Live
//    private $productDataCsvFilePath = '/usr/home/mipzhm/public_html/files/demharter/ProductData.csv';
    private $helper;
    private $modelManager;

    public function __construct()
    {
        $this->helper = Shopware()->Container()->get('magedia_demharter.helper');
        ini_set('memory_limit', '-1');
        $this->modelManager = Shopware()->Container()->get('models');
    }

    public function execute()
    {
        $startTime = microtime(true);

        $csvFile = fopen($this->productDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);

            if (strlen($rowData['external_id']) < 4) {
                continue;
            }

            $rowData['external_id'] = $this->helper->fixExternalId($rowData['external_id']);
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
