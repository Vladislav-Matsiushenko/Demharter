<?php

namespace MagediaDemharter\Service;

class CreateTechPartsService
{
// Local
    private $techPartsDataJsonFilePath = '/var/www/quad-ersatzteile.loc/TechPartsData.txt';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $techPartsDataJsonFilePath = '/usr/home/mipzhm/public_html/staging/TechPartsData.txt';
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $techPartsDataJsonFilePath = '/usr/home/mipzhm/public_html/TechPartsData.txt';
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

        $techPartsData = json_decode(file_get_contents($this->techPartsDataJsonFilePath), true);

        $categoriesTrees = $this->helper->getCategoriesTrees($this->endpointUrl, $this->userName, $this->apiKey, $this->categoryName);

        foreach ($techPartsData as $index => $techPartData) {
            $categoryTree = explode('=>', $techPartData['products_category_tree']);
            $categoryTree = array_map('trim', $categoryTree);
            $categoryTree = implode(' => ', $categoryTree);

            $categoryId = array_search($categoryTree, $categoriesTrees);
            if ($categoryId !== false) {
                $techPartsData[$index]['category_id'] = $categoryId;
            }
        }
        unset($categoriesTrees);

        $categoriesInsertData = [];
        $insertedCategoriesData = [];
        $hotspotsInsertData = [];
        $insertedHotspotsData = [];
        $techPartsCount = count($techPartsData);
        $createdTechPartsCount = 0;
        foreach($techPartsData as $techPart) {
            if (!isset($techPart['category_id'])) {
                echo 'Category ' . $techPart['products_category_tree'] . " does not exist\n";
                continue;
            }

            if (!isset($categoriesInsertData[$techPart['category_id']]) && !isset($insertedCategoriesData[$techPart['category_id']])) {
                $needInsert = true;
                $result = Shopware()->Db()->query("SELECT * FROM pk_explosion_chart_categories WHERE categoryID = " . $techPart['category_id']);
                foreach ($result as $row) {
                    $needInsert = !$row['id'];
                }
                if ($needInsert) {
                    $categoriesInsertData[$techPart['category_id']] = "('"
                        . $techPart['cat_article_component_image'] . "', '"
                        . $techPart['cat_article_component_image_size'] . "', "
                        . $techPart['category_id'] . ")";
                } else {
                    $insertedCategoriesData[$techPart['category_id']] = true;
                }
            }

            $hotspotId = null;
            $result = Shopware()->Db()->query("SELECT * FROM pk_explosion_chart_hotspots WHERE categoryID = " . $techPart['category_id']
                . " AND coords = '" . $techPart['products_coords'] . "'");
            foreach ($result as $row) {
                $hotspotId = $row['id'];
            }
            if (!$hotspotId) {
                Shopware()->Db()->query("INSERT INTO pk_explosion_chart_hotspots (categoryID, coords) VALUES("
                    . $techPart['category_id'] . ", '"
                    . $techPart['products_coords'] . "')");

                $result = Shopware()->Db()->query("SELECT * FROM pk_explosion_chart_hotspots WHERE categoryID = " . $techPart['category_id']
                    . " AND coords = '" . $techPart['products_coords'] . "'");
                foreach ($result as $row) {
                    $hotspotId = $row['id'];
                }
            }

            if (!isset($hotspotsInsertData[$hotspotId]) && !isset($insertedHotspotsData[$hotspotId])) {
                $needInsert = true;
                $result = Shopware()->Db()->query("SELECT * FROM pk_explosion_chart_articles WHERE hotspotID = " . $hotspotId);
                foreach ($result as $row) {
                    $needInsert = !$row['id'];
                }
                if ($needInsert) {
                    $hotspotsInsertData[$hotspotId] = "("
                        . $hotspotId . ", "
                        . $techPart['product_id'] . ", "
                        . $techPart['product_details_id'] . ", 1)";
                } else {
                    $insertedHotspotsData[$hotspotId] = true;
                }
            }

            if (count($categoriesInsertData) >= 500) {
                Shopware()->Db()->query("INSERT INTO pk_explosion_chart_categories (img, size, categoryID) VALUES "
                    . implode(", ", $categoriesInsertData));

                $categoriesInsertData = [];
            }

            if (count($hotspotsInsertData) >= 500) {
                Shopware()->Db()->query("INSERT INTO pk_explosion_chart_articles (hotspotID, articleID, articleDetailID, active) VALUES "
                    . implode(", ", $hotspotsInsertData));

                $hotspotsInsertData = [];
            }

           $createdTechPartsCount++;
            if ($createdTechPartsCount % 500 == 0) {
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
