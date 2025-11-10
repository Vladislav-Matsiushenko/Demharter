<?php

namespace MagediaDemharter\Service;

use MagediaDemharter\Subscriber\ProductSubscriber;

class CreateProductsService
{
// Local
    private $ebayPricesFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/EbayPrices.txt';
    private $productDataCsvFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/ProductData.csv';
    private $hotspotDataCsvFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/HotspotData.csv';
    private $updatedProductDataFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/UpdatedProductData.txt';
    private $updatedCategoryDataFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/UpdatedCategoryData.txt';
    private $updatedManufacturerDataFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/UpdatedManufactureData.txt';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $ebayPricesFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/EbayPrices.txt';
//    private $productDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/ProductData.csv';
//    private $hotspotDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/HotspotData.csv';
//    private $updatedProductDataFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/UpdatedProductData.txt';
//    private $updatedCategoryDataFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/UpdatedCategoryData.txt';
//    private $updatedManufacturerDataFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/UpdatedManufactureData.txt';
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $ebayPricesFilePath = '/usr/home/mipzhm/public_html/files/demharter/EbayPrices.txt';
//    private $productDataCsvFilePath = '/usr/home/mipzhm/public_html/files/demharter/ProductData.csv';
//    private $hotspotDataCsvFilePath = '/usr/home/mipzhm/public_html/files/demharter/HotspotData.csv';
//    private $updatedProductDataFilePath = '/usr/home/mipzhm/public_html/files/demharter/UpdatedProductData.txt';
//    private $updatedCategoryDataFilePath = '/usr/home/mipzhm/public_html/files/demharter/UpdatedCategoryData.txt';
//    private $updatedManufacturerDataFilePath = '/usr/home/mipzhm/public_html/files/demharter/UpdatedManufactureData.txt';
//    private $endpointUrl = 'https://www.quad-ersatzteile.com/api';
    private $manufacturerName = 'Default manufacturer';
    private $userName = 'schwab';
    private $apiKey = 'pdw4kVus56U9IcFaKuHKv7QFQABtKeG20ub5rAh3';
    private $helper;
    private $modelManager;
    private $productSubscriber;

    public function __construct(ProductSubscriber $productSubscriber)
    {
        $this->helper = Shopware()->Container()->get('magedia_demharter.helper');
        ini_set('memory_limit', '-1');
        $this->modelManager = Shopware()->Container()->get('models');
        $this->productSubscriber = $productSubscriber;
    }

