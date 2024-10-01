<?php

namespace MagediaDemharter\Service;

use Doctrine\ORM\ORMException;

class CreateTechParts
{
    private $logFilePath = '/var/www/quad-ersatzteile.loc/NotCreatedTechParts.txt';
    private $csvFilePath = '/var/www/quad-ersatzteile.loc/cat_data.csv';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';
    private $imageFilePath = '/var/www/quad-ersatzteile.loc/media/image/pkExplosionChart/';
//    private $logFilePath = '/usr/home/mipzhm/public_html/NotCreatedTechParts.txt';
//    private $csvFilePath = '/usr/home/mipzhm/public_html/cat_data.csv';
//    private $endpointUrl = 'https://www.quad-ersatzteile.com/api';
//    private $imageFilePath = '/usr/home/mipzhm/public_html/media/image/pkExplosionChart/';
    private $userName = 'schwab';
    private $apiKey = 'pdw4kVus56U9IcFaKuHKv7QFQABtKeG20ub5rAh3';
    private $modelManager;
    private $dbalConnection;
    private $categoryName = '';
    private $productCategories;

    public function __construct()
    {
        ini_set('memory_limit', '-1');
        $this->modelManager = Shopware()->Container()->get('models');
        $this->dbalConnection = Shopware()->Container()->get('dbal_connection');
    }

    public function create($category = null)
    {
        file_put_contents($this->logFilePath, '');

        if ($category) {
            $result = Shopware()->Db()->query("SELECT * FROM s_categories WHERE id = " . $category);
            foreach ($result as $row) {
                $this->categoryName = $row['description'];
            }
            echo "Importing into category {$this->categoryName}\n";
        }

        $this->downloadTeileexportFile();
        $csvFile = fopen($this->csvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        $data = [];
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            $data[] = $rowData;
        }
        fclose($csvFile);

        $createdTechPartsCount = 0;
        foreach ($data as $row) {
            if ($row['products_category_tree'] == '' || $row['products_category_tree'] == "Artikel noch nicht zugewiesen") {
                $logMessage = 'Category with ID = '.$row['categories_id'].' linked with product with ID = '.$row['products_id']." has no name\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            if ($row['position_x'] != '' && $row['position_y'] != '') {
                $row['products_coords'] = $row['position_x'] . ';' . $row['position_y'];
            } else {
                $logMessage = 'Product with ID = '.$row['products_id'].' linked with category with ID = '.$row['categories_id']." has no coords\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            $imageSize = @getimagesize($row['cat_article_component_image_link']);
            if ($imageSize !== false) {
                $row['cat_article_component_image_size'] = $imageSize[0] . ';' . $imageSize[1];
            } else {
                $logMessage = 'Category with ID = '.$row['categories_id'].' linked with product with ID = '.$row['products_id']." has no image\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            if (strlen($row['external_id']) < 4){
                $logMessage = 'Product with ID = '.$row['products_id'].' linked with category with ID = '.$row['categories_id']." has no external ID\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            for ($i = 0; $i < strlen($row['external_id']); $i++){
                if (!preg_match('/^[a-zA-Z0-9-_.]+$/', $row['external_id'][$i])){
                    $row['external_id'][$i] = '_';
                }
            }

            $sku = $row['external_id'];
            $productExists = $this->checkUniqueProducts($sku);
            if ($productExists) {
                $this->createTechPart($row, $productExists->getArticleID(), $productExists->getId());
                $createdTechPartsCount++;

                if ($createdTechPartsCount % 100 == 0) {
                    echo "Created {$createdTechPartsCount} products\n";
                }
            } else {
                $logMessage = 'Product with ID = '.$row['products_id']." does not exist\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
            }
        }

        foreach ($this->productCategories as $productId => $categoryIds) {
            $this->addCategoriesToProduct($productId, $categoryIds);
        }
        
        echo "{$createdTechPartsCount} tech parts were created!\nCreating completed";
    }

    private function checkUniqueProducts($sku)
    {
        $productExists = $this->modelManager
            ->getRepository('Shopware\Models\Article\Detail')
            ->findOneBy(['number' => $sku]);

        return $productExists;
    }

    public function createTechPart($csvRow, $productId, $detailId)
    {
        $categoriesList = json_decode($this->getCategoriesList());
        $categoryPath = $this->processString($this->categoryName ?
            $this->categoryName . ' => ' . $csvRow['products_category_tree'] :
            $csvRow['products_category_tree']
        );
        $categoryId = $this->findOrCreateCategory($categoriesList, $categoryPath);
        $this->productCategories[$productId][]['id'] = $categoryId;

        echo "CAT ID: " . $categoryId . "; SKU: " . $csvRow['external_id'] . "\n";

        if (!file_exists($this->imageFilePath . $csvRow['cat_article_component_image'])) {
            $fileContent = file_get_contents($csvRow['cat_article_component_image_link']);
            file_put_contents($this->imageFilePath . $csvRow['cat_article_component_image'], $fileContent);
        }

        $needInsert = true;
        $result = Shopware()->Db()->query("SELECT * FROM pk_explosion_chart_categories WHERE categoryID = " . $categoryId);
        foreach ($result as $row) {
            $needInsert = $row['id'] ? false : true;
        }
        if ($needInsert) {
            Shopware()->Db()->query("INSERT INTO pk_explosion_chart_categories (img, size, categoryID) VALUES('"
                . $csvRow['cat_article_component_image'] . "', '"
                . $csvRow['cat_article_component_image_size'] . "', "
                . $categoryId . ")");
        }

        $hotspotId = null;
        $result = Shopware()->Db()->query("SELECT * FROM pk_explosion_chart_hotspots WHERE categoryID = ". $categoryId . " AND coords = '" . $csvRow['products_coords'] . "'");
        foreach ($result as $row) {
            $hotspotId = $row['id'];
        }
        if (!$hotspotId) {
            Shopware()->Db()->query("INSERT INTO pk_explosion_chart_hotspots (categoryID, coords) VALUES("
                . $categoryId . ", '"
                . $csvRow['products_coords'] . "')");

            $result = Shopware()->Db()->query("SELECT * FROM pk_explosion_chart_hotspots WHERE categoryID = ". $categoryId . " AND coords = '" . $csvRow['products_coords'] . "'");
            foreach ($result as $row) {
                $hotspotId = $row['id'];
            }
        }

        $needInsert = true;
        $result = Shopware()->Db()->query("SELECT * FROM pk_explosion_chart_articles WHERE hotspotID = ". $hotspotId);
        foreach ($result as $row) {
            $needInsert = $row['id'] ? false : true;
        }

        if ($needInsert) {
            Shopware()->Db()->query("INSERT INTO pk_explosion_chart_articles (hotspotID, articleID, articleDetailID, active) VALUES("
                . $hotspotId . ", "
                . $productId . ", "
                . $detailId . ", 1)");
        }
    }

    private function addCategoriesToProduct($productId, $categoryIds)
    {
        $productData = array(
            'categories' => $categoryIds
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpointUrl . '/articles/' . $productId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($productData));
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->userName:$this->apiKey");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);

        if (!json_decode($response)) {
            echo $response;
            $logMessage = 'Product with ID = ' . $productId . " was not updated!\n";
            echo $logMessage;
            file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
        }
        if (curl_errno($ch)) {
            echo 'Error: ' . curl_error($ch);
        }

        curl_close($ch);
    }

    private function getCategoriesList()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpointUrl . '/categories?limit=30000');
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->userName:$this->apiKey");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        $response = curl_exec($ch);

