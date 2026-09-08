/*
 * SPDX-FileCopyrightText: 2026 Vitor Mattos
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { recommended } from '@nextcloud/eslint-config'

export default [
	...recommended,
	{
		name: 'usage-statistics-server/ignores',
		ignores: [
			'build/*',
			'node_modules/*',
			'src/types/openapi/*',
			'openapi*.json',
		],
	},
]
