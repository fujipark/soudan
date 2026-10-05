<form method="get" class="inline">
  <input type="hidden" name="p" value="student">
  学籍番号 <input name="no" value="<?= h($no) ?>" size="12">
  <button>表示</button>
  <?php if ($no === ''): ?>
    期間 <input type="month" name="from" value="<?= h($fromYm) ?>"> ～ <input type="month" name="to" value="<?= h($toYm) ?>">
  <?php endif; ?>
</form>
<?php if ($no !== ''): ?>
  <h2><?= h($no) ?> <?= $carry ? '<span class="chip">前年度から継続</span>' : '' ?></h2>
  <table>
    <tr><th>#</th><th>日付</th><th>時間</th><th>担当</th><th>来談</th><th>状況</th><th>学年</th><th>学科</th><th>相談内容</th><th>連携</th><th>経緯</th><th>備考</th></tr>
  <?php foreach ($history as $i => $r): ?>
    <tr><td><?= $i + 1 ?></td><td><a href="index.php?p=day&date=<?= h($r['date']) ?>"><?= h($r['date']) ?></a></td>
      <td><?= h($r['slot']) ?></td><td><?= h($r['staff']) ?></td><td><?= h($r['visit']) ?></td><td><?= h($r['status']) ?></td>
      <td><?= h($r['grade']) ?></td><td><?= h($r['dept']) ?></td><td><?= h($r['topic']) ?></td><td><?= h($r['referral']) ?></td>
      <td><?= h($r['route']) ?></td><td><?= h($r['note']) ?></td></tr>
  <?php endforeach; ?>
  </table>
<?php else: ?>
  <p><?= count($summary) ?>人</p>
  <table>
    <tr><th>学籍番号</th><th>回数</th><th>初回</th><th>直近</th><th></th></tr>
  <?php foreach ($summary as $s): ?>
    <tr><td><a href="index.php?p=student&no=<?= urlencode($s['student_no']) ?>"><?= h($s['student_no']) ?></a></td>
      <td><?= $s['n'] ?></td><td><?= h($s['first_date']) ?></td><td><?= h($s['last_date']) ?></td>
      <td><?= isset($carrySet[$s['student_no']]) ? '<span class="chip">継続</span>' : '' ?></td></tr>
  <?php endforeach; ?>
  </table>
<?php endif; ?>
