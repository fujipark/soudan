<?php
$prev = date('Y-m-d', strtotime("$date -1 day")); $next = date('Y-m-d', strtotime("$date +1 day"));
$staffList = array_keys($grid); $idx = array_flip($staffList);
?>
<p class="pager">
  <a href="index.php?p=day&date=<?= $prev ?>">&laquo; 前日</a>
  <strong><?= h($date) ?> (<?= weekday_ja($date) ?>)<?= $holiday ? ' 祝日' : '' ?></strong>
  <a href="index.php?p=day&date=<?= $next ?>">翌日 &raquo;</a>
  <a href="index.php?p=calendar&ym=<?= h(substr($date, 0, 7)) ?>">カレンダーへ</a>
</p>
<form method="post" action="index.php?p=day&date=<?= h($date) ?>" id="dayform" data-date="<?= h($date) ?>">
<?= csrf_field() ?>
<div class="scroll">
<table class="grid">
  <tr><th>担当</th><th>時間</th><th>学籍番号</th><th>来談</th><th>状況</th><th>学年</th><th>学科</th>
      <th>相談内容</th><th>家族</th><th>連携</th><th>経緯</th><th>卒業生</th><th>備考</th></tr>
<?php foreach ($grid as $staff => $slots): $first = true; foreach ($slots as $slot => $r):
    $p = fn($f) => "r[" . $staff . "][" . $slot . "][" . $f . "]"; $v = fn($f) => $r[$f] ?? ''; ?>
  <tr class="staff<?= $idx[$staff] % 2 ?>" data-staff="<?= h($staff) ?>" data-slot="<?= h($slot) ?>">
    <td><?= $first ? h($staff) : '' ?></td><td><?= h($slot) ?></td>
    <td><input name="<?= h($p('student_no')) ?>" value="<?= h($v('student_no')) ?>" class="sno" size="10" autocomplete="off"></td>
    <td><?= sel($p('visit'), Master::all('visit'), $v('visit')) ?></td>
    <td><?= sel($p('status'), Master::all('status'), $v('status')) ?></td>
    <td><?= sel($p('grade'), Master::all('grade'), $v('grade')) ?></td>
    <td><?= sel($p('dept'), Master::all('dept'), $v('dept')) ?></td>
    <td><?= sel($p('topic'), Master::all('topic'), $v('topic')) ?></td>
    <td><?= sel($p('family'), Master::all('family'), $v('family')) ?></td>
    <td><?= sel($p('referral'), Master::all('referral'), $v('referral')) ?></td>
    <td><?= sel($p('route'), Master::all('route'), $v('route')) ?></td>
    <td><input type="checkbox" name="<?= h($p('graduate')) ?>" value="〇"<?= $v('graduate') === '〇' ? ' checked' : '' ?>></td>
    <td><input name="<?= h($p('note')) ?>" value="<?= h($v('note')) ?>" size="14" maxlength="255"></td>
  </tr>
<?php $first = false; endforeach; endforeach; ?>
</table>
</div>
<p><button>この日を保存</button> <span class="muted">全項目が空の枠は未予約として扱います。学籍番号は半角大文字に自動変換されます。</span></p>
</form>
