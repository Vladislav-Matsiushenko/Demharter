<?php

namespace MagediaDemharter\Service;

class CreateTechPartsService
{
// Local
    private $logFilePath = '/var/www/quad-ersatzteile.loc/NotCreatedTechParts.txt';
    private $techPartsDataCsvFilePath = '/var/www/quad-ersatzteile.loc/TechPartsData.csv';
    private $imagesFilePath = '/var/www/quad-ersatzteile.loc/media/image/pkExplosionChart/';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $logFilePath = '/usr/home/mipzhm/public_html/staging/NotCreatedTechParts.txt';
//    private $techPartsDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/TechPartsData.csv';
//    private $imagesFilePath = '/usr/home/mipzhm/public_html/staging/media/image/pkExplosionChart/';
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $logFilePath = '/usr/home/mipzhm/public_html/NotCreatedTechParts.txt';
//    private $techPartsDataCsvFilePath = '/usr/home/mipzhm/public_html/TechPartsData.csv';
//    private $imagesFilePath = '/usr/home/mipzhm/public_html/media/image/pkExplosionChart/';
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
        file_put_contents($this->logFilePath, '');

        $imageSizes = [];
        $techPartsData = [];
        $csvFile = fopen($this->techPartsDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if ($rowData['products_category_tree'] == '' || $rowData['products_category_tree'] == "Artikel noch nicht zugewiesen") {
                $logMessage = 'Category with ID = ' . $rowData['categories_id'] . ' linked with product with ID = ' . $rowData['products_id'] . " has no name\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            if (strlen($rowData['external_id']) < 4){
                $logMessage = 'Product with ID = ' . $rowData['products_id'] . ' linked with category with ID = ' . $rowData['categories_id'] . " has no external ID\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            if ($rowData['position_x'] == '' || $rowData['position_y'] == '') {
                $logMessage = 'Product with ID = ' . $rowData['products_id'] . ' linked with category with ID = ' . $rowData['categories_id'] . " has no coords\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            if ($rowData['cat_article_component_image'] == '') {
                $logMessage = 'Category with ID = ' . $rowData['categories_id'] . ' linked with product with ID = ' . $rowData['products_id'] . " has no image\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            $productDetails = $this->modelManager->getRepository('Shopware\Models\Article\Detail')->findOneBy(['number' => $rowData['external_id']]);
            if (!$productDetails) {
                $logMessage = 'Product with External ID = ' . $rowData['external_id'] . " does not exist\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            if (!isset($imageSizes[$rowData['cat_article_component_image']])) {
                $imageSize = @getimagesize($rowData['cat_article_component_image_link']);
                if ($imageSize === false) {
                    $imageSizes[$rowData['cat_article_component_image']] = false;

                    $logMessage = 'Image ' . $rowData['cat_article_component_image_link'] . " has wrong link\n";
                    echo $logMessage;
                    file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                    continue;
                } else {
                    $imageSizes[$rowData['cat_article_component_image']] = $imageSize[0] . ';' . $imageSize[1];

                    if (!file_exists($this->imagesFilePath . $rowData['cat_article_component_image'])) {
                        $fileContent = file_get_contents($rowData['cat_article_component_image_link']);
                        file_put_contents($this->imagesFilePath . $rowData['cat_article_component_image'], $fileContent);
                    }
                }
            } elseif ($imageSizes[$rowData['cat_article_component_image']] === false) {
                continue;
            }

            for ($i = 0; $i < strlen($rowData['external_id']); $i++){
                if (!preg_match('/^[a-zA-Z0-9-_.]+$/', $rowData['external_id'][$i])){
                    $rowData['external_id'][$i] = '_';
                }
            }
            $rowData['products_coords'] = $rowData['position_x'] . ';' . $rowData['position_y'];
            $rowData['product_id'] = $productDetails->getArticleID();
            $rowData['product_details_id'] = $productDetails->getId();
            $rowData['cat_article_component_image_size'] = $imageSizes[$rowData['cat_article_component_image']];

            $techPartsData[] =  $rowData;
        }
        fclose($csvFile);

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

        foreach ($techPartsData as $index => $techPartData) {
            $categoryTree = explode('=>', $techPartData['products_category_tree']);
            $categoryTree = array_map('trim', $categoryTree);
            $categoryTree = implode(' => ', $categoryTree);

            $categoryId = array_search($categoryTree, $categoriesTrees);
            if ($categoryId !== false) {
                $techPartsData[$index]['category_id'] = $categoryId;
            }
        }

        $techPartsCount = count($techPartsData);
        $createdTechPartsCount = 0;
        foreach($techPartsData as $techPart) {
            if (!isset($techPart['category_id'])) {
                $logMessage = 'Category with ID = ' . $techPart['categories_id'] . ' linked with product with ID = ' . $techPart['products_id'] . " does not exist\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            $needInsert = true;
            $result = Shopware()->Db()->query("SELECT * FROM pk_explosion_chart_categories WHERE categoryID = " . $techPart['category_id']);
            foreach ($result as $row) {
                $needInsert = !$row['id'];
            }
            if ($needInsert) {
                Shopware()->Db()->query("INSERT INTO pk_explosion_chart_categories (img, size, categoryID) VALUES('"
                    . $techPart['cat_article_component_image'] . "', '"
                    . $techPart['cat_article_component_image_size'] . "', "
                    . $techPart['category_id'] . ")");
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

            $needInsert = true;
            $result = Shopware()->Db()->query("SELECT * FROM pk_explosion_chart_articles WHERE hotspotID = " . $hotspotId);
            foreach ($result as $row) {
                $needInsert = !$row['id'];
            }

            if ($needInsert) {
                Shopware()->Db()->query("INSERT INTO pk_explosion_chart_articles (hotspotID, articleID, articleDetailID, active) VALUES("
                    . $hotspotId . ", "
                    . $techPart['product_id'] . ", "
                    . $techPart['product_details_id'] . ", 1)");
            }

           $createdTechPartsCount++;
            if ($createdTechPartsCount % 100 == 0) {
                echo 'Created ' . $createdTechPartsCount . ' tech parts. ' . ($techPartsCount - $createdTechPartsCount) . " left\n";
            }
        }

        echo "Creating tech parts completed\n";
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
