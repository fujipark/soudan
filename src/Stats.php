<?php
declare(strict_types=1);

/** 集計 (元Excel「集計」「集計２表」「担当別登録集計」シートの数式群に相当) */
final class Stats
{
    public const DIMS = ['staff', 'visit', 'status', 'grade', 'dept', 'topic', 'family', 'referral', 'route'];

    /** 年月(開始/終了)から期間 [from, to] を作る */
    public static function period(int $y1, int $m1, int $y2, int $m2): array
    {
        $from = sprintf('%04d-%02d-01', $y1, $m1);
        $to = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $y2, $m2)));
        return [$from, $to];
    }

    /** 学科 × 学年 × 相談内容 の件数表 + 初回数 + 実人数 + のべ人数 (元「集計」シート C6:Q111) */
    public static function main(string $from, string $to): array
    {
        $pdo = Db::pdo();
        $depts = Master::all('dept'); $grades = Master::all('grade'); $topics = Master::all('topic');

        $cells = [];
        $st = $pdo->prepare("SELECT dept, grade, topic, COUNT(*) AS n FROM reservations
            WHERE date BETWEEN ? AND ? AND dept <> '' AND grade <> '' AND topic <> '' GROUP BY dept, grade, topic");
        $st->execute([$from, $to]);
        foreach ($st->fetchAll() as $r) $cells[$r['dept']][$r['grade']][$r['topic']] = (int)$r['n'];

        $first = [];
        $st = $pdo->prepare("SELECT dept, grade, COUNT(*) AS n FROM reservations
            WHERE date BETWEEN ? AND ? AND status = '初回' AND dept <> '' AND grade <> '' GROUP BY dept, grade");
        $st->execute([$from, $to]);
        foreach ($st->fetchAll() as $r) $first[$r['dept']][$r['grade']] = (int)$r['n'];

        // 実人数 = 学籍番号の重複を除いた人数 / のべ人数 = 相談件数 (学科・学年ごと)
        $uniq = [];
        $st = $pdo->prepare("SELECT dept, grade, COUNT(DISTINCT student_no) AS u, COUNT(*) AS t FROM reservations
            WHERE date BETWEEN ? AND ? AND student_no <> '' AND dept <> '' AND grade <> '' GROUP BY dept, grade");
        $st->execute([$from, $to]);
        foreach ($st->fetchAll() as $r) $uniq[$r['dept']][$r['grade']] = [(int)$r['u'], (int)$r['t']];

        $out = ['topics' => $topics, 'depts' => []];
        $grand = array_fill_keys($topics, 0); $grand += ['_first' => 0, '_uniq' => 0, '_total' => 0];
        foreach ($depts as $d) {
            $sub = array_fill_keys($topics, 0); $sub += ['_first' => 0, '_uniq' => 0, '_total' => 0];
            $rows = [];
            foreach ($grades as $g) {
                $row = [];
                foreach ($topics as $t) { $row[$t] = $cells[$d][$g][$t] ?? 0; $sub[$t] += $row[$t]; $grand[$t] += $row[$t]; }
                $row['_first'] = $first[$d][$g] ?? 0;
                [$row['_uniq'], $row['_total']] = $uniq[$d][$g] ?? [0, 0];
                foreach (['_first', '_uniq', '_total'] as $k) { $sub[$k] += $row[$k]; $grand[$k] += $row[$k]; }
                $rows[$g] = $row;
            }
            $out['depts'][$d] = ['rows' => $rows, 'sub' => $sub];
        }
        $out['grand'] = $grand;
        return $out;
    }

    /** 任意の2軸クロス集計 (行軸 × 列軸)。軸はマスタの並び順で表示 */
    public static function crosstab(string $rowDim, string $colDim, string $from, string $to): array
    {
        if (!in_array($rowDim, self::DIMS, true) || !in_array($colDim, self::DIMS, true)) {
            throw new InvalidArgumentException('不正な集計軸');
        }
        $st = Db::pdo()->prepare("SELECT $rowDim AS r, $colDim AS c, COUNT(*) AS n FROM reservations
            WHERE date BETWEEN ? AND ? AND $rowDim <> '' AND $colDim <> '' GROUP BY $rowDim, $colDim");
        $st->execute([$from, $to]);
        $cells = [];
        foreach ($st->fetchAll() as $x) $cells[$x['r']][$x['c']] = (int)$x['n'];

        $rows = Master::all($rowDim); $cols = Master::all($colDim);
        $rt = []; $ct = array_fill_keys($cols, 0); $total = 0;
        foreach ($rows as $r) {
            $rt[$r] = 0;
            foreach ($cols as $c) { $v = $cells[$r][$c] ?? 0; $rt[$r] += $v; $ct[$c] += $v; $total += $v; }
        }
        return compact('rows', 'cols', 'cells', 'rt', 'ct', 'total');
    }

    /** 連携・家族相談・卒業生のまとめ (元「集計」シート L112:L117) */
    public static function linkage(string $from, string $to): array
    {
        $pdo = Db::pdo();
        $count = function (string $where, array $args = []) use ($pdo, $from, $to): int {
            $st = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE date BETWEEN ? AND ? AND $where");
            $st->execute([$from, $to, ...$args]);
            return (int)$st->fetchColumn();
        };
        $in = fn(array $v) => 'referral IN (' . implode(',', array_fill(0, count($v), '?')) . ')';

        $r = [
            '家族相談'           => $count("family <> ''"),
            '教職員(件数)'       => $count($in(Master::REFERRAL_STAFF), Master::REFERRAL_STAFF),
            '地域・医療機関'     => $count($in(Master::REFERRAL_COMMUNITY), Master::REFERRAL_COMMUNITY),
            '卒業生'             => $count("graduate = '〇'"),
        ];
        $r['小計(連携)'] = array_sum($r);
        return $r;
    }

    /** 月別 × 担当別の件数 (元「カレンダー」シートの月合計) */
    public static function monthly(string $from, string $to): array
    {
        $st = Db::pdo()->prepare("SELECT SUBSTR(date, 1, 7) AS ym, staff, COUNT(*) AS n FROM reservations
            WHERE date BETWEEN ? AND ? AND student_no <> '' GROUP BY SUBSTR(date, 1, 7), staff ORDER BY ym");
        $st->execute([$from, $to]);
        $out = [];
        foreach ($st->fetchAll() as $r) $out[$r['ym']][$r['staff']] = (int)$r['n'];
        return $out;
    }
}
