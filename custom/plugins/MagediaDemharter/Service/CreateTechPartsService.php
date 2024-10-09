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

        $techPartsData = json_decode(file_get_contents($this->techPartsDataJsonFilePath), true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpointUrl . '/categories?limit=50000');
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->userName . ':' . $this->apiKey);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        $response = curl_exec($ch);

        if(curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
            curl_close($ch);

            return;
        }

        curl_close($ch);
        $categories = json_decode($response)->data;

        $mainCategoryId = 0;
        foreach ($categories as $category) {
            if ($category->name == $this->categoryName) {
                $mainCategoryId = $category->id;
                break;
            }
        }

        $categoriesTrees = $this->buildCategoriesTrees($categories, $mainCategoryId, []);
        unset($categories);

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
                echo 'Category with ID = ' . $techPart['categories_id'] . ' linked with product with ID = ' . $techPart['products_id'] . " does not exist\n";
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

    private function buildCategoriesTrees($categories, $parentId, $categoriesTree): array
    {
        $categoriesTrees = [];

        $childCategories = [];
        foreach ($categories as $category) {
            if ($category->parentId == $parentId) {
                $childCategories[] = $category;
            }
        }

        if (count($childCategories) > 0) {
            foreach ($childCategories as $childCategory) {
                $newCategoryTree = array_merge($categoriesTree, [$childCategory->name]);
                $childCategoryTree = $this->buildCategoriesTrees($categories, $childCategory->id, $newCategoryTree);

                if (empty($childCategoryTree)) {
                    $categoriesTrees[$childCategory->id] = implode(' => ', $newCategoryTree);
                } else {
                    foreach ($childCategoryTree as $key => $value) {
                        $categoriesTrees[$key] = $value;
                    }
                }

            }
        }

        return $categoriesTrees;
    }
}
