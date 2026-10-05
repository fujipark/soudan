<?php
declare(strict_types=1);

/** 予約(相談記録)の読み書き。元Excel「予約」シート・検索・学生毎一覧に相当 */
final class Reservations
{
    /** 入力項目 (予約シートの F〜O 列 + 備考) */
    public const FIELDS = ['student_no', 'visit', 'status', 'grade', 'dept', 'topic', 'family', 'referral', 'route', 'graduate', 'note'];
    /** マスタの選択肢で検証する項目 */
    private const MASTER_FIELDS = ['visit', 'status', 'grade', 'dept', 'topic', 'family', 'referral', 'route'];
    /** 前回記録からコピーする項目 (元VBA Worksheet_Change: 7〜14列目) */
    public const COPY_FIELDS = ['visit', 'status', 'grade', 'dept', 'topic', 'family', 'referral', 'route'];

    /** 1日分: [担当][時間枠] => 行 or null */
    public static function day(string $date): array
    {
        $st = Db::pdo()->prepare('SELECT * FROM reservations WHERE date = ?');
        $st->execute([$date]);
        $map = [];
        foreach ($st->fetchAll() as $r) $map[$r['staff']][$r['slot']] = $r;

        $out = [];
        foreach (Master::all('staff') as $staff) {
            foreach (cfg('slots') as $slot) $out[$staff][$slot] = $map[$staff][$slot] ?? null;
        }
        return $out;
    }

