import { expect, test } from '@playwright/test';
import { accounts, openAs } from './helpers/auth';

const pages: Array<{ path: string; text: string }> = [
  { path: '/report/', text: 'Report' },
  { path: '/reports/', text: 'My Reports' },
  { path: '/animals/', text: 'Adoption Gallery' },
  { path: '/adoptions/', text: 'My Adoptions' },
  { path: '/listings/', text: 'Community Listings' },
  { path: '/learning/', text: 'Responsible Pet Ownership' },
  { path: '/messages/', text: 'Messages' },
  { path: '/notifications/', text: 'Notifications' },
  { path: '/account/', text: 'Account' },
];

test.describe('resident portal', () => {
  test('desktop page map loads', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await openAs(page, accounts.resident, '/reports/');
    for (const item of pages) {
      await page.goto(item.path);
      await expect(page).not.toHaveURL(/\/auth\/login\.php/);
      await expect(page.locator('body')).toContainText(item.text);
    }
  });

  test('mobile reports and animals load', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await openAs(page, accounts.resident, '/reports/');
    await expect(page.locator('body')).toContainText('My Reports');
    await page.goto('/animals/');
    await expect(page.locator('body')).toContainText('Adoption Gallery');
    const firstCard = page.locator('a[href*="animals/detail.php"]').first();
    if (await firstCard.count()) {
      await firstCard.click();
      await expect(page).toHaveURL(/animals\/detail\.php/);
      await expect(page.locator('body')).toBeVisible();
    }
  });
});
