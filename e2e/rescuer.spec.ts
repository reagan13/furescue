import { expect, test } from '@playwright/test';
import { accounts, openAs } from './helpers/auth';

test.describe('rescuer portal', () => {
  test('cases list, detail, duty, and shared pages', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await openAs(page, accounts.rescuer, '/cases/');
    await expect(page.locator('.rpage-title')).toContainText('My Cases');
    await expect(page.locator('#duty-toggle')).toBeVisible();

    const detailLink = page.locator('a[href*="cases/detail.php"]').first();
    if (await detailLink.count()) {
      await detailLink.click();
      await expect(page).toHaveURL(/cases\/detail\.php/);
      await expect(page.locator('body')).toBeVisible();
    }

    await page.goto('/cases/');
    const duty = page.locator('#duty-toggle');
    const before = (await duty.innerText()).trim();
    await duty.click();
    await page.getByRole('button', { name: /Go (on|off) duty/ }).click();
    await expect(duty).not.toHaveText(before, { timeout: 10_000 });

    await page.goto('/learning/');
    await expect(page.locator('body')).toContainText('Responsible Pet Ownership');
    await page.goto('/messages/');
    await expect(page.locator('.rpage-title')).toContainText('Messages');
    await page.goto('/notifications/');
    await expect(page.locator('.rpage-title')).toContainText('Notifications');
    await page.goto('/account/');
    await expect(page.locator('.rpage-title')).toContainText('Account');
  });

  test('mobile cases list loads', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await openAs(page, accounts.rescuer, '/cases/');
    await expect(page.locator('.rpage-title')).toContainText('My Cases');
  });
});
