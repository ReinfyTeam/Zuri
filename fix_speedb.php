<?php
$path = "C:/Users/Rods/Desktop/PocketMine-MP/plugins_source/Zuri/src/ReinfyTeam/Zuri/check/moving/speed/SpeedB.php";
$content = file_get_contents($path);
$old = "\t\$movementUp = \$blocksPerSeconds > 0.0;\n\t\$movementDown = \$blocksPerSeconds < 0.0;";
$new = "\t// Determine vertical movement direction from position samples.\n\t// \$blocksPerSeconds is always non-negative (calculated from absolute vertical distances),\n\t// so its sign cannot be used to determine up/down movement.\n\t\$movementUp = false;\n\t\$movementDown = false;\n\t\$vectors = [];\n\tforeach (\$movement as \$sample) {\n\t\tif (\$sample instanceof Vector3) {\n\t\t\t\$vectors[] = \$sample;\n\t\t}\n\t}\n\t\$vectorCount = count(\$vectors);\n\tif (\$vectorCount >= 2) {\n\t\t\$firstY = \$vectors[0]->y;\n\t\t\$lastY = \$vectors[\$vectorCount - 1]->y;\n\t\t\$movementUp = \$lastY > \$firstY;\n\t\t\$movementDown = \$lastY < \$firstY;\n\t}";
if (strpos($content, $old) === false) {
    echo "OLD TEXT NOT FOUND\n";
    $lines = explode("\n", $content);
    echo "Line 133: " . var_export($lines[132], true) . "\n";
    echo "Line 134: " . var_export($lines[133], true) . "\n";
} else {
    $content = str_replace($old, $new, $content);
    file_put_contents($path, $content);
    echo "REPLACED SUCCESSFULLY\n";
}
?>