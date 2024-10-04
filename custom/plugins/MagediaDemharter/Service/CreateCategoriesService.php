<?php

namespace MagediaDemharter\Service;

class CreateCategoriesService
{
// Local
    private $logFilePath = '/var/www/quad-ersatzteile.loc/NotCreatedCategories.txt';
    private $productsDataCsvFilePath = '/var/www/quad-ersatzteile.loc/ProductsData.csv';
    private $techPartsDataCsvFilePath = '/var/www/quad-ersatzteile.loc/TechPartsData.csv';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $logFilePath = '/usr/home/mipzhm/public_html/staging/NotCreatedCategories.txt';
//    private $productsDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/ProductsData.csv';
//    private $techPartsDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/TechPartsData.csv';
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $logFilePath = '/usr/home/mipzhm/public_html/NotCreatedCategories.txt';
//    private $productsDataCsvFilePath = '/usr/home/mipzhm/public_html/ProductsData.csv';
//    private $techPartsDataCsvFilePath = '/usr/home/mipzhm/public_html/TechPartsData.csv';
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

        $categoriesData = [];
        $csvFile = fopen($this->productsDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if (!$rowData['products_name']){
                $logMessage = 'Product with ID = ' . $rowData['products_id'] . " has no name!\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            if ($rowData['products_category_tree'] == '' || $rowData['products_category_tree'] == "Artikel noch nicht zugewiesen") {
                $logMessage = 'Product with ID = ' . $rowData['products_id'] . " has no category\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            if (strlen($rowData['external_id']) < 4){
                $logMessage = 'Product with ID = ' . $rowData['products_id'] . " has no external ID\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            for ($i = 0; $i < strlen($rowData['external_id']); $i++){
                if (!preg_match('/^[a-zA-Z0-9-_.]+$/', $rowData['external_id'][$i])){
                    $rowData['external_id'][$i] = '_';
                }
            }

            $categoriesData[] =  $this->categoryName . ' => ' . $rowData['products_category_tree'];
        }
        fclose($csvFile);

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

            for ($i = 0; $i < strlen($rowData['external_id']); $i++){
                if (!preg_match('/^[a-zA-Z0-9-_.]+$/', $rowData['external_id'][$i])){
                    $rowData['external_id'][$i] = '_';
                }
            }

            $categoriesData[] =  $this->categoryName . ' => ' . $rowData['products_category_tree'];
        }
        fclose($csvFile);
        $categoriesData = array_unique($categoriesData);

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
        $categoriesCount = count($categoriesData);
        $createdCategoriesCount = 0;
        foreach ($categoriesData as $names) {
            $names = explode('=>', $names);
            $names = array_map('trim', $names);

            $categoryId = 0;
            $subCategoriesCount = 0;
            $firstIteration = true;
            foreach ($names as $item){
                foreach ($categories as $category) {
                    if ($item == $category->name && ($firstIteration || $category->parentId == $categoryId)) {
                        $subCategoriesCount++;
                        $categoryId = $category->id;
                        break;
                    }
                }
                $firstIteration = false;
            }

            for ($i = $subCategoriesCount; $i < count($names); $i++) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->endpointUrl . '/categories');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('name' => $names[$i], 'parentId' => $categoryId)));
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_USERPWD, $this->userName . ':' . $this->apiKey);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                $response = curl_exec($ch);

                if (curl_errno($ch)) {
                    echo 'Curl error: ' . curl_error($ch);
                }

                curl_close($ch);

                $newCategoryId = json_decode($response)->data->id;
                if ($newCategoryId) {
                    $categories[] = (Object)['id' => $newCategoryId, 'name' => $names[$i], 'parentId' => $categoryId];
                    $categoryId = $newCategoryId;
                    $subCategoriesCount = $i + 1;
                }
            }

            $createdCategoriesCount++;
            if ($createdCategoriesCount % 100 == 0) {
                echo 'Created ' . $createdCategoriesCount . ' categories. ' . ($categoriesCount - $createdCategoriesCount) . " left\n";
            }
        }

        echo "Creating categories completed\n";
    }
}
