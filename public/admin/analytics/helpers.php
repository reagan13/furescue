<?php

declare(strict_types=1);

/**
 * Render helpers for /admin/analytics/ (page-local).
 * Row builders intentionally mirror what analytics.js renders client-side.
 */

function overview_value(array $rows, string $key): int
{
    foreach ($rows as $r) {
        if (($r['key'] ?? '') === $key) {
            return (int) ($r['value'] ?? 0);
        }
    }
    return 0;
}

function overview_rows_html(array $rows): string
{
    $html = '';
    foreach ($rows as $r) {
        $html .= "
    <tr>
      <td class=\"table-cell\">" . e($r['label'] ?? '') . "</td>
      <td class=\"table-cell table-cell--mono table-cell--strong table-cell--right\">" . e($r['value'] ?? '') . '</td>
    </tr>';
    }
    return $html;
}

function trend_rows_html(array $trends): string
{
    $html = '';
    foreach ($trends as $t) {
        $html .= "
    <tr>
      <td class=\"table-cell table-cell--mono\">" . e($t['day'] ?? '') . "</td>
      <td class=\"table-cell table-cell--mono table-cell--strong table-cell--right\">" . e($t['completed'] ?? 0) . '</td>
    </tr>';
    }
    return $html;
}

function health_update_row(array $h): array
{
    $status = (string) ($h['vaccination_status'] ?? 'none');
    $label = match ($status) {
        'complete' => 'Vaccination complete',
        'partial' => 'Vaccination partial',
        default => 'No vaccination',
    };
    $animalParts = array_filter(
        [(string) ($h['animal_name'] ?? ''), (string) ($h['breed_type'] ?? '')],
        static fn($p) => $p !== ''
    );
    return [
        'id' => short_id($h['id'] ?? null),
        'animal' => $animalParts !== [] ? implode(', ', $animalParts) : 'Unnamed animal',
        'by' => ($h['updated_by_name'] ?? null) ?: '—',
        'when' => time_ago($h['updated_at'] ?? null),
        'status' => $label,
        'statusCls' => $status === 'complete' ? 'stamp--accent' : 'stamp--coral',
    ];
}

function personnel_duty_row(array $r): array
{
    $onDuty = ($r['duty_status'] ?? 'off_duty') === 'on_duty';
    return [
        'name' => (string) (($r['full_name'] ?? '') !== '' ? $r['full_name'] : 'Rescuer'),
        'contact' => (string) (($r['phone_number'] ?? '') !== '' ? $r['phone_number'] : (($r['email'] ?? '') !== '' ? $r['email'] : '—')),
        'duty' => $onDuty ? 'On duty' : 'Off duty',
        'dutyCls' => $onDuty ? 'stamp--accent' : 'stamp--muted',
        'when' => time_ago($r['duty_updated_at'] ?? null),
    ];
}

function personnel_rows_html(array $rows): string
{
    $html = '';
    foreach ($rows as $r) {
        $html .= "
    <tr>
      <td class=\"table-cell table-cell--strong\">" . e($r['name']) . "</td>
      <td class=\"table-cell\">" . e($r['contact']) . "</td>
      <td class=\"table-cell\"><span class=\"stamp stamp--sm " . e($r['dutyCls']) . "\">" . e($r['duty']) . "</span></td>
      <td class=\"table-cell table-cell--mono table-cell--muted\">" . e($r['when']) . '</td>
    </tr>';
    }
    return $html;
}

function health_rows_html(array $rows): string
{
    $html = '';
    foreach ($rows as $r) {
        $html .= "
    <tr>
      <td class=\"table-cell table-cell--mono table-cell--strong\">" . e($r['id']) . "</td>
      <td class=\"table-cell\">" . e($r['animal']) . "</td>
      <td class=\"table-cell\">" . e($r['by']) . "</td>
      <td class=\"table-cell\"><span class=\"stamp stamp--sm " . e($r['statusCls']) . "\">" . e($r['status']) . "</span></td>
      <td class=\"table-cell table-cell--mono table-cell--muted\">" . e($r['when']) . '</td>
    </tr>';
    }
    return $html;
}
