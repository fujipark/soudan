<?php
declare(strict_types=1);

/** CSV の取り込み / 書き出し (Excelからの移行・年度末バックアップ用) */
final class Importer
{
    public const HEADER = ['日付', '担当', '時間', '学籍番号', '来談', '状況', '学年', '学科', '相談内容', '家族相談', '連携', '経緯', '卒業生', '備考'];
    private const COLS = ['date', 'staff', 'slot', 'student_no', 'visit', 'status', 'grade', 'dept', 'topic', 'family', 'referral', 'route', 'graduate', 'note'];

    public static function export(array $rows, $out): void
    {
        fwrite($out, "\xEF\xBB\xBF");  // Excelで文字化けしないよう BOM 付き UTF-8
        fputcsv($out, self::HEADER);
        foreach ($rows as $r) fputcsv($out, array_map(fn($c) => $r[$c], self::COLS));
    }

    /** @return array{0:int,1:string[]} [取込件数, エラー一覧] */
    public static function import(string $path): array
    {
        $fp = fopen($path, 'rb');
        if (!$fp) return [0, ['ファイルを開けません']];
        $first = fgets($fp);
        rewind($fp);
        if (str_starts_with((string)$first, "\xEF\xBB\xBF")) fread($fp, 3);

        $head = fgetcsv($fp);
        $idx = [];
        foreach (self::HEADER as $i => $name) {
            $pos = array_search($name, $head ?: [], true);
            if ($pos === false) { fclose($fp); return [0, ["ヘッダーに「{$name}」列がありません"]]; }
            $idx[self::COLS[$i]] = $pos;
        }

        $pdo = Db::pdo();
        $now = date('Y-m-d H:i:s');
        $fields = Reservations::FIELDS;
        $upsertSel = $pdo->prepare('SELECT id FROM reservations WHERE date = ? AND staff = ? AND slot = ?');
        $ins = $pdo->prepare('INSERT INTO reservations (date, staff, slot, ' . implode(',', $fields) . ', created_at, updated_at)
                              VALUES (?, ?, ?, ' . implode(',', array_fill(0, count($fields), '?')) . ', ?, ?)');
        $upd = $pdo->prepare('UPDATE reservations SET ' . implode(' = ?, ', $fields) . ' = ?, updated_at = ? WHERE id = ?');

        $n = 0; $errors = []; $line = 1;
        $pdo->beginTransaction();
        while (($cols = fgetcsv($fp)) !== false) {
            $line++;
            if ($cols === [null]) continue;
            $get = fn(string $k) => trim((string)($cols[$idx[$k]] ?? ''));
            $date = str_replace('/', '-', $get('date'));
            if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) $date = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
            if (!valid_date($date)) { $errors[] = "{$line}行目: 日付が不正です"; continue; }
            $staff = $get('staff'); $slot = $get('slot');
            if (!in_array($slot, cfg('slots'), true)) { $errors[] = "{$line}行目: 時間「{$slot}」が不正です"; continue; }
            if (!in_array($staff, Master::all('staff'), true)) { $errors[] = "{$line}行目: 担当「{$staff}」が不正です"; continue; }

            $vals = [];
            foreach ($fields as $f) $vals[] = $f === 'student_no' ? normalize_student_no($get($f)) : $get($f);
            $upsertSel->execute([$date, $staff, $slot]);
            if ($id = $upsertSel->fetchColumn()) $upd->execute([...$vals, $now, $id]);
            else $ins->execute([$date, $staff, $slot, ...$vals, $now, $now]);
            $n++;
        }
        $pdo->commit();
        fclose($fp);
        return [$n, $errors];
    }
}
