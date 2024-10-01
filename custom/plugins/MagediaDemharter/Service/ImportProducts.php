<?php

namespace MagediaDemharter\Service;

use Doctrine\ORM\ORMException;

class ImportProducts
{
    private $csvFilePath = '/var/www/quad-ersatzteile.loc/TEST.csv';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';
//    private $csvFilePath = '/usr/home/mipzhm/public_html/Teileexport-grp-3.csv';
//    private $endpointUrl = 'https://www.quad-ersatzteile.com/api';
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

    public function import()
    {
        //$this->downloadTeileexportFile();
        $csvFile = fopen($this->csvFilePath, 'r');
        $headers = fgetcsv($csvFile, 0, ';');
        $data = [];
        while ($row = fgetcsv($csvFile, 0, ';')) {
            $rowData = array_combine($headers, $row);
            $data[] = $rowData;
        }
        fclose($csvFile);


        $updatedProductsCount = 0;
//        foreach ($data as $row) {
//            $sku = $row['external_id'];
//            $productExists = $this->checkUniqueProducts($sku);
//            if (!$productExists) {
//                continue;
//            } else {
//                $this->updateV2($productExists, $row);
//                $updatedProductsCount++;
//            }
//            if ($updatedProductsCount % 1000 == 0){
//                echo "Updated {$updatedProductsCount} products\n";
//            }
//        }
//        echo "{$updatedProductsCount} already existing products were updated!";
//        echo "\n";
//        echo 'Import complete';
        foreach ($data as $row) {
            $sku = $row['external_id'];
            $productExists = $this->checkUniqueProducts($sku);
            if (!$productExists) {
                continue;
            } else {
		$this->updateProducts($productExists, $row);
                $updatedProductsCount++;
            }
            if ($updatedProductsCount % 10000 == 0){
                echo "Updated {$updatedProductsCount} products\n";
                $this->modelManager->flush();
            }
        }
        $this->modelManager->flush();
        echo "{$updatedProductsCount} already existing products were updated!";
        echo "\n";
        echo 'Import complete';

    }

    private function checkUniqueProducts($sku)
    {
        $productExists = $this->modelManager
            ->getRepository('Shopware\Models\Article\Detail')
            ->findOneBy(['number' => $sku]);

        return $productExists;
    }

    public function updateProducts($product, $row)
    {
        $articleId = $product->getArticleId();
        $product->setPurchasePrice($row['VK_netto']);
        $product->setInStock($row['stock_count']);

        $this->modelManager->persist($product);
        
        // Prevent ebay price update
        Shopware()->Db()->query("UPDATE s_articles_prices SET price = " . $row['VK_netto'] . " WHERE articleID = " . $articleId . " AND pricegroup = 'EK';");
        //$price = $this->modelManager->getRepository('Shopware\Models\Article\Price')->findOneBy(['articleId' => $articleId]);
        //$price->setPrice($row['VK_netto']);

        //$this->modelManager->persist($price);
    }


    private function updateV2($product, $row)
    {
        $articleId = $product->getArticleId();
        $productData = array(
            'mainDetail' => array(
                'inStock' => $row['stock_count'],
                'prices' => array(
                    array(
                        'customerGroupKey' => "EK",
                        'price' => $row['VK_brutto'],
                    )
                )
            )
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpointUrl . '/articles/' . $articleId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($productData));
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->userName:$this->apiKey");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);

//        if (curl_errno($ch)) {
//            echo 'Error: ' . curl_error($ch);
//        } else {
//            // Product created successfully
//            echo 'Product updated successfully!';
//        }

        curl_close($ch);
    }

    public function downloadTeileexportFile()
    {
        $filePath = '/usr/home/mipzhm/public_html/Teileexport-grp-3.csv';
//        $filePath = '/var/www/quad.loc/public_html/Teileexport-grp-3.csv';
        //$fileUrl = 'https://www.dataparts.eu/media/files_public/082d903fd40773ff462c5a5ddbddd0fe_file/Teileexport-grp-3.csv';
	$fileUrl = 'https://www.dataparts.eu/media/files_public/b06c2426688d447a44d22e6c13e1ed6e_file/Teileexport-grp-3.csv';
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        $fileContent = file_get_contents($fileUrl);
        if ($fileContent !== false) {
            file_put_contents($filePath, $fileContent);
            echo 'The file was successfully downloaded and saved to the following path: ' . $filePath;
            echo "\n";
        } else {
            echo 'Error while downloading the file';
        }
    }
}
