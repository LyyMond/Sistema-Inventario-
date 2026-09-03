<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

$sqlFile = __DIR__ . '/sql/gestion_bienes.sql';
$sql = file_get_contents($sqlFile);

if ($conn->multi_query($sql)) {
    $i = 1;
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
        if ($conn->errno) {
            echo "Error at statement $i: " . $conn->error . "\n";
            exit(1);
        }
        $i++;
    } while ($conn->more_results() && $conn->next_result());
    echo "All $i statements executed successfully.\n";
} else {
    echo "Error executing multi_query: " . $conn->error . "\n";
}
