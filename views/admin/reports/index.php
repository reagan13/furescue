<?php

declare(strict_types=1);

require_once shared_path('search/search.php');

/** @var list<array<string, mixed>> $reports */
/** @var list<array<string, mixed>> $cases */
/** @var list<array<string, mixed>> $rescuers */

$rptStampCls = static function (?string $status): string {
    if ($status === 'dismissed' || $status === 'rejected') {
        return 'stamp--muted';
    }
    if ($status === 'assigned' || $status === 'pending_verification' || $status === 'open') {
        return 'stamp--coral';
    }
    return 'stamp--accent';
};

$rptBtnBase = 'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-[13px] font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:pointer-events-none disabled:opacity-50';
$rptBtnVariants = [
    'default' => 'bg-primary text-primary-foreground shadow hover:bg-primary/90',
    'outline' => 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
    'destructive' => 'bg-destructive text-destructive-foreground shadow-sm hover:bg-destructive/90',
];
$rptButton = static function (string $text, string $variant, string $icon, string $attrs) use ($rptBtnBase, $rptBtnVariants): string {
    $cls = trim($rptBtnBase . ' ' . $rptBtnVariants[$variant] . ' h-7 px-3');
    return '<button type="button" class="' . e($cls) . '" ' . $attrs . '><i data-lucide="' . e($icon) . '" class="icon"></i><span>' . e($text) . '</span></button>';
};
$rptLink = static function (string $href, string $text, string $variant, string $icon) use ($rptBtnBase, $rptBtnVariants): string {
    $cls = trim($rptBtnBase . ' ' . ($rptBtnVariants[$variant] ?? $rptBtnVariants['outline']) . ' h-7 px-3');
    return '<a href="' . e($href) . '" class="' . e($cls) . '" onclick="event.stopPropagation()"><i data-lucide="' . e($icon) . '" class="icon"></i><span>' . e($text) . '</span></a>';
};

$counts = [
    'all' => count($reports),
    'pending' => count(array_filter($reports, static fn(array $r) => ($r['status'] ?? null) === 'pending_verification')),
    'verified' => count(array_filter($reports, static fn(array $r) => ($r['status'] ?? null) === 'verified')),
    'dismissed' => count(array_filter($reports, static fn(array $r) => ($r['status'] ?? null) === 'dismissed')),
    'activeCases' => count(array_filter($cases, static fn(array $c) => ($c['status'] ?? '') !== 'resolved')),
    'resolvedCases' => count(array_filter($cases, static fn(array $c) => ($c['status'] ?? '') === 'resolved')),
];

$kpiData = [
    ['icon' => 'map-pin', 'value' => $counts['all'], 'label' => 'Total reports', 'tone' => 'jungle', 'trend' => '', 'trendTone' => 'neutral'],
    ['icon' => 'badge-check', 'value' => $counts['pending'], 'label' => 'Pending verify', 'tone' => 'coral', 'trend' => $counts['pending'] ? 'Needs You' : '', 'trendTone' => 'down'],
    ['icon' => 'file-check', 'value' => $counts['verified'], 'label' => 'Verified', 'tone' => 'ink', 'trend' => '', 'trendTone' => 'neutral'],
    ['icon' => 'file-x', 'value' => $counts['dismissed'], 'label' => 'Dismissed', 'tone' => 'ink', 'trend' => '', 'trendTone' => 'neutral'],
    ['icon' => 'clipboard-list', 'value' => $counts['activeCases'], 'label' => 'Active cases', 'tone' => 'sky', 'trend' => '', 'trendTone' => 'neutral'],
    ['icon' => 'check-circle-2', 'value' => $counts['resolvedCases'], 'label' => 'Resolved cases', 'tone' => 'jungle', 'trend' => '', 'trendTone' => 'neutral'],
];
$kpiTiles = '';
foreach ($kpiData as $k) {
    $label = (string) $k['label'];
    $value = (string) $k['value'];
    $aria = $label . ': ' . $value;
    $trend = (string) ($k['trend'] ?? '');
    $trendHtml = $trend !== ''
        ? '<p class="kpi-card__trend kpi-card__trend--' . e((string) ($k['trendTone'] ?? 'neutral')) . '">' . e($trend) . '</p>'
        : '';
    $kpiTiles .= '
  <article class="kpi-card" aria-label="' . e($aria) . '">
    <div class="kpi-card__icon kpi-card__icon--' . e((string) $k['tone']) . '"><i data-lucide="' . e((string) $k['icon']) . '"></i></div>
    <div class="kpi-card__body">
      <p class="kpi-card__label">' . e($label) . '</p>
      <p class="kpi-card__value">' . e($value) . '</p>
      ' . $trendHtml . '
    </div>
  </article>';
}

