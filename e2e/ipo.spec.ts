import { expect, test, type Page } from '@playwright/test';
import { accounts, expectSignedInAt, loginAs, PASSWORD } from './helpers/auth';

const PROOF = 'e2e/fixtures/proof.png';

// The assign menu is the newest 100 active users who are on duty. Older seed
// accounts such as Rescuer One drop out of that window, so use a demo rescuer
// who is still listed.
const DUTY_RESCUERS = [
  { name: 'Elena Castillo', email: 'rescuer4@furescue.local' },
  { name: 'Diego Villanueva', email: 'rescuer5@furescue.local' },
  { name: 'Rescuer One', email: accounts.rescuer },
];

function matiPin(): { latitude: number; longitude: number } {
  const rows = 40;
  const cols = 48;
  const slot = Date.now() % (rows * cols);
  return {
    latitude: 6.902 + (slot % rows) * 0.0025,
    longitude: 126.142 + Math.floor(slot / rows) * 0.0025,
  };
}

async function confirmDialog(page: Page): Promise<void> {
  const dialog = page.locator('.dialog-overlay').last();
  await expect(dialog).toBeVisible();
  await dialog.locator('[data-act="ok"]').click();
}

test('walks report to admin resolve with mocked location and fixture files', async ({ browser }) => {
  test.setTimeout(180_000);
  const pin = matiPin();
  const address = `IPO walk ${Date.now()}`;
  const description = `Injured aspin for the IPO walk at ${address}.`;

  const residentContext = await browser.newContext({
    geolocation: pin,
    permissions: ['geolocation'],
  });
  const adminContext = await browser.newContext();
  const rescuerContext = await browser.newContext();

  const resident = await residentContext.newPage();
  const admin = await adminContext.newPage();
  const rescuer = await rescuerContext.newPage();
  for (const page of [resident, admin, rescuer]) {
    page.setDefaultTimeout(12_000);
    page.setDefaultNavigationTimeout(20_000);
  }

  try {
    await loginAs(resident, accounts.resident, PASSWORD);
    await expectSignedInAt(resident, '/reports/');
    await resident.goto('/report/');
    await resident.locator('.leaflet-container').waitFor({ timeout: 20_000 });
    await resident.locator('#animal_description').fill(description);
    await resident.locator('#report-photos').setInputFiles(PROOF);
    await resident.locator('#address_text').fill(address);
    await resident.locator('#use-my-location').click();
    await expect(resident.locator('#latitude')).not.toHaveValue('');
    await resident.locator('#report-submit').click();
    await confirmDialog(resident);
    await resident.waitForURL((url) => url.pathname.startsWith('/reports/'), { timeout: 20_000 });

    await loginAs(admin, accounts.admin, PASSWORD);
    await expectSignedInAt(admin, '/admin/');
    await admin.goto('/admin/reports/');
    await admin.locator('[data-filter="pending_verification"]').click();
    const reportRow = admin.locator('tr', { hasText: address });
    await expect(reportRow).toBeVisible({ timeout: 15_000 });
    await reportRow.getByRole('button', { name: 'Verify' }).click();
    await confirmDialog(admin);
    await expect(admin.locator('.toast--success')).toContainText(/verified/i, { timeout: 15_000 });

    await admin.locator('[data-filter="verified"]').click();
    await admin.locator('#report-search').fill(address);
    const verifiedRow = admin.locator('tr', { hasText: address });
    await expect(verifiedRow.getByRole('button', { name: 'Assign rescuer' })).toBeVisible({
      timeout: 15_000,
    });
    await verifiedRow.getByRole('button', { name: 'Assign rescuer' }).click();
    await admin.locator('#assign-rescuer [data-select-trigger]').click();
    let rescuerEmail = '';
    for (const candidate of DUTY_RESCUERS) {
      const option = admin.locator('#assign-rescuer [role="option"]', { hasText: candidate.name });
      if (await option.count()) {
        await option.click();
        rescuerEmail = candidate.email;
        break;
      }
    }
    expect(rescuerEmail, 'on-duty demo rescuer in the assign list').not.toBe('');
    await confirmDialog(admin);
    await confirmDialog(admin);
    await expect(admin.locator('.toast--success')).toContainText(/assigned/i, { timeout: 15_000 });

    await loginAs(rescuer, rescuerEmail, PASSWORD);
    await rescuer.waitForURL((url) => !url.pathname.includes('/auth/login.php'), { timeout: 15_000 });
    await rescuer.goto('/cases/');
    const duty = rescuer.locator('#duty-toggle');
    await expect(duty).toBeVisible();
    if ((await duty.innerText()).includes('Off duty')) {
      await duty.click();
      await confirmDialog(rescuer);
      await expect(duty).toContainText('On duty');
    }
    await rescuer.goto('/cases/');
    const caseRow = rescuer.locator('#cases-list li', { hasText: address });
    await expect(caseRow).toBeVisible({ timeout: 15_000 });
    await caseRow.getByRole('button', { name: 'Accept' }).click();
    await confirmDialog(rescuer);
    await expect(rescuer.locator('.toast--success')).toBeVisible({ timeout: 15_000 });
    const proofLink = rescuer.locator('#cases-list li', { hasText: address }).getByRole('link', { name: 'File proof' });
    await expect(proofLink).toBeVisible({ timeout: 15_000 });
    await proofLink.click();
    await rescuer.locator('#proof-files').setInputFiles(PROOF);
    await rescuer.locator('#proof-submit').click();
    await confirmDialog(rescuer);
    await expect(rescuer.locator('.toast--success')).toBeVisible({ timeout: 15_000 });
    const caseId = new URL(rescuer.url()).searchParams.get('id');
    expect(caseId).toBeTruthy();

    await admin.goto(`/admin/cases/case-detail.php?id=${caseId}`);
    await admin.locator('[data-cd-action="resolve"]').click({ timeout: 15_000 });
    await confirmDialog(admin);
    await expect(admin.locator('body')).toContainText(/resolved/i, { timeout: 15_000 });
  } finally {
    await Promise.all([
      residentContext.close().catch(() => undefined),
      adminContext.close().catch(() => undefined),
      rescuerContext.close().catch(() => undefined),
    ]);
  }
});
