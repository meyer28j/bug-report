<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';

$slug = $_GET['slug'] ?? '';

$stmt = $pdo->prepare('SELECT * FROM cases WHERE slug = ?');
$stmt->execute([$slug]);
$case = $stmt->fetch();

if (!$case) {
    render404('style.css', 'index.php', 'Back to home');
}

renderPageHead($case['company'] . ' — ' . $case['issue_title'] . ' — The Bug Report', 'style.css');
renderSiteHeader('index.php');
?>
<main class="case">

<h1><?= htmlspecialchars($case['company'], ENT_QUOTES) ?> — <?= htmlspecialchars($case['issue_title'], ENT_QUOTES) ?></h1>

<?= renderStatusBadge($case['status']) ?>

<dl class="case-meta">
<dt>Date Reported</dt>
<dd><?= htmlspecialchars($case['date_reported'], ENT_QUOTES) ?></dd>

<dt>Company</dt>
<dd><?= htmlspecialchars($case['company'], ENT_QUOTES) ?></dd>

<dt>Page URL</dt>
<dd><a href="<?= htmlspecialchars($case['page_url'], ENT_QUOTES) ?>"><?= htmlspecialchars($case['page_url'], ENT_QUOTES) ?></a></dd>

<?php if (!empty($case['reported_via'])): ?>
<dt>Reported Via</dt>
<dd><?= htmlspecialchars($case['reported_via'], ENT_QUOTES) ?></dd>
<?php endif; ?>
</dl>

<?php if (!empty($case['issue_description'])): ?>
<p class="issue-description"><?= htmlspecialchars($case['issue_description'], ENT_QUOTES) ?></p>
<?php endif; ?>

<figure class="screenshot">
<a href="screenshots/<?= urlencode($case['screenshot_bug']) ?>">
<img src="screenshots/<?= htmlspecialchars($case['screenshot_bug'], ENT_QUOTES) ?>" alt="Screenshot of the bug">
</a>
<figcaption>Bug</figcaption>
</figure>

<?php if (!empty($case['suggested_fix'])): ?>
<h2>Suggested Fix</h2>
<pre><code><?= htmlspecialchars($case['suggested_fix'], ENT_QUOTES) ?></code></pre>
<?php endif; ?>

<?php if (!empty($case['screenshot_fix_proof'])): ?>
<figure class="screenshot">
<a href="screenshots/<?= urlencode($case['screenshot_fix_proof']) ?>">
<img src="screenshots/<?= htmlspecialchars($case['screenshot_fix_proof'], ENT_QUOTES) ?>" alt="Screenshot of the fix proof">
</a>
<figcaption>Fix Proof</figcaption>
</figure>
<?php endif; ?>

<?php if (!empty($case['screenshot_after'])): ?>
<figure class="screenshot">
<a href="screenshots/<?= urlencode($case['screenshot_after']) ?>">
<img src="screenshots/<?= htmlspecialchars($case['screenshot_after'], ENT_QUOTES) ?>" alt="Screenshot of the deployed fix">
</a>
<figcaption>After</figcaption>
</figure>
<?php endif; ?>

<?= renderTimeline($case['date_reported'], $case['date_fixed']) ?>

</main>
</body>
</html>
