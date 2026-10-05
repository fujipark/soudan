<?php /** @var string $viewFile */ $flash = is_logged_in() ? flash() : null; ?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title ?? '') ?> - 学生相談室 予約管理</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if (is_logged_in()): ?>
<header>
  <strong>学生相談室 予約管理</strong>
  <nav>
    <a href="index.php?p=calendar">カレンダー</a>
    <a href="index.php?p=student">学生別一覧</a>
    <a href="index.php?p=search">検索</a>
    <a href="index.php?p=check">整合性チェック</a>
    <a href="index.php?p=stats">集計</a>
    <a href="index.php?p=admin">管理</a>
    <a href="index.php?p=logout">ログアウト</a>
  </nav>
</header>
<?php endif; ?>
<main>
<?php if ($flash): ?><p class="flash <?= h($flash[1]) ?>"><?= h($flash[0]) ?></p><?php endif; ?>
<h1><?= h($title ?? '') ?></h1>
<?php require $viewFile; ?>
</main>
<script src="assets/app.js"></script>
</body>
</html>
