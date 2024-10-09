<?php

namespace MagediaDemharter\Service;

class DownloadTechPartsImagesService
{
// Local
    private $techPartsDataCsvFilePath = '/var/www/quad-ersatzteile.loc/TechPartsData.csv';
    private $imagesFilePath = '/var/www/quad-ersatzteile.loc/media/image/pkExplosionChart/';
    private $techPartsDataJsonFilePath = '/var/www/quad-ersatzteile.loc/TechPartsData.txt';

// Staging
//    private $techPartsDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/TechPartsData.csv';
//    private $imagesFilePath = '/usr/home/mipzhm/public_html/staging/media/image/pkExplosionChart/';
//    private $techPartsDataJsonFilePath = '/usr/home/mipzhm/public_html/staging/TechPartsData.txt';

// Live
//    private $techPartsDataCsvFilePath = '/usr/home/mipzhm/public_html/TechPartsData.csv';
//    private $imagesFilePath = '/usr/home/mipzhm/public_html/media/image/pkExplosionChart/';
//    private $techPartsDataJsonFilePath = '/usr/home/mipzhm/public_html/TechPartsData.txt';
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

        file_put_contents($this->techPartsDataJsonFilePath, json_encode($techPartsData));

        $executionTime = (microtime(true) - $startTime);
        echo 'Downloading tech parts images completed in ' . $executionTime . " seconds\n";
    }
}
