<?php

namespace MagediaDemharter\Service;

class CreateTechPartsService
{
// Local
    private $techPartsDataCsvFilePath = '/var/www/quad-ersatzteile.loc/TechPartsData.csv';
    private $imagesFilePath = '/var/www/quad-ersatzteile.loc/media/image/pkExplosionChart/';
    private $categoriesTreesFilePath = '/var/www/quad-ersatzteile.loc/CategoriesTrees.txt';

// Staging
//    private $techPartsDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/TechPartsData.csv';
//    private $imagesFilePath = '/usr/home/mipzhm/public_html/staging/media/image/pkExplosionChart/';
//    private $categoriesTreesFilePath = '/usr/home/mipzhm/public_html/staging/CategoriesTrees.txt';

// Live
//    private $techPartsDataCsvFilePath = '/usr/home/mipzhm/public_html/TechPartsData.csv';
//    private $imagesFilePath = '/usr/home/mipzhm/public_html/media/image/pkExplosionChart/';
//    private $categoriesTreesFilePath = '/usr/home/mipzhm/public_html/CategoriesTrees.txt';
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
        unset($categoryIds);

        $categoriesTrees = json_decode(file_get_contents($this->categoriesTreesFilePath), true);
        $imageSizes = [];
        $categoriesInsertData = [];
        $hotspotsInsertData = [];
        $createdTechPartsCount = 0;
        $csvFile = fopen($this->techPartsDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if ($rowData['products_category_tree'] == '' || $rowData['products_category_tree'] == "Artikel noch nicht zugewiesen") {
                echo 'Category with ID = ' . $rowData['categories_id'] . ' linked with product with ID = ' . $rowData['products_id'] . " has no name\n";
                continue;
            }

            $categoryTree = explode('=>', $rowData['products_category_tree']);
            $categoryTree = array_map('trim', $categoryTree);
            $categoryTree = implode(' => ', $categoryTree);

            $categoryId = array_search($categoryTree, $categoriesTrees);
            if ($categoryId === false) {
                echo 'Category ' . $rowData['products_category_tree'] . " does not exist\n";
                continue;
            }

            if ($rowData['cat_article_component_image'] != '') {
                if (!isset($imageSizes[$rowData['cat_article_component_image']])) {
                    $imageSize = @getimagesize($rowData['cat_article_component_image_link']);
                    if ($imageSize === false) {
                        $imageSizes[$rowData['cat_article_component_image']] = false;

                        echo 'Image ' . $rowData['cat_article_component_image_link'] . " has wrong link\n";
                    } else {
                        $imageSizes[$rowData['cat_article_component_image']] = $imageSize[0] . ';' . $imageSize[1];

                        if (!file_exists($this->imagesFilePath . $rowData['cat_article_component_image'])) {
                            $fileContent = file_get_contents($rowData['cat_article_component_image_link']);
                            file_put_contents($this->imagesFilePath . $rowData['cat_article_component_image'], $fileContent);
                        }
                    }
                }

                if ($imageSizes[$rowData['cat_article_component_image']] !== false) {
                    if (!isset($categoriesInsertData[$categoryId])) {
                        $categoriesInsertData[$categoryId] = "('"
                            . $rowData['cat_article_component_image'] . "', '"
                            . $imageSizes[$rowData['cat_article_component_image']] . "', "
                            . $categoryId . ")";
                    }
                }
            }

            if ($rowData['position_x'] != '' && $rowData['position_y'] != '') {
                if (strlen($rowData['external_id']) >= 4) {
                    $rowData['external_id'] = $this->helper->fixExternalId($rowData['external_id']);
                    $productDetails = $this->modelManager->getRepository('Shopware\Models\Article\Detail')->findOneBy(['number' => $rowData['external_id']]);
                    if ($productDetails) {
                        Shopware()->Db()->query("INSERT INTO pk_explosion_chart_hotspots (categoryID, coords) VALUES("
                            . $categoryId . ", '"
                            . $rowData['position_x'] . ';' . $rowData['position_y'] . "')");

                        $hotspotId = 0;
                        $result = Shopware()->Db()->query("SELECT * FROM pk_explosion_chart_hotspots WHERE categoryID = " . $categoryId
                            . " AND coords = '" . $rowData['position_x'] . ';' . $rowData['position_y'] . "'");
                        foreach ($result as $row) {
                            $hotspotId = $row['id'];
                        }

                        if (!isset($hotspotsInsertData[$hotspotId])) {
                            $hotspotsInsertData[$hotspotId] = "("
                                . $hotspotId . ", "
                                . $productDetails->getArticleID() . ", "
                                . $productDetails->getId() . ", 1)";
                        }
                    } else {
                        echo 'Product with external ID = ' . $rowData['external_id'] . " does not exist\n";
                    }
                }
            }

           $createdTechPartsCount++;
            if ($createdTechPartsCount % 1000 == 0) {
                echo 'Created ' . $createdTechPartsCount . " tech parts\n";
            }
        }
        fclose($csvFile);
        unset($imageSizes);
        unset($categoriesTrees);

        while (count($categoriesInsertData) >= 500) {
            Shopware()->Db()->query("INSERT INTO pk_explosion_chart_categories (img, size, categoryID) VALUES "
                . implode(', ', array_slice($categoriesInsertData, 0, 500)));

            $categoriesInsertData = array_slice($categoriesInsertData, 500);
        }
        if (count($categoriesInsertData) > 0) {
            Shopware()->Db()->query("INSERT INTO pk_explosion_chart_categories (img, size, categoryID) VALUES "
                . implode(', ', $categoriesInsertData));
        }

        while (count($hotspotsInsertData) >= 1000) {
            Shopware()->Db()->query("INSERT INTO pk_explosion_chart_articles (hotspotID, articleID, articleDetailID, active) VALUES "
                . implode(', ', array_slice($hotspotsInsertData, 0, 1000)));

            $hotspotsInsertData = array_slice($hotspotsInsertData, 1000);
        }
        if (count($hotspotsInsertData) > 0) {
            Shopware()->Db()->query("INSERT INTO pk_explosion_chart_articles (hotspotID, articleID, articleDetailID, active) VALUES "
                . implode(', ', $hotspotsInsertData));
        }

        $executionTime = (microtime(true) - $startTime);
        echo 'Creating tech parts completed in ' . $executionTime . " seconds\n";
    }
}
