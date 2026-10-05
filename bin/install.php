<?php
// 初期セットアップ: php bin/install.php [ログインパスワード]
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

if (!is_dir(dirname(__DIR__) . '/data')) mkdir(dirname(__DIR__) . '/data', 0700, true);
Db::migrate();
Master::seed();
echo "テーブルとマスタ初期値を作成しました。\n";

$pw = $argv[1] ?? null;
if ($pw === null) {
    echo 'ログイン用パスワードを入力してください: ';
    $pw = trim((string)fgets(STDIN));
}
if (strlen($pw) < 8) { fwrite(STDERR, "パスワードは8文字以上にしてください。\n"); exit(1); }

$local = dirname(__DIR__) . '/config.local.php';
$existing = is_file($local) ? require $local : [];
$existing['admin_password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
file_put_contents($local, "<?php\nreturn " . var_export($existing, true) . ";\n");
chmod($local, 0600);
echo "config.local.php にパスワードを保存しました。\n";
