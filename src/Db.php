<?php
declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = new PDO(cfg('db_dsn'), cfg('db_user'), cfg('db_pass'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$pdo;
    }

    public static function driver(): string { return explode(':', (string)cfg('db_dsn'))[0]; }

    /** テーブル作成 (SQLite / MySQL 両対応) */
    public static function migrate(): void
    {
        $pdo = self::pdo();
        $sqlite = self::driver() === 'sqlite';
        $pk  = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $opt = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

        // 選択肢マスタ (元Excel「マスタ」シート)
        $pdo->exec("CREATE TABLE IF NOT EXISTS master (
            id $pk,
            category VARCHAR(20) NOT NULL,
            value VARCHAR(60) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            UNIQUE (category, value)
        )$opt");

        // 予約表: 元Excel「予約」シートの1行 = 1レコード
        $pdo->exec("CREATE TABLE IF NOT EXISTS reservations (
            id $pk,
            date DATE NOT NULL,
            staff VARCHAR(30) NOT NULL,
            slot VARCHAR(8) NOT NULL,
            student_no VARCHAR(30) NOT NULL DEFAULT '',
            visit VARCHAR(30) NOT NULL DEFAULT '',
            status VARCHAR(30) NOT NULL DEFAULT '',
            grade VARCHAR(30) NOT NULL DEFAULT '',
            dept VARCHAR(30) NOT NULL DEFAULT '',
            topic VARCHAR(30) NOT NULL DEFAULT '',
            family VARCHAR(30) NOT NULL DEFAULT '',
            referral VARCHAR(60) NOT NULL DEFAULT '',
            route VARCHAR(30) NOT NULL DEFAULT '',
            graduate VARCHAR(4) NOT NULL DEFAULT '',
            note VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE (date, staff, slot)
        )$opt");
        try { $pdo->exec('CREATE INDEX idx_res_student ON reservations (student_no)'); } catch (PDOException $e) { /* 作成済み */ }

        // 継続学生 (前年度から継続している学生の学籍番号)
        $pdo->exec("CREATE TABLE IF NOT EXISTS carryover (
            student_no VARCHAR(30) NOT NULL PRIMARY KEY,
            added_at DATETIME NOT NULL
        )$opt");
    }
}
