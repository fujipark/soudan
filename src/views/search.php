<?php $get = fn($k) => $f[$k] ?? ''; ?>
<form method="get" class="searchform">
  <input type="hidden" name="p" value="search">
  <label>学籍番号 <input name="student_no" value="<?= h($get('student_no')) ?>" size="12"></label>
  <label>期間 <input type="date" name="from" value="<?= h($get('from')) ?>"> ～ <input type="date" name="to" value="<?= h($get('to')) ?>"></label>
  <label>担当 <?= sel('staff', Master::all('staff'), $get('staff')) ?></label>
  <?php foreach (['visit' => '来談', 'status' => '状況', 'grade' => '学年', 'dept' => '学科', 'topic' => '相談内容',
                  'family' => '家族相談', 'referral' => '連携', 'route' => '経緯'] as $k => $label): ?>
    <label><?= h($label) ?> <?= sel($k, Master::all($k), $get($k)) ?></label>
  <?php endforeach; ?>
  <button name="go" value="1">検索</button>
  <button name="go" value="csv">CSV出力</button>
</form>
<?php if ($searched): ?>
  <p><?= count($rows) ?>件<?= count($rows) >= 5000 ? ' (上限5000件まで表示)' : '' ?></p>
  <div class="scroll"><table>
    <tr><th>日付</th><th>時間</th><th>担当</th><th>学籍番号</th><th>来談</th><th>状況</th><th>学年</th><th>学科</th><th>相談内容</th><th>家族</th><th>連携</th><th>経緯</th><th>卒業生</th><th>備考</th></tr>
  <?php foreach ($rows as $r): ?>
    <tr><td><a href="index.php?p=day&date=<?= h($r['date']) ?>"><?= h($r['date']) ?></a></td><td><?= h($r['slot']) ?></td><td><?= h($r['staff']) ?></td>
      <td><a href="index.php?p=student&no=<?= urlencode($r['student_no']) ?>"><?= h($r['student_no']) ?></a></td>
      <td><?= h($r['visit']) ?></td><td><?= h($r['status']) ?></td><td><?= h($r['grade']) ?></td><td><?= h($r['dept']) ?></td>
      <td><?= h($r['topic']) ?></td><td><?= h($r['family']) ?></td><td><?= h($r['referral']) ?></td><td><?= h($r['route']) ?></td>
      <td><?= h($r['graduate']) ?></td><td><?= h($r['note']) ?></td></tr>
  <?php endforeach; ?>
  </table></div>
<?php endif; ?>
