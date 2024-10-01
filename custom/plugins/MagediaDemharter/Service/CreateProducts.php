<?php

namespace MagediaDemharter\Service;

use Doctrine\ORM\ORMException;

class CreateProducts
{
    private $logFilePath = '/var/www/quad-ersatzteile.loc/NotCreatedProducts.txt';
    private $ebayPricesFilePath = '/var/www/quad-ersatzteile.loc/EbayPrices.txt';
    private $csvFilePath = '/var/www/quad-ersatzteile.loc/Teileexport-grp-3.csv';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';
//    private $logFilePath = '/usr/home/mipzhm/public_html/NotCreatedProducts.txt';
//    private $ebayPricesFilePath = '/usr/home/mipzhm/public_html/EbayPrices.txt';
//    private $csvFilePath = '/usr/home/mipzhm/public_html/Teileexport-grp-3.csv';
//    private $endpointUrl = 'https://www.quad-ersatzteile.com/api';
    private $userName = 'schwab';
    private $apiKey = 'pdw4kVus56U9IcFaKuHKv7QFQABtKeG20ub5rAh3';
    private $modelManager;
    private $dbalConnection;
    private $ebayPrices;
    private $categoryId = null;
    private $categoryName = '';

    public function __construct()
    {
        ini_set('memory_limit', '-1');
        $this->modelManager = Shopware()->Container()->get('models');
        $this->dbalConnection = Shopware()->Container()->get('dbal_connection');
    }

    public function create($category = null, $updateQty = null)
    {
        file_put_contents($this->logFilePath, '');

        if ($category) {
            $result = Shopware()->Db()->query("SELECT * FROM s_categories WHERE id = " . $category);
            foreach ($result as $row) {
                $this->categoryName = $row['description'];
            }
            echo "Importing into category {$this->categoryName}\n";
        }

        if ($updateQty) {
            echo "Updating qty\n";
        }

        $this->downloadTeileexportFile();
        $csvFile = fopen($this->csvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        $data = [];
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            $data[] = $rowData;
        }
        fclose($csvFile);

        if (!$updateQty) {
            $this->ebayPrices = json_decode(file_get_contents($this->ebayPricesFilePath), true);
        }

        $charactersForSku = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersForSkuLength = strlen($charactersForSku);

        $createdProductsCount = 0;
        foreach ($data as $row) {
            if (!$row['products_name']){
                $logMessage = 'Product with ID = '.$row['products_id']." has no name!\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            if ($row['products_category_tree'] == '' || $row['products_category_tree'] == "Artikel noch nicht zugewiesen") {
                $logMessage = 'Product with ID = '.$row['products_id']." has no category\n";
                echo $logMessage;
                file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                continue;
            }

            if (strlen($row['external_id']) < 4){
                $row['external_id'] = 'dataparts-';
                for ($i = 0; $i < 6; $i++){
                    $row['external_id'] .= $charactersForSku[random_int(0, $charactersForSkuLength - 1)];
                }
                $row['external_id'] .= '-'.$row['products_id'];
            }

            for ($i = 0; $i < strlen($row['external_id']); $i++){
                if (!preg_match('/^[a-zA-Z0-9-_.]+$/', $row['external_id'][$i])){
                    $row['external_id'][$i] = '_';
                }
            }

            $sku = $row['external_id'];
            $productExists = $this->checkUniqueProducts($sku);
            if ($productExists) {
                if ($updateQty) {
                    $productExists->setInStock($row['stock_count']);
                    $this->modelManager->persist($productExists);

                    $createdProductsCount++;
                    if ($createdProductsCount % 100 == 0) {
                        echo "Updated {$createdProductsCount} products\n";
                    }
                } else {
                    $logMessage = 'Product with ID = '.$row['products_id']." in not unique!\n";
                    echo $logMessage;
                    file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
                }
            } else {
                if (!$updateQty) {
                    $this->createProduct($row);
                    $createdProductsCount++;

                    if ($createdProductsCount % 100 == 0) {
                        echo "Created {$createdProductsCount} products\n";
                    }
                }
            }
        }

        if ($updateQty) {
            $this->modelManager->flush();
            echo "{$createdProductsCount} products were updated!\nUpdating completed";
        } else {
            echo "{$createdProductsCount} products were created!\nCreating completed";
        }
    }

    private function checkUniqueProducts($sku)
    {
        $productExists = $this->modelManager
            ->getRepository('Shopware\Models\Article\Detail')
            ->findOneBy(['number' => $sku]);

        return $productExists;
    }

