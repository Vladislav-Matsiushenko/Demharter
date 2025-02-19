<?php

namespace MagediaDemharter\Service;

use MagediaDemharter\Subscriber\ProductSubscriber;

class CreateProductsService
{
// Local
    private $ebayPricesFilePath = '/var/www/quad-ersatzteile.loc/EbayPrices.txt';
    private $productsDataCsvFilePath = '/var/www/quad-ersatzteile.loc/ProductsData.csv';
    private $techPartsDataCsvFilePath = '/var/www/quad-ersatzteile.loc/TechPartsData.csv';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';
    private $categoriesTreesFilePath = '/var/www/quad-ersatzteile.loc/CategoriesTrees.txt';

// Staging
//    private $ebayPricesFilePath = '/usr/home/mipzhm/public_html/staging/EbayPrices.txt';
//    private $productsDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/ProductsData.csv';
//    private $techPartsDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/TechPartsData.csv';
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';
//    private $categoriesTreesFilePath = '/usr/home/mipzhm/public_html/staging/CategoriesTrees.txt';

// Live
//    private $ebayPricesFilePath = '/usr/home/mipzhm/public_html/EbayPrices.txt';
//    private $productsDataCsvFilePath = '/usr/home/mipzhm/public_html/ProductsData.csv';
//    private $techPartsDataCsvFilePath = '/usr/home/mipzhm/public_html/TechPartsData.csv';
//    private $endpointUrl = 'https://www.quad-ersatzteile.com/api';
//    private $categoriesTreesFilePath = '/usr/home/mipzhm/public_html/CategoriesTrees.txt';
    private $userName = 'schwab';
    private $apiKey = 'pdw4kVus56U9IcFaKuHKv7QFQABtKeG20ub5rAh3';
    private $helper;
    private $modelManager;
    private $dbalConnection;
    private $productSubscriber;

    public function __construct(ProductSubscriber $productSubscriber)
    {
        $this->helper = Shopware()->Container()->get('magedia_demharter.helper');
        ini_set('memory_limit', '-1');
        $this->modelManager = Shopware()->Container()->get('models');
        $this->dbalConnection = Shopware()->Container()->get('dbal_connection');
        $this->productSubscriber = $productSubscriber;
    }

