<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../App/Models/Config/bootstrap.php';