<?php

declare(strict_types=1);

require_once shared_path('search/search.php');

/** @var list<array<string, mixed>> $listings */

const LISTING_STATUS_LABELS = [
    'pending_review' => 'In review',
    'approved' => 'Live',
    'rejected' => 'Rejected',
];
const LISTING_PAGE_SIZE = 20;

$listingSpecies = static function (?string $species): string {
    $s = strtolower(trim((string) $species));
    if ($s === 'cat') {
        return 'Cat';
    }
    if ($s === 'dog') {
        return 'Dog';
    }
    return $s !== '' ? title_case($s) : '—';
};

$listingStamp = static function (?string $status): string {
    if ($status === 'pending_review') {
        return 'stamp--coral';
    }
    if ($status === 'rejected') {
        return 'stamp--muted';
    }
    return 'stamp--accent';
};

$listingButton = static function (string $text, string $variant, string $icon, string $attrs): string {
    if ($variant === 'destructive') {
        $cls = trim(BTN_BASE . ' bg-destructive text-destructive-foreground shadow-sm hover:bg-destructive/90 ' . BTN_SIZE_SM);
        $inner = '<i data-lucide="' . e($icon) . '" class="icon"></i><span>' . e($text) . '</span>';
        return '<button type="button" class="' . e($cls) . '" ' . $attrs . '>' . $inner . '</button>';
    }
    return button_html($text, $variant, 'sm', icon: $icon, attrs: $attrs);
};

$countStatus = static function (string $status) use ($listings): int {
    return count(array_filter($listings, static fn(array $row) => ($row['status'] ?? '') === $status));
};
$counts = [
    'all' => count($listings),
    'pending' => $countStatus('pending_review'),
    'live' => $countStatus('approved'),
    'rejected' => $countStatus('rejected'),
];

$kpiData = [
    ['icon' => 'home', 'value' => $counts['all'], 'label' => 'Total listings', 'tone' => 'jungle'],
    [
        'icon' => 'clock',
        'value' => $counts['pending'],
        'label' => 'In review',
        'tone' => 'coral',
        'trend' => $counts['pending'] ? 'Needs You' : '',
        'trendTone' => 'down',
    ],
    ['icon' => 'badge-check', 'value' => $counts['live'], 'label' => 'Live', 'tone' => 'sky'],
    ['icon' => 'file-x', 'value' => $counts['rejected'], 'label' => 'Rejected', 'tone' => 'ink'],
];
$kpiTiles = '';
foreach ($kpiData as $k) {
    $kpiTiles .= kpi_card_html($k + ['compact' => true]);
}

$filterDefs = [
    ['key' => 'all', 'label' => 'All', 'count' => $counts['all']],
    ['key' => 'pending_review', 'label' => 'In review', 'count' => $counts['pending']],
    ['key' => 'approved', 'label' => 'Live', 'count' => $counts['live']],
    ['key' => 'rejected', 'label' => 'Rejected', 'count' => $counts['rejected']],
];
$tabButtons = '';
foreach ($filterDefs as $f) {
    $activeCls = $f['key'] === 'all' ? ' is-active' : '';
    $tabButtons .= '
        <button data-filter="' . e($f['key']) . '" class="q-btn' . $activeCls . '">' . e($f['label']) . ' &middot; ' . e($f['count']) . '</button>';
}

$pageRows = array_slice($listings, 0, LISTING_PAGE_SIZE);
$rowsHtml = '';
foreach ($pageRows as $row) {
    $status = (string) ($row['status'] ?? '');
    $name = trim((string) ($row['animal_name'] ?? '')) !== '' ? (string) $row['animal_name'] : 'Unnamed';
    $species = $listingSpecies($row['species'] ?? null);
    $poster = trim((string) ($row['poster_name'] ?? '')) !== '' ? (string) $row['poster_name'] : 'Unknown poster';
    $label = LISTING_STATUS_LABELS[$status] ?? title_case($status);
    $actions = '';
    if ($status === 'pending_review') {
        $idAttr = 'data-id="' . e((string) ($row['id'] ?? '')) . '"';
        $actions = $listingButton('Approve', 'default', 'badge-check', 'data-action="approve" ' . $idAttr)
            . $listingButton('Reject', 'destructive', 'file-x', 'data-action="reject" ' . $idAttr);
    }
    $rowsHtml .= '
    <tr data-id="' . e((string) ($row['id'] ?? '')) . '">
      <td class="table-cell table-cell--strong">' . e($name) . '</td>
      <td class="table-cell">' . e($species) . '</td>
      <td class="table-cell">' . e($poster) . '</td>
      <td class="table-cell"><span class="stamp stamp--sm ' . e($listingStamp($status)) . '">' . e($label) . '</span></td>
      <td class="table-cell table-cell--mono table-cell--muted">' . e(time_ago($row['created_at'] ?? null)) . '</td>
      <td class="table-cell table-cell--right table-cell--nowrap">
        <span class="table-actions">' . $actions . '</span>
      </td>
    </tr>';
}

if ($listings === []) {
    $tableInner = '<div class="queue-empty">' . empty_state('home', 'No adoption listings yet.') . '</div>';
} else {
    $pagination = '<div class="queue-pagination">' . pagination_bar(count($listings), LISTING_PAGE_SIZE, 1) . '</div>';
    $tableInner = '
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr class="table-head">
            <th>Animal</th><th>Species</th><th>Poster</th><th>Status</th><th>Posted</th><th class="table-cell--right">Action</th>
          </tr>
        </thead>
        <tbody>' . $rowsHtml . '</tbody>
      </table>
    </div>
    ' . $pagination;
}

$adminChildren = '
  <div class="page-head">
    <div>
      <span class="stamp stamp--accent">Adoption</span>
      <h1 class="page-title">Listings</h1>
      <p class="page-sub">Review community adoption posts. Approving a listing sets the animal as available.</p>
    </div>
    <div class="page-head-actions">
      ' . button_html('Export CSV', 'outline', icon: 'download', attrs: 'data-export="csv"') . '
    </div>
  </div>
  <div id="listing-kpis" class="kpi-grid kpi-grid--compact">' . $kpiTiles . '</div>
  <div class="panel">
    <div class="panel-head">
      <div class="panel-title-wrap">
        <i data-lucide="home"></i>
        <h2 class="panel-title" id="listing-panel-title">All listings</h2>
      </div>
      <div class="panel-head-tools">
        <div id="listing-tabs-wrap">' . filter_tabs_html('listing-tabs', $tabButtons) . '</div>
      </div>
    </div>
    <div id="listing-filters">
      ' . toolbar_html(search_control('listing-search', 'Search animal, species, poster…')) . '
    </div>
    <div id="listing-table" class="panel-body">' . $tableInner . '</div>
  </div>';
