<?php

namespace MagediaDemharter\Service;

class CreateHotspotsService
{
// Local
    private $categoryDataCsvFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/CategoryData.csv';
    private $hotspotDataCsvFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/HotspotData.csv';
    private $updatedProductDataFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/UpdatedProductData.txt';
    private $updatedCategoryDataFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/UpdatedCategoryData.txt';
    private $imagesFilePath = '/var/www/quad-ersatzteile.loc/media/image/pkExplosionChart/';

// Staging
//    private $categoryDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/CategoryData.csv';
//    private $hotspotDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/HotspotData.csv';
//    private $updatedProductDataFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/UpdatedProductData.txt';
//    private $updatedCategoryDataFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/UpdatedCategoryData.txt';
//    private $imagesFilePath = '/usr/home/mipzhm/public_html/staging/media/image/pkExplosionChart/';

// Live
//    private $categoryDataCsvFilePath = '/usr/home/mipzhm/public_html/files/demharter/CategoryData.csv';
//    private $hotspotDataCsvFilePath = '/usr/home/mipzhm/public_html/files/demharter/HotspotData.csv';
//    private $updatedProductDataFilePath = '/usr/home/mipzhm/public_html/files/demharter/UpdatedProductData.txt';
//    private $updatedCategoryDataFilePath = '/usr/home/mipzhm/public_html/files/demharter/UpdatedCategoryData.txt';
//    private $imagesFilePath = '/usr/home/mipzhm/public_html/media/image/pkExplosionChart/';
    private $categoryName = 'Ersatzteile';
    private $imageUrl = 'https://www.dataparts.eu/media/images/org/';
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

        $categoryIds = $this->helper->getChildCategories($this->categoryName);
        Shopware()->Db()->query("DELETE FROM pk_explosion_chart_categories WHERE categoryID IN ('" . implode("','", $categoryIds) . "')");
        Shopware()->Db()->query("DELETE FROM pk_explosion_chart_hotspots WHERE categoryID IN ('" . implode("','", $categoryIds) . "')");
        Shopware()->Db()->query("DELETE FROM pk_explosion_chart_articles WHERE articleID IN (SELECT articleID FROM s_articles_categories WHERE categoryID IN ('" . implode("','", $categoryIds) . "'))");
        unset($categoryIds);

        $updatedCategoriesData = json_decode(file_get_contents($this->updatedCategoryDataFilePath), true);
        $imageSizes = [];
        $categoriesInsertData = [];
        $downloadedImagesCount = 0;
        $csvFile = fopen($this->categoryDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);

            if (isset($updatedCategoriesData[$rowData['categories_id']]) && trim($rowData['categories_image']) !== '') {
                $categoryId = $updatedCategoriesData[$rowData['categories_id']];
                $imageName = trim($rowData['categories_image']);
                if (!isset($imageSizes[$imageName])) {
                    $imageSize = @getimagesize($this->imageUrl . $imageName);
                    if ($imageSize === false) {
                        $imageSizes[$imageName] = false;

                        echo 'Image ' . $imageName . " has wrong link\n";
                    } else {
                        $imageSizes[$imageName] = $imageSize[0] . ';' . $imageSize[1];

                        if (!file_exists($this->imagesFilePath . $imageName)) {
                            $fileContent = file_get_contents($this->imageUrl . $imageName);
                            file_put_contents($this->imagesFilePath . $imageName, $fileContent);
                        }
                    }
                }

                if ($imageSizes[$imageName] !== false) {
                    $categoriesInsertData[$categoryId] = "('"
                        . $imageName . "', '"
                        . $imageSizes[$imageName] . "', "
                        . $categoryId . ")";
                }
            }

            $downloadedImagesCount++;
            if ($downloadedImagesCount % 1000 == 0) {
                echo 'Downloaded ' . $downloadedImagesCount . " images\n";
            }
        }
        fclose($csvFile);
        unset($imageSizes);

        while (count($categoriesInsertData) >= 500) {
            Shopware()->Db()->query("INSERT INTO pk_explosion_chart_categories (img, size, categoryID) VALUES "
                . implode(', ', array_slice($categoriesInsertData, 0, 500)));

            $categoriesInsertData = array_slice($categoriesInsertData, 500);
        }
        if (count($categoriesInsertData) > 0) {
            Shopware()->Db()->query("INSERT INTO pk_explosion_chart_categories (img, size, categoryID) VALUES "
                . implode(', ', $categoriesInsertData));
        }
        unset($categoriesInsertData);


        $updatedProductData = json_decode(file_get_contents($this->updatedProductDataFilePath), true);
        $hotspotsInsertData = [];
        $createdHotspotsCount = 0;
        $csvFile = fopen($this->hotspotDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);

            if (isset($updatedCategoriesData[$rowData['categories_id']]) && isset($updatedProductData[$rowData['products_id']])) {
                $categoryId = $updatedCategoriesData[$rowData['categories_id']];
                $productDetails = $this->modelManager->getRepository('Shopware\Models\Article\Detail')->findOneBy(['number' => $updatedProductData[$rowData['products_id']]]);
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

                    if ($hotspotId !== 0) {
                        $hotspotsInsertData[$hotspotId] = "("
                            . $hotspotId . ", "
                            . $productDetails->getArticleID() . ", "
                            . $productDetails->getId() . ", 1)";
                    }
                }
            }

            $createdHotspotsCount++;
            if ($createdHotspotsCount % 1000 == 0) {
                echo 'Created ' . $createdHotspotsCount . " hotspots\n";
            }
        }
        fclose($csvFile);
        unset($updatedCategoriesData);
        unset($updatedProductData);

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
        echo 'Creating hotspots completed in ' . $executionTime . " seconds\n";
    }
}
