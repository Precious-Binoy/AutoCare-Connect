<?php
require 'config/db.php'; 
$c = getDbConnection(); 
$r = $c->query("SELECT id, name, email, role, is_active FROM users WHERE name LIKE '%PRECIOUS%'");
while($row = $r->fetch_assoc()) {
    print_r($row);
}
?>
