<?php

namespace MagediaDemharter\Helper;

class Helper
{
    private const MANUFACTURER_ENTITY = 'manufacturers';
    private const CATEGORY_ENTITY = 'categories';
    private const PRODUCT_ENTITY = 'articles';

    public function getManufacturers($endpointUrl, $userName, $apiKey): array
    {
        return $this->getEntities(self::MANUFACTURER_ENTITY, $endpointUrl, $userName, $apiKey);
    }

    public function getCategories($endpointUrl, $userName, $apiKey): array
    {
        return $this->getEntities(self::CATEGORY_ENTITY, $endpointUrl, $userName, $apiKey);
    }

    private function getEntities($entity, $endpointUrl, $userName, $apiKey): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpointUrl . '/' . $entity . '?limit=100000');
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $userName . ':' . $apiKey);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        $response = curl_exec($ch);

        if(curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
            curl_close($ch);

            return [];
        }

        curl_close($ch);

        return json_decode($response)->data;
    }

    public function createManufacturer($endpointUrl, $userName, $apiKey, $data)
    {
        return $this->createEntity(self::MANUFACTURER_ENTITY, $endpointUrl, $userName, $apiKey, $data);
    }

    public function createCategory($endpointUrl, $userName, $apiKey, $data)
    {
        return $this->createEntity(self::CATEGORY_ENTITY, $endpointUrl, $userName, $apiKey, $data);
    }

    public function createProduct($endpointUrl, $userName, $apiKey, $data)
    {
        return $this->createEntity(self::PRODUCT_ENTITY, $endpointUrl, $userName, $apiKey, $data);
    }

    private function createEntity($entity, $endpointUrl, $userName, $apiKey, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpointUrl . '/' . $entity);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $userName . ':' . $apiKey);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);

        curl_close($ch);

        return $response;
    }

    public function updateProduct($endpointUrl, $userName, $apiKey, $data, $id)
    {
        return $this->updateEntity(self::PRODUCT_ENTITY, $endpointUrl, $userName, $apiKey, $data, $id);
    }

    private function updateEntity($entity, $endpointUrl, $userName, $apiKey, $data, $id)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpointUrl . '/' . $entity . '/' . $id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $userName . ':' . $apiKey);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);

        curl_close($ch);

        return $response;
    }

    public function deleteProduct($endpointUrl, $userName, $apiKey, $data)
    {
        return $this->deleteEntity(self::PRODUCT_ENTITY, $endpointUrl, $userName, $apiKey, $data);
    }

    private function deleteEntity($entity, $endpointUrl, $userName, $apiKey, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpointUrl . '/' . $entity);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $userName . ':' . $apiKey);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);

        curl_close($ch);

        return $response;
    }

    public function getCategoriesTrees($endpointUrl, $userName, $apiKey, $categoryName): array
    {
        $categories = $this->getCategories($endpointUrl, $userName, $apiKey);
        $mainCategoryId = 0;
        foreach ($categories as $category) {
            if ($category->name == $categoryName) {
                $mainCategoryId = $category->id;
                break;
            }
        }

        return $this->buildCategoriesTrees($categories, $mainCategoryId, []);
    }

    public function buildCategoriesTrees($categories, $parentId, $categoriesTree): array
    {
        $categoriesTrees = [];

        $childCategories = [];
        foreach ($categories as $category) {
            if ($category->parentId == $parentId) {
                $childCategories[] = $category;
            }
        }

        if (count($childCategories) > 0) {
            foreach ($childCategories as $childCategory) {
                $newCategoryTree = array_merge($categoriesTree, [$childCategory->name]);
                $childCategoryTree = $this->buildCategoriesTrees($categories, $childCategory->id, $newCategoryTree);

                if (empty($childCategoryTree)) {
                    $categoriesTrees[$childCategory->id] = implode(' => ', $newCategoryTree);
                } else {
                    foreach ($childCategoryTree as $key => $value) {
                        $categoriesTrees[$key] = $value;
                    }
                }

            }
        }

        return $categoriesTrees;
    }

    public function getChildCategories($categoryName)
    {
        $mainCategoryId = 0;
        $result = Shopware()->Db()->query('SELECT * FROM s_categories WHERE description = :value', [
            'value' => $categoryName
        ]);
        foreach ($result as $row) {
            $mainCategoryId = $row['id'];
        }

        $categoryIds = [];
        $this->getChildCategoriesByParentId($mainCategoryId, $categoryIds);

        return $categoryIds;
    }

    public function getChildCategoriesByParentId(int $parentId, array &$categoryIds)
    {
        $categories = Shopware()->Db()->query('SELECT * FROM s_categories WHERE parent = :value', [
            'value' => $parentId
        ]);

        foreach ($categories as $category) {
            $categoryIds[$category['id']] = $category['id'];
            $this->getChildCategoriesByParentId($category['id'], $categoryIds);
        }

        return $categoryIds;
    }

    public function fixExternalId($id): string
    {
        for ($i = 0; $i < strlen($id); $i++){
            if (!preg_match('/^[a-zA-Z0-9-_.]+$/', $id[$i])){
                $id[$i] = '_';
            }
        }

        return $id;
    }
}
