<?php

namespace MagediaDemharter\Service;

class DownloadTechPartsImagesService
{
// Local
    private $techPartsDataCsvFilePath = '/var/www/quad-ersatzteile.loc/TechPartsData.csv';
    private $imagesFilePath = '/var/www/quad-ersatzteile.loc/media/image/pkExplosionChart/';
    private $techPartsDataJsonFilePath = '/var/www/quad-ersatzteile.loc/TechPartsData.txt';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $techPartsDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/TechPartsData.csv';
//    private $imagesFilePath = '/usr/home/mipzhm/public_html/staging/media/image/pkExplosionChart/';
//    private $techPartsDataJsonFilePath = '/usr/home/mipzhm/public_html/staging/TechPartsData.txt';
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $techPartsDataCsvFilePath = '/usr/home/mipzhm/public_html/TechPartsData.csv';
//    private $imagesFilePath = '/usr/home/mipzhm/public_html/media/image/pkExplosionChart/';
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

        $categoriesTrees = $this->helper->getCategoriesTrees($this->endpointUrl, $this->userName, $this->apiKey, $this->categoryName);

        $imageSizes = [];
        $techPartsData = [];
        $csvFile = fopen($this->techPartsDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if ($rowData['products_category_tree'] == '' || $rowData['products_category_tree'] == "Artikel noch nicht zugewiesen") {
                echo 'Category with ID = ' . $rowData['categories_id'] . ' linked with product with ID = ' . $rowData['products_id'] . " has no name\n";
                continue;
            }

            if (strlen($rowData['external_id']) < 4){
                echo 'Product with ID = ' . $rowData['products_id'] . ' linked with category with ID = ' . $rowData['categories_id'] . " has no external ID\n";
                continue;
            }

            if ($rowData['position_x'] == '' || $rowData['position_y'] == '') {
                echo 'Product with ID = ' . $rowData['products_id'] . ' linked with category with ID = ' . $rowData['categories_id'] . " has no coords\n";
                continue;
            }

            if ($rowData['cat_article_component_image'] == '') {
                echo 'Category with ID = ' . $rowData['categories_id'] . ' linked with product with ID = ' . $rowData['products_id'] . " has no image\n";
                continue;
            }

            for ($i = 0; $i < strlen($rowData['external_id']); $i++){
                if (!preg_match('/^[a-zA-Z0-9-_.]+$/', $rowData['external_id'][$i])){
                    $rowData['external_id'][$i] = '_';
                }
            }

            $productDetails = $this->modelManager->getRepository('Shopware\Models\Article\Detail')->findOneBy(['number' => $rowData['external_id']]);
            if (!$productDetails) {
                echo 'Product with External ID = ' . $rowData['external_id'] . " does not exist\n";
                continue;
            }

            if (!isset($imageSizes[$rowData['cat_article_component_image']])) {
                $imageSize = @getimagesize($rowData['cat_article_component_image_link']);
                if ($imageSize === false) {
                    $imageSizes[$rowData['cat_article_component_image']] = false;

                    echo 'Image ' . $rowData['cat_article_component_image_link'] . " has wrong link\n";
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

            $categoryTree = explode('=>', $rowData['products_category_tree']);
            $categoryTree = array_map('trim', $categoryTree);
            $categoryTree = implode(' => ', $categoryTree);

            $categoryId = array_search($categoryTree, $categoriesTrees);
            if ($categoryId === false) {
                echo 'Category ' . $rowData['products_category_tree'] . " does not exist\n";
                continue;
            }

            $techPartsData[] = array(
                'category_id' => $categoryId,
                'products_coords' => $rowData['position_x'] . ';' . $rowData['position_y'],
                'product_id' => $productDetails->getArticleID(),
                'product_details_id' => $productDetails->getId(),
                'cat_article_component_image' => $rowData['cat_article_component_image'],
                'cat_article_component_image_size' => $imageSizes[$rowData['cat_article_component_image']],
            );
        }
        fclose($csvFile);

        file_put_contents($this->techPartsDataJsonFilePath, json_encode($techPartsData));

        $executionTime = (microtime(true) - $startTime);
        echo 'Downloading tech parts images completed in ' . $executionTime . " seconds\n";
    }
}
