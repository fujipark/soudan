<?php
// 学生相談室 予約管理 設定ファイル
// 個別設定は config.local.php (bin/install.php が生成) で上書きされます。
$config = [
    // SQLite(標準)。MySQL にする場合: 'mysql:host=localhost;dbname=soudan;charset=utf8mb4'
    'db_dsn'  => 'sqlite:' . __DIR__ . '/data/soudan.sqlite',
    'db_user' => null,
    'db_pass' => null,

    // ログイン用パスワードのハッシュ (bin/install.php で設定)
    'admin_password_hash' => '',

    // 年度の開始月 (4月始まり)
    'fiscal_start_month' => 4,

    // 1日の予約枠 (元Excelの 09時〜16時 の8枠 × 担当2名)
    'slots' => ['09時', '10時', '11時', '12時', '13時', '14時', '15時', '16時'],
];

if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_replace($config, require __DIR__ . '/config.local.php');
}
return $config;
