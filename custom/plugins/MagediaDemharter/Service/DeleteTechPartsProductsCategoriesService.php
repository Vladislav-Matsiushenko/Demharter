<?php

namespace MagediaDemharter\Service;

class DeleteTechPartsProductsCategoriesService
{
// Local
    private $ebayPricesFilePath = '/var/www/quad-ersatzteile.loc/EbayPrices.txt';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $ebayPricesFilePath = '/usr/home/mipzhm/public_html/staging/EbayPrices.txt';
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $ebayPricesFilePath = '/usr/home/mipzhm/public_html/EbayPrices.txt';
//    private $endpointUrl = 'https://www.quad-ersatzteile.com/api';
    private $categoryName = 'Quad/Scooter spare parts';
    private $categoryIds = [];
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
        $startTime = microtime(true);

        $mainCategoryId = 0;
        $result = Shopware()->Db()->query('SELECT * FROM s_categories WHERE description = :value', [
            'value' => $this->categoryName
        ]);
        foreach ($result as $row) {
            $mainCategoryId = $row['id'];
        }

        $this->getChildCategories($mainCategoryId);

        Shopware()->Db()->query("DELETE FROM pk_explosion_chart_categories WHERE categoryID IN ('" . implode("','", $this->categoryIds) . "')");
        Shopware()->Db()->query("DELETE FROM pk_explosion_chart_hotspots WHERE categoryID IN ('" . implode("','", $this->categoryIds) . "')");

        $productIds = [];
        $productIdsForTechParts =[];
        $ebayPrices = [];
        $result = Shopware()->Db()->query("SELECT * FROM s_articles_categories WHERE categoryID IN ('" . implode("','", $this->categoryIds) . "')");
        foreach ($result as $row) {
            $orderNumber = null;
            $result = Shopware()->Db()->query("SELECT * FROM s_articles_details WHERE articleID = " . $row['articleID']);
            foreach ($result as $rowOrderNumber) {
                $orderNumber = $rowOrderNumber['ordernumber'];
            }

            if ($orderNumber) {
                $result = Shopware()->Db()->query("SELECT * FROM s_articles_prices WHERE pricegroup = 'Ebay' AND articleID = " . $row['articleID']);
                foreach ($result as $rowEbayPrice) {
                    $ebayPrices[$orderNumber] = [
                        'price' => $rowEbayPrice['price'],
                        'pseudoprice' => $rowEbayPrice['pseudoprice'],
                        'percent' => $rowEbayPrice['percent']
                    ];
                }
            }

            $productIds[] = array('id' => $row['articleID']);
            $productIdsForTechParts[] = $row['articleID'];

            if (count($productIds) >= 500) {
                $this->deleteProducts($productIds);
                $productIds = [];
            }
        }

        if (count($productIds) > 0) {
            $this->deleteProducts($productIds);
        }

        file_put_contents($this->ebayPricesFilePath, json_encode($ebayPrices));

        Shopware()->Db()->query("DELETE FROM pk_explosion_chart_articles WHERE articleID IN ('" . implode("','", $productIdsForTechParts) . "')");
        Shopware()->Db()->query("DELETE FROM s_categories WHERE id IN ('" . implode("','", $this->categoryIds) . "')");

        $executionTime = (microtime(true) - $startTime);
        echo 'Deleting tech parts, products and categories completed in ' . $executionTime . " seconds\n";
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

    function getChildCategories(int $parentId)
    {
        $categories = Shopware()->Db()->query('SELECT * FROM s_categories WHERE parent = :value', [
            'value' => $parentId
        ]);

        foreach ($categories as $category) {
            $this->categoryIds[$category['id']] = $category['id'];
            $this->getChildCategories($category['id']);
        }
    }
}
