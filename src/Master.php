<?php
declare(strict_types=1);

/** 選択肢マスタ (元Excel「マスタ」シート) */
final class Master
{
    /** カテゴリ => [表示名, 予約テーブルの列名] */
    public const CATEGORIES = [
        'visit'    => ['来談',     'visit'],
        'status'   => ['状況',     'status'],
        'grade'    => ['学年',     'grade'],
        'dept'     => ['学科',     'dept'],
        'topic'    => ['相談内容', 'topic'],
        'family'   => ['家族の相談', 'family'],
        'referral' => ['連携',     'referral'],
        'route'    => ['経緯',     'route'],
        'staff'    => ['担当者',   'staff'],
        'holiday'  => ['祝日',     'holiday'],
    ];

    /** 初期データ (元ファイルのマスタシートから) */
    public const SEED = [
        'visit'    => ['来談', 'tel', 'Teams', 'メール', 'その他'],
        'status'   => ['初回', '継続', 'リファー', '危機介入', '終結', '中断', 'その他'],
        'grade'    => ['1年生', '2年生', '3年生', '4年生', '留学生1', '留学生2', '留学生3', '留学生4', '大学院1', '大学院2'],
        'dept'     => ['経営', '経済', '人間科学', '英語英米', '子ども発達', '臨床心理', '法律'],
        'topic'    => ['学業', '生活', '人間関係', '性格', '発達', '精神', '心理検査', '将来', 'その他'],
        'family'   => ['1人', '2人'],
        'referral' => ['教員', 'サポートセンター', '教育支援課', 'キャリア支援課', '国際交流課', '保健室(センター)', '医療機関', '地域関係機関', '家族', '卒業生'],
        'route'    => ['自主', '教職員', '家族', '友人', '掲示', 'HP', 'GUD', 'その他'],
        'staff'    => ['卜部', '辻'],
        'holiday'  => [],
    ];

    /** 連携の集計区分 (元VBA 集計シート L112〜L115) */
    public const REFERRAL_STAFF     = ['教員', 'サポートセンター', '教育支援課', 'キャリア支援課', '国際交流課', '保健室(センター)'];
    public const REFERRAL_COMMUNITY = ['医療機関', '地域関係機関'];

    private static array $cache = [];

    public static function all(string $category): array
    {
        if (!isset(self::$cache[$category])) {
            $st = Db::pdo()->prepare('SELECT value FROM master WHERE category = ? ORDER BY sort_order, id');
            $st->execute([$category]);
            self::$cache[$category] = $st->fetchAll(PDO::FETCH_COLUMN);
        }
        return self::$cache[$category];
    }

    public static function replace(string $category, array $values): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM master WHERE category = ?')->execute([$category]);
        $ins = $pdo->prepare('INSERT INTO master (category, value, sort_order) VALUES (?, ?, ?)');
        $seen = [];
        foreach (array_values($values) as $i => $v) {
            $v = trim((string)$v);
            if ($v === '' || isset($seen[$v])) continue;
            $seen[$v] = true;
            $ins->execute([$category, $v, $i]);
        }
        $pdo->commit();
        unset(self::$cache[$category]);
    }

    public static function seed(): void
    {
        foreach (self::SEED as $cat => $values) {
            if (!Master::all($cat)) self::replace($cat, $values);
        }
    }

    public static function holidays(): array { return array_flip(self::all('holiday')); }
}
