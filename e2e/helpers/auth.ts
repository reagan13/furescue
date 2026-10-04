import { expect, type Page } from '@playwright/test';

export const PASSWORD = 'Password123!';

export const accounts = {
  resident: 'juan@furescue.local',
  rescuer: 'rescuer@furescue.local',
  admin: 'admin@furescue.local',
  pending: 'rescuer6@furescue.local',
} as const;

export async function loginAs(page: Page, email: string, password = PASSWORD): Promise<void> {
  await page.goto('/auth/login.php');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.locator('#login-form button[type="submit"]').click();
}

export async function expectSignedInAt(page: Page, path: string): Promise<void> {
  await page.waitForURL((url) => url.pathname.startsWith(path), { timeout: 15_000 });
  await expect(page).not.toHaveURL(/\/auth\/login\.php/);
}

export async function openAs(page: Page, email: string, path: string): Promise<void> {
  await loginAs(page, email);
  await page.waitForURL((url) => !url.pathname.includes('/auth/login.php'), { timeout: 15_000 });
  if (!page.url().includes(path)) {
    await page.goto(path);
  }
}
