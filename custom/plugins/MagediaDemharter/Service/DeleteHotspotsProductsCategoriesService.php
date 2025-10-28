<?php

namespace MagediaDemharter\Service;

class DeleteHotspotsProductsCategoriesService
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
    private $categoryName = 'BETA';
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

        $categoryIds = $this->helper->getChildCategories($this->categoryName);

        Shopware()->Db()->query("DELETE FROM pk_explosion_chart_categories WHERE categoryID IN ('" . implode("','", $categoryIds) . "')");
        Shopware()->Db()->query("DELETE FROM pk_explosion_chart_hotspots WHERE categoryID IN ('" . implode("','", $categoryIds) . "')");

        if (file_exists($this->ebayPricesFilePath)) {
            $ebayPrices = json_decode(file_get_contents($this->ebayPricesFilePath), true);
        } else {
            $ebayPrices = [];
        }

        $productIds = [];
        $productIdsForHotspots =[];
        $result = Shopware()->Db()->query("SELECT * FROM s_articles_categories WHERE categoryID IN ('" . implode("','", $categoryIds) . "')");
        foreach ($result as $row) {
            $orderNumber = null;
            $res = Shopware()->Db()->query("SELECT * FROM s_articles_details WHERE articleID = " . $row['articleID']);
            foreach ($res as $rowOrderNumber) {
                $orderNumber = $rowOrderNumber['ordernumber'];
            }

            if ($orderNumber) {
                $res = Shopware()->Db()->query("SELECT * FROM s_articles_prices WHERE pricegroup = 'Ebay' AND articleID = " . $row['articleID']);
                foreach ($res as $rowEbayPrice) {
                    $ebayPrices[$orderNumber] = [
                        'price' => $rowEbayPrice['price'],
                        'pseudoprice' => $rowEbayPrice['pseudoprice'],
                        'percent' => $rowEbayPrice['percent']
                    ];
                }
            }

            $productIds[] = array('id' => $row['articleID']);
            $productIdsForHotspots[] = $row['articleID'];

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

        Shopware()->Db()->query("DELETE FROM pk_explosion_chart_articles WHERE articleID IN ('" . implode("','", $productIdsForHotspots) . "')");
        Shopware()->Db()->query("DELETE FROM s_categories WHERE id IN ('" . implode("','", $categoryIds) . "')");

        $executionTime = (microtime(true) - $startTime);
        echo 'Deleting hotspots, products and categories completed in ' . $executionTime . " seconds\n";
    }
}
