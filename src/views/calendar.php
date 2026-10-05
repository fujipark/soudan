<?php
$prev = date('Y-m', strtotime("$first -1 month")); $next = date('Y-m', strtotime("$first +1 month"));
$startDow = (int)date('w', strtotime($first)); $days = (int)date('t', strtotime($first));
$tot = array_fill_keys($staff, 0);
?>
<p class="pager">
  <a href="index.php?p=calendar&ym=<?= $prev ?>">&laquo; 前月</a>
  <strong><?= h(date('Y年n月', strtotime($first))) ?></strong>
  <a href="index.php?p=calendar&ym=<?= $next ?>">次月 &raquo;</a>
  <a href="index.php?p=calendar">今月</a>
</p>
<p class="muted">各日の数字は <?= h(implode('／', $staff)) ?> の予約(学籍番号入力済み)件数です。</p>
<table class="cal">
  <tr><?php foreach (WEEKDAYS as $w): ?><th><?= $w ?></th><?php endforeach; ?></tr>
  <tr>
  <?php
  for ($i = 0; $i < $startDow; $i++) echo '<td class="empty"></td>';
  for ($d = 1; $d <= $days; $d++) {
      $date = sprintf('%s-%02d', $ym, $d);
      $dow = ($startDow + $d - 1) % 7;
      $cls = isset($holidays[$date]) ? 'hol' : ($dow === 0 ? 'sun' : ($dow === 6 ? 'sat' : ''));
      $nums = [];
      foreach ($staff as $s) { $n = $counts[$date][$s] ?? 0; $tot[$s] += $n; $nums[] = $n; }
      echo "<td class=\"$cls\"><a href=\"index.php?p=day&date=$date\"><span class=\"d\">$d</span><span class=\"n\">" . h(implode('／', $nums)) . "</span></a></td>";
      if ($dow === 6 && $d < $days) echo '</tr><tr>';
  }
  for ($i = ($startDow + $days) % 7; $i !== 0 && $i < 7; $i++) echo '<td class="empty"></td>';
  ?>
  </tr>
</table>
<p>月合計:
<?php foreach ($tot as $s => $n): ?><span class="chip"><?= h($s) ?> <?= $n ?>件</span><?php endforeach; ?>
</p>