    public function createProduct($row)
    {
        $manufacturersList = json_decode($this->getManufacturersList());
        $supplierId = $this->findOrCreateManufacturer($manufacturersList, $row);

        $categoriesList = json_decode($this->getCategoriesList());
        $categoryPath = $this->processString($this->categoryName ?
            $this->categoryName . ' => ' . $row['products_category_tree'] :
            $row['products_category_tree']
        );
        $this->categoryId = $this->findOrCreateCategory($categoriesList, $categoryPath);

        if ($row['products_image_1']){
            if ($row['products_image_1'] == 'https://www.dataparts.eu/media/images/org/noimage.gif'
                || !@getimagesize($row['products_image_1'])){
                $row['products_image_1'] = null;
            }
        }

        echo "CAT ID: " . $this->categoryId . "; SUPP ID: " . $supplierId . "; SKU: " . $row['products_model'] . "\n";

        $prices = array(
            array(
                'customerGroupKey' => "EK",
                'price' => $row['VK_brutto'],
            )
        );

        $ebayPrice = null;
        if (isset($this->ebayPrices[$row['external_id']])) {
            $ebayPrice['customerGroupKey'] = 'Ebay';
            $ebayPrice['pseudoprice'] = $this->ebayPrices[$row['external_id']]['pseudoprice'];
            $ebayPrice['percent'] = $this->ebayPrices[$row['external_id']]['percent'];

            $prices[] = $ebayPrice;
        }

        $productData = array(
            'name' => $row['products_name'],
            'taxId' => $row['products_tax_class_id'],
            'tax' => $row['products_tax_percent'],
            'supplierId' => $supplierId,
            'descriptionLong' => $row['products_description'],
            'active' => true,
            'categories' => array(
                array('id' => $this->categoryId)
            ),
            'mainDetail' => array(
                'number' => $row['external_id'],
                'inStock' => $row['stock_count'],
                'weight' => $row['products_weight'],
                'active' => true,
                'prices' => $prices,
            ),
            'images' => array(
                array('link' => $row['products_image_1'])
            )
        );


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpointUrl . '/articles');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($productData));
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->userName:$this->apiKey");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);

        if (!json_decode($response)) {
            $logMessage = 'Product with ID = '.$row['products_id'].'; External ID = '.$row['external_id'].'; Name = '.$row['products_name'].'; Tax ID = '.$row['products_tax_class_id']." was not created!\n";
            echo $logMessage;
            file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
        }
        elseif (curl_errno($ch)) {
            echo 'Error: ' . curl_error($ch);
        } else {
            if ($ebayPrice) {
                Shopware()->Db()->query("UPDATE s_articles_prices SET price = " . $this->ebayPrices[$row['external_id']]['price'] . " WHERE pricegroup = 'Ebay' AND articleID = 
                    (SELECT articleID FROM s_articles_details WHERE ordernumber = '" . $row['external_id'] . "')");
            }

            echo 'Product created successfully!';
            echo "\n";
        }

        curl_close($ch);
    }

    private function getManufacturersList()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpointUrl . '/manufacturers?limit=30000');
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->userName:$this->apiKey");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        $response = curl_exec($ch);

        if(curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
        }

        curl_close($ch);

        return $response;
    }

    private function getCategoriesList()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpointUrl . '/categories?limit=30000');
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->userName:$this->apiKey");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        $response = curl_exec($ch);

        if(curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
        }

        curl_close($ch);

        return $response;
    }

    private function createManufacturer($name)
    {
        $manufacturerData = array(
            'name' => $name,
            'image' => ""
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpointUrl . '/manufacturers');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($manufacturerData));
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->userName:$this->apiKey");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);

        if(curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
        }

        curl_close($ch);

        return $response;
    }

    private function createCategory($name, $id)
    {
        $categoryData = array(
            'name' => $name,
            'parentId' => $id
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpointUrl . '/categories');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($categoryData));
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->userName:$this->apiKey");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);

        if(curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
        }

        curl_close($ch);

        return $response;
    }

    public function findOrCreateManufacturer($manufacturersList, $row)
    {
        $supplierId = 0;
        $manufacturers = $manufacturersList->data;
        foreach ($manufacturers as $item){
            if ($row['cat_manufacturer'] == $item->name){
                $supplierId = $item->id;
                break;
            }
        }

        if ($supplierId == 0){
            $response = json_decode($this->createManufacturer($row['cat_manufacturer']));
            $supplierId = $response->data->id;
        }

        return $supplierId;
    }

    public function findOrCreateCategory($categoriesList, $categoryPath)
    {
        $categoryId = 0;
        $arrId = 0;
        $categories = $categoriesList->data;
        $foundInInnerLoop = false;
        $firstIteration = true;
        foreach ($categoryPath as $item){
            foreach ($categories as $category) {
                if ($item == $category->name && ($firstIteration || $category->parentId == $categoryId)) {
                    $arrId++;
                    $categoryId = $category->id;
                    $foundInInnerLoop = true;
                    break;
                }
            }
            if (!$foundInInnerLoop) {
                $categoryId = 11833;
                break;
            }
            $firstIteration = false;
        }

        for ($i = $arrId; $i < count($categoryPath); $i++) {
            $response = json_decode($this->createCategory($categoryPath[$i], $categoryId));
            $categoryId = $response->data->id;
            $arrId = $i + 1;
        }

        return $categoryId;
    }

    public function processString($inputString) {
        $elements = explode("=>", $inputString);
        $result = array_map('trim', $elements);
        return $result;
    }

    public function downloadTeileexportFile()
    {
//        $filePath = '/usr/home/mipzhm/public_html/Teileexport-grp-3.csv';
        $filePath = '/var/www/quad-ersatzteile.loc/Teileexport-grp-3.csv';
        $fileUrl = 'https://www.dataparts.eu/media/files_public/082d903fd40773ff462c5a5ddbddd0fe_file/Teileexport-grp-3.csv';
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        $fileContent = file_get_contents('https://www.dataparts.eu/media/files_public/b06c2426688d447a44d22e6c13e1ed6e_file/Teileexport-grp-3.csv');
        if ($fileContent !== false) {
            file_put_contents($filePath, $fileContent);
            echo 'The file was successfully downloaded and saved to the following path: ' . $filePath;
            echo "\n";
        } else {
            echo 'Error while downloading the file';
        }
    }
}
