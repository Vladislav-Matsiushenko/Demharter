<?php

namespace MagediaDemharter\Service;

class DeleteCategoriesAndProducts
{
    private $ebayPricesFilePath = '/var/www/quad-ersatzteile.loc/EbayPrices.txt';
    private $endpointUrl = 'http://quad-ersatzteile.loc/api';
//    private $ebayPricesFilePath = '/usr/home/mipzhm/public_html/EbayPrices.txt';
//    private $endpointUrl = 'https://www.quad-ersatzteile.com/api';
    private $userName = 'schwab';
    private $apiKey = 'pdw4kVus56U9IcFaKuHKv7QFQABtKeG20ub5rAh3';
    private $modelManager;
    private $dbalConnection;

    private $categoryIds = [];
    private $productIds = [];

    public function __construct()
    {
        ini_set('memory_limit', '-1');
        $this->modelManager = Shopware()->Container()->get('models');
        $this->dbalConnection = Shopware()->Container()->get('dbal_connection');
    }

    public function deleteCategoriesAndProducts($category = null)
    {
        if ($category) {
            echo "Deleting from category with id {$category}\n";
        }

        $this->getChildren((int)$category ?? $this->getBaseParentIdByName('Roller Ersatzteile'));
        $this->productIds = $this->getProductsIdsByCategoryIds($this->categoryIds);

        $productData = [];
        $ebayPrices = [];
        $countProductsNeedDelete = count($this->productIds);
        foreach ($this->productIds as $id) {

            $orderNumber = null;
            $result = Shopware()->Db()->query("SELECT * FROM s_articles_details WHERE articleID = " . $id);
            foreach ($result as $rowOrderNumber) {
                $orderNumber = $rowOrderNumber['ordernumber'];
            }

            $result = Shopware()->Db()->query("SELECT * FROM s_articles_prices WHERE pricegroup = 'Ebay' AND articleID = " . $id);
            foreach ($result as $rowEbayPrice) {
                $ebayPrices[$orderNumber] = [
                    'price' => $rowEbayPrice['price'],
                    'pseudoprice' => $rowEbayPrice['pseudoprice'],
                    'percent' => $rowEbayPrice['percent']
                ];
            }

            array_push($productData, array('id' => $id));

            if (count($productData) >= 500 || $countProductsNeedDelete < 500) {
                $time_start = microtime(true);

                $countProductsNeedDelete -= count($productData);
                $this->deleteProducts($productData, $countProductsNeedDelete);
                $productData = [];

                $time_end = microtime(true);
                $execution_time = ($time_end - $time_start);

                echo 'Total execution time for remove 500 products: '.$execution_time.' sec';
                echo "\n";
            }
        }

        file_put_contents($this->ebayPricesFilePath, json_encode($ebayPrices));

        $this->deleteCategoriesByIds($this->categoryIds);
    }

    /**
     * @param int $parentId
     * @return array
     * @throws \Zend_Db_Adapter_Exception
     */
    function getChildren(int $parentId)
    {
        $sql = 'Select * from s_categories where parent = :value';
        $categories = Shopware()->Db()->query($sql, [
            'value' => $parentId
        ]);

        $children = [];
        $i = 0;
        foreach ($categories as $key => $catValue) {
            $this->categoryIds[$catValue['id']] = $catValue['id'];

            $children[$i] = [];
            $children[$i]['description'] = $catValue['description'];
            $children[$i]['id'] = $catValue['id'];
            $children[$i]['children'] = $this->getChildren($catValue['id']);
            $i++;
        }

        return $children;
    }

    /**
     * @param array $categoryIds
     * @return array
     * @throws \Zend_Db_Adapter_Exception
     */
    function getProductsIdsByCategoryIds(array $categoryIds)
    {
        $productIds = [];

        $result = Shopware()->Db()->query("SELECT * FROM s_articles_categories WHERE categoryID IN ('" . implode("','", $categoryIds) . "')");

        foreach ($result as $row) {
            $productIds[$row['articleID']] = $row['articleID'];
        }

        return $productIds;
    }

    /**
     * @param string $description
     * @return int|mixed
     * @throws \Zend_Db_Adapter_Exception
     */
    function getBaseParentIdByName(string $description)
    {
        $sql = 'SELECT * FROM s_categories where description = :value';
        $rows = Shopware()->Db()->query($sql, [
            'value' => $description
        ]);

        foreach ($rows as $row) {
            return $row['parent'];
        }

        return 0;
    }

    /**
     * @param $ids
     * @return void
     * @throws \Zend_Db_Adapter_Exception
     */
    function deleteCategoriesByIds($ids)
    {
        Shopware()->Db()->query("DELETE FROM s_categories WHERE id IN ('" . implode("','", $ids) . "')");

        echo count($ids) . ' categories deleted!';
        echo "\n";
    }

    /**
     * @param array $productData
     * @param int $needDelete
     * @return void
     */
    function deleteProducts(array $productData, int $needDelete)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpointUrl . '/articles');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($productData));
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->userName:$this->apiKey");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Error: ' . curl_error($ch);
        } else {
            echo '500 product deleted successfully! There is still time to delete: ' . $needDelete;
            echo "\n";
        }

        curl_close($ch);
    }
}
