<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';

$cases = $pdo->query('SELECT slug, company, status FROM cases ORDER BY date_reported DESC')->fetchAll();

renderPageHead('Admin — The Bug Report', '../style.css');
renderSiteHeader('../index.php');
?>
<main class="admin-list">

<h1>Admin</h1>
<a href="new.php" class="button">New Case</a>

<?php if (empty($cases)): ?>
<p class="empty-state">No cases yet.</p>
<?php else: ?>
<ul class="case-list">
<?php foreach ($cases as $case): ?>
<li class="case-row">
<span class="case-company"><?= htmlspecialchars($case['company'], ENT_QUOTES) ?></span>
<?= renderStatusBadge($case['status']) ?>
<a class="case-edit" href="edit.php?slug=<?= urlencode($case['slug']) ?>">Edit</a>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

</main>
</body>
</html>
