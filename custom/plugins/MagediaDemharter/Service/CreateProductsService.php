<?php

namespace MagediaDemharter\Service;

class CreateProductsService
{
// Local
    private $ebayPricesFilePath = '/var/www/quad-ersatzteile.loc/EbayPrices.txt';
    private $productsDataCsvFilePath = '/var/www/quad-ersatzteile.loc/ProductsData.csv';
    private $techPartsDataCsvFilePath = '/var/www/quad-ersatzteile.loc/TechPartsData.csv';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $ebayPricesFilePath = '/usr/home/mipzhm/public_html/staging/EbayPrices.txt';
//    private $productsDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/ProductsData.csv';
//    private $techPartsDataCsvFilePath = '/usr/home/mipzhm/public_html/staging/TechPartsData.csv';
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $ebayPricesFilePath = '/usr/home/mipzhm/public_html/EbayPrices.txt';
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

        $productsData = [];
        $manufacturersData = [];
        $categoriesData = [];
        $csvFile = fopen($this->productsDataCsvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            if (!$rowData['products_name']) {
                echo 'Product with ID = ' . $rowData['products_id'] . " has no name\n";
                continue;
            }

            if ($rowData['products_category_tree'] == '' || $rowData['products_category_tree'] == "Artikel noch nicht zugewiesen") {
                echo 'Product with ID = ' . $rowData['products_id'] . " has no category\n";
                continue;
            }

            if (strlen($rowData['external_id']) < 4) {
                echo 'Product with ID = ' . $rowData['products_id'] . " has no external ID\n";
                continue;
            }

            for ($i = 0; $i < strlen($rowData['external_id']); $i++) {
                if (!preg_match('/^[a-zA-Z0-9-_.]+$/', $rowData['external_id'][$i])){
                    $rowData['external_id'][$i] = '_';
                }
            }

            if ($this->modelManager->getRepository('Shopware\Models\Article\Detail')->findOneBy(['number' => $rowData['external_id']])) {
                echo 'Product with ID = '.$rowData['products_id'] . " already exists\n";
                continue;
            }

            $productsData[] = array(
                'external_id' => $rowData['external_id'],
                'products_weight' => $rowData['products_weight'],
                'products_image_1' => $rowData['products_image_1'],
                'products_tax_class_id' => $rowData['products_tax_class_id'],
                'products_tax_percent' => $rowData['products_tax_percent'],
                'products_name' => $rowData['products_name'],
                'products_description' => $rowData['products_description'],
                'cat_manufacturer' => $rowData['cat_manufacturer'],
                'VK_brutto' => $rowData['VK_brutto'],
                'stock_count' => $rowData['stock_count'],
            );

            $manufacturersData[] = $rowData['cat_manufacturer'];

            $categoriesData[] = array(
                'external_id' => $rowData['external_id'],
                'products_category_tree' => $rowData['products_category_tree']
            );
        }
        fclose($csvFile);

        $manufacturersData = array_unique($manufacturersData);
        $manufacturers = $this->helper->getManufacturers($this->endpointUrl, $this->userName, $this->apiKey);
        foreach ($manufacturersData as $name) {
            $manufacturerId = 0;
            foreach ($manufacturers as $manufacturer){
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
        unset($manufacturersData);
        $manufacturers = $this->helper->getManufacturers($this->endpointUrl, $this->userName, $this->apiKey);

        $ebayPrices = json_decode(file_get_contents($this->ebayPricesFilePath), true);

        $categoriesTrees = $this->helper->getCategoriesTrees($this->endpointUrl, $this->userName, $this->apiKey, $this->categoryName);

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

            for ($i = 0; $i < strlen($rowData['external_id']); $i++){
                if (!preg_match('/^[a-zA-Z0-9-_.]+$/', $rowData['external_id'][$i])){
                    $rowData['external_id'][$i] = '_';
                }
            }

            $categoriesData[] =  array(
                'external_id' => $rowData['external_id'],
                'products_category_tree' => $rowData['products_category_tree']
            );
        }
        fclose($csvFile);

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

        $productsCount = count($productsData);
        $createdProductsCount = 0;
        foreach($productsData as $product) {
            $manufacturerId = 0;
            foreach ($manufacturers as $manufacturer) {
                if ($product['cat_manufacturer'] == $manufacturer->name){
                    $manufacturerId = $manufacturer->id;
                    break;
                }
            }

            if ($manufacturerId == 0 || !isset($productCategories[$product['external_id']])) {
                echo 'Product with External ID = ' . $product['external_id'] . " has wrong manufacturer or category\n";
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
                'supplierId' => $manufacturerId,
                'descriptionLong' => $product['products_description'],
                'active' => true,
                'categories' => $productCategories[$product['external_id']],
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
                echo 'Product with External ID = ' . $product['external_id'] . '; Name = ' . $product['products_name'] . '; Tax ID = ' . $product['products_tax_class_id'] . " was not created\n";
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

        $executionTime = (microtime(true) - $startTime);
        echo 'Creating products completed in ' . $executionTime . " seconds\n";
    }
}
