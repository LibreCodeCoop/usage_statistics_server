/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export interface ServerSettings {
	retentionDays: number
	minimumRetentionDays: number
	maximumRetentionDays: number
}

interface OcsResponse<T> {
	ocs: {
		data: T
	}
}

const settingsUrl = generateOcsUrl('/apps/usage_statistics_server/api/v1/admin/settings')

/**
 * Load the current server settings.
 */
export async function getServerSettings(): Promise<ServerSettings> {
	const response = await axios.get<OcsResponse<ServerSettings>>(settingsUrl, {
		headers: { 'OCS-APIRequest': 'true' },
	})
	return response.data.ocs.data
}

/**
 * Update the report retention period.
 *
 * @param retentionDays Retention period in days
 */
export async function updateServerSettings(retentionDays: number): Promise<number> {
	const response = await axios.put<OcsResponse<{ retentionDays: number }>>(
		settingsUrl,
		{ retentionDays },
		{ headers: { 'OCS-APIRequest': 'true' } },
	)
	return response.data.ocs.data.retentionDays
}
