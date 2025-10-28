<?php

namespace MagediaDemharter\Service;

class DeleteProductsService
{
// Local
    private $productDataCsvFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/ProductData.csv';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $productDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/ProductData.csv';
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $productDataCsvFilePath = '/usr/home/mipzhm/public_html/files/demharter/ProductData.csv';
//    private $endpointUrl = 'https://www.quad-ersatzteile.com/api';
    private $categoryName = 'Ersatzteile Roller/Quad';
    private $excludedCategoryName = 'Can Am Ersatzteile';
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

        $orderNumbers = [];
        $csvFile = fopen($this->productDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if (strlen($rowData['external_id']) < 4) {
                continue;
            }
            $orderNumbers[] = $this->helper->fixExternalId($rowData['external_id']);
        }
        fclose($csvFile);

        $csvProductIds = [];
        $result = Shopware()->Db()->query("SELECT articleID FROM s_articles_details WHERE ordernumber IN ('" . implode("','", $orderNumbers) . "')");
        foreach ($result as $row) {
            $csvProductIds[] = $row['articleID'];
        }
        unset($orderNumbers);


        $categoryIds = $this->helper->getChildCategories($this->categoryName);
        $excludedCategory = 0;
        $result = Shopware()->Db()->query('SELECT * FROM s_categories WHERE description = :value', [
            'value' => $this->excludedCategoryName
        ]);
        foreach ($result as $row) {
            $excludedCategory = $row['id'];
        }
        $categoryIds = array_diff($categoryIds, [$excludedCategory]);

        $productIdsToDelete = [];
        $productIdsForHotspots =[];
        $result = Shopware()->Db()->query("SELECT DISTINCT articleID FROM s_articles_categories WHERE categoryID IN ('" . implode("','", $categoryIds) . "')");
        foreach ($result as $row) {
            if (!in_array($row['articleID'], $csvProductIds, true)) {
                echo 'Product with ID = ' . $row['articleID'] . " was deleted\n";

                $productIdsToDelete[] = array('id' => $row['articleID']);
                $productIdsForHotspots[] = $row['articleID'];

                if (count($productIdsToDelete) >= 500) {
                    $this->helper->deleteProduct($this->endpointUrl, $this->userName, $this->apiKey,
                        json_encode($productIdsToDelete)
                    );
                    $productIdsToDelete = [];
                }
            }
        }

        if (count($productIdsToDelete) > 0) {
            $this->helper->deleteProduct($this->endpointUrl, $this->userName, $this->apiKey,
                json_encode($productIdsToDelete)
            );
        }

        Shopware()->Db()->query("DELETE FROM pk_explosion_chart_articles WHERE articleID IN ('" . implode("','", $productIdsForHotspots) . "')");

        $executionTime = (microtime(true) - $startTime);
        echo 'Deleting products completed in ' . $executionTime . " seconds\n";
    }
}
