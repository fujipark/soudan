<?php
declare(strict_types=1);
mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Tokyo');

$GLOBALS['config'] = require dirname(__DIR__) . '/config.php';

foreach (['Db', 'Master', 'Reservations', 'Validator', 'Stats', 'Importer'] as $c) {
    require_once __DIR__ . "/$c.php";
}

function cfg(string $key) { return $GLOBALS['config'][$key] ?? null; }
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** 学籍番号の正規化: 全角→半角、英小文字→大文字 (元VBA: UCase + StrConv(vbNarrow)) */
function normalize_student_no(string $s): string
{
    return strtoupper(mb_convert_kana(trim($s), 'as'));
}

/** その日が属する年度の開始日 (4月始まり) */
function fiscal_start(?string $date = null): string
{
    $t = $date ? strtotime($date) : time();
    $y = (int)date('Y', $t);
    $sm = (int)cfg('fiscal_start_month');
    if ((int)date('n', $t) < $sm) $y--;
    return sprintf('%04d-%02d-01', $y, $sm);
}

const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];
function weekday_ja(string $date): string { return WEEKDAYS[(int)date('w', strtotime($date))]; }

function valid_date(string $d): bool
{
    $t = DateTime::createFromFormat('Y-m-d', $d);
    return $t && $t->format('Y-m-d') === $d;
}

// ---- セッション / 認証 / CSRF -------------------------------------------
function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}
function is_logged_in(): bool { return !empty($_SESSION['login']); }
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">'; }
function check_csrf(): void
{
    $ok = isset($_POST['_csrf']) && hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['_csrf']);
    if (!$ok) { http_response_code(400); exit('不正なリクエストです(CSRF)'); }
}
