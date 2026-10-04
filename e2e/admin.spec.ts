import { expect, test } from '@playwright/test';
import { accounts, openAs } from './helpers/auth';

const consolePages: Array<{ path: string; text: string }> = [
  { path: '/admin/', text: 'Command Center' },
  { path: '/admin/reports/', text: 'Reports' },
  { path: '/admin/cases/', text: 'Cases' },
  { path: '/admin/rescuers/', text: 'Rescuers' },
  { path: '/admin/animals/', text: 'Animals' },
  { path: '/admin/health-records/', text: 'Health' },
  { path: '/admin/listings/', text: 'Listings' },
  { path: '/admin/applications/', text: 'Applications' },
  { path: '/admin/elearning/', text: 'E-Learning' },
  { path: '/admin/messages/', text: 'Messages' },
  { path: '/admin/notifications/', text: 'Notifications' },
  { path: '/admin/analytics/', text: 'Analytics' },
];

test.describe('admin console', () => {
  test('page map loads on desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await openAs(page, accounts.admin, '/admin/');
    await expect(page.locator('body')).toContainText('Command Center');
    for (const item of consolePages) {
      await page.goto(item.path);
      await expect(page).not.toHaveURL(/\/auth\/login\.php/);
      await expect(page.locator('body')).toContainText(item.text);
    }
  });

  test('verify one pending report on the test DB', async ({ page }) => {
    await openAs(page, accounts.admin, '/admin/reports/');
    const pendingReports = page.locator('[data-filter="pending_verification"]');
    await expect(pendingReports).toBeVisible({ timeout: 15_000 });
    await pendingReports.click();
    const verify = page.locator('[data-action="verify"]').first();
    await expect(verify).toBeVisible({ timeout: 15_000 });
    await verify.click();
    await page.getByRole('button', { name: 'Verify' }).last().click();
    await expect(page.locator('.toast--success, .toast[class*="success"]')).toBeVisible({
      timeout: 15_000,
    });
  });

  test('approve one pending application on the test DB', async ({ page }) => {
    await openAs(page, accounts.admin, '/admin/applications/');
    const pendingApps = page.locator('[data-filter="pending"]');
    await expect(pendingApps).toBeVisible({ timeout: 15_000 });
    await pendingApps.click();
    const view = page.locator('[data-action="view"]').first();
    await expect(view).toBeVisible({ timeout: 15_000 });
    await view.click();
    const approve = page.locator('[data-drawer-act="approve"]');
    await expect(approve).toBeVisible();
    await approve.click();
    await page.getByRole('button', { name: 'Approve' }).last().click();
    await expect(page.locator('.toast--success, .toast[class*="success"]')).toBeVisible({
      timeout: 15_000,
    });
  });

  test('analytics export returns a CSV', async ({ page }) => {
    await openAs(page, accounts.admin, '/admin/analytics/');
    await expect(page.locator('body')).toContainText('Analytics');
    const downloadPromise = page.waitForEvent('download');
    await page.locator('#export-overview').click();
    const download = await downloadPromise;
    expect(download.suggestedFilename().toLowerCase()).toContain('csv');
  });
});
