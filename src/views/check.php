<form method="get" class="inline">
  <input type="hidden" name="p" value="check">
  期間 <input type="month" name="from" value="<?= h($fromYm) ?>"> ～ <input type="month" name="to" value="<?= h($toYm) ?>">
  <label><input type="checkbox" name="only_errors" value="1"<?= !empty($_GET['only_errors']) ? ' checked' : '' ?>> エラーのみ</label>
  <button>確認</button>
</form>
<p><?= count($items) ?>件 (<span class="ng">エラー</span> = 修正が必要 / <span class="warn">確認</span> = 任意項目の未入力)</p>
<table>
  <tr><th>日付</th><th>時間</th><th>担当</th><th>学籍番号</th><th>回数</th><th>来談</th><th>状況</th><th>学年</th><th>学科</th><th>相談内容</th><th>内容</th></tr>
<?php foreach ($items as $i): ?>
  <tr>
    <td><a href="index.php?p=day&date=<?= h($i['date']) ?>"><?= h($i['date']) ?></a></td>
    <td><?= h($i['slot']) ?></td><td><?= h($i['staff']) ?></td><td><?= h($i['student_no']) ?></td><td><?= $i['count'] ?: '' ?></td>
    <td><?= h($i['visit']) ?></td><td><?= h($i['status']) ?></td><td><?= h($i['grade']) ?></td><td><?= h($i['dept']) ?></td><td><?= h($i['topic']) ?></td>
    <td><?php foreach ($i['errors'] as $e): ?><span class="tag ng"><?= h($e) ?></span> <?php endforeach; ?>
        <?php foreach ($i['notices'] as $e): ?><span class="tag warn"><?= h($e) ?></span> <?php endforeach; ?></td>
  </tr>
<?php endforeach; ?>
</table>
