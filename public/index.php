<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
start_session();

if (!is_file(dirname(__DIR__) . '/data/soudan.sqlite') && Db::driver() === 'sqlite') {
    exit('先に php bin/install.php を実行してください。');
}

function render(string $view, array $vars = []): void
{
    extract($vars);
    $viewFile = dirname(__DIR__) . "/src/views/$view.php";
    require dirname(__DIR__) . '/src/views/layout.php';
}
function redirect(string $to): never { header("Location: $to"); exit; }
function flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function sel(string $name, array $options, string $value = '', string $blank = ''): string
{
    $o = '<option value="">' . h($blank) . '</option>';
    foreach ($options as $v) $o .= '<option' . ($v === $value ? ' selected' : '') . ' value="' . h($v) . '">' . h($v) . '</option>';
    return '<select name="' . h($name) . '">' . $o . '</select>';
}
/** 期間指定 (?from=YYYY-MM&to=YYYY-MM)。既定は今年度の開始月〜今月 */
function period_from_request(): array
{
    $ok = fn($v) => is_string($v) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $v);
    $fromYm = $ok($_GET['from'] ?? null) ? $_GET['from'] : substr(fiscal_start(), 0, 7);
    $toYm   = $ok($_GET['to'] ?? null)   ? $_GET['to']   : date('Y-m');
    return [$fromYm . '-01', date('Y-m-t', strtotime($toYm . '-01')), $fromYm, $toYm];
}

$page = $_GET['p'] ?? 'calendar';

// ---- ログイン ------------------------------------------------------------
if ($page === 'login') {
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $hash = (string)cfg('admin_password_hash');
        if ($hash !== '' && password_verify((string)($_POST['password'] ?? ''), $hash)) {
            session_regenerate_id(true);
            $_SESSION['login'] = true;
            redirect('index.php');
        }
        $error = 'パスワードが違います';
        usleep(500000);
    }
    render('login', ['error' => $error, 'title' => 'ログイン']);
    exit;
}
if ($page === 'logout') { $_SESSION = []; session_destroy(); redirect('index.php?p=login'); }
if (!is_logged_in()) redirect('index.php?p=login');

