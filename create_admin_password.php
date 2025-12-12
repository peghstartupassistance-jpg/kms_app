<?php
// create_admin_password.php
$hash = password_hash('admin123', PASSWORD_DEFAULT);
echo $hash;
