<?php

const STATUS_LABELS = [
    'reported' => 'Reported',
    'fixed' => 'Fixed',
    'no_response' => 'No Response',
    'wont_fix' => "Won't Fix",
];

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
