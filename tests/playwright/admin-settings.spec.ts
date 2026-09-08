/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test, type Page } from '@playwright/test'

/**
 * Sign in as the test administrator.
 *
 * @param page Playwright page
 */
async function login(page: Page): Promise<void> {
	await page.goto('/index.php/login')
	await page.locator('#user').fill('admin')
	await page.locator('#password').fill('admin')
	await page.locator('#submit-form').click()
	await page.waitForURL(/\/index\.php\/apps\/|\/index\.php\/settings\/user/)
}

test('administrator can change the retention period', async ({ page }) => {
	await login(page)
	await page.goto('/index.php/settings/admin/usage-statistics-server')

	const input = page.getByLabel('Retention period (days)')
	await expect(input).toHaveValue('1095')
	await input.fill('120')
	await page.getByRole('button', { name: 'Save' }).click()
	await expect(page.getByText('Settings saved.')).toBeVisible()

	await page.reload()
	await expect(input).toHaveValue('120')
})
