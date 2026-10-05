<section>
  <h2>年度の切り替え</h2>
  <ol>
    <li><a href="index.php?p=admin&export=1&from=<?= h($fromYm) ?>&to=<?= h($toYm) ?>">予約データをCSVでバックアップ</a>
        (期間: <?= h($fromYm) ?> ～ <?= h($toYm) ?>。変更は<a href="index.php?p=student">学生別一覧</a>等の期間指定と同じ <code>from</code>/<code>to</code>)</li>
    <li>
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="carryover">
        <button>予約内の学籍番号を「継続学生」に追加</button>
        <span class="muted">既存の継続学生は消えません。翌年度の「初回/継続」チェックに使います。</span></form>
    </li>
    <li>
      <form method="post" class="inline" onsubmit="return confirm('指定期間の予約を削除します。バックアップは済んでいますか?')">
        <?= csrf_field() ?><input type="hidden" name="action" value="purge">
        削除期間 <input type="date" name="from" value="<?= h($fyStart) ?>" required> ～ <input type="date" name="to" value="<?= h($fyEnd) ?>" required>
        確認のため「削除」と入力 <input name="confirm" size="4" required>
        <button class="danger">予約を削除</button></form>
    </li>
  </ol>
</section>
<section>
  <h2>CSV取り込み (Excelからの移行など)</h2>
  <p class="muted">列: <?= h(implode(', ', Importer::HEADER)) ?>(UTF-8)。同じ 日付・担当・時間 の枠は上書きされます。</p>
  <form method="post" enctype="multipart/form-data" class="inline"><?= csrf_field() ?>
    <input type="hidden" name="action" value="import"><input type="file" name="csv" accept=".csv" required> <button>取り込み</button></form>
</section>
<section>
  <h2>マスタ(選択肢)の編集</h2>
  <p class="muted">1行に1項目。並びが画面と集計表の並びになります。<b>名称を変えると過去データとの対応が外れる</b>ので、変更は新年度の開始前に。祝日は <code>YYYY-MM-DD</code> 形式。</p>
  <?php foreach (Master::CATEGORIES as $cat => [$label]): ?>
    <form method="post" class="master"><?= csrf_field() ?>
      <input type="hidden" name="action" value="master"><input type="hidden" name="cat" value="<?= h($cat) ?>">
      <label><b><?= h($label) ?></b><br><textarea name="values" rows="<?= max(3, min(12, count(Master::all($cat)) + 1)) ?>"><?= h(implode("\n", Master::all($cat))) ?></textarea></label>
      <button>更新</button>
    </form>
  <?php endforeach; ?>
</section>
