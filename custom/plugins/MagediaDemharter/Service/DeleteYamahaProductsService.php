<?php

namespace MagediaDemharter\Service;

class DeleteYamahaProductsService
{
// Local
    private $yamahaProductDataFilePath = '/var/www/quad-ersatzteile.loc/files/demharter/Yamaha/ProductData';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';

// Staging
//    private $yamahaProductDataFilePath = '/usr/home/mipzhm/public_html/staging/files/demharter/Yamaha/ProductData';
//    private $endpointUrl = 'http://staging.quad-ersatzteile.com/api';

// Live
//    private $yamahaProductDataFilePath = '/usr/home/mipzhm/public_html/files/demharter/Yamaha/ProductData';
//    private $endpointUrl = 'https://www.quad-ersatzteile.com/api';
    private $manufacturerName = 'Yamaha';
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

//        $filesCount = 0;
//        $xml = simplexml_load_file('/var/www/quad-ersatzteile.loc/files/demharter/YamahaProductData.xml');
//        foreach ($xml->articlesInStock->article as $article) {
//            if (strtolower($this->manufacturerName) === strtolower($article->_supplier)) {
//                    $orderNumbers[] = (string)$article->ordernumber;
//
//                    if (count($orderNumbers) >= 10000) {
//                        $filesCount++;
//                        file_put_contents($this->yamahaProductDataFilePath . $filesCount . '.txt', json_encode($orderNumbers));
//                        $orderNumbers = [];
//                    }
//            } else {
//                echo 'Product with order number = ' . $article->ordernumber . ' has manufacturer = ' . $article->_supplier . "\n";
//            }
//        }
//
//        return;

        for ($i = 1; $i <= 47; $i++) {
            $orderNumbers = json_decode(file_get_contents($this->yamahaProductDataFilePath . $i . '.txt'));
            $this->deleteProducts($orderNumbers);
            echo 'Deleted ' . (10000 * $i) . " products\n";
        }

        $executionTime = (microtime(true) - $startTime);
        echo 'Deleting yamaha products completed in ' . $executionTime . " seconds\n";
    }

    private function deleteProducts($orderNumbers)
    {
        $productIds = [];
        $productIdsForHotspots =[];
        $result = Shopware()->Db()->query("SELECT articleID FROM s_articles_details WHERE ordernumber IN ('" . implode("','", $orderNumbers) . "')");
        foreach ($result as $row) {
            $productIds[] = array('id' => $row['articleID']);
            $productIdsForHotspots[] = $row['articleID'];

            if (count($productIds) >= 500) {
                $this->helper->deleteProduct($this->endpointUrl, $this->userName, $this->apiKey,
                    json_encode($productIds)
                );
                $productIds = [];
            }
        }

        if (count($productIds) > 0) {
            $this->helper->deleteProduct($this->endpointUrl, $this->userName, $this->apiKey,
                json_encode($productIds)
            );
        }

        Shopware()->Db()->query("DELETE FROM s_articles_details WHERE ordernumber IN ('" . implode("','", $orderNumbers) . "')");
        Shopware()->Db()->query("DELETE FROM pk_explosion_chart_articles WHERE articleID IN ('" . implode("','", $productIdsForHotspots) . "')");
    }
}