        if(curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
        }

        curl_close($ch);

        return $response;
    }

    private function createCategory($name, $id)
    {
        $categoryData = array(
            'name' => $name,
            'parentId' => $id
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpointUrl . '/categories');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($categoryData));
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->userName:$this->apiKey");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);

        if(curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
        }

        curl_close($ch);

        return $response;
    }

    public function findOrCreateCategory($categoriesList, $categoryPath)
    {
        $categoryId = 0;
        $arrId = 0;
        $categories = $categoriesList->data;
        $foundInInnerLoop = false;
        $firstIteration = true;
        foreach ($categoryPath as $item){
            foreach ($categories as $category) {
                if ($item == $category->name && ($firstIteration || $category->parentId == $categoryId)) {
                    $arrId++;
                    $categoryId = $category->id;
                    $foundInInnerLoop = true;
                    break;
                }
            }
            if (!$foundInInnerLoop) {
                $categoryId = 11833;
                break;
            }
            $firstIteration = false;
        }

        for ($i = $arrId; $i < count($categoryPath); $i++) {
            $response = json_decode($this->createCategory($categoryPath[$i], $categoryId));
            $categoryId = $response->data->id;
            $arrId = $i + 1;
        }

        return $categoryId;
    }

    public function processString($inputString) {
        $elements = explode("=>", $inputString);
        $result = array_map('trim', $elements);
        return $result;
    }

    public function downloadTeileexportFile()
    {
        $filePath = '/var/www/quad-ersatzteile.loc/cat_data.csv';
//        $filePath = '/usr/home/mipzhm/public_html/cat_data.csv';
        $fileUrl = 'https://www.dataparts.eu/media/files_public/e1d6bb2bf5293105f019865e9904d969_file/cat_data.csv';
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        $fileContent = file_get_contents($fileUrl);
        if ($fileContent !== false) {
            file_put_contents($filePath, $fileContent);
            echo 'The file was successfully downloaded and saved to the following path: ' . $filePath;
            echo "\n";
        } else {
            echo 'Error while downloading the file';
        }
    }
}
