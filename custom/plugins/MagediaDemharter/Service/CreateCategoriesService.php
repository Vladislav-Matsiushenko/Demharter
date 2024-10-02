<?php

namespace MagediaDemharter\Service;

class CreateCategoriesService
{
// Local
    private $csvFilePath = '/var/www/quad-ersatzteile.loc/TechPartsData.csv';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $csvFilePath = '/usr/home/mipzhm/public_html/staging/TechPartsData.csv';
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $csvFilePath = '/usr/home/mipzhm/public_html/TechPartsData.csv';
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
        $categoriesData = [];
        $csvFile = fopen($this->csvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if ($rowData['products_category_tree'] != '' && $rowData['products_category_tree'] != "Artikel noch nicht zugewiesen") {
                $categoriesData[] =  $this->categoryName . ' => ' . $rowData['products_category_tree'];
            }
        }
        fclose($csvFile);
        $categoriesData = array_unique($categoriesData);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpointUrl . '/categories?limit=50000');
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->userName:$this->apiKey");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        $response = curl_exec($ch);

        if(curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);

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

                $createdCategoriesCount++;
                if ($createdCategoriesCount % 100 == 0) {
                    echo 'Created ' . $createdCategoriesCount . ' categories. ' . ($categoriesCount - $createdCategoriesCount) . " left\n";
                }

                $newCategoryId = json_decode($response)->data->id;
                $categories[] = (Object)['id' => $newCategoryId, 'name' => $names[$i], 'parentId' => $categoryId];
                $categoryId = $newCategoryId;

                $subCategoriesCount = $i + 1;
            }
        }

        echo "Creating categories completed\n";
    }
}
