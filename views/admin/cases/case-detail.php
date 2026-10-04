<?php

declare(strict_types=1);

/** @var array<string, mixed> $caseRow */
/** @var array<string, mixed>|null $report */
/** @var array<string, mixed>|null $rescuer */
/** @var string $rescuerName */
/** @var list<array<string, mixed>> $activityRows */
/** @var list<string> $attachments */
/** @var list<string> $proofUrls */

$eventTimeParts = static function (mixed $iso): array {
    if (!is_string($iso) || $iso === '') {
        return ['date' => '', 'time' => ''];
    }
    $ts = strtotime($iso);
    if ($ts === false) {
        return ['date' => '', 'time' => ''];
    }
    return [
        'date' => date('M j, Y', $ts),
        'time' => date('g:i A', $ts),
    ];
};

$badgeBaseCls = 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2';
$badgeHtml = static function (string $text, string $variant = 'default', string $icon = '') use ($badgeBaseCls): string {
    $variantCls = $variant === 'secondary'
        ? ' border-transparent bg-secondary text-secondary-foreground'
        : ($variant === 'outline' ? ' text-foreground' : ' border-transparent bg-primary text-primary-foreground');
    $iconHtml = $icon !== '' ? '<i data-lucide="' . e($icon) . '" class="badge-icon"></i>' : '';
    return '<span class="' . e($badgeBaseCls . $variantCls) . '">' . $iconHtml . e($text) . '</span>';
};

$statusRaw = (string) ($caseRow['status'] ?? '');
$isResolved = $statusRaw === 'resolved';
$showResolve = $statusRaw === 'in_progress' && count($proofUrls) >= 1 && count($attachments) >= 1;
$stampCls = ($statusRaw === 'in_progress' || $statusRaw === 'resolved') ? 'stamp--accent' : 'stamp--coral';

$locationBtn = button_html('See location', 'outline', 'sm', '', 'map-pin', 'data-cd-action="location"');
if ($isResolved) {
    $actionsHtml = '<div class="cd-actions">' . $locationBtn
        . button_html('Resolved', 'outline', 'sm', '', 'check-circle-2') . '</div>';
} else {
    $assignLabel = !empty($caseRow['assigned_rescuer_id']) ? 'Reassign' : 'Assign rescuer';
    $actionsHtml = '
      <div class="cd-actions">
        ' . $locationBtn . '
        ' . button_html($assignLabel, 'outline', 'sm', '', 'user-plus', 'data-cd-action="assign"') . '
        ' . ($showResolve ? button_html(
            'Resolve',
            'default',
            'sm',
            '',
            'check-circle-2',
            'data-cd-action="resolve"'
        ) : '') . '
      </div>';
}

$pageHeadHtml = '
  <div class="page-head">
    <div>
      ' . button_anchor_html('/admin/cases/', 'Back to cases', 'outline', 'sm', '', 'arrow-left') . '
    </div>
    ' . $actionsHtml . '
  </div>';

$events = [];
$openTime = $eventTimeParts($caseRow['created_at'] ?? null);
$events[] = [
    'type' => 'open',
    'title' => 'Case opened',
    'note' => 'Case created from a verified report.',
    'actor' => '',
    'date' => $openTime['date'],
    'time' => $openTime['time'],
];

$workflowLabels = [
    'assigned' => 'Rescuer assigned',
    'status_change' => 'Status updated',
    'accepted' => 'Rescue accepted',
    'declined' => 'Rescue declined',
    'proof_added' => 'Rescue proof added',
];
$statusNotes = [
    'in_progress' => 'Rescuer accepted and started the rescue',
    'resolved' => 'Admin marked the case resolved',
    'assigned' => 'Rescuer re-assigned to the case',
];
$assignedCount = 0;
foreach ($activityRows as $ev) {
    $type = (string) (($ev['action'] ?? '') !== '' ? $ev['action'] : ($ev['type'] ?? ''));
    $rowTitle = $workflowLabels[$type] ?? title_case($type !== '' ? $type : 'event');
    $rowActor = '';
    $rowNote = '';
    if ($type === 'assigned') {
        $assignedCount++;
        $isReassign = $assignedCount > 1;
        $rowTitle = $isReassign ? 'Rescuer reassigned' : 'Rescuer assigned';
        $rowActor = $badgeHtml($rescuerName !== '' ? $rescuerName : 'Rescuer', 'secondary', 'user');
        $rowNote = $statusRaw === 'assigned'
            ? 'Waiting for rescuer to accept'
            : ($isReassign ? 'Rescuer reassigned to the case' : 'Rescuer assigned to the case');
    } elseif ($type === 'status_change') {
        $notesStr = (string) (($ev['notes'] ?? '') !== '' ? $ev['notes'] : ($ev['note'] ?? ''));
        if (preg_match('/^Status set to (.+)$/sD', $notesStr, $m) === 1) {
            $st = (string) $m[1];
            $rowNote = $statusNotes[$st] ?? ('Status changed to ' . title_case($st));
        }
        $role = strtolower((string) (($ev['actor_role'] ?? '') !== '' ? $ev['actor_role'] : 'admin'));
        $byRescuer = $role === 'rescuer';
        $rowActor = $byRescuer
            ? $badgeHtml($rescuerName !== '' ? $rescuerName : 'Rescuer', 'secondary', 'user')
            : $badgeHtml(title_case((string) (($ev['actor_role'] ?? '') !== '' ? $ev['actor_role'] : 'Admin')), 'outline', 'shield');
    } elseif (in_array($type, ['accepted', 'declined', 'proof_added'], true)) {
        $rowActor = $badgeHtml($rescuerName !== '' ? $rescuerName : 'Rescuer', 'secondary', 'user');
        $rowNote = match ($type) {
            'accepted' => 'Rescuer accepted the assignment',
            'declined' => 'Rescuer declined the assignment',
            'proof_added' => 'Rescuer uploaded rescue proof',
            default => '',
        };
    } else {
        $rowNote = (string) (($ev['notes'] ?? '') !== '' ? $ev['notes'] : ($ev['note'] ?? ''));
    }
    $t = $eventTimeParts($ev['created_at'] ?? null);
    $events[] = [
        'type' => $type,
        'title' => $rowTitle,
        'note' => $rowNote,
        'actor' => $rowActor,
        'date' => $t['date'],
        'time' => $t['time'],
    ];
}

