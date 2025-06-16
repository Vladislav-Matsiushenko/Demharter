<?php

namespace MagediaDemharter\Service;

class UpdateProductsPriceService
{
    const INF_PRICE = 9999.99 / 1.19;

// Local
    private $productsDataCsvFilePaths = [
        '/var/www/quad-ersatzteile.loc/files/demharter/AtvDlrEuropeEUROAllXLS.csv',
        '/var/www/quad-ersatzteile.loc/files/demharter/RdstrDlrEuropeEUROAllXLS.csv',
        '/var/www/quad-ersatzteile.loc/files/demharter/SSVDlrEuropeEUROAllXLS.csv',
    ];

// Staging
//    private $productsDataCsvFilePaths = [
//        '/usr/home/mipzhm/public_html/staging/files/demharter/AtvDlrEuropeEUROAllXLS.csv',
//        '/usr/home/mipzhm/public_html/staging/files/demharter/RdstrDlrEuropeEUROAllXLS.csv',
//        '/usr/home/mipzhm/public_html/staging/files/demharter/SSVDlrEuropeEUROAllXLS.csv',
//    ];

// Live
//    private $productsDataCsvFilePaths = [
//        '/usr/home/mipzhm/public_html/files/demharter/AtvDlrEuropeEUROAllXLS.csv',
//        '/usr/home/mipzhm/public_html/files/demharter/RdstrDlrEuropeEUROAllXLS.csv',
//        '/usr/home/mipzhm/public_html/files/demharter/SSVDlrEuropeEUROAllXLS.csv',
//    ];
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

        $updatedProductsCount = 0;
        $productNumbers = [];
        foreach ($this->productsDataCsvFilePaths as $productsDataCsvFilePath) {
            $csvFile = fopen($productsDataCsvFilePath, 'r');
            $headers = fgetcsv($csvFile, 0, ';');
            while ($row = fgetcsv($csvFile, 0, ';')) {
                $rowData = array_combine($headers, $row);
                $productNumber = 'B-' . $rowData[$headers[0]];
                if (strlen($productNumber) < 4) {
                    continue;
                }

                $productNumber = $this->helper->fixExternalId($productNumber);

                if (isset($productNumbers[$productNumber])) {
                    continue;
                }

                $productDetails = $this->modelManager->getRepository('Shopware\Models\Article\Detail')->findOneBy(['number' => $productNumber]);
                if (!$productDetails) {
                    echo 'Product with external ID = ' . $productNumber . " does not exist\n";
                    continue;
                }

                $productNumbers[$productNumber] = true;

                $price = (float)$rowData['Retail_Price'];
                if ($price == 0) {
                    $price = self::INF_PRICE;
                }
                Shopware()->Db()->query("UPDATE s_articles_prices SET price = "
                    . $price
                    . " WHERE pricegroup = 'EK' AND articleDetailsID = (SELECT id FROM s_articles_details WHERE ordernumber = '"
                    . $productNumber . "')");

                $purchasePrice = (float)$rowData['Dealer_Price'];
                $productDetails->setPurchasePrice($purchasePrice);
                $this->modelManager->persist($productDetails);

                $updatedProductsCount++;
                if ($updatedProductsCount % 500 == 0) {
                    $this->modelManager->flush();
                }
            }
            fclose($csvFile);
        }
        $this->modelManager->flush();

        echo 'Updated ' . $updatedProductsCount . " products\n";

        $executionTime = (microtime(true) - $startTime);
        echo 'Updating products price completed in ' . $executionTime . " seconds\n";
    }
}
