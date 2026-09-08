/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'

export default defineConfig({
	plugins: [vue()],
	test: {
		include: ['src/**/*.{test,spec}.?(c|m)[jt]s?(x)'],
		environment: 'happy-dom',
		pool: 'vmForks',
		deps: {
			inline: ['@nextcloud/vue'],
		},
		server: {
			deps: {
				inline: ['@nextcloud/vue'],
			},
		},
		coverage: {
			provider: 'v8',
			reporter: ['text', 'lcov'],
			include: ['src/**/*.{ts,vue}'],
			exclude: ['src/settings.ts', 'src/types/**', 'src/env.d.ts', 'src/tests/**'],
		},
	},
})
