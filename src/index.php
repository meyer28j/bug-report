<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';

$cases = $pdo->query('SELECT slug, company, issue_title, status, date_reported FROM cases ORDER BY date_reported DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>The Bug Report</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header>
<h1>The Bug Report</h1>
</header>
<main>
<?php if (empty($cases)): ?>
<p class="empty-state">No cases yet.</p>
<?php else: ?>
<ul class="case-list">
<?php foreach ($cases as $case): ?>
<li class="case-row">
<span class="case-date"><?= htmlspecialchars($case['date_reported'], ENT_QUOTES) ?></span>
<span class="case-company"><?= htmlspecialchars($case['company'], ENT_QUOTES) ?></span>
<a class="case-title" href="case.php?slug=<?= urlencode($case['slug']) ?>"><?= htmlspecialchars($case['issue_title'], ENT_QUOTES) ?></a>
<?= renderStatusBadge($case['status']) ?>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</main>
</body>
</html>
