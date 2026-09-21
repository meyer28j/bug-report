<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';

$errors = [];
$values = [
    'company' => '',
    'page_url' => '',
    'issue_title' => '',
    'issue_description' => '',
    'reported_via' => '',
    'date_reported' => date('Y-m-d'),
    'suggested_fix' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['company'] = trim($_POST['company'] ?? '');
    $values['page_url'] = trim($_POST['page_url'] ?? '');
    $values['issue_title'] = trim($_POST['issue_title'] ?? '');
    $values['issue_description'] = trim($_POST['issue_description'] ?? '');
    $values['reported_via'] = trim($_POST['reported_via'] ?? '');
    $values['date_reported'] = trim($_POST['date_reported'] ?? '');
    $values['suggested_fix'] = trim($_POST['suggested_fix'] ?? '');

    if ($values['company'] === '') {
        $errors['company'] = 'Company is required.';
    } elseif (mb_strlen($values['company']) > 200) {
        $errors['company'] = 'Company must be 200 characters or fewer.';
    }

    if ($values['page_url'] === '') {
        $errors['page_url'] = 'Page URL is required.';
    } elseif (!filter_var($values['page_url'], FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $values['page_url'])) {
        $errors['page_url'] = 'Enter a valid http(s) URL.';
    }

    if ($values['issue_title'] === '') {
        $errors['issue_title'] = 'Issue title is required.';
    } elseif (mb_strlen($values['issue_title']) > 200) {
        $errors['issue_title'] = 'Issue title must be 200 characters or fewer.';
    }

    if ($values['reported_via'] !== '' && mb_strlen($values['reported_via']) > 100) {
        $errors['reported_via'] = 'Reported via must be 100 characters or fewer.';
    }

    if ($values['date_reported'] === '') {
        $errors['date_reported'] = 'Date reported is required.';
    } elseif (!DateTime::createFromFormat('Y-m-d', $values['date_reported'])) {
        $errors['date_reported'] = 'Enter a valid date (YYYY-MM-DD).';
    }

    $screenshotFile = $_FILES['screenshot_bug'] ?? null;
    $filename = null;

    if ($screenshotFile === null || ($screenshotFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors['screenshot_bug'] = 'Bug screenshot is required.';
    }

    if (empty($errors)) {
        $slug = generateSlug($pdo, $values['date_reported'], $values['company']);
        [$filename, $uploadError] = validateAndStoreUpload($screenshotFile, $slug, 'bug', __DIR__ . '/../screenshots');
        if ($uploadError !== null) {
            $errors['screenshot_bug'] = $uploadError;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO cases (slug, company, page_url, issue_title, issue_description, reported_via, date_reported, screenshot_bug, suggested_fix) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $slug,
            $values['company'],
            $values['page_url'],
            $values['issue_title'],
            $values['issue_description'] !== '' ? $values['issue_description'] : null,
            $values['reported_via'] !== '' ? $values['reported_via'] : null,
            $values['date_reported'],
            $filename,
            $values['suggested_fix'] !== '' ? $values['suggested_fix'] : null,
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
<title>New Case — The Bug Report</title>
<link rel="stylesheet" href="../style.css">
</head>
<body>
<header>
<a href="../index.php">The Bug Report</a>
</header>
<main class="admin-form">

<h1>New Case</h1>

<form method="post" enctype="multipart/form-data">

<label for="company">Company</label>
<input type="text" id="company" name="company" value="<?= htmlspecialchars($values['company'], ENT_QUOTES) ?>" maxlength="200" required>
<?= fieldError($errors, 'company') ?>

<label for="page_url">Page URL</label>
<input type="url" id="page_url" name="page_url" value="<?= htmlspecialchars($values['page_url'], ENT_QUOTES) ?>" required>
<?= fieldError($errors, 'page_url') ?>

<label for="issue_title">Issue Title</label>
<input type="text" id="issue_title" name="issue_title" value="<?= htmlspecialchars($values['issue_title'], ENT_QUOTES) ?>" maxlength="200" required>
<?= fieldError($errors, 'issue_title') ?>

<label for="date_reported">Date Reported</label>
<input type="date" id="date_reported" name="date_reported" value="<?= htmlspecialchars($values['date_reported'], ENT_QUOTES) ?>" required>
<?= fieldError($errors, 'date_reported') ?>

<label for="reported_via">Reported Via</label>
<input type="text" id="reported_via" name="reported_via" value="<?= htmlspecialchars($values['reported_via'], ENT_QUOTES) ?>" maxlength="100">
<?= fieldError($errors, 'reported_via') ?>

<label for="issue_description">Issue Description</label>
<textarea id="issue_description" name="issue_description" rows="4"><?= htmlspecialchars($values['issue_description'], ENT_QUOTES) ?></textarea>

<label for="screenshot_bug">Screenshot — Bug</label>
<input type="file" id="screenshot_bug" name="screenshot_bug" accept="image/*" required>
<?= fieldError($errors, 'screenshot_bug') ?>

<label for="suggested_fix">Suggested Fix</label>
<textarea id="suggested_fix" name="suggested_fix" rows="8" class="code"><?= htmlspecialchars($values['suggested_fix'], ENT_QUOTES) ?></textarea>

<button type="submit">Add Case</button>

</form>

</main>
</body>
</html>
