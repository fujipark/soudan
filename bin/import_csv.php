<?php
// CSV取込: php bin/import_csv.php ファイル.csv
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if (empty($argv[1]) || !is_file($argv[1])) { fwrite(STDERR, "使い方: php bin/import_csv.php ファイル.csv\n"); exit(1); }
Db::migrate();
[$n, $errors] = Importer::import($argv[1]);
echo "{$n}件取り込みました。\n";
foreach ($errors as $e) echo "  ! $e\n";
