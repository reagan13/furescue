<?php

$overview = $record['overview'];
$subCard = static function (string $field, string $label, mixed $value, string $extra = '') use ($toneFor, $TONE, $ICON, $chip): string {
    $tone = $toneFor($field, (string) ($value ?? ''));
    $toneClass = $TONE[$tone] ?? $TONE['blue'];
    $icon = $ICON[$tone] ?? 'circle';
    $displayValue = $value ?? '—';
    return '
    <div class="hr-subcard">
      <span class="tint-circle ' . e(explode(' ', $toneClass)[0]) . '"><i data-lucide="' . e($icon) . '"></i></span>
      <div>
        <div class="hr-subcard-label">' . e($label) . '</div>
        <div class="hr-subcard-value">' . $chip($tone, (string) $displayValue) . '</div>
        ' . $extra . '
      </div>
    </div>';
};

$vaxList = $record['vaccinations'] ?? [];
$vaxSorted = array_values(array_filter($vaxList, static fn($v) => !empty($v['vaccine'])));
usort($vaxSorted, static fn($a, $b) => strcmp((string) ($b['dateGiven'] ?? ''), (string) ($a['dateGiven'] ?? '')));
$latestVax = $vaxSorted[0] ?? null;

$interpretation = '';
if ($overview) {
    $parts = [];
    if (!empty($overview['healthStatus'])) {
        $parts[] = $overview['healthStatus'] === 'not_healthy'
            ? 'This animal is currently flagged as not healthy and needs prompt veterinary attention.'
            : 'This animal is in good general health.';
    }
    if (!empty($overview['vaccinationStatus'])) {
        $parts[] = match ($overview['vaccinationStatus']) {
            'complete' => 'Vaccinations are complete and up to date.',
            'partial' => 'Vaccinations are only partially done; remaining doses should be scheduled.',
            default => 'Vaccinations are not up to date and should be prioritised.',
        };
    }
    if (!empty($overview['deworming'])) {
        $parts[] = match ($overview['deworming']) {
            'up_to_date' => 'Deworming is up to date.',
            'overdue' => 'Deworming is overdue and should be repeated soon.',
            default => 'Deworming status is pending.',
        };
    }
    if (!empty($overview['neutered'])) {
        $parts[] = match ($overview['neutered']) {
            'yes' => 'The animal is neutered.',
            'no' => 'The animal is not neutered; consider scheduling the procedure.',
            default => 'Neutering status is unknown.',
        };
    }
    $interpretation = implode(' ', $parts);
}

$savedNotes = trim((string) ($overview['notes'] ?? ''));
$savedNotesHtml = $savedNotes !== ''
    ? '<div class="hr-notes"><p class="hr-notes-text">' . e($savedNotes) . '</p>'
        . (!empty($overview['notesMeta']) ? '<p class="hr-notes-meta">' . e((string) $overview['notesMeta']) . '</p>' : '')
        . '</div>'
    : '';
$interpretationHtml = $interpretation !== ''
    ? '<div class="hr-notes"><p class="hr-notes-text">' . e($interpretation) . '</p><p class="hr-notes-meta">' . e($overview['notesMeta'] ?? 'Interpretation of the health overview data above') . '</p></div>'
    : '';
$notesHtml = $savedNotesHtml . $interpretationHtml;
if ($notesHtml === '') {
    $notesHtml = $emptyState('No health data recorded');
}

$overviewPanelHtml = '
  <section class="panel hr-overview-panel">
    <div class="panel-head">
      <div class="panel-title-wrap"><i data-lucide="activity"></i><h3 class="panel-title">Health Overview</h3></div>
    </div>
    <div class="panel-body hr-overview-body">
      <div class="hr-subcards">
        ' . $subCard('healthStatus', 'Health Status', $overview['healthStatus'] ?? '') . '
        ' . $subCard('vaccinationStatus', 'Vaccination', $latestVax ? $latestVax['vaccine'] : ($overview['vaccinationStatus'] ?? '')) . '
        ' . $subCard('deworming', 'Deworming', $overview['deworming'] ?? '') . '
        ' . $subCard('neutered', 'Neutered', $overview['neutered'] ?? '') . '
      </div>
      ' . $notesHtml . '
    </div>
  </section>';
