<?php
require 'config/db.php'; 
$c = getDbConnection(); 
$r = $c->query("SELECT id, is_read, title FROM notifications WHERE user_id = 20 ORDER BY id DESC LIMIT 5");
while($row = $r->fetch_assoc()) {
    print_r($row);
}
?>
