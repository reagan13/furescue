import { expect, test, type Page } from '@playwright/test';
import { PASSWORD, accounts, expectSignedInAt, loginAs } from './helpers/auth';

async function assertLoginFormUsable(page: Page, width: number, height: number): Promise<void> {
  await page.setViewportSize({ width, height });
  await page.goto('/auth/login.php');
  const form = page.locator('#login-form');
  await expect(form).toBeVisible();
  await expect(page.locator('#email')).toBeVisible();
  await expect(page.locator('#password')).toBeVisible();
  const box = await form.boundingBox();
  expect(box, 'login form should have a box').toBeTruthy();
  const mid = (box?.y ?? 0) + (box?.height ?? 0) / 2;
  expect(mid).toBeGreaterThan(height * 0.15);
  expect(mid).toBeLessThan(height * 0.9);
}

test.describe('auth', () => {
  test('resident lands on /reports/', async ({ page }) => {
    await loginAs(page, accounts.resident);
    await expectSignedInAt(page, '/reports/');
  });

  test('rescuer lands on /cases/', async ({ page }) => {
    await loginAs(page, accounts.rescuer);
    await expectSignedInAt(page, '/cases/');
  });

  test('admin lands on /admin/', async ({ page }) => {
    await loginAs(page, accounts.admin);
    await expectSignedInAt(page, '/admin/');
  });

  test('wrong password shows an error toast', async ({ page }) => {
    await loginAs(page, accounts.resident, 'not-the-password');
    await expect(page.locator('.toast--error .toast-message')).toContainText(
      'Email or password is incorrect'
    );
  });

  test('pending rescuer is blocked', async ({ page }) => {
    await loginAs(page, accounts.pending);
    await expect(page.locator('.toast--error .toast-message')).toContainText('Account is not active.');
    await expect(page).toHaveURL(/\/auth\/login\.php/);
  });

  test('signed-in visit to login redirects home', async ({ page }) => {
    await loginAs(page, accounts.resident);
    await expectSignedInAt(page, '/reports/');
    await page.goto('/auth/login.php');
    await expectSignedInAt(page, '/reports/');
  });

  test('logout returns to login', async ({ page }) => {
    await loginAs(page, accounts.resident);
    await expectSignedInAt(page, '/reports/');
    await page.goto('/auth/logout.php');
    await expect(page).toHaveURL(/\/auth\/login\.php/);
  });

  test('resident is bounced off /admin/', async ({ page }) => {
    await loginAs(page, accounts.resident);
    await expectSignedInAt(page, '/reports/');
    await page.goto('/admin/');
    await expectSignedInAt(page, '/reports/');
  });

  test('signup resident stores a session and opens reports', async ({ page }) => {
    const email = `pw-resident-${Date.now()}@furescue.local`;
    await page.goto('/auth/signup.php');
    await page.locator('#full_name').fill('Playwright Resident');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(PASSWORD);
    await page.locator('#role').selectOption('resident');
    await page.locator('#signup-submit').click();
    await page.getByRole('button', { name: 'Create account' }).click();
    await expectSignedInAt(page, '/reports/');
    const token = await page.evaluate(() => localStorage.getItem('furescue_access_token'));
    expect(token).toBeTruthy();
  });

  test('login form stays usable at 375 / 768 / 1440', async ({ page }) => {
    await assertLoginFormUsable(page, 375, 812);
    await assertLoginFormUsable(page, 768, 1024);
    await assertLoginFormUsable(page, 1440, 900);
  });
});
