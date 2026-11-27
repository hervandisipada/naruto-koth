<?php
declare(strict_types=1);

$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ../admin.php' . $query);
exit;
