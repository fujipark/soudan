// 予約入力画面: 学籍番号を入力したら、同じ学生の直近の記録(来談〜経緯)を自動コピーする
// (元Excel VBA: Worksheet_Change と同等。入力済みの行は上書きしない)
(function () {
  var form = document.getElementById('dayform');
  if (!form) return;
  var FIELDS = ['visit', 'status', 'grade', 'dept', 'topic', 'family', 'referral', 'route'];

  form.addEventListener('change', function (ev) {
    var input = ev.target;
    if (!input.classList.contains('sno')) return;
    var tr = input.closest('tr');
    var q = new URLSearchParams({ p: 'api_last', student_no: input.value, date: form.dataset.date,
                                  staff: tr.dataset.staff, slot: tr.dataset.slot });
    fetch('index.php?' + q).then(function (r) { return r.json(); }).then(function (res) {
      input.value = res.student_no;                         // 半角・大文字に正規化
      if (!res.found) return;
      var sel = function (f) { return tr.querySelector('[name$="[' + f + ']"]'); };
      var empty = FIELDS.every(function (f) { return !sel(f).value; });
      if (!empty) return;                                   // 既に入力があれば上書きしない
      FIELDS.forEach(function (f) { sel(f).value = res.values[f]; });
      alert(res.date + ' の学籍番号【' + res.student_no + '】の内容をコピーしました。\n「状況」(初回/継続)は今回の内容に合わせて確認してください。');
    });
  });
})();
