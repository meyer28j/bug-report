<?php

const ALLOWED_UPLOAD_MIME_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

// Top-down ant, head pointing up. Sized by CSS.
const ANT_SVG = '<svg viewBox="0 0 24 24">'
    . '<g fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">'
    . '<path d="M11 3.6Q9.8 1.8 8.2 1.2M13 3.6Q14.2 1.8 15.8 1.2"/>'
    . '<path d="M11 9L8 7.5L6.5 5M11 10.2L7.5 10.5L5.5 12M11 11.2L8.5 13L7.5 16"/>'
    . '<path d="M13 9L16 7.5L17.5 5M13 10.2L16.5 10.5L18.5 12M13 11.2L15.5 13L16.5 16"/>'
    . '</g>'
    . '<g fill="currentColor">'
    . '<ellipse cx="12" cy="5" rx="2.2" ry="2"/>'
    . '<ellipse cx="12" cy="10" rx="1.6" ry="2.4"/>'
    . '<ellipse cx="12" cy="17" rx="3" ry="4.2"/>'
    . '</g></svg>';

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
 * @param string $root Path from the current page to src/ ('' or '../').
 * @param bool $isHome Wrap the title in the page's <h1> (feed only; other pages have their own).
 */
function renderSiteHeader(string $root, bool $isHome = false): void
{
    // The ant logo is bugs.js's easter-egg trigger, and the source of its ant markup.
    $title = '<span class="site-logo" aria-hidden="true">' . ANT_SVG . '</span> '
        . '<a href="' . htmlspecialchars($root, ENT_QUOTES) . 'index.php">The Bug Report</a>';
    $tag = $isHome ? 'h1' : 'div';
    echo "<header>\n<$tag class=\"site-title\">$title</$tag>\n"
        . '<script src="' . htmlspecialchars($root, ENT_QUOTES) . 'bugs.js" defer></script>' . "\n</header>\n";
}

/**
 * @param string $root Path from the current page to src/ ('' or '../').
 */
function render404(string $root, string $backHref, string $backLabel): void
{
    http_response_code(404);
    renderPageHead('Case Not Found — The Bug Report', $root . 'style.css');
    renderSiteHeader($root);
    ?>
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
 * CSRF guard for admin POSTs. Browsers re-send cached Basic Auth credentials on
 * cross-site form posts, so reject any POST whose Origin isn't this host.
 * A missing Origin (non-browser clients like curl) is allowed — they'd need the credentials anyway.
 */
function rejectCrossOriginPost(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    if ($origin !== null && preg_replace('~^https?://~i', '', $origin) !== ($_SERVER['HTTP_HOST'] ?? '')) {
        http_response_code(403);
        exit('Forbidden');
    }
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
