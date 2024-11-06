<?php

namespace MagediaDemharter\Service;

class CreateCategoriesService
{
// Local
    private $productsDataCsvFilePath = '/var/www/quad-ersatzteile.loc/ProductsData.csv';
    private $techPartsDataCsvFilePath = '/var/www/quad-ersatzteile.loc/TechPartsData.csv';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $productsDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/ProductsData.csv';
//    private $techPartsDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/TechPartsData.csv';
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $productsDataCsvFilePath = '/usr/home/mipzhm/public_html/ProductsData.csv';
//    private $techPartsDataCsvFilePath = '/usr/home/mipzhm/public_html/TechPartsData.csv';
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

        $categoriesData = [];
        $csvFile = fopen($this->productsDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if ($rowData['products_category_tree'] == '' || $rowData['products_category_tree'] == "Artikel noch nicht zugewiesen") {
                echo 'Product with ID = ' . $rowData['products_id'] . " has no category\n";
                continue;
            }

            $categoriesData[] =  $this->categoryName . ' => ' . $rowData['products_category_tree'];
        }
        fclose($csvFile);

        $csvFile = fopen($this->techPartsDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if ($rowData['products_category_tree'] == '' || $rowData['products_category_tree'] == "Artikel noch nicht zugewiesen") {
                echo 'Category with ID = ' . $rowData['categories_id'] . ' linked with product with ID = ' . $rowData['products_id'] . " has no name\n";
                continue;
            }

            $categoriesData[] =  $this->categoryName . ' => ' . $rowData['products_category_tree'];
        }
        fclose($csvFile);
        $categoriesData = array_unique($categoriesData);

        $categories = $this->helper->getCategories($this->endpointUrl, $this->userName, $this->apiKey);
        $categoriesCount = count($categoriesData);
        $createdCategoriesCount = 0;
        foreach ($categoriesData as $names) {
            $names = explode('=>', $names);
            $names = array_map('trim', $names);

            $parentCategoryId = 0;
            $subCategoriesCount = 0;
            $firstIteration = true;
            foreach ($names as $name){
                $found = false;
                foreach ($categories as $category) {
                    if ($name == $category->name && ($firstIteration || $category->parentId == $parentCategoryId)) {
                        $subCategoriesCount++;
                        $parentCategoryId = $category->id;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    break;
                }
                $firstIteration = false;
            }

            for ($i = $subCategoriesCount; $i < count($names); $i++) {
                $response = $this->helper->createCategory($this->endpointUrl, $this->userName, $this->apiKey,
                    json_encode(array('name' => $names[$i], 'parentId' => $parentCategoryId))
                );

                $newCategoryId = json_decode($response)->data->id;
                if ($newCategoryId) {
                    $categories[] = (Object)['id' => $newCategoryId, 'name' => $names[$i], 'parentId' => $parentCategoryId];
                    $parentCategoryId = $newCategoryId;
                }
            }

            $createdCategoriesCount++;
            if ($createdCategoriesCount % 500 == 0) {
                echo 'Created ' . $createdCategoriesCount . ' categories. ' . ($categoriesCount - $createdCategoriesCount) . " left\n";
            }
        }

        $executionTime = (microtime(true) - $startTime);
        echo 'Creating categories completed in ' . $executionTime . " seconds\n";
    }
}