    /**
     * 1日分を保存。全項目が空の枠は削除、入力のある枠は登録/更新。
     * @param array $input [担当][時間枠][項目名] => 値
     * @return array エラーメッセージ一覧 (空なら成功)
     */
    public static function saveDay(string $date, array $input): array
    {
        $errors = [];
        $rows = [];
        $staffList = Master::all('staff');
        $slots = cfg('slots');

        foreach ($staffList as $staff) {
            foreach ($slots as $slot) {
                $in = $input[$staff][$slot] ?? [];
                $row = [];
                foreach (self::FIELDS as $f) $row[$f] = trim((string)($in[$f] ?? ''));
                $row['student_no'] = normalize_student_no($row['student_no']);
                $row['graduate'] = $row['graduate'] === '〇' ? '〇' : '';

                foreach (self::MASTER_FIELDS as $f) {
                    if ($row[$f] !== '' && !in_array($row[$f], Master::all($f), true)) {
                        $errors[] = "{$staff} {$slot}: 「{$row[$f]}」は選択肢にありません";
                        $row[$f] = '';
                    }
                }
                $row['note'] = mb_substr($row['note'], 0, 255);
                $rows[$staff][$slot] = $row;
            }
        }
        if ($errors) return $errors;

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $del = $pdo->prepare('DELETE FROM reservations WHERE date = ? AND staff = ? AND slot = ?');
            $sel = $pdo->prepare('SELECT id FROM reservations WHERE date = ? AND staff = ? AND slot = ?');
            $ins = $pdo->prepare('INSERT INTO reservations (date, staff, slot, ' . implode(',', self::FIELDS) . ', created_at, updated_at)
                                  VALUES (?, ?, ?, ' . implode(',', array_fill(0, count(self::FIELDS), '?')) . ', ?, ?)');
            $upd = $pdo->prepare('UPDATE reservations SET ' . implode(' = ?, ', self::FIELDS) . ' = ?, updated_at = ? WHERE id = ?');

            foreach ($rows as $staff => $bySlot) {
                foreach ($bySlot as $slot => $row) {
                    $empty = implode('', $row) === '';
                    $sel->execute([$date, $staff, $slot]);
                    $id = $sel->fetchColumn();
                    if ($empty) {
                        if ($id) $del->execute([$date, $staff, $slot]);
                    } elseif ($id) {
                        $upd->execute([...array_values($row), $now, $id]);
                    } else {
                        $ins->execute([$date, $staff, $slot, ...array_values($row), $now, $now]);
                    }
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return [];
    }

    /** 同一学籍番号の直近の過去記録 (自動コピー用) */
    public static function lastRecord(string $studentNo, string $date, string $staff, string $slot): ?array
    {
        if ($studentNo === '') return null;
        $st = Db::pdo()->prepare('SELECT * FROM reservations
            WHERE student_no = ? AND date <= ? AND NOT (date = ? AND staff = ? AND slot = ?)
            ORDER BY date DESC, slot DESC, id DESC LIMIT 1');
        $st->execute([$studentNo, $date, $date, $staff, $slot]);
        return $st->fetch() ?: null;
    }

    /** カレンダー用: 日付 => [担当 => 件数] (件数 = 学籍番号が入力された枠) */
    public static function monthCounts(string $from, string $to): array
    {
        $st = Db::pdo()->prepare("SELECT date, staff, COUNT(*) AS n FROM reservations
            WHERE date BETWEEN ? AND ? AND student_no <> '' GROUP BY date, staff");
        $st->execute([$from, $to]);
        $out = [];
        foreach ($st->fetchAll() as $r) $out[$r['date']][$r['staff']] = (int)$r['n'];
        return $out;
    }

    /** 検索 (元「検索」シート) */
    public static function search(array $f): array
    {
        $where = ['1=1']; $args = [];
        if (!empty($f['student_no'])) { $where[] = 'student_no = ?'; $args[] = normalize_student_no($f['student_no']); }
        foreach (['staff', ...self::MASTER_FIELDS] as $col) {
            if (!empty($f[$col])) { $where[] = "$col = ?"; $args[] = $f[$col]; }
        }
        if (!empty($f['from']) && valid_date($f['from'])) { $where[] = 'date >= ?'; $args[] = $f['from']; }
        if (!empty($f['to'])   && valid_date($f['to']))   { $where[] = 'date <= ?'; $args[] = $f['to']; }
        // 学籍番号なし条件のみの検索は、何らかの記録がある行だけ (元VBA: 学籍番号未入力時の挙動)
        $where[] = "(student_no || visit || status || grade || dept || topic || family || referral || route || graduate) <> ''";
        if (Db::driver() !== 'sqlite') {
            array_pop($where);
            $where[] = "CONCAT(student_no, visit, status, grade, dept, topic, family, referral, route, graduate) <> ''";
        }
        $st = Db::pdo()->prepare('SELECT * FROM reservations WHERE ' . implode(' AND ', $where)
            . ' ORDER BY date, slot, staff LIMIT 5000');
        $st->execute($args);
        return $st->fetchAll();
    }

    /** 学生ごとの相談日一覧 (元「学生毎相談日一覧」シート) */
    public static function studentHistory(string $studentNo): array
    {
        $st = Db::pdo()->prepare('SELECT * FROM reservations WHERE student_no = ? ORDER BY date, slot');
        $st->execute([$studentNo]);
        return $st->fetchAll();
    }

    /** 全学生のサマリ: 学籍番号, 回数, 初回日, 直近日 */
    public static function studentSummary(?string $from = null, ?string $to = null): array
    {
        $where = "student_no <> ''"; $args = [];
        if ($from) { $where .= ' AND date >= ?'; $args[] = $from; }
        if ($to)   { $where .= ' AND date <= ?'; $args[] = $to; }
        $st = Db::pdo()->prepare("SELECT student_no, COUNT(*) AS n, MIN(date) AS first_date, MAX(date) AS last_date
            FROM reservations WHERE $where GROUP BY student_no ORDER BY student_no");
        $st->execute($args);
        return $st->fetchAll();
    }

    public static function isCarryover(string $studentNo): bool
    {
        $st = Db::pdo()->prepare('SELECT 1 FROM carryover WHERE student_no = ?');
        $st->execute([$studentNo]);
        return (bool)$st->fetchColumn();
    }

    public static function carryoverSet(): array
    {
        return array_flip(Db::pdo()->query('SELECT student_no FROM carryover')->fetchAll(PDO::FETCH_COLUMN));
    }

    /** 予約内の学籍番号を継続学生に追加 (元 CommandButton7。既存分は消さない・重複なし) */
    public static function addCarryover(): int
    {
        $pdo = Db::pdo();
        $nos = $pdo->query("SELECT DISTINCT student_no FROM reservations WHERE student_no <> ''")->fetchAll(PDO::FETCH_COLUMN);
        $now = date('Y-m-d H:i:s');
        $exists = self::carryoverSet();
        $ins = $pdo->prepare('INSERT INTO carryover (student_no, added_at) VALUES (?, ?)');
        $added = 0;
        $pdo->beginTransaction();
        foreach ($nos as $no) {
            if (isset($exists[$no])) continue;
            $ins->execute([$no, $now]);
            $added++;
        }
        $pdo->commit();
        return $added;
    }

    /** 期間内の予約を削除 (元 CommandButton12: 年度初期化) */
    public static function deleteRange(string $from, string $to): int
    {
        $st = Db::pdo()->prepare('DELETE FROM reservations WHERE date BETWEEN ? AND ?');
        $st->execute([$from, $to]);
        return $st->rowCount();
    }

    public static function range(string $from, string $to): array
    {
        $st = Db::pdo()->prepare('SELECT * FROM reservations WHERE date BETWEEN ? AND ? ORDER BY date, slot, staff');
        $st->execute([$from, $to]);
        return $st->fetchAll();
    }
}
