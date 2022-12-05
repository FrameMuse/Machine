<?php

require_once "/home/koto/project/usages/listener.php";

$listener = new MysqliListener();
$listener->AddListener("for_add", "SELECT id, status FROM for_add WHERE id = '-1'", $_GET['value']);
$listener->listen();

?>