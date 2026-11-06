<?php

declare(strict_types=1);

/**
 * Page-local partial for /admin/analytics/ — renders $adminChildren.
 * Consumes (from index.php scope): $start, $end, $overviewRows,
 * $trends, $updates, $personnel, plus shared ui-helpers.
 */

$today = date('Y-m-d');

$btnOverview = button_html('Export Overview CSV', 'outline', icon: 'download', attrs: 'id="export-overview" data-export="overview"');
$btnTrends = button_html('Export Adoption Trends CSV', 'outline', icon: 'download', attrs: 'id="export-trends" data-export="adoption-trends"');
$btnHealth = button_html('Export Health CSV', 'outline', icon: 'download', attrs: 'id="export-health" data-export="health-updates"');
?>
  <div class="page-head">
    <div>
      <span class="stamp stamp--jungle">Analytics</span>
      <h1 class="page-title">Analytics &amp; exports</h1>
      <p class="page-sub">Shelter-wide metrics for City of Mati</p>
    </div>
    <div class="page-head-actions analytics-actions">
      <?= $btnOverview ?>
      <?= $btnTrends ?>
      <?= $btnHealth ?>
    </div>
  </div>
<?= kpi_grid_html([
    ['icon' => 'map-pin', 'value' => overview_value($overviewRows, 'reports'), 'label' => 'Total reports', 'tone' => 'jungle'],
    ['icon' => 'badge-check', 'value' => overview_value($overviewRows, 'reports_verified'), 'label' => 'Reports verified', 'tone' => 'ink'],
    ['icon' => 'check-circle-2', 'value' => overview_value($overviewRows, 'cases_resolved'), 'label' => 'Cases resolved', 'tone' => 'jungle'],
    ['icon' => 'home', 'value' => overview_value($overviewRows, 'animals_adopted'), 'label' => 'Animals adopted', 'tone' => 'ink'],
    [
        'icon' => 'paw-print',
        'value' => overview_value($overviewRows, 'adoptions_pending'),
        'label' => 'Adoptions pending',
        'tone' => 'coral',
    ],
    [
        'icon' => 'siren',
        'value' => overview_value($overviewRows, 'rescuers_on_duty'),
        'label' => 'Rescuers on duty',
        'tone' => 'sky',
        'href' => '/admin/rescuers/',
    ],
]) ?>

  <div class="analytics-range">
    <?= date_range_picker([
        'id' => 'analytics-range',
        'start_id' => 'range-start',
        'end_id' => 'range-end',
        'start' => $start,
        'end' => $end,
        'max' => $today,
        'placeholder' => 'All dates',
        'className' => 'analytics-range-picker',
    ]) ?>
  </div>

  <div class="cols cols--two analytics-split">
    <div class="panel">
      <div class="panel-head">
        <div class="panel-title-wrap"><i data-lucide="bar-chart-3"></i><h2 class="panel-title panel-title--sm">Overview metrics</h2></div>
        <a href="#" class="btn-link" data-export="overview">Download CSV <?= chevron_right() ?></a>
      </div>
<?php if ($overviewRows === []): ?>
      <div id="table-overview" class="queue-empty"><?= empty_state('inbox', 'No records.') ?></div>
<?php else: ?>
      <div id="table-overview" class="table-wrap">
        <table class="table">
          <?= table_head(['Metric', 'Value']) ?>
          <tbody id="tbody-overview"><?= overview_rows_html($overviewRows) ?></tbody>
        </table>
      </div>
<?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel-head">
        <div class="panel-title-wrap"><i data-lucide="trending-up"></i><h2 class="panel-title panel-title--sm">Adoption trends</h2></div>
        <a href="#" class="btn-link" data-export="adoption-trends">Download CSV <?= chevron_right() ?></a>
      </div>
<?php if ($trends === []): ?>
      <div id="table-trends" class="queue-empty"><?= empty_state('bar-chart-3', 'No completed adoptions in this range.') ?></div>
<?php else: ?>
      <div id="table-trends" class="table-wrap">
        <table class="table">
          <?= table_head(['Day', 'Completed adoptions']) ?>
          <tbody id="tbody-trends"><?= trend_rows_html($trends) ?></tbody>
        </table>
      </div>
<?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <div class="panel-title-wrap"><i data-lucide="siren"></i><h2 class="panel-title panel-title--sm">Personnel duty status</h2></div>
      <a href="/admin/rescuers/" class="btn-link">View all <?= chevron_right() ?></a>
    </div>
<?php if ($personnel === []): ?>
    <div id="table-personnel" class="queue-empty"><?= empty_state('siren', 'No active rescuers.') ?></div>
<?php else: ?>
    <div id="table-personnel" class="table-wrap">
      <table class="table">
        <?= table_head(['Rescuer', 'Contact', 'Duty', 'Updated']) ?>
        <tbody id="tbody-personnel"><?= personnel_rows_html(array_map('personnel_duty_row', $personnel)) ?></tbody>
      </table>
    </div>
<?php endif; ?>
  </div>

  <div class="panel">
    <div class="panel-head">
      <div class="panel-title-wrap"><i data-lucide="activity"></i><h2 class="panel-title panel-title--sm">Rescue activity</h2></div>
    </div>
    <div id="rescue-activity" class="panel-pad">
      <p>Resolved cases <?= e((string) ($rescue['resolved_cases'] ?? 0)) ?> of <?= e((string) ($rescue['verified_reports'] ?? 0)) ?> verified reports<?php if (isset($rescue['rescue_rate'])): ?> (<?= e((string) round(((float) $rescue['rescue_rate']) * 100, 1)) ?>%)<?php endif; ?>.</p>
      <p class="field-hint">Average time from assign to resolve: <?= isset($rescue['avg_hours_to_resolve']) ? e((string) $rescue['avg_hours_to_resolve']) . ' hours' : 'not enough resolved cases yet' ?>.</p>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <div class="panel-title-wrap"><i data-lucide="map"></i><h2 class="panel-title panel-title--sm">Where verified reports were pinned</h2></div>
    </div>
    <div id="geo-summary">
<?php if (($places ?? []) === []): ?>
      <div class="queue-empty"><?= empty_state('map-pin', 'No verified pins in this range.') ?></div>
<?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <?= table_head(['Place', 'Verified reports']) ?>
          <tbody>
<?php foreach ($places as $place): ?>
            <tr>
              <td class="table-cell"><?= e($place['place'] ?? '') ?></td>
              <td class="table-cell table-cell--right"><?= e($place['reports'] ?? 0) ?></td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <div class="panel-title-wrap"><i data-lucide="heart-pulse"></i><h2 class="panel-title panel-title--sm">Health records</h2></div>
      <a href="#" class="btn-link" data-export="health-updates">Download CSV <?= chevron_right() ?></a>
    </div>
<?php if ($updates === []): ?>
    <div id="table-health" class="queue-empty"><?= empty_state('heart-pulse', 'No health updates in this range.') ?></div>
<?php else: ?>
    <div id="table-health" class="table-wrap">
      <table class="table">
        <?= table_head(['Update', 'Animal', 'Logged by', 'Status', 'When']) ?>
        <tbody id="tbody-health"><?= health_rows_html(array_map('health_update_row', $updates)) ?></tbody>
      </table>
    </div>
<?php endif; ?>
  </div>
