<?php

namespace MagediaDemharter\Service;

use almParallaxxBanner\ComponentHandler\ParallaxxBannerComponentHandler;

class DeleteOldProductsService
{
// Local
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $endpointUrl = 'https://www.quad-ersatzteile.com/api';
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

        $categoryName = 'Roller Ersatzteile';
        echo 'Deleting from category ' . $categoryName . "\n";
        $categoryIds = $this->helper->getChildCategories($categoryName);

        $this->deleteProducts($categoryIds);


        $categoryName = 'Quad Ersatzteile';
        echo 'Deleting from category ' . $categoryName . "\n";
        $categoryIds = $this->helper->getChildCategories($categoryName);

        $mainCategoryId = 0;
        $result = Shopware()->Db()->query('SELECT * FROM s_categories WHERE description = :value', [
            'value' => 'Can Am Ersatzteile'
        ]);
        foreach ($result as $row) {
            $mainCategoryId = $row['id'];
        }
        $categoryIds = array_diff($categoryIds, [$mainCategoryId]);

        $this->deleteProducts($categoryIds);

        $executionTime = (microtime(true) - $startTime);
        echo 'Deleting old products completed in ' . $executionTime . " seconds\n";
    }


    private function deleteProducts($categoryIds)
    {
        Shopware()->Db()->query("DELETE FROM pk_explosion_chart_categories WHERE categoryID IN ('" . implode("','", $categoryIds) . "')");
        Shopware()->Db()->query("DELETE FROM pk_explosion_chart_hotspots WHERE categoryID IN ('" . implode("','", $categoryIds) . "')");

        $productIds = [];
        $productIdsForHotspots =[];
        $result = Shopware()->Db()->query("SELECT * FROM s_articles_categories WHERE categoryID IN ('" . implode("','", $categoryIds) . "')");
        foreach ($result as $row) {
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

        Shopware()->Db()->query("DELETE FROM pk_explosion_chart_articles WHERE articleID IN ('" . implode("','", $productIdsForHotspots) . "')");
        Shopware()->Db()->query("DELETE FROM s_categories WHERE id IN ('" . implode("','", $categoryIds) . "')");
    }
}
