<?php

// Staging
$username = 'mipzhm_4';
$password = 'CvRfruLrgW7qfJUX';
$dbname = 'mipzhm_db4';

// Live
//$username = 'dedirxpkn_1';
//$password = 'MW52Cyhm3ckULuRZ';
//$dbname = 'dedirxpkn_db1';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8mb4", $username, $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (\PDOException $e) {
    die("DB error: " . $e->getMessage());
}


$sql = "
UPDATE s_articles a
JOIN s_articles_supplier s ON a.supplierID = s.id
SET a.notification = 0
WHERE s.name = 'CAN-AM';
";
$stmt = $pdo->prepare($sql);
$stmt->execute();
echo "Updated CAN-AM products.\n";


$categoryName = "Zubehör";
setNotification($pdo, $categoryName);

$categoryName = "Bekleidung";
setNotification($pdo, $categoryName);

$categoryName = "Ersatzteile";
$parentId = setNotification($pdo, $categoryName);

$categoryName = "Can Am Ersatzteile";
setNotification($pdo, $categoryName, $parentId, 0);


function setNotification($pdo, $categoryName, $parentId = 3, $value = 1)
{
    $sql = "SELECT id FROM s_categories WHERE description = :description AND parent = :parent";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['description' => $categoryName, 'parent' => $parentId]);
    $categoryId = (int)$stmt->fetch()["id"];

    $sql = "
    UPDATE s_articles a
    JOIN s_articles_details ad ON a.id = ad.articleID
    JOIN s_articles_categories ac ON a.id = ac.articleID
    SET a.notification = :value1, a.laststock = :value2, ad.laststock = :value3
    WHERE ac.categoryID IN (
        SELECT id FROM (
        WITH RECURSIVE category_tree AS (
            SELECT id FROM s_categories WHERE id = :id
                UNION ALL
                SELECT c.id FROM s_categories c
                JOIN category_tree ct ON c.parent = ct.id
            )
            SELECT id FROM category_tree
        ) AS subquery
    );
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $categoryId, 'value1' => $value, 'value2' => $value, 'value3' => $value]);
    echo "Updated $categoryName products.\n";

    return $categoryId;
}