// ---- 各ページ -----------------------------------------------------------
switch ($page) {
    case 'calendar':
        $ym = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_GET['ym'] ?? '') ? $_GET['ym'] : date('Y-m');
        $first = $ym . '-01'; $last = date('Y-m-t', strtotime($first));
        render('calendar', [
            'title' => '予約カレンダー', 'ym' => $ym, 'first' => $first, 'last' => $last,
            'counts' => Reservations::monthCounts($first, $last), 'holidays' => Master::holidays(),
            'staff' => Master::all('staff'),
        ]);
        break;

    case 'day':
        $date = $_GET['date'] ?? date('Y-m-d');
        if (!valid_date($date)) { http_response_code(400); exit('日付が不正です'); }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_csrf();
            $errors = Reservations::saveDay($date, $_POST['r'] ?? []);
            if ($errors) { flash(implode(' / ', $errors), 'ng'); }
            else { flash('保存しました'); }
            redirect('index.php?p=day&date=' . $date);
        }
        render('day', ['title' => $date . ' の予約', 'date' => $date, 'grid' => Reservations::day($date),
                       'holiday' => isset(Master::holidays()[$date])]);
        break;

    case 'api_last':   // 学籍番号入力時の前回内容コピー用 (元VBA Worksheet_Change)
        header('Content-Type: application/json; charset=UTF-8');
        $no = normalize_student_no((string)($_GET['student_no'] ?? ''));
        $date = (string)($_GET['date'] ?? '');
        $rec = valid_date($date) ? Reservations::lastRecord($no, $date, (string)($_GET['staff'] ?? ''), (string)($_GET['slot'] ?? '')) : null;
        $copy = [];
        if ($rec) foreach (Reservations::COPY_FIELDS as $f) $copy[$f] = $rec[$f];
        echo json_encode(['student_no' => $no, 'found' => (bool)$rec, 'date' => $rec['date'] ?? null, 'values' => $copy], JSON_UNESCAPED_UNICODE);
        break;

    case 'check':
        [$from, $to, $fromYm, $toYm] = period_from_request();
        $items = Validator::check($from, $to);
        if (!empty($_GET['only_errors'])) $items = array_values(array_filter($items, fn($i) => $i['errors']));
        render('check', ['title' => '整合性チェック', 'items' => $items, 'fromYm' => $fromYm, 'toYm' => $toYm]);
        break;

    case 'stats':
        [$from, $to, $fromYm, $toYm] = period_from_request();
        $axes = [['grade', 'staff'], ['visit', 'staff'], ['status', 'staff'], ['grade', 'visit'],
                 ['grade', 'topic'], ['grade', 'route'], ['grade', 'referral']];
        $tables = [];
        foreach ($axes as [$r, $c]) $tables[] = [$r, $c, Stats::crosstab($r, $c, $from, $to)];
        render('stats', [
            'title' => '集計', 'fromYm' => $fromYm, 'toYm' => $toYm, 'main' => Stats::main($from, $to),
            'tables' => $tables, 'linkage' => Stats::linkage($from, $to), 'monthly' => Stats::monthly($from, $to),
        ]);
        break;

    case 'student':
        $no = normalize_student_no((string)($_GET['no'] ?? ''));
        [$from, $to, $fromYm, $toYm] = period_from_request();
        render('student', [
            'title' => '学生ごとの相談日一覧', 'no' => $no,
            'history' => $no !== '' ? Reservations::studentHistory($no) : [],
            'carry' => $no !== '' && Reservations::isCarryover($no),
            'summary' => $no === '' ? Reservations::studentSummary($from, $to) : [],
            'carrySet' => Reservations::carryoverSet(), 'fromYm' => $fromYm, 'toYm' => $toYm,
        ]);
        break;

    case 'search':
        $filters = array_intersect_key($_GET, array_flip(['student_no', 'staff', 'from', 'to', ...Stats::DIMS]));
        $filters = array_filter($filters, fn($v) => is_string($v) && $v !== '');
        $searched = isset($_GET['go']);
        $rows = $searched ? Reservations::search($filters) : [];
        if ($searched && ($_GET['go'] === 'csv')) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="search_' . date('Ymd_His') . '.csv"');
            Importer::export($rows, fopen('php://output', 'w'));
            exit;
        }
        render('search', ['title' => '検索', 'f' => $filters, 'rows' => $rows, 'searched' => $searched]);
        break;

    case 'admin':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_csrf();
            $act = $_POST['action'] ?? '';
            if ($act === 'carryover') {
                flash(Reservations::addCarryover() . '人を継続学生に追加しました');
            } elseif ($act === 'purge') {
                $f = (string)($_POST['from'] ?? ''); $t = (string)($_POST['to'] ?? '');
                if (($_POST['confirm'] ?? '') !== '削除' || !valid_date($f) || !valid_date($t)) {
                    flash('期間と確認文字(削除)を正しく入力してください', 'ng');
                } else {
                    flash(Reservations::deleteRange($f, $t) . '件を削除しました');
                }
            } elseif ($act === 'import' && isset($_FILES['csv']) && $_FILES['csv']['error'] === UPLOAD_ERR_OK) {
                [$n, $errs] = Importer::import($_FILES['csv']['tmp_name']);
                flash("{$n}件取り込みました" . ($errs ? '。エラー: ' . implode(' / ', array_slice($errs, 0, 5)) : ''), $errs ? 'ng' : 'ok');
            } elseif ($act === 'master' && isset(Master::CATEGORIES[$_POST['cat'] ?? ''])) {
                Master::replace($_POST['cat'], preg_split('/\R/u', (string)($_POST['values'] ?? '')));
                flash(Master::CATEGORIES[$_POST['cat']][0] . 'を更新しました');
            }
            redirect('index.php?p=admin');
        }
        if (isset($_GET['export'])) {
            [$from, $to] = period_from_request();
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="reservations_' . $from . '_' . $to . '.csv"');
            Importer::export(Reservations::range($from, $to), fopen('php://output', 'w'));
            exit;
        }
        [$from, $to, $fromYm, $toYm] = period_from_request();
        render('admin', ['title' => '管理', 'fromYm' => $fromYm, 'toYm' => $toYm,
                         'fyStart' => fiscal_start(), 'fyEnd' => date('Y-m-d', strtotime(fiscal_start() . ' +1 year -1 day'))]);
        break;

    default:
        http_response_code(404);
        render('login', ['error' => 'ページが見つかりません', 'title' => '404']);
}
