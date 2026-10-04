<?php

declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

if (!function_exists('kpi_card_html')) {
    function kpi_card_html(array $k): string
    {
        $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $label = (string) ($k['label'] ?? '');
        $value = (string) ($k['value'] ?? '');
        $tone = (string) ($k['tone'] ?? 'jungle');
        $icon = (string) ($k['icon'] ?? 'activity');
        $trend = (string) ($k['trend'] ?? '');
        $className = (string) ($k['className'] ?? '');
        $href = (string) ($k['href'] ?? '');
        $interactive = !empty($k['interactive']);
        $attrs = (string) ($k['attrs'] ?? '');
        $isInteractive = $href !== '' || $interactive;
        $compact = !empty($k['compact']);
        $cls = trim(
            'kpi-card'
            . ($compact ? ' kpi-card--compact' : '')
            . ($isInteractive ? ' kpi-card--interactive' : '')
            . ($className !== '' ? ' ' . $className : '')
        );
        $extra = $attrs !== '' ? ' ' . $attrs : '';
        $trendHtml = $trend !== ''
            ? '<p class="kpi-card__trend kpi-card__trend--' . $esc((string) ($k['trendTone'] ?? 'neutral')) . '">' . $esc($trend) . '</p>'
            : '';
        $inner = '
    <div class="kpi-card__icon kpi-card__icon--' . $esc($tone) . '" aria-hidden="true"><i data-lucide="' . $esc($icon) . '"></i></div>
    <div class="kpi-card__body">
      <p class="kpi-card__label">' . $esc($label) . '</p>
      <p class="kpi-card__value">' . $esc($value) . '</p>
      ' . $trendHtml . '
    </div>';
        $aria = ' aria-label="' . $esc($label . ': ' . $value) . '"';
        if ($href !== '') {
            return '<a href="' . $esc($href) . '" class="' . $esc($cls) . '"' . $aria . $extra . '>' . $inner . '</a>';
        }
        if ($interactive) {
            return '<button type="button" class="' . $esc($cls) . '"' . $aria . $extra . '>' . $inner . '</button>';
        }
        return '
  <article class="' . $esc($cls) . '"' . $aria . $extra . '>' . $inner . '
  </article>';
    }
}

if (!function_exists('kpi_grid_html')) {
    function kpi_grid_html(array $tiles, string $id = '', string $className = ''): string
    {
        $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $idAttr = $id !== '' ? ' id="' . $esc($id) . '"' : '';
        $cls = trim('kpi-grid' . ($className !== '' ? ' ' . $className : ''));
        $inner = '';
        foreach ($tiles as $k) {
            $inner .= kpi_card_html($k);
        }
        return '<div class="' . $esc($cls) . '"' . $idAttr . '>' . $inner . '</div>';
    }
}
