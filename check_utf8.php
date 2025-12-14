<?php
require 'db/db.php';
$result = $pdo->query('SELECT @@character_set_client, @@character_set_connection, @@character_set_results')->fetch(PDO::FETCH_NUM);
echo "✅ Configuration MySQL UTF-8:\n";
echo "   • character_set_client: " . $result[0] . "\n";
echo "   • character_set_connection: " . $result[1] . "\n";
echo "   • character_set_results: " . $result[2] . "\n";
?>
