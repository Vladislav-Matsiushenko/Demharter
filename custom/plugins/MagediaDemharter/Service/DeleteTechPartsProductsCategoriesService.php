<?php

namespace MagediaDemharter\Service;

class DeleteTechPartsProductsCategoriesService
{
// Local
    private $ebayPricesFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/EbayPrices.txt';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $ebayPricesFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/EbayPrices.txt';
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $ebayPricesFilePath = '/usr/home/mipzhm/public_html/files/demharter/EbayPrices.txt';
//    private $endpointUrl = 'https://www.quad-ersatzteile.com/api';
    private $categoryName = 'Quad/Scooter spare parts';
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

        $categoryIds = $this->helper->getChildCategories($this->categoryName);

        Shopware()->Db()->query("DELETE FROM pk_explosion_chart_categories WHERE categoryID IN ('" . implode("','", $categoryIds) . "')");
        Shopware()->Db()->query("DELETE FROM pk_explosion_chart_hotspots WHERE categoryID IN ('" . implode("','", $categoryIds) . "')");

        $productIds = [];
        $productIdsForTechParts =[];
        $ebayPrices = [];
        $result = Shopware()->Db()->query("SELECT * FROM s_articles_categories WHERE categoryID IN ('" . implode("','", $categoryIds) . "')");
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

        file_put_contents($this->ebayPricesFilePath, json_encode($ebayPrices));

        Shopware()->Db()->query("DELETE FROM pk_explosion_chart_articles WHERE articleID IN ('" . implode("','", $productIdsForTechParts) . "')");
        Shopware()->Db()->query("DELETE FROM s_categories WHERE id IN ('" . implode("','", $categoryIds) . "')");

        $executionTime = (microtime(true) - $startTime);
        echo 'Deleting tech parts, products and categories completed in ' . $executionTime . " seconds\n";
    }
}
