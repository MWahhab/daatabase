<?php

use database\Database;

putenv("DB_HOST=localhost");
putenv("DB_NAME=*");
putenv("DB_USER=*");
putenv("DB_PASS=*");

require_once ("Database.php");
require_once ("C:\\xampp\htdocs\abstractFactory\Factory.php");
require_once ("C:\\xampp\htdocs\abstractFactory\AbstractTable.php");

try {
    $connection = new Database();
} catch(Exception $e) {
    die($e->getMessage());
}

$factory = new Factory($connection);