    public function execute()
    {
        $startTime = microtime(true);

        $this->productSubscriber->setIsActive(false);

        if (file_exists($this->ebayPricesFilePath)) {
            $ebayPrices = json_decode(file_get_contents($this->ebayPricesFilePath), true);
        } else {
            $ebayPrices = [];
        }

        $updatedManufacturersData = json_decode(file_get_contents($this->updatedManufacturerDataFilePath), true);
        $updatedProductsData = [];
        $productsData = [];
        $updated = 0;
        $csvFile = fopen($this->productDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if ($rowData['products_id'] === '' || strlen($rowData['external_id']) < 4 || $rowData['products_name'] === '') {
                echo 'Product with ID = ' . $rowData['products_id'] . " was not created\n";
                continue;
            }

            if (trim($rowData['cat_manufacturer']) === '') {
                $manufacturer = $updatedManufacturersData[$this->manufacturerName];
            } else {
                if (trim($rowData['cat_manufacturer']) === 'Artikel noch nicht zugewiesen' || !isset($updatedManufacturersData[trim($rowData['cat_manufacturer'])])) {
                    echo 'Product with ID = ' . $rowData['products_id'] . " has no manufacturer\n";
                    continue;
                }
                $manufacturer = $updatedManufacturersData[trim($rowData['cat_manufacturer'])];
            }

            $rowData['external_id'] = $this->helper->fixExternalId($rowData['external_id']);
            $productDetails = $this->modelManager->getRepository('Shopware\Models\Article\Detail')->findOneBy(['number' => $rowData['external_id']]);

            $productsData[$rowData['products_id']] = array(
                'external_id' => $rowData['external_id'],
                'products_weight' => $rowData['products_weight'],
                'products_image_1' => $rowData['products_image_1'],
                'products_tax_class_id' => $rowData['products_tax_class_id'],
                'products_tax_percent' => $rowData['products_tax_percent'],
                'products_name' => $rowData['products_name'],
                'products_description' => $rowData['products_description'],
                'cat_manufacturer' => $manufacturer,
                'VK_brutto' => $rowData['VK_brutto'],
                'stock_count' => $rowData['stock_count'],
                'article_id' => $productDetails ? $productDetails->getArticle()->getId() : false,
            );

            if ($productDetails) {
                $article = $productDetails->getArticle();
                $article->getImages()->clear();
                $this->modelManager->persist($article);

                $updated++;
                if ($updated >= 500) {
                    $updated = 0;
                    $this->modelManager->flush();
                    $this->modelManager->clear();
                }

                $res = Shopware()->Db()->query("SELECT * FROM s_articles_prices WHERE pricegroup = 'Ebay' AND articleID = " . $productDetails->getArticle()->getId());
                foreach ($res as $rowEbayPrice) {
                    $ebayPrices[$rowData['external_id']] = [
                        'price' => $rowEbayPrice['price'],
                        'pseudoprice' => $rowEbayPrice['pseudoprice'],
                        'percent' => $rowEbayPrice['percent']
                    ];
                }
            }

            $updatedProductsData[$rowData['products_id']] = $rowData['external_id'];
        }
        fclose($csvFile);
        unset($updatedManufacturersData);

        file_put_contents($this->updatedProductDataFilePath, json_encode($updatedProductsData));
        unset($updatedProductsData);

        file_put_contents($this->ebayPricesFilePath, json_encode($ebayPrices));

        $this->modelManager->flush();
        $this->modelManager->clear();


        $updatedCategoriesData = json_decode(file_get_contents($this->updatedCategoryDataFilePath), true);
        $csvFile = fopen($this->hotspotDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if (isset($productsData[$rowData['products_id']]) && isset($updatedCategoriesData[$rowData['categories_id']])) {
                $catId = $updatedCategoriesData[$rowData['categories_id']];
                $productsData[$rowData['products_id']]['categories'][$catId] = ['id' => $catId];
            }
        }
        fclose($csvFile);
        unset($updatedCategoriesData);

        $productsCount = count($productsData);
        $createdProductsCount = 0;
        foreach($productsData as $product) {
            if (!isset($product['categories'])) {
                echo 'Product with external ID = ' . $product['external_id'] . " has no category\n";
                continue;
            }

            if ($product['products_image_1']){
                if ($product['products_image_1'] === 'https://www.dataparts.eu/media/images/org/noimage.gif' || !@getimagesize($product['products_image_1'])){
                    $product['products_image_1'] = null;
                }
            }

            $prices = array(
                array(
                    'customerGroupKey' => "EK",
                    'price' => $product['VK_brutto'],
                )
            );

            $ebayPrice = null;
            if (isset($ebayPrices[$product['external_id']])) {
                $ebayPrice['customerGroupKey'] = 'Ebay';
                $ebayPrice['price'] = $ebayPrices[$product['external_id']]['price'] * 1.19;
                $ebayPrice['pseudoprice'] = $ebayPrices[$product['external_id']]['pseudoprice'];
                $ebayPrice['percent'] = $ebayPrices[$product['external_id']]['percent'];

                $prices[] = $ebayPrice;
            }

            if ($product['article_id']) {
                $productData = array(
                    'name' => $product['products_name'],
                    'supplierId' => $product['cat_manufacturer'],
                    'descriptionLong' => $product['products_description'] . ProductSubscriber::MANUFACTURER_DESCRIPTION,
                    'categories' => $product['categories'],
                    'mainDetail' => array(
                        'inStock' => $product['stock_count'],
                        'weight' => $product['products_weight'],
                        'prices' => $prices,
                    ),
                    'images' => array(
                        array('link' => $product['products_image_1'])
                    )
                );

                $response = $this->helper->updateProduct($this->endpointUrl, $this->userName, $this->apiKey,
                    json_encode($productData), $product['article_id']
                );
                if (!json_decode($response)) {
                    echo 'Product with external ID = ' . $product['external_id'] . " was not updated\n";
                }
            } else {
                $productData = array(
                    'name' => $product['products_name'],
                    'taxId' => $product['products_tax_class_id'],
                    'tax' => $product['products_tax_percent'],
                    'supplierId' => $product['cat_manufacturer'],
                    'descriptionLong' => $product['products_description'] . ProductSubscriber::MANUFACTURER_DESCRIPTION,
                    'active' => true,
                    'notification' => true,
                    'categories' => $product['categories'],
                    'mainDetail' => array(
                        'number' => $product['external_id'],
                        'inStock' => $product['stock_count'],
                        'weight' => $product['products_weight'],
                        'active' => true,
                        'prices' => $prices,
                    ),
                    'images' => array(
                        array('link' => $product['products_image_1'])
                    )
                );

                $response = $this->helper->createProduct($this->endpointUrl, $this->userName, $this->apiKey,
                    json_encode($productData)
                );
                if (!json_decode($response)) {
                    echo 'Product with external ID = ' . $product['external_id'] . " was not created\n";
                }
            }

            $createdProductsCount++;
            if ($createdProductsCount % 500 == 0) {
                echo 'Created ' . $createdProductsCount . ' products. ' . ($productsCount - $createdProductsCount) . " left\n";
            }
        }
        $this->productSubscriber->setIsActive(true);

        $executionTime = (microtime(true) - $startTime);
        echo 'Creating products completed in ' . $executionTime . " seconds\n";
    }
}
