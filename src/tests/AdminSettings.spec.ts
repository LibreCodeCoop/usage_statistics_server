/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import AdminSettings from '../views/AdminSettings.vue'
import { getServerSettings, updateServerSettings } from '../api/settings'

vi.mock('../api/settings', () => ({
	getServerSettings: vi.fn(),
	updateServerSettings: vi.fn(),
}))

const stubs = {
	NcSettingsSection: { template: '<section><slot /></section>' },
	NcNoteCard: { template: '<div><slot /></div>' },
	NcTextField: {
		props: ['modelValue', 'label', 'type', 'min', 'max', 'disabled', 'helpText'],
		emits: ['update:modelValue'],
		template: '<label>{{ label }}<input :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)"></label>',
	},
	NcButton: {
		props: ['disabled'],
		emits: ['click'],
		template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
}

describe('AdminSettings', () => {
	beforeEach(() => {
		vi.mocked(getServerSettings).mockReset()
		vi.mocked(updateServerSettings).mockReset()
		vi.mocked(getServerSettings).mockResolvedValue({
			retentionDays: 1095,
			minimumRetentionDays: 45,
			maximumRetentionDays: 3650,
		})
	})

	it('loads and saves the retention period', async () => {
		vi.mocked(updateServerSettings).mockResolvedValue(120)
		const wrapper = mount(AdminSettings, { global: { stubs } })
		await flushPromises()

		const input = wrapper.get('input')
		expect(input.element.value).toBe('1095')

		await input.setValue('120')
		await wrapper.get('button').trigger('click')
		await flushPromises()

		expect(updateServerSettings).toHaveBeenCalledWith(120)
		expect(wrapper.text()).toContain('Settings saved.')
	})

	it('does not allow values outside the configured range', async () => {
		const wrapper = mount(AdminSettings, { global: { stubs } })
		await flushPromises()

		await wrapper.get('input').setValue('44')
		expect(wrapper.get('button').attributes('disabled')).toBeDefined()
	})
})
