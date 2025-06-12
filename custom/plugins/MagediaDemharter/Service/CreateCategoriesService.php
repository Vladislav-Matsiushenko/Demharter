<?php

namespace MagediaDemharter\Service;

class CreateCategoriesService
{
// Local
    private $categoryDataCsvFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/CategoryData.csv';
    private $updatedCategoryDataFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/UpdatedCategoryData.txt';
    private $updatedManufacturerDataFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/UpdatedManufactureData.txt';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $categoryDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/CategoryData.csv';
//    private $updatedCategoryDataFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/UpdatedCategoryData.txt';
//    private $updatedManufacturerDataFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/UpdatedManufactureData.txt';
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $categoryDataCsvFilePath = '/usr/home/mipzhm/public_html/files/demharter/CategoryData.csv';
//    private $updatedCategoryDataFilePath = '/usr/home/mipzhm/public_html/files/demharter/UpdatedCategoryData.txt';
//    private $updatedManufacturerDataFilePath = '/usr/home/mipzhm/public_html/files/demharter/UpdatedManufactureData.txt';
//    private $endpointUrl = 'https://www.quad-ersatzteile.com/api';
    private $categoryName = 'Quad/Scooter spare parts';
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

        $parentCategoryId = 0;
        $result = Shopware()->Db()->query('SELECT * FROM s_categories WHERE description = :value', [
            'value' => $this->categoryName
        ]);
        foreach ($result as $row) {
            $parentCategoryId = $row['id'];
        }
        if ($parentCategoryId === 0) {
            echo 'Category ' . $this->categoryName . " does not exist\n";
            return;
        }

        $categoriesData = [];
        $csvFile = fopen($this->categoryDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if ($rowData['categories_id'] === '' || $rowData['categories_level'] === '' || $rowData['parent_id'] === '' || $rowData['categories_name'] === '' || $rowData['categories_name'] === "Artikel noch nicht zugewiesen") {
                echo 'Category with ID = ' . $rowData['categories_id'] . " was not created\n";
                continue;
            }

            $categoriesData[] =  [
                'categories_id' => $rowData['categories_id'],
                'categories_level' => $rowData['categories_level'],
                'parent_id' => $rowData['parent_id'],
                'categories_name' => $rowData['categories_name'],
            ];
        }
        fclose($csvFile);

        usort($categoriesData, function ($a, $b) {
            $levelCompare = $a['categories_level'] <=> $b['categories_level'];
            if ($levelCompare !== 0) {
                return $levelCompare;
            }

            return strcmp($a['categories_name'], $b['categories_name']);
        });


        $existingManufacturers = $this->helper->getManufacturers($this->endpointUrl, $this->userName, $this->apiKey);
        $categoriesCount = count($categoriesData);
        $createdCategoriesCount = 0;
        $updatedCategoriesData = [];
        $updatedManufacturersData = [];
        foreach ($categoriesData as &$categoryData) {
            if ($categoryData['categories_level'] === '1' && $categoryData['parent_id'] === '0') {
                $response = $this->helper->createCategory($this->endpointUrl, $this->userName, $this->apiKey,
                    json_encode(array('name' => $categoryData['categories_name'], 'parentId' => $parentCategoryId))
                );

                $newCategoryId = json_decode($response)->data->id;
                if ($newCategoryId) {
                    $categoryData['categories_db_id'] = $newCategoryId;
                    $updatedCategoriesData[$categoryData['categories_id']] = $newCategoryId;
                }

                $manufacturerId = 0;
                foreach ($existingManufacturers as $manufacturer){
                    if ($categoryData['categories_name'] == $manufacturer->name){
                        $manufacturerId = $manufacturer->id;
                        $updatedManufacturersData[$categoryData['categories_name']] = $manufacturerId;
                        break;
                    }
                }

                if ($manufacturerId === 0) {
                    $response = $this->helper->createManufacturer($this->endpointUrl, $this->userName, $this->apiKey,
                        json_encode(array('name' => $categoryData['categories_name'], 'image' => ''))
                    );
                    $manufacturerId = json_decode($response)->data->id;
                    if ($manufacturerId) {
                        $updatedManufacturersData[$categoryData['categories_name']] = $manufacturerId;
                    }
                }
            } else {
                foreach ($categoriesData as $categoryDataParent) {
                    if ($categoryData['parent_id'] === $categoryDataParent['categories_id']) {
                        $response = $this->helper->createCategory($this->endpointUrl, $this->userName, $this->apiKey,
                            json_encode(array('name' => $categoryData['categories_name'], 'parentId' => $categoryDataParent['categories_db_id']))
                        );

                        $newCategoryId = json_decode($response)->data->id;
                        if ($newCategoryId) {
                            $categoryData['categories_db_id'] = $newCategoryId;
                            $updatedCategoriesData[$categoryData['categories_id']] = $newCategoryId;
                        }
                        break;
                    }
                }
            }

            $createdCategoriesCount++;
            if ($createdCategoriesCount % 500 == 0) {
                echo 'Created ' . $createdCategoriesCount . ' categories. ' . ($categoriesCount - $createdCategoriesCount) . " left\n";
            }
        }
        unset($categoryData);
        unset($existingManufacturers);

        file_put_contents($this->updatedCategoryDataFilePath, json_encode($updatedCategoriesData));
        file_put_contents($this->updatedManufacturerDataFilePath, json_encode($updatedManufacturersData));

        $executionTime = (microtime(true) - $startTime);
        echo 'Creating categories completed in ' . $executionTime . " seconds\n";
    }
}
