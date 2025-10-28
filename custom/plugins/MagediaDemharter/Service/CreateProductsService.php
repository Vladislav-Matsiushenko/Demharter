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

        $productCategoriesData = [];

        $updatedCategoriesData = json_decode(file_get_contents($this->updatedCategoryDataFilePath), true);
        sort($updatedCategoriesData);
        $csvFile = fopen($this->hotspotDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if (isset($updatedCategoriesData[$rowData['categories_id']])) {
                $productCategoriesData[$rowData['products_id']][] = $updatedCategoriesData[$rowData['categories_id']];
            }
        }
        fclose($csvFile);
        unset($updatedCategoriesData);


        $updatedManufacturersData = json_decode(file_get_contents($this->updatedManufacturerDataFilePath), true);
        $updatedProductsData = [];
        $productsData = [];
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
                if (!isset($updatedManufacturersData[trim($rowData['cat_manufacturer'])])) {
                    echo 'Product with ID = ' . $rowData['products_id'] . " has no manufacturer\n";
                    continue;
                }
                $manufacturer = $updatedManufacturersData[trim($rowData['cat_manufacturer'])];
            }

            $rowData['external_id'] = $this->helper->fixExternalId($rowData['external_id']);
            $productDetails = $this->modelManager->getRepository('Shopware\Models\Article\Detail')->findOneBy(['number' => $rowData['external_id']]);
            if ($productDetails) {
                echo 'Product with ID = ' . $rowData['products_id'] . " already exists\n";

                if ($rowData['VK_netto'] !== '') {
                    $productDetails->setPurchasePrice($rowData['VK_netto']);
                    Shopware()->Db()->query("UPDATE s_articles_prices SET price = " . $rowData['VK_netto'] . " WHERE articleID = " . $productDetails->getArticleId() . " AND pricegroup = 'EK';");
                }

                $productDetails->setWeight($rowData['products_weight']);
                $productDetails->setInStock($rowData['stock_count']);

                $article = $productDetails->getArticle();

                if ($article->getName() !== $rowData['products_name']) {
                    $article->setName($rowData['products_name']);
                }
                if ($article->getDescriptionLong() !== $rowData['products_description'] . ProductSubscriber::MANUFACTURER_DESCRIPTION) {
                    $article->setDescriptionLong($rowData['products_description'] . ProductSubscriber::MANUFACTURER_DESCRIPTION);
                }
                if ($article->getSupplier()->getId() !== $manufacturer) {
                    $supplier = $this->modelManager->getRepository('Shopware\Models\Article\Supplier')->find($manufacturer);
                    if ($supplier) {
                        $article->setSupplier($supplier);
                    }
                }


                $validUrl = $rowData['products_image_1'] !== ''
                    && $rowData['products_image_1'] !== 'https://www.dataparts.eu/media/images/org/noimage.gif'
                    && @getimagesize($rowData['products_image_1']);

                $image = $article->getImages()->first();
                if ($validUrl || $image !== false) {
                    if ($validUrl) {
                        $imageName = pathinfo(parse_url($rowData['products_image_1'], PHP_URL_PATH), PATHINFO_FILENAME);
                        $imageName = str_replace('.', '-', $imageName);
                        if (stripos($image === false ? '' : $image->getPath(), $imageName) === false) {
                            $article->getImages()->clear();
                            $productData = array(
                                'images' => array(
                                    array('link' => $rowData['products_image_1'])
                                )
                            );

                            $response = $this->helper->updateProduct($this->endpointUrl, $this->userName, $this->apiKey,
                                json_encode($productData), $productDetails->getArticleId()
                            );

                            if (!json_decode($response)) {
                                echo 'Product with external ID = ' . $rowData['external_id'] . " has not updated image\n";
                            }
                        }
                    } else {
                        $article->getImages()->clear();
                    }
                }


                $articleCategoriesIds = [];
                foreach ($article->getCategories() as $category) {
                    $articleCategoriesIds[] = (string)$category->getId();
                }
                sort($articleCategoriesIds);

                if ($articleCategoriesIds != $productCategoriesData[$rowData['products_id']]) {
                    $article->getCategories()->clear();
                    foreach ($productCategoriesData[$rowData['products_id']] as $categoryId) {
                        $category = $this->modelManager->getRepository('Shopware\Models\Category\Category')->find($categoryId);
                        if ($category) {
                            $article->addCategory($category);
                        }
                    }
                }

                $this->modelManager->persist($productDetails);
                $this->modelManager->persist($article);
                $this->modelManager->flush();

                $updatedProductsData[$rowData['products_id']] = $rowData['external_id'];

                continue;
            }

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
            );

            $updatedProductsData[$rowData['products_id']] = $rowData['external_id'];
        }
        fclose($csvFile);
        unset($updatedManufacturersData);

        file_put_contents($this->updatedProductDataFilePath, json_encode($updatedProductsData));
        unset($updatedProductsData);

        unset($productCategoriesData);

        echo "Update finished.\n";

        $updatedCategoriesData = json_decode(file_get_contents($this->updatedCategoryDataFilePath), true);
        $csvFile = fopen($this->hotspotDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if (isset($productsData[$rowData['products_id']]) && isset($updatedCategoriesData[$rowData['categories_id']])) {
                $productsData[$rowData['products_id']]['categories'][]['id'] = $updatedCategoriesData[$rowData['categories_id']];
            }
        }
        fclose($csvFile);
        unset($updatedCategoriesData);


        $ebayPrices = json_decode(file_get_contents($this->ebayPricesFilePath), true);
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
                $ebayPrice['pseudoprice'] = $ebayPrices[$product['external_id']]['pseudoprice'];
                $ebayPrice['percent'] = $ebayPrices[$product['external_id']]['percent'];

                $prices[] = $ebayPrice;
            }

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
            } else {
                if ($ebayPrice) {
                    Shopware()->Db()->query("UPDATE s_articles_prices SET price = "
                        . $ebayPrices[$product['external_id']]['price']
                        . " WHERE pricegroup = 'Ebay' AND articleID = (SELECT articleID FROM s_articles_details WHERE ordernumber = '"
                        . $product['external_id'] . "')");
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
