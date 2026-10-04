<?php

declare(strict_types=1);

require_once shared_path('search/search.php');

/** @var list<array<string, mixed>> $enrichedCases */
/** @var int $cAll */
/** @var int $cOpen */
/** @var int $cAssigned */
/** @var int $cInProgress */
/** @var int $cResolved */

$pageHeadHtml = '
  <div class="page-head">
    <div>
      <span class="stamp stamp--coral">Rescue Management</span>
      <h1 class="page-title">Cases</h1>
      <p class="page-sub">Track active rescues, assign rescuers, and follow each case to resolution.</p>
    </div>
    <div class="page-head-actions">
      ' . button_html('Export CSV', 'outline', icon: 'download', attrs: 'data-export="csv"') . '
    </div>
  </div>';

$kpiData = [
    ['icon' => 'clipboard-list', 'value' => $cAll, 'label' => 'Total cases', 'tone' => 'jungle', 'trend' => '', 'trendTone' => 'neutral', 'desc' => 'Every case in the system, all statuses included.'],
    ['icon' => 'folder-open', 'value' => $cOpen, 'label' => 'Open', 'tone' => 'coral', 'trend' => $cOpen > 0 ? 'Intake' : '', 'trendTone' => 'down', 'desc' => 'Newly reported cases not yet assigned to a rescuer.'],
    ['icon' => 'user-plus', 'value' => $cAssigned, 'label' => 'Assigned', 'tone' => 'sky', 'trend' => '', 'trendTone' => 'neutral', 'desc' => 'Assigned to a rescuer, awaiting their acceptance.'],
    ['icon' => 'activity', 'value' => $cInProgress, 'label' => 'In progress', 'tone' => 'sky', 'trend' => '', 'trendTone' => 'neutral', 'desc' => 'Rescues that are actively underway.'],
    ['icon' => 'check-circle-2', 'value' => $cResolved, 'label' => 'Resolved', 'tone' => 'jungle', 'trend' => '', 'trendTone' => 'neutral', 'desc' => 'Cases successfully completed and closed.'],
];
$kpiTiles = '';
foreach ($kpiData as $k) {
    $label = (string) $k['label'];
    $value = (string) $k['value'];
    $desc = (string) ($k['desc'] ?? '');
    $aria = $label . ': ' . $value . ($desc !== '' ? '. ' . $desc : '');
    $title = $desc !== '' ? ' title="' . e($desc) . '"' : '';
    $trend = (string) ($k['trend'] ?? '');
    $trendHtml = $trend !== ''
        ? '<p class="kpi-card__trend kpi-card__trend--' . e((string) ($k['trendTone'] ?? 'neutral')) . '">' . e($trend) . '</p>'
        : '';
    $kpiTiles .= '
  <article class="kpi-card kpi-card--compact" aria-label="' . e($aria) . '"' . $title . '>
    <div class="kpi-card__icon kpi-card__icon--' . e((string) $k['tone']) . '"><i data-lucide="' . e((string) $k['icon']) . '"></i></div>
    <div class="kpi-card__body">
      <p class="kpi-card__label">' . e($label) . '</p>
      <p class="kpi-card__value">' . e($value) . '</p>
      ' . $trendHtml . '
    </div>
  </article>';
}

$legendRows = '';
foreach ([
    ['Open', $cOpen, 'status-seg--open'],
    ['Assigned', $cAssigned, 'status-seg--assigned'],
    ['In progress', $cInProgress, 'status-seg--live'],
    ['Resolved', $cResolved, 'status-seg--resolved'],
] as [$legendLabel, $legendVal, $legendCls]) {
    $legendRows .= '<span class="status-legend-item"><span class="status-dot ' . $legendCls . '"></span>' . e($legendLabel) . ' &middot; ' . e($legendVal) . '</span>';
}
$statusChartHtml = '
  <div class="panel panel--padded">
    <div class="panel-title-wrap"><i data-lucide="pie-chart"></i><h2 class="panel-title panel-title--sm">Case status breakdown</h2></div>
    <div class="donut-wrap">
      <div class="donut' . ($cAll > 0 ? '' : ' donut--empty') . '">
        <canvas id="status-donut"></canvas>
        <div class="donut-center"><span class="donut-total">' . e($cAll) . '</span><span class="donut-label">Cases</span></div>
      </div>
      <div class="status-legend">' . $legendRows . '</div>
    </div>
  </div>';

$kpiStripHtml = '<div class="kpi-grid kpi-grid--compact">' . $kpiTiles . '</div><div class="kpi-donut" id="kpi-donut-card">' . $statusChartHtml . '</div>';

$tabButtons = '';
foreach ([
    ['all', 'All', $cAll],
    ['open', 'Open', $cOpen],
    ['assigned', 'Assigned', $cAssigned],
    ['in_progress', 'In Progress', $cInProgress],
    ['resolved', 'Resolved', $cResolved],
] as [$filterKey, $filterLabel, $filterCount]) {
    $active = $filterKey === 'in_progress' ? ' is-active' : '';
    $tabButtons .= '<button data-filter="' . e($filterKey) . '" class="q-btn' . $active . '">' . e($filterLabel) . ' &middot; ' . e($filterCount) . '</button>';
}

$toolbarHtml = toolbar_html(
    search_control('case-search', 'Search case #, barangay, animal…')
    . '<div class="report-sort">'
    . select_control('case-sort', [
        ['value' => '', 'label' => 'Sort'],
        ['value' => 'newest', 'label' => 'Newest'],
        ['value' => 'status', 'label' => 'Status'],
        ['value' => 'updated', 'label' => 'Updated'],
    ], '', 'Sort', '', '', 'report-sort-control')
    . '</div>'
);

