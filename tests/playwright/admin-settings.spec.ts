/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'

const adminSettingsUrl = '/index.php/settings/admin/usage-statistics-server'

/**
 * Sign in as the test administrator.
 *
 * The Nextcloud dashboard loads many secondary resources. Waiting for the
 * complete load event makes the test depend on all of them, while the login
 * itself is complete as soon as Nextcloud redirects the browser.
 *
 * @param page Playwright page
 */
async function login(page: Page): Promise<void> {
	await page.goto('/index.php/login', { waitUntil: 'domcontentloaded' })
	await page.locator('#user').fill('admin')
	await page.locator('#password').fill('admin')

	await Promise.all([
		page.waitForURL(/\/index\.php\/apps\//),
		page.locator('button[type="submit"]').click(),
	])
}

/**
 * Open the application administration page directly.
 *
 * @param page Playwright page
 */
async function openAdminSettings(page: Page): Promise<void> {
	await page.goto(adminSettingsUrl, { waitUntil: 'domcontentloaded' })
	await expect(page).toHaveURL(/\/index\.php\/settings\/admin\/usage-statistics-server$/)
	await expect(page.getByRole('heading', { name: 'Data retention' })).toBeVisible()
}

test.beforeEach(async ({ page }) => {
	await login(page)
	await openAdminSettings(page)
})

test('administrator can persist the retention period', async ({ page }) => {
	const input = page.getByLabel('Retention period (days)')
	const saveButton = page.getByRole('button', { name: 'Save', exact: true })

	await expect(input).toBeVisible()
	const originalValue = await input.inputValue()
	const updatedValue = originalValue === '120' ? '121' : '120'

	await input.fill(updatedValue)
	await saveButton.click()
	await expect(page.getByText('Settings saved.')).toBeVisible()

	await page.reload({ waitUntil: 'domcontentloaded' })
	await expect(page.getByLabel('Retention period (days)')).toHaveValue(updatedValue)

	// Restore the value that was present when the test started so retries and
	// subsequent scenarios do not depend on state left behind by this test.
	const reloadedInput = page.getByLabel('Retention period (days)')
	await reloadedInput.fill(originalValue)
	await page.getByRole('button', { name: 'Save', exact: true }).click()
	await expect(page.getByText('Settings saved.')).toBeVisible()
})

test('administrator cannot save a retention period outside the allowed range', async ({ page }) => {
	const input = page.getByLabel('Retention period (days)')
	const saveButton = page.getByRole('button', { name: 'Save', exact: true })

	await expect(page.getByText('Allowed range: 45 to 3650 days.')).toBeVisible()

	await input.fill('44')
	await expect(saveButton).toBeDisabled()

	await input.fill('3651')
	await expect(saveButton).toBeDisabled()

	await input.fill('45')
	await expect(saveButton).toBeEnabled()

	await input.fill('3650')
	await expect(saveButton).toBeEnabled()
})