$caseByReport = [];
foreach ($cases as $c) {
    if (!empty($c['report_id'])) {
        $caseByReport[(string) $c['report_id']] = $c;
    }
}
$rescuerById = [];
foreach ($rescuers as $u) {
    $rescuerById[(string) ($u['id'] ?? '')] = $u;
}

usort($reports, static function (array $a, array $b): int {
    $tsA = (int) strtotime((string) ($a['created_at'] ?? ''));
    $tsB = (int) strtotime((string) ($b['created_at'] ?? ''));
    return $tsB <=> $tsA;
});

$PAGE_SIZE = pagination_default_page_size();

$actionLinksFor = static function (array $r) use ($caseByReport, $rptButton, $rptLink): string {
    $ridAttr = 'data-action="%s" data-id="' . e((string) ($r['id'] ?? '')) . '"';
    $status = (string) ($r['status'] ?? '');
    if ($status === 'pending_verification') {
        return $rptButton('Verify', 'default', 'badge-check', sprintf($ridAttr, 'verify'))
            . $rptButton('Dismiss', 'destructive', 'file-x', sprintf($ridAttr, 'dismiss'));
    }
    if ($status === 'verified') {
        $c = $caseByReport[(string) ($r['id'] ?? '')] ?? null;
        if ($c === null) {
            return '';
        }
        $cid = (string) ($c['id'] ?? '');
        $timeline = $rptButton('Timeline', 'outline', 'history', 'data-action="timeline" data-id="' . e((string) ($r['id'] ?? '')) . '" data-case="' . e($cid) . '"');
        $assignee = $c['assigned_rescuer_id'] ?? null;
        if (!$assignee) {
            return $rptButton('Assign rescuer', 'default', 'user-plus', 'data-action="assign" data-id="' . e((string) ($r['id'] ?? '')) . '" data-case="' . e($cid) . '"')
                . $timeline;
        }
        $cStatus = (string) ($c['status'] ?? '');
        $detailHref = '/admin/cases/case-detail.php?id=' . rawurlencode($cid);
        $detailVariant = $cStatus === 'in_progress' ? 'default' : 'outline';
        return $rptLink($detailHref, 'Case detail', $detailVariant, 'folder-open') . $timeline;
    }
    return '';
};

$rowsHtml = '';
$pageRows = array_slice($reports, 0, $PAGE_SIZE);
foreach ($pageRows as $r) {
    $c = $caseByReport[(string) ($r['id'] ?? '')] ?? null;
    $resolved = ($c !== null && (string) ($c['status'] ?? '') === 'resolved');
    $rowCls = $resolved ? 'row--resolved' : '';
    $rescuerName = '—';
    if ($c !== null && !empty($c['assigned_rescuer_id'])) {
        $ru = $rescuerById[(string) $c['assigned_rescuer_id']] ?? null;
        $rescuerName = ($ru !== null && !empty($ru['full_name'])) ? (string) $ru['full_name'] : 'Assigned';
    }
    $rowsHtml .= '
    <tr data-id="' . e((string) ($r['id'] ?? '')) . '" class="' . $rowCls . '">
      <td class="table-cell table-cell--mono table-cell--strong">' . e(short_id($r['id'] ?? null)) . '</td>
      <td class="table-cell">' . e(($r['address_text'] ?? null) ?: '—') . '</td>
      <td class="table-cell table-cell--mono table-cell--muted">' . e(short_id($r['resident_id'] ?? null)) . '</td>
      <td class="table-cell"><span class="stamp stamp--sm ' . e($rptStampCls((string) ($r['status'] ?? null))) . '">' . e(title_case($r['status'] ?? null)) . '</span></td>
      <td class="table-cell">' . ($c !== null ? '<span class="stamp stamp--sm ' . e($rptStampCls((string) ($c['status'] ?? null))) . '">' . e(title_case($c['status'] ?? null)) . '</span>' : '—') . '</td>
      <td class="table-cell">' . e($rescuerName) . '</td>
      <td class="table-cell table-cell--mono table-cell--muted">' . e(time_ago($r['created_at'] ?? null)) . '</td>
      <td class="table-cell table-cell--right table-cell--nowrap">
        <span class="table-actions">' . $actionLinksFor($r) . '</span>
      </td>
    </tr>';
}

