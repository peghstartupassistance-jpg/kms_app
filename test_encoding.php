<?php
// test_encoding.php - Tester l'encodage UTF-8

header('Content-Type: text/html; charset=UTF-8');

$mysqli = new mysqli('localhost', 'root', '', 'kms_gestion');
$mysqli->set_charset('utf8mb4');

echo "<!DOCTYPE html>\n";
echo "<html><head><meta charset='UTF-8'></head><body>\n";
echo "<h2>Test d'encodage UTF-8</h2>\n";

$result = $mysqli->query("SELECT nom, type_client FROM clients LIMIT 10");

echo "<table border='1'>\n";
echo "<tr><th>Nom</th><th>Type</th></tr>\n";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['nom'], ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['type_client'], ENT_QUOTES, 'UTF-8') . "</td>";
    echo "</tr>\n";
}
echo "</table>\n";

echo "</body></html>";
$mysqli->close();
?>
