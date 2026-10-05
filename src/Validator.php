<?php
declare(strict_types=1);

/**
 * 入力データの整合性チェック (元Excel CommandButton3 →「確認」シート)
 * 期間内の記録を日付順に見て、学生ごとの利用回数と前回記録を突き合わせる。
 */
final class Validator
{
    /** 未入力だとエラーにする項目 (元VBA: 学籍番号/来談/状況/学年/学科/相談内容) */
    private const REQUIRED = ['student_no' => '学籍番号', 'visit' => '来談', 'status' => '状況',
                              'grade' => '学年', 'dept' => '学科', 'topic' => '相談内容'];
    /** 未入力だと「確認」扱いにする項目 (元VBAでは同じ一覧に出ていた任意項目) */
    private const OPTIONAL = ['family' => '家族', 'referral' => '連携', 'route' => '経緯'];

    /** @return array[] 問題のある行: 元レコード + count, errors[], notices[] */
    public static function check(string $from, string $to): array
    {
        $rows = Reservations::range($from, $to);
        $carry = Reservations::carryoverSet();
        $count = [];     // 学籍番号 => 期間内の利用回数
        $prev  = [];     // 学籍番号 => 直前の記録
        $result = [];

        foreach ($rows as $r) {
            $content = $r['student_no'] . $r['visit'] . $r['status'] . $r['grade'] . $r['dept']
                     . $r['topic'] . $r['family'] . $r['referral'] . $r['route'] . $r['graduate'];
            if ($content === '') continue;   // 空き枠は対象外

            $no = $r['student_no'];
            $n = 0;
            if ($no !== '') { $count[$no] = ($count[$no] ?? 0) + 1; $n = $count[$no]; }

            $errors = []; $notices = [];
            foreach (self::REQUIRED as $f => $label) if ($r[$f] === '') $errors[] = "{$label}なし";
            foreach (self::OPTIONAL as $f => $label) if ($r[$f] === '') $notices[] = "{$label}なし";

            if ($no !== '') {
                $isCarry = isset($carry[$no]);
                if ($n === 1 && $r['status'] !== '' && $r['status'] !== '初回' && !$isCarry) $errors[] = '相談回数が1回目なのに「初回」でない';
                if ($n > 1  && $r['status'] === '初回') $errors[] = '相談回数が2回目以降なのに「初回」';
                if ($isCarry && $r['status'] === '初回') $errors[] = '前年度から継続の学生なのに「初回」';
                if (isset($prev[$no])) {
                    if ($prev[$no]['grade'] !== '' && $r['grade'] !== '' && $prev[$no]['grade'] !== $r['grade']) $errors[] = '同一学籍番号で学年が異なる';
                    if ($prev[$no]['dept']  !== '' && $r['dept']  !== '' && $prev[$no]['dept']  !== $r['dept'])  $errors[] = '同一学籍番号で学科が異なる';
                }
                $prev[$no] = $r;
            }

            if ($errors || $notices) {
                $result[] = $r + ['count' => $n, 'errors' => $errors, 'notices' => $notices];
            }
        }
        return $result;
    }
}
