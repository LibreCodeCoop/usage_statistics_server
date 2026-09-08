/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'

/**
 * Sign in as the test administrator.
 *
 * @param page Playwright page
 */
async function login(page: Page): Promise<void> {
	await page.goto('/index.php/login')
	await page.locator('#user').fill('admin')
	await page.locator('#password').fill('admin')
	await page.locator('button[type="submit"]').click()
	await page.waitForURL(/\/index\.php\/apps\/|\/index\.php\/settings\/user/)
}

/**
 * Open the app administration settings through the Nextcloud settings navigation.
 *
 * @param page Playwright page
 */
async function openAdminSettings(page: Page): Promise<void> {
	await page.goto('/index.php/settings/admin/overview')
	await page.getByRole('link', { name: 'Usage Statistics', exact: true }).click()
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

	await page.reload()
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
