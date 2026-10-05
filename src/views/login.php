<?php if ($error): ?><p class="flash ng"><?= h($error) ?></p><?php endif; ?>
<form method="post" action="index.php?p=login" class="narrow">
  <?= csrf_field() ?>
  <label>パスワード <input type="password" name="password" autofocus required></label>
  <button>ログイン</button>
</form>
