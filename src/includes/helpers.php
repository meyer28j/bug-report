<?php

const ALLOWED_UPLOAD_MIME_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

const STATUS_LABELS = [
    'reported' => 'Reported',
    'fixed' => 'Fixed',
    'no_response' => 'No Response',
    'wont_fix' => "Won't Fix",
];

function fieldError(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<span class="field-error">' . htmlspecialchars($errors[$field], ENT_QUOTES) . '</span>' : '';
}

function renderStatusBadge(string $status): string
{
    $label = STATUS_LABELS[$status] ?? $status;
    $class = htmlspecialchars($status, ENT_QUOTES);
    return '<span class="badge badge-' . $class . '">' . htmlspecialchars($label, ENT_QUOTES) . '</span>';
}

function renderTimeline(?string $dateReported, ?string $dateFixed): string
{
    $milestones = [
        'Reported' => $dateReported,
        'Fixed' => $dateFixed,
    ];

    $items = [];
    foreach ($milestones as $label => $date) {
        $value = $date !== null ? htmlspecialchars($date, ENT_QUOTES) : '—';
        $items[] = '<div class="timeline-step"><span class="timeline-label">' . $label . '</span><span class="timeline-date">' . $value . '</span></div>';
    }

    return '<div class="timeline">' . implode('', $items) . '</div>';
}

/**
 * Validates and stores an uploaded screenshot.
 *
 * @return array{0: ?string, 1: ?string} [$storedFilename, $error]. Both null means no file was submitted.
 */
function validateAndStoreUpload(?array $file, string $slug, string $suffix, string $destDir): array
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Upload failed.'];
    }

    if ($file['size'] > MAX_UPLOAD_BYTES) {
        return [null, 'File exceeds the 5MB size limit.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = mime_content_type($file['tmp_name']);
    $expectedExt = ALLOWED_UPLOAD_MIME_TYPES[$mime] ?? null;
    $validExts = $expectedExt === 'jpg' ? ['jpg', 'jpeg'] : [$expectedExt];

    if ($expectedExt === null || !in_array($ext, $validExts, true)) {
        return [null, 'File must be a JPG, PNG, or WEBP image.'];
    }

    $filename = $slug . '-' . $suffix . '.' . $expectedExt;
    if (!move_uploaded_file($file['tmp_name'], $destDir . '/' . $filename)) {
        return [null, 'Failed to save uploaded file.'];
    }

    return [$filename, null];
}

function renderPageHead(string $title, string $cssHref): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title, ENT_QUOTES) ?></title>
<link rel="stylesheet" href="<?= htmlspecialchars($cssHref, ENT_QUOTES) ?>">
</head>
<body>
<?php
}

/**
 * @param ?string $homeHref Link target, or null to render the plain home-page heading (no link).
 */
function renderSiteHeader(?string $homeHref): void
{
    if ($homeHref === null) {
        echo "<header>\n<h1>The Bug Report</h1>\n</header>\n";
    } else {
        echo '<header>' . "\n" . '<a href="' . htmlspecialchars($homeHref, ENT_QUOTES) . '">The Bug Report</a>' . "\n</header>\n";
    }
}

function render404(string $cssHref, string $backHref, string $backLabel): void
{
    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Case Not Found — The Bug Report</title>
<link rel="stylesheet" href="<?= htmlspecialchars($cssHref, ENT_QUOTES) ?>">
</head>
<body>
<main class="not-found">
<p>Case not found.</p>
<a href="<?= htmlspecialchars($backHref, ENT_QUOTES) ?>" class="button"><?= htmlspecialchars($backLabel, ENT_QUOTES) ?></a>
</main>
</body>
</html>
    <?php
    exit;
}

/**
 * Validates and stores an optional replacement screenshot upload for edit.php,
 * keeping the existing filename when no new file was submitted.
 *
 * @return array{0: ?string, 1: ?string} [$filename, $error]. $filename is the existing or new filename.
 */
function handleOptionalUpload(string $field, string $suffix, ?string $currentFilename, string $slug, array &$errors): ?string
{
    $file = $_FILES[$field] ?? null;
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $currentFilename;
    }

    [$newFilename, $uploadError] = validateAndStoreUpload($file, $slug, $suffix, __DIR__ . '/../screenshots');
    if ($uploadError !== null) {
        $errors[$field] = $uploadError;
        return $currentFilename;
    }

    return $newFilename;
}

function generateSlug(PDO $pdo, string $dateReported, string $company): string
{
    $companySlug = strtolower(trim($company));
    $companySlug = preg_replace('/[^a-z0-9]+/', '-', $companySlug);
    $companySlug = trim($companySlug, '-');

    $base = $dateReported . '-' . $companySlug;

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM cases WHERE slug = ?');

    $slug = $base;
    $suffix = 2;
    while (true) {
        $stmt->execute([$slug]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $suffix;
        $suffix++;
    }
}