$rescuerChip = static function (?array $rescuer): string {
    if ($rescuer === null) {
        return '<span class="case-card-unassigned">Unassigned</span>';
    }
    $name = (string) ($rescuer['full_name'] ?? '');
    return '
    <span class="case-card-rescuer">
      <span class="table-avatar table-avatar--initial">' . e(initials_of($name)) . '</span>
      <span class="case-card-rescuer-name">' . e($name) . '</span>
    </span>';
};

$caseAction = static function (array $c): string {
    $status = (string) ($c['statusRaw'] ?? '');
    if (!in_array($status, ['open', 'assigned', 'in_progress'], true)) {
        return '';
    }
    $attrs = 'data-action="assign" data-case="' . e($c['id']) . '" data-report="' . e(($c['report']['id'] ?? '') === null ? '' : (string) ($c['report']['id'] ?? '')) . '"';
    $label = $c['rescuer'] !== null ? 'Reassign' : 'Assign rescuer';
    $variant = $c['rescuer'] !== null ? 'outline' : 'default';
    return button_html($label, $variant, 'sm', '', 'user-plus', $attrs);
};

$caseCardHtml = static function (array $c) use ($rescuerChip, $caseAction): string {
    $live = $c['statusRaw'] === 'in_progress' ? ' case-card--live' : '';
    $time = $c['statusRaw'] === 'in_progress' ? 'Updated ' . e($c['updated']) : e($c['when']);
    return "
  <article class=\"case-card{$live}\" data-case-id=\"" . e($c['id']) . '">
    <div class="case-card-head">
      <span class="case-card-id">' . e($c['shortId']) . '</span>
      <span class="stamp stamp--sm ' . e($c['statusCls']) . '">' . e($c['status']) . '</span>
    </div>
    <div class="case-card-body">
      <div class="case-card-row"><i data-lucide="map-pin"></i><span>' . e($c['brgy']) . '</span></div>
      <div class="case-card-row"><i data-lucide="paw-print"></i><span>' . e($c['animal']) . '</span></div>
    </div>
    <div class="case-card-foot">
      ' . $rescuerChip($c['rescuer']) . '
      <span class="case-card-time">' . $time . '</span>
    </div>
    <div class="case-card-actions">' . $caseAction($c) . '</div>
  </article>';
};

$initialList = array_values(array_filter($enrichedCases, static fn(array $c) => $c['statusRaw'] === 'in_progress'));
usort($initialList, static fn(array $a, array $b) => strtotime($b['createdAt']) <=> strtotime($a['createdAt']));
$casePageSize = 10;
$casePageItems = array_slice($initialList, 0, $casePageSize);

if ($initialList === []) {
    $listInnerHtml = '<div class="queue-empty"><div class="empty-state"><i data-lucide="clipboard-list"></i><span>No cases match.</span></div></div>';
} else {
    $cards = '';
    foreach ($casePageItems as $c) {
        $cards .= $caseCardHtml($c);
    }
    $listInnerHtml = '<div class="case-grid">' . $cards . '</div>'
        . '<div class="queue-pagination">' . pagination_bar(count($initialList), $casePageSize, 1) . '</div>';
}

$pinCount = count(array_filter($enrichedCases, static fn(array $c) => $c['lat'] !== null && $c['lng'] !== null));

$mapPanelHtml = '
  <div class="panel case-map-panel" id="case-map-panel">
    <div class="panel-head">
      <div class="panel-title-wrap">
        <i data-lucide="map"></i>
        <h2 class="panel-title">Case map &middot; City of Mati</h2>
      </div>
      <div class="map-tools">
        <span class="map-label">Heat intensity</span>
        ' . select_control('case-map-intensity', [
            ['value' => 'low', 'label' => 'Low'],
            ['value' => 'medium', 'label' => 'Medium'],
            ['value' => 'high', 'label' => 'High'],
        ], 'medium', 'Heat intensity') . '
        <button type="button" id="case-map-expand" class="map-expand" aria-label="Expand map" title="Expand map"><i data-lucide="maximize"></i></button>
        <div class="map-toggle" id="case-map-toggle" role="group" aria-label="Map display mode">
          <button type="button" class="map-toggle-btn is-active" data-map-mode="pins"><i data-lucide="map-pin"></i> Pins</button>
          <button type="button" class="map-toggle-btn" data-map-mode="heatmap"><i data-lucide="flame"></i> Heatmap</button>
        </div>
      </div>
    </div>
    <div id="case-map" class="map-canvas map-canvas--leaflet case-map"></div>
    <div class="map-foot"><span id="case-map-count">' . e($pinCount) . '</span> <span id="case-map-foot-label">pinned cases &middot; Click a pin for details</span></div>
  </div>';

$adminChildren = $pageHeadHtml
    . '<div id="case-kpis" class="case-kpis">' . $kpiStripHtml . '</div>'
    . '<div class="cols case-split">'
    . '<div class="case-list-col">'
    . '
  <div class="panel case-panel">
    <div class="panel-head">
      <div class="panel-title-wrap">
        <i data-lucide="clipboard-list"></i>
        <h2 class="panel-title">Cases</h2>
      </div>
      <div class="panel-head-tools">
        <div id="case-tabs-wrap">' . filter_tabs_html('case-tabs', $tabButtons) . '</div>
        <span class="stamp stamp--sm stamp--accent" id="case-total-badge">' . e($cAll) . '</span>
      </div>
    </div>
    <div id="case-controls">' . $toolbarHtml . '</div>
    <div id="case-list" class="panel-body">' . $listInnerHtml . '</div>
  </div>'
    . '</div>'
    . '<div class="case-map-col">' . $mapPanelHtml . '</div>'
    . '</div>';
