<?php
require_once "config.php";
$type = $_POST['type'];
$value = trim($_POST['value']);

if(!$value){ echo json_encode(["status"=>"error","message"=>"Empty value"]); exit; }

$table = ($type=="role") ? "roles" : (($type=="position") ? "positions" : "departments");
$conn->query("INSERT INTO $table (name) VALUES ('$value')");
echo json_encode(["status"=>"success","message"=>"$type added successfully."]);