    public function execute()
    {
        $startTime = microtime(true);

        $this->productSubscriber->setIsActive(false);

        $productsData = [];
        $manufacturersData = [];
        $categoriesData = [];
        $csvFile = fopen($this->productsDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if ($rowData['products_category_tree'] == "Artikel noch nicht zugewiesen") {
                echo 'Product with ID = ' . $rowData['products_id'] . " has no category\n";
                continue;
            }

            if (!$rowData['products_name']) {
                echo 'Product with ID = ' . $rowData['products_id'] . " has no name\n";
                continue;
            }

            if (strlen($rowData['external_id']) < 4) {
                echo 'Product with ID = ' . $rowData['products_id'] . " has no external ID\n";
                continue;
            }

            $rowData['external_id'] = $this->helper->fixExternalId($rowData['external_id']);
            if ($this->modelManager->getRepository('Shopware\Models\Article\Detail')->findOneBy(['number' => $rowData['external_id']])) {
                echo 'Product with ID = ' . $rowData['products_id'] . " already exists\n";
                continue;
            }

            $productsData[$rowData['external_id']] = array(
                'products_weight' => $rowData['products_weight'],
                'products_image_1' => $rowData['products_image_1'],
                'products_tax_class_id' => $rowData['products_tax_class_id'],
                'products_tax_percent' => $rowData['products_tax_percent'],
                'products_name' => $rowData['products_name'],
                'products_description' => $rowData['products_description'],
                'VK_brutto' => $rowData['VK_brutto'],
                'stock_count' => $rowData['stock_count'],
            );

            if ($rowData['products_category_tree'] != '') {
                $manufacturersData[$rowData['external_id']] = $rowData['cat_manufacturer'];

                $categoriesData[] = array(
                    'external_id' => $rowData['external_id'],
                    'products_category_tree' => $rowData['products_category_tree']
                );
            }
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

            if (strlen($rowData['external_id']) < 4){
                echo 'Product with ID = ' . $rowData['products_id'] . ' linked with category with ID = ' . $rowData['categories_id'] . " has no external ID\n";
                continue;
            }

            $rowData['external_id'] = $this->helper->fixExternalId($rowData['external_id']);

            if (!isset($productsData[$rowData['external_id']])) {
                continue;
            }

            if (!isset($manufacturersData[$rowData['external_id']])) {
                $manufacturersData[$rowData['external_id']] = trim(explode('=>', $rowData['products_category_tree'])[0]);
            }

            $categoriesData[] =  array(
                'external_id' => $rowData['external_id'],
                'products_category_tree' => $rowData['products_category_tree']
            );
        }
        fclose($csvFile);


        $manufacturers = array_unique($manufacturersData);
        $existingManufacturers = $this->helper->getManufacturers($this->endpointUrl, $this->userName, $this->apiKey);
        foreach ($manufacturers as $name) {
            $manufacturerId = 0;
            foreach ($existingManufacturers as $manufacturer){
                if ($name == $manufacturer->name){
                    $manufacturerId = $manufacturer->id;
                    break;
                }
            }

            if ($manufacturerId == 0) {
                $this->helper->createManufacturer($this->endpointUrl, $this->userName, $this->apiKey,
                    json_encode(array('name' => $name, 'image' => ''))
                );
            }
        }
        unset($existingManufacturers);

        $manufacturers = $this->helper->getManufacturers($this->endpointUrl, $this->userName, $this->apiKey);
        foreach ($manufacturersData as $key => $name) {
            foreach ($manufacturers as $manufacturer) {
                if ($name == $manufacturer->name){
                    $manufacturersData[$key] = $manufacturer->id;
                    break;
                }
            }
        }
        unset($manufacturers);


        $categoriesTrees = json_decode(file_get_contents($this->categoriesTreesFilePath), true);
        $productCategories = [];
        foreach ($categoriesData as $categoryData) {
            $categoryTree = explode('=>', $categoryData['products_category_tree']);
            $categoryTree = array_map('trim', $categoryTree);
            $categoryTree = implode(' => ', $categoryTree);

            $categoryId = array_search($categoryTree, $categoriesTrees);
            if ($categoryId !== false) {
                $productCategories[$categoryData['external_id']][]['id'] = $categoryId;
            }
        }
        unset($categoriesTrees);
        unset($categoriesData);


        $ebayPrices = json_decode(file_get_contents($this->ebayPricesFilePath), true);
        $productsCount = count($productsData);
        $createdProductsCount = 0;
        foreach($productsData as $productNumber => $product) {
            if (!isset($productCategories[$productNumber])) {
                echo 'Product with external ID = ' . $productNumber . " has wrong category\n";
                continue;
            }
            if (!isset($manufacturersData[$productNumber])) {
                echo 'Product with external ID = ' . $productNumber . " has wrong manufacturer\n";
                continue;
            }

            if ($product['products_image_1']){
                if ($product['products_image_1'] == 'https://www.dataparts.eu/media/images/org/noimage.gif'
                    || !@getimagesize($product['products_image_1'])){
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
            if (isset($ebayPrices[$productNumber])) {
                $ebayPrice['customerGroupKey'] = 'Ebay';
                $ebayPrice['pseudoprice'] = $ebayPrices[$productNumber]['pseudoprice'];
                $ebayPrice['percent'] = $ebayPrices[$productNumber]['percent'];

                $prices[] = $ebayPrice;
            }

            $productData = array(
                'name' => $product['products_name'],
                'taxId' => $product['products_tax_class_id'],
                'tax' => $product['products_tax_percent'],
                'supplierId' => $manufacturersData[$productNumber],
                'descriptionLong' => $product['products_description'] . ProductSubscriber::MANUFACTURER_DESCRIPTION,
                'active' => true,
                'categories' => $productCategories[$productNumber],
                'mainDetail' => array(
                    'number' => $productNumber,
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
                echo 'Product with external ID = ' . $productNumber . '; Name = ' . $product['products_name'] . '; Tax ID = ' . $product['products_tax_class_id'] . " was not created\n";
            } else {
                if ($ebayPrice) {
                    Shopware()->Db()->query("UPDATE s_articles_prices SET price = "
                        . $ebayPrices[$productNumber]['price']
                        . " WHERE pricegroup = 'Ebay' AND articleID = (SELECT articleID FROM s_articles_details WHERE ordernumber = '"
                        . $productNumber . "')");
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
