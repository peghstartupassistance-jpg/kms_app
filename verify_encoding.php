<?php
$m = new mysqli('localhost', 'root', '', 'kms_gestion');
$m->set_charset('utf8mb4');
$r = $m->query('SELECT c.nom, t.libelle as type_client FROM clients c LEFT JOIN types_client t ON c.type_client_id = t.id LIMIT 15');
echo "✅ Clients avec encodage UTF-8 correct:\n\n";
while($d = $r->fetch_assoc()) {
    echo $d['nom'] . " - " . $d['type_client'] . "\n";
}
?>

