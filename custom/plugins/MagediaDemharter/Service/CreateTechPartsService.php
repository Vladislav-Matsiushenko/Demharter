<?php

namespace MagediaDemharter\Service;

class CreateTechPartsService
{
// Local
    private $techPartsDataJsonFilePath = '/var/www/quad-ersatzteile.loc/TechPartsData.txt';

// Staging
//    private $techPartsDataJsonFilePath = '/usr/home/mipzhm/public_html/staging/TechPartsData.txt';

// Live
//    private $techPartsDataJsonFilePath = '/usr/home/mipzhm/public_html/TechPartsData.txt';
    private $categoryName = 'Quad/Scooter spare parts';
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
        Shopware()->Db()->query("DELETE FROM pk_explosion_chart_articles WHERE articleID IN (SELECT articleID FROM s_articles_categories WHERE categoryID IN ('" . implode("','", $categoryIds) . "'))");

        $techPartsData = json_decode(file_get_contents($this->techPartsDataJsonFilePath), true);

        $categoriesInsertData = [];
        $hotspotsInsertData = [];
        $techPartsCount = count($techPartsData);
        $createdTechPartsCount = 0;
        foreach($techPartsData as $techPart) {
            if (!isset($categoriesInsertData[$techPart['category_id']])) {
                $categoriesInsertData[$techPart['category_id']] = "('"
                    . $techPart['cat_article_component_image'] . "', '"
                    . $techPart['cat_article_component_image_size'] . "', "
                    . $techPart['category_id'] . ")";
            }

            Shopware()->Db()->query("INSERT INTO pk_explosion_chart_hotspots (categoryID, coords) VALUES("
                . $techPart['category_id'] . ", '"
                . $techPart['products_coords'] . "')");

            $hotspotId = 0;
            $result = Shopware()->Db()->query("SELECT * FROM pk_explosion_chart_hotspots WHERE categoryID = " . $techPart['category_id']
                . " AND coords = '" . $techPart['products_coords'] . "'");
            foreach ($result as $row) {
                $hotspotId = $row['id'];
            }

            if (!isset($hotspotsInsertData[$hotspotId])) {
                $hotspotsInsertData[$hotspotId] = "("
                    . $hotspotId . ", "
                    . $techPart['product_id'] . ", "
                    . $techPart['product_details_id'] . ", 1)";
            }

            if (count($categoriesInsertData) >= 500) {
                Shopware()->Db()->query("INSERT INTO pk_explosion_chart_categories (img, size, categoryID) VALUES "
                    . implode(", ", $categoriesInsertData));

                $categoriesInsertData = [];
            }

            if (count($hotspotsInsertData) >= 1000) {
                Shopware()->Db()->query("INSERT INTO pk_explosion_chart_articles (hotspotID, articleID, articleDetailID, active) VALUES "
                    . implode(", ", $hotspotsInsertData));

                $hotspotsInsertData = [];
            }

           $createdTechPartsCount++;
            if ($createdTechPartsCount % 1000 == 0) {
                echo 'Created ' . $createdTechPartsCount . ' tech parts. ' . ($techPartsCount - $createdTechPartsCount) . " left\n";
            }
        }

        if (count($categoriesInsertData) > 0) {
            Shopware()->Db()->query("INSERT INTO pk_explosion_chart_categories (img, size, categoryID) VALUES "
                . implode(', ', $categoriesInsertData));
        }

        if (count($hotspotsInsertData) > 0) {
            Shopware()->Db()->query("INSERT INTO pk_explosion_chart_articles (hotspotID, articleID, articleDetailID, active) VALUES "
                . implode(', ', $hotspotsInsertData));
        }

        $executionTime = (microtime(true) - $startTime);
        echo 'Creating tech parts completed in ' . $executionTime . " seconds\n";
    }
}
