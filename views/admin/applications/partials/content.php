<?php

declare(strict_types=1);

require_once shared_path('search/search.php');

/** @var array<int, array<string, mixed>> $items */
/** @var array<string, int> $counts */
/** @var string $loadError */
/** @var callable $appButton */
/** @var callable $stampCls */

$exportCsvButton = $appButton('Export CSV', 'outline', 'default', 'download', 'data-export="csv"');

$kpiData = [
    ['icon' => 'file-check', 'value' => $counts['all'], 'label' => 'Total applications', 'tone' => 'jungle'],
    [
        'icon' => 'clock',
        'value' => $counts['pending'],
        'label' => 'Pending',
        'tone' => 'coral',
        'trend' => $counts['pending'] ? 'Needs You' : '',
        'trendTone' => 'down',
    ],
    ['icon' => 'badge-check', 'value' => $counts['approved'], 'label' => 'Approved', 'tone' => 'sky'],
    ['icon' => 'file-x', 'value' => $counts['rejected'], 'label' => 'Rejected', 'tone' => 'ink'],
    ['icon' => 'check-circle-2', 'value' => $counts['completed'], 'label' => 'Completed', 'tone' => 'ink'],
    ['icon' => 'ban', 'value' => $counts['cancelled'], 'label' => 'Cancelled', 'tone' => 'ink'],
];
$kpiTiles = '';
foreach ($kpiData as $k) {
    $kpiTiles .= kpi_card_html($k + ['compact' => true]);
}

$filterDefs = [
    ['key' => 'all', 'label' => 'All', 'count' => $counts['all']],
    ['key' => 'pending', 'label' => 'Pending', 'count' => $counts['pending']],
    ['key' => 'approved', 'label' => 'Approved', 'count' => $counts['approved']],
    ['key' => 'rejected', 'label' => 'Rejected', 'count' => $counts['rejected']],
    ['key' => 'completed', 'label' => 'Completed', 'count' => $counts['completed']],
    ['key' => 'cancelled', 'label' => 'Cancelled', 'count' => $counts['cancelled']],
];
$tabButtons = '';
foreach ($filterDefs as $f) {
    $activeCls = $f['key'] === 'all' ? ' is-active' : '';
    $tabButtons .= '
        <button type="button" data-filter="' . e($f['key']) . '" class="q-btn' . $activeCls . '">' . e($f['label']) . ' &middot; ' . e($f['count']) . '</button>';
}
$filterTabs = toolbar_html(search_control('application-search', 'Search applicant, animal, message…'));

const APPLICATIONS_PAGE_SIZE = 20;
$actionLinksFor = static function (array $a) use ($appButton): string {
    $id = e((string) ($a['id'] ?? ''));
    $view = $appButton('View application', 'outline', 'sm', 'eye', 'data-action="view" data-id="' . $id . '"');
    $status = (string) ($a['status'] ?? '');
    if ($status === 'pending') {
        return $view
            . $appButton('Reject', 'destructive', 'sm', 'file-x', 'data-action="reject" data-id="' . $id . '"');
    }
    return $view;
};

if ($loadError !== '') {
    $tableInner = '<div class="queue-empty">' . empty_state('alert-triangle', $loadError)
        . $appButton('Retry', 'outline', 'sm', 'refresh-cw', 'data-action="retry"') . '</div>';
} elseif ($items === []) {
    $tableInner = '<div class="queue-empty">' . empty_state('home', 'No adoption applications yet.') . '</div>';
} else {
    $pageRows = array_slice($items, 0, APPLICATIONS_PAGE_SIZE);
    $rowsHtml = '';
    foreach ($pageRows as $a) {
        $name = ((string) ($a['applicant_name'] ?? '')) !== '' ? (string) $a['applicant_name'] : short_id($a['applicant_id'] ?? null);
        $animal = ((string) ($a['animal_name'] ?? '')) !== '' ? (string) $a['animal_name'] : short_id($a['animal_id'] ?? null);
        $msgRaw = trim((string) ($a['message'] ?? ''));
        $msg = $msgRaw !== '' ? truncate_text($msgRaw, 28) : '—';
        $status = (string) ($a['status'] ?? '');
        $rowsHtml .= '
    <tr data-id="' . e((string) ($a['id'] ?? '')) . '">
      <td class="table-cell table-cell--strong">' . e($name) . '</td>
      <td class="table-cell">' . e($animal) . '</td>
      <td class="table-cell"><span class="stamp stamp--sm ' . e($stampCls($status)) . '">' . e(title_case($status)) . '</span></td>
      <td class="table-cell">' . e($msg) . '</td>
      <td class="table-cell table-cell--mono table-cell--muted">' . e(time_ago($a['created_at'] ?? null)) . '</td>
      <td class="table-cell table-cell--right table-cell--nowrap">
        <span class="table-actions">' . $actionLinksFor($a) . '</span>
      </td>
    </tr>';
    }
    $pagination = '<div class="queue-pagination">' . pagination_bar(count($items), APPLICATIONS_PAGE_SIZE, 1) . '</div>';
    $tableInner = '
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr class="table-head">
            <th>Applicant</th><th>Animal</th><th>Status</th><th>Message</th><th>Submitted</th><th class="table-cell--right">Action</th>
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
      <span class="stamp stamp--coral">Adoption</span>
      <h1 class="page-title">Applications</h1>
      <p class="page-sub">Review adoption applications, approve or decline pending requests, and complete approved placements.</p>
    </div>
    <div class="page-head-actions">
      ' . $exportCsvButton . '
    </div>
  </div>
  <div id="application-kpis" class="kpi-grid kpi-grid--compact">' . $kpiTiles . '</div>
  <div class="panel">
    <div class="panel-head">
      <div class="panel-title-wrap">
        <i data-lucide="file-check"></i>
        <h2 class="panel-title">Applications</h2>
      </div>
      <div class="panel-head-tools">
        <div id="application-tabs-wrap">' . filter_tabs_html('application-tabs', $tabButtons) . '</div>
      </div>
    </div>
    <div id="application-filters">' . $filterTabs . '</div>
    <div id="application-table" class="panel-body">' . $tableInner . '</div>
  </div>';
