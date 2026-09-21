<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';

$slug = $_GET['slug'] ?? '';

$stmt = $pdo->prepare('SELECT * FROM cases WHERE slug = ?');
$stmt->execute([$slug]);
$case = $stmt->fetch();

if (!$case) {
    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Case Not Found — The Bug Report</title>
<link rel="stylesheet" href="../style.css">
</head>
<body>
<main class="not-found">
<p>Case not found.</p>
<a href="index.php" class="button">Back to admin</a>
</main>
</body>
</html>
    <?php
    exit;
}

$errors = [];
$values = [
    'status' => $case['status'],
    'date_fixed' => $case['date_fixed'] ?? '',
    'issue_description' => $case['issue_description'] ?? '',
    'suggested_fix' => $case['suggested_fix'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['status'] = trim($_POST['status'] ?? '');
    $values['date_fixed'] = trim($_POST['date_fixed'] ?? '');
    $values['issue_description'] = trim($_POST['issue_description'] ?? '');
    $values['suggested_fix'] = trim($_POST['suggested_fix'] ?? '');

    if (!array_key_exists($values['status'], STATUS_LABELS)) {
        $errors['status'] = 'Select a valid status.';
    }

    if ($values['date_fixed'] !== '' && !DateTime::createFromFormat('Y-m-d', $values['date_fixed'])) {
        $errors['date_fixed'] = 'Enter a valid date (YYYY-MM-DD).';
    }

    $fixProofFilename = $case['screenshot_fix_proof'];
    $fixProofFile = $_FILES['screenshot_fix_proof'] ?? null;
    if ($fixProofFile !== null && ($fixProofFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        [$newFilename, $uploadError] = validateAndStoreUpload($fixProofFile, $slug, 'fix-proof', __DIR__ . '/../screenshots');
        if ($uploadError !== null) {
            $errors['screenshot_fix_proof'] = $uploadError;
        } else {
            $fixProofFilename = $newFilename;
        }
    }

    $afterFilename = $case['screenshot_after'];
    $afterFile = $_FILES['screenshot_after'] ?? null;
    if ($afterFile !== null && ($afterFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        [$newFilename, $uploadError] = validateAndStoreUpload($afterFile, $slug, 'after', __DIR__ . '/../screenshots');
        if ($uploadError !== null) {
            $errors['screenshot_after'] = $uploadError;
        } else {
            $afterFilename = $newFilename;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('UPDATE cases SET status = ?, date_fixed = ?, issue_description = ?, suggested_fix = ?, screenshot_fix_proof = ?, screenshot_after = ?, updated_at = CURRENT_TIMESTAMP WHERE slug = ?');
        $stmt->execute([
            $values['status'],
            $values['date_fixed'] !== '' ? $values['date_fixed'] : null,
            $values['issue_description'] !== '' ? $values['issue_description'] : null,
            $values['suggested_fix'] !== '' ? $values['suggested_fix'] : null,
            $fixProofFilename,
            $afterFilename,
            $slug,
        ]);
        header('Location: ../case.php?slug=' . urlencode($slug));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Case — The Bug Report</title>
<link rel="stylesheet" href="../style.css">
</head>
<body>
<header>
<a href="../index.php">The Bug Report</a>
</header>
<main class="admin-form">

<h1>Edit Case</h1>

<dl class="case-meta">
<dt>Company</dt>
<dd><?= htmlspecialchars($case['company'], ENT_QUOTES) ?></dd>

<dt>Issue</dt>
<dd><?= htmlspecialchars($case['issue_title'], ENT_QUOTES) ?></dd>

<dt>Page URL</dt>
<dd><a href="<?= htmlspecialchars($case['page_url'], ENT_QUOTES) ?>"><?= htmlspecialchars($case['page_url'], ENT_QUOTES) ?></a></dd>

<dt>Date Reported</dt>
<dd><?= htmlspecialchars($case['date_reported'], ENT_QUOTES) ?></dd>

<?php if (!empty($case['reported_via'])): ?>
<dt>Reported Via</dt>
<dd><?= htmlspecialchars($case['reported_via'], ENT_QUOTES) ?></dd>
<?php endif; ?>
</dl>

<form method="post" enctype="multipart/form-data">

<label for="status">Status</label>
<select id="status" name="status">
<?php foreach (STATUS_LABELS as $value => $label): ?>
<option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"<?= $values['status'] === $value ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
<?php endforeach; ?>
</select>
<?= fieldError($errors, 'status') ?>

<label for="date_fixed">Date Fixed</label>
<input type="date" id="date_fixed" name="date_fixed" value="<?= htmlspecialchars($values['date_fixed'], ENT_QUOTES) ?>">
<?= fieldError($errors, 'date_fixed') ?>

<label for="issue_description">Issue Description</label>
<textarea id="issue_description" name="issue_description" rows="4"><?= htmlspecialchars($values['issue_description'], ENT_QUOTES) ?></textarea>

<label for="suggested_fix">Suggested Fix</label>
<textarea id="suggested_fix" name="suggested_fix" rows="8" class="code"><?= htmlspecialchars($values['suggested_fix'], ENT_QUOTES) ?></textarea>

<label for="screenshot_fix_proof">Screenshot — Fix Proof</label>
<?php if (!empty($case['screenshot_fix_proof'])): ?>
<p class="current-file">Current: <a href="../screenshots/<?= urlencode($case['screenshot_fix_proof']) ?>"><?= htmlspecialchars($case['screenshot_fix_proof'], ENT_QUOTES) ?></a></p>
<?php endif; ?>
<input type="file" id="screenshot_fix_proof" name="screenshot_fix_proof" accept="image/*">
<?= fieldError($errors, 'screenshot_fix_proof') ?>

<label for="screenshot_after">Screenshot — After</label>
<?php if (!empty($case['screenshot_after'])): ?>
<p class="current-file">Current: <a href="../screenshots/<?= urlencode($case['screenshot_after']) ?>"><?= htmlspecialchars($case['screenshot_after'], ENT_QUOTES) ?></a></p>
<?php endif; ?>
<input type="file" id="screenshot_after" name="screenshot_after" accept="image/*">
<?= fieldError($errors, 'screenshot_after') ?>

<button type="submit">Save Changes</button>

</form>

</main>
</body>
</html>