if ($reports === []) {
    $tableInner = '<div class="queue-empty"><div class="empty-state"><i data-lucide="file-text"></i><span>No reports match.</span></div></div>';
} else {
    $pagination = '<div class="queue-pagination">' . pagination_bar(count($reports), $PAGE_SIZE, 1) . '</div>';
    $tableInner = '
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr class="table-head">
            <th>Case</th><th>Barangay</th><th>Reporter</th><th>Status</th><th>Case status</th><th>Rescuer</th><th>Submitted</th><th>Action</th>
          </tr>
        </thead>
        <tbody>' . $rowsHtml . '</tbody>
      </table>
    </div>
    ' . $pagination;
}

$exportCsvButton = (static function () use ($rptBtnBase, $rptBtnVariants): string {
    $cls = trim($rptBtnBase . ' ' . $rptBtnVariants['outline'] . ' h-8 px-4');
    return '<button type="button" class="' . e($cls) . '" data-export="csv"><i data-lucide="download" class="icon"></i><span>Export CSV</span></button>';
})();

$filterButtons = static function (array $defs, string $activeKey, array $chipCounts): string {
    $out = '';
    foreach ($defs as $f) {
        $isActive = $activeKey === $f['key'];
        $out .= '<button data-filter="' . e($f['key']) . '" class="q-btn' . ($isActive ? ' is-active' : '') . '">' . e($f['label']) . ' &middot; ' . e($chipCounts[$f['key']]) . '</button>';
    }
    return $out;
};
$chipCounts = [
    'all' => $counts['all'],
    'pending_verification' => $counts['pending'],
    'verified' => $counts['verified'],
    'dismissed' => $counts['dismissed'],
];
$filterDefs = [
    ['key' => 'all', 'label' => 'All'],
    ['key' => 'pending_verification', 'label' => 'Pending verification'],
    ['key' => 'verified', 'label' => 'Verified'],
    ['key' => 'dismissed', 'label' => 'Dismissed'],
];

$sortSelect = select_control('report-sort', [
    ['value' => 'newest', 'label' => 'Newest'],
    ['value' => 'assigned', 'label' => 'Assigned'],
    ['value' => 'verified', 'label' => 'Verified'],
], 'newest', 'Sort', '', '', 'report-sort-control');

$adminChildren = '
  <div class="page-head">
    <div>
      <span class="stamp stamp--coral">Rescue Management</span>
      <h1 class="page-title">Reports</h1>
      <p class="page-sub">Verify reports, assign rescuers, and track the full case workflow.</p>
    </div>
    <div class="page-head-actions">
      ' . $exportCsvButton . '
    </div>
  </div>'
    . '<div id="report-kpis" class="kpi-grid">' . $kpiTiles . '</div>'
    . '
  <div class="panel">
    <div class="panel-head">
      <div class="panel-title-wrap">
        <i data-lucide="map-pin"></i>
        <h2 class="panel-title">All reports</h2>
      </div>
      <div class="panel-head-tools">
        <div id="report-tabs-wrap">' . filter_tabs_html('report-tabs', $filterButtons($filterDefs, 'all', $chipCounts)) . '</div>
      </div>
    </div>
    <div id="report-filters">'
        . toolbar_html(
            search_control('report-search', 'Search case #, barangay, description…')
            . '<div class="report-sort">
      <label for="report-sort" class="report-sort-label">Sort</label>
      ' . $sortSelect . '
    </div>'
        )
        . '</div>
    <div id="report-table" class="panel-body">' . $tableInner . '</div>
  </div>';
