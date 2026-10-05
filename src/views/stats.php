<?php
$labels = array_map(fn($c) => $c[0], Master::CATEGORIES);
function crosstab_table(string $r, string $c, array $x, array $labels): void { ?>
  <h3><?= h($labels[$r]) ?> × <?= h($labels[$c]) ?></h3>
  <div class="scroll"><table class="num">
    <tr><th></th><?php foreach ($x['cols'] as $col): ?><th><?= h($col) ?></th><?php endforeach; ?><th>合計</th></tr>
    <?php foreach ($x['rows'] as $row): ?>
      <tr><th><?= h($row) ?></th>
        <?php foreach ($x['cols'] as $col): ?><td><?= $x['cells'][$row][$col] ?? 0 ?></td><?php endforeach; ?>
        <td class="sum"><?= $x['rt'][$row] ?></td></tr>
    <?php endforeach; ?>
    <tr class="sum"><th>合計</th><?php foreach ($x['cols'] as $col): ?><td><?= $x['ct'][$col] ?></td><?php endforeach; ?><td><?= $x['total'] ?></td></tr>
  </table></div>
<?php } ?>
<form method="get" class="inline">
  <input type="hidden" name="p" value="stats">
  集計期間 <input type="month" name="from" value="<?= h($fromYm) ?>"> ～ <input type="month" name="to" value="<?= h($toYm) ?>">
  <button>集計</button>
</form>

<h2>1. 学科 × 学年 × 相談内容</h2>
<p class="muted">実人数 = 学籍番号の重複を除いた人数、のべ人数 = 相談件数(学科・学年ごと)</p>
<div class="scroll"><table class="num">
  <tr><th>学科</th><th>学年</th><?php foreach ($main['topics'] as $t): ?><th><?= h($t) ?></th><?php endforeach; ?><th>初回</th><th>実人数</th><th>のべ人数</th></tr>
<?php foreach ($main['depts'] as $dept => $d): $n = count($d['rows']); $i = 0; ?>
  <?php foreach ($d['rows'] as $grade => $row): ?>
    <tr><?php if ($i++ === 0): ?><th rowspan="<?= $n + 1 ?>"><?= h($dept) ?></th><?php endif; ?>
      <th><?= h($grade) ?></th>
      <?php foreach ($main['topics'] as $t): ?><td><?= $row[$t] ?></td><?php endforeach; ?>
      <td><?= $row['_first'] ?></td><td><?= $row['_uniq'] ?></td><td><?= $row['_total'] ?></td></tr>
  <?php endforeach; ?>
  <tr class="sum"><th>小計</th>
    <?php foreach ($main['topics'] as $t): ?><td><?= $d['sub'][$t] ?></td><?php endforeach; ?>
    <td><?= $d['sub']['_first'] ?></td><td><?= $d['sub']['_uniq'] ?></td><td><?= $d['sub']['_total'] ?></td></tr>
<?php endforeach; ?>
  <tr class="sum"><th colspan="2">総合計</th>
    <?php foreach ($main['topics'] as $t): ?><td><?= $main['grand'][$t] ?></td><?php endforeach; ?>
    <td><?= $main['grand']['_first'] ?></td><td><?= $main['grand']['_uniq'] ?></td><td><?= $main['grand']['_total'] ?></td></tr>
</table></div>

<h2>2. 連携・家族相談・卒業生</h2>
<table class="num"><?php foreach ($linkage as $k => $v): ?><tr><th><?= h($k) ?></th><td><?= $v ?></td></tr><?php endforeach; ?></table>

<h2>3. 月別・担当別 件数</h2>
<table class="num">
  <tr><th>年月</th><?php foreach (Master::all('staff') as $s): ?><th><?= h($s) ?></th><?php endforeach; ?><th>合計</th></tr>
<?php foreach ($monthly as $ym => $by): ?>
  <tr><th><?= h($ym) ?></th><?php foreach (Master::all('staff') as $s): ?><td><?= $by[$s] ?? 0 ?></td><?php endforeach; ?><td class="sum"><?= array_sum($by) ?></td></tr>
<?php endforeach; ?>
</table>

<h2>4. クロス集計</h2>
<?php foreach ($tables as [$r, $c, $x]) crosstab_table($r, $c, $x, $labels); ?>
