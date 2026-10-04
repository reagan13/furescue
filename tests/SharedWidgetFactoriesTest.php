<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/views/path.php';
require_once \shared_path('empty-state/empty-state.php');
require_once \shared_path('kpi-card/kpi-card.php');
require_once \shared_path('toolbar/toolbar.php');
require_once \shared_path('filter-tabs/filter-tabs.php');
require_once \shared_path('input/input.php');
require_once \shared_path('checkbox/checkbox.php');
require_once \shared_path('label/label.php');
require_once \shared_path('spinner/spinner.php');
require_once \shared_path('separator/separator.php');
require_once \shared_path('badge/badge.php');

class SharedWidgetFactoriesTest extends TestCase
{
    public function testEmptyStateEscapesAndKeepsAliasClass(): void
    {
        $html = \empty_state('inbox', 'No records.');
        $this->assertSame(
            '<div class="empty-state"><i data-lucide="inbox"></i><span>No records.</span></div>',
            $html
        );

        $escaped = \empty_state('x"y', '<b>');
        $this->assertStringContainsString('data-lucide="x&quot;y"', $escaped);
        $this->assertStringContainsString('<span>&lt;b&gt;</span>', $escaped);
    }

    public function testKpiCardAndGrid(): void
    {
        $card = \kpi_card_html(['label' => 'Open', 'value' => '3', 'tone' => 'coral', 'icon' => 'folder-open']);
        $this->assertStringContainsString('class="kpi-card"', $card);
        $this->assertStringContainsString('kpi-card__icon--coral', $card);
        $compact = \kpi_card_html(['label' => 'Open', 'value' => '3', 'compact' => true]);
        $this->assertStringContainsString('kpi-card--compact', $compact);
        $this->assertStringContainsString('aria-label="Open: 3"', $card);

        $grid = \kpi_grid_html([
            ['label' => 'A', 'value' => '1'],
        ], 'kpis');
        $this->assertStringContainsString('id="kpis"', $grid);
        $this->assertStringContainsString('class="kpi-grid"', $grid);
    }

    public function testToolbarAndFilterTabs(): void
    {
        $tabs = \filter_tabs_html('report-tabs', \filter_tab_button_html('all', 'All', true));
        $bar = \toolbar_html($tabs, 'animal-toolbar');

        $this->assertStringContainsString('class="report-toolbar animal-toolbar"', $bar);
        $this->assertStringContainsString('id="report-tabs"', $bar);
        $this->assertStringContainsString('class="q-tabs"', $bar);
        $this->assertStringContainsString('data-filter="all"', $bar);
        $this->assertStringContainsString('is-active', $bar);
    }

    public function testInputCheckboxLabelSpinnerSeparatorBadge(): void
    {
        $this->assertStringContainsString('class="input"', \input_control('q', 'q', 'text', 'Search'));
        $this->assertStringContainsString('checked', \checkbox_control('ok', 'ok', true));
        $this->assertStringContainsString('for="q"', \label_html('q', 'Query'));
        $required = \label_html('rescuer', 'Rescuer', 'dialog-label', true);
        $this->assertStringContainsString('class="label-text"', $required);
        $this->assertStringContainsString('class="label-req"', $required);
        $this->assertStringContainsString('>Rescuer <span class="label-req"', $required);
        $this->assertStringContainsString('data-lucide="loader-circle"', \spinner_html());
        $this->assertStringContainsString('role="separator"', \separator_html());
        $this->assertStringContainsString('bg-primary', \badge_html('Live'));
    }

    public function testFactoriesAreSilentOnRequire(): void
    {
        ob_start();
        require_once \shared_path('empty-state/empty-state.php');
        require_once \shared_path('kpi-card/kpi-card.php');
        require_once \shared_path('toolbar/toolbar.php');
        $out = ob_get_clean();
        $this->assertSame('', $out);
    }
}