$timelineItems = '';
foreach ($events as $i => $evItem) {
    $noteHtml = $evItem['note'] !== '' && $evItem['note'] !== null
        ? '<div class="cd-tl-notes">' . e((string) $evItem['note']) . '</div>' : '';
    $actorHtml = $evItem['actor'] !== ''
        ? '<span class="cd-tl-actor">' . $evItem['actor'] . '</span>' : '';
    $dateHtml = $evItem['date'] !== ''
        ? '<span class="cd-tl-date">' . e($evItem['date']) . '</span>' : '';
    $clockHtml = $evItem['time'] !== ''
        ? '<span class="cd-tl-clock">' . e($evItem['time']) . '</span>' : '';
    $timelineItems .= '
    <li class="cd-tl-item cd-tl--' . e((string) $evItem['type']) . '">
      <span class="cd-tl-dot">' . e((string) ($i + 1)) . '</span>
      <div class="cd-tl-body">
        <div class="cd-tl-title">' . e((string) $evItem['title']) . '</div>
        ' . $noteHtml . '
        <div class="cd-tl-meta">
          ' . $actorHtml . '
          <span class="cd-tl-time">
            ' . $dateHtml . '
            ' . $clockHtml . '
          </span>
        </div>
      </div>
    </li>';
}

$workflowPanelHtml = '
  <div class="panel case-detail-panel">
    <div class="panel-head">
      <div class="panel-title-wrap">
        <i data-lucide="git-branch"></i>
        <h2 class="panel-title">Workflow &amp; transactions</h2>
      </div>
      <span class="stamp stamp--sm stamp--muted">' . e((string) count($events)) . ' events</span>
    </div>
    <div class="panel-body"><ul class="cd-timeline">' . $timelineItems . '</ul></div>
  </div>';

$infoRow = static function (string $label, string $value): string {
    return '
        <div class="dialog-info-row">
          <span class="dialog-info-label">' . e($label) . '</span>
          <span class="dialog-info-value">' . $value . '</span>
        </div>';
};

$rescuerCell = !empty($caseRow['assigned_rescuer_id'])
    ? $badgeHtml($rescuerName !== '' ? $rescuerName : 'Unassigned', 'secondary', 'user')
    : $badgeHtml('Unassigned', 'outline');

$infoPanelHtml = '
  <div class="panel case-detail-panel">
    <div class="panel-head">
      <div class="panel-title-wrap">
        <i data-lucide="clipboard-list"></i>
        <h2 class="panel-title">Case details</h2>
      </div>
    </div>
    <div class="panel-body"><div class="dialog-info">'
    . $infoRow('Case', e(short_id($caseRow['id'] ?? null)))
    . $infoRow('Status', '<span class="stamp stamp--sm ' . e($stampCls) . '">' . e(title_case($statusRaw)) . '</span>')
    . $infoRow('Rescuer', $rescuerCell)
    . $infoRow('Source report', !empty($caseRow['report_id']) ? e(short_id($caseRow['report_id'])) : '—')
    . $infoRow('Created', !empty($caseRow['created_at']) ? e(time_ago($caseRow['created_at'])) : '—')
    . $infoRow('Updated', !empty($caseRow['updated_at']) ? e(time_ago($caseRow['updated_at'])) : '—')
    . '</div></div>
  </div>';

if ($report === null) {
    $sourcePanelHtml = '
  <div class="panel case-detail-panel">
    <div class="panel-head"><div class="panel-title-wrap"><i data-lucide="file-text"></i><h2 class="panel-title">Source report</h2></div></div>
    <div class="panel-body"><div class="empty-state"><i data-lucide="file-x"></i><span>No linked report.</span></div></div>
  </div>';
} else {
    $animalText = ($report['animal_description'] ?? '') ?: '—';
    $locValue = ($report['address_text'] ?? '') ?: '';
    $sourcePanelHtml = '
  <div class="panel case-detail-panel">
    <div class="panel-head">
      <div class="panel-title-wrap">
        <i data-lucide="file-text"></i>
        <h2 class="panel-title">Source report &middot; ' . e(short_id($report['id'] ?? null)) . '</h2>
      </div>
    </div>
    <div class="panel-body">
      <div class="dialog-info">'
        . $infoRow('Animal', e((string) $animalText))
        . $infoRow('Location', $locValue !== '' ? e($locValue) : '—')
        . $infoRow('Validation', e((string) (($report['validation_status'] ?? '') ?: '—')))
        . $infoRow('Report status', e((string) (($report['status'] ?? '') ?: '—')))
      . '</div>
    </div>
  </div>';
}

require __DIR__ . '/partials/files.php';

$adminChildren = $pageHeadHtml
    . '<div class="case-detail-grid">'
    . '<div class="cd-col-workflow">' . $workflowPanelHtml . '</div>'
    . '<div class="cd-col-info">' . $infoPanelHtml . $sourcePanelHtml . '</div>'
    . '<div class="cd-col-files">' . $attachmentsPanelHtml . '</div>'
    . '<div class="cd-col-rescuer">' . $proofPanelHtml . '</div>'
    . '</div>';
