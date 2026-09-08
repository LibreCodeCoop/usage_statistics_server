<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection
		:name="t('usage_statistics_server', 'Data retention')"
		:description="t('usage_statistics_server', 'Choose how long historical usage reports are kept before automatic cleanup.')">
		<NcNoteCard v-if="loadError" type="error">
			{{ loadError }}
		</NcNoteCard>

		<div v-else class="retention-settings">
			<NcTextField
				v-model="retentionDays"
				:label="t('usage_statistics_server', 'Retention period (days)')"
				type="number"
				:min="minimumRetentionDays"
				:max="maximumRetentionDays"
				:disabled="loading || saving"
				:help-text="t('usage_statistics_server', 'Reports older than this period are removed by the daily cleanup job.')" />

			<p class="retention-settings__range">
				{{ t('usage_statistics_server', 'Allowed range: {min} to {max} days.', { min: minimumRetentionDays, max: maximumRetentionDays }) }}
			</p>

			<NcButton
				variant="primary"
				:disabled="!canSave"
				@click="save">
				{{ saving ? t('usage_statistics_server', 'Saving…') : t('usage_statistics_server', 'Save') }}
			</NcButton>

			<NcNoteCard v-if="saveError" type="error">
				{{ saveError }}
			</NcNoteCard>
			<NcNoteCard v-else-if="saved" type="success">
				{{ t('usage_statistics_server', 'Settings saved.') }}
			</NcNoteCard>
		</div>
	</NcSettingsSection>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { getServerSettings, updateServerSettings } from '../api/settings'

const loading = ref(true)
const saving = ref(false)
const saved = ref(false)
const loadError = ref('')
const saveError = ref('')
const retentionDays = ref('')
const minimumRetentionDays = ref(45)
const maximumRetentionDays = ref(3650)

const parsedRetentionDays = computed(() => Number.parseInt(retentionDays.value, 10))
const canSave = computed(() => {
	const value = parsedRetentionDays.value
	return !loading.value
		&& !saving.value
		&& Number.isInteger(value)
		&& value >= minimumRetentionDays.value
		&& value <= maximumRetentionDays.value
})

onMounted(async () => {
	try {
		const settings = await getServerSettings()
		retentionDays.value = String(settings.retentionDays)
		minimumRetentionDays.value = settings.minimumRetentionDays
		maximumRetentionDays.value = settings.maximumRetentionDays
	} catch {
		loadError.value = t('usage_statistics_server', 'Could not load Usage Statistics settings.')
	} finally {
		loading.value = false
	}
})

async function save(): Promise<void> {
	if (!canSave.value) {
		return
	}

	saving.value = true
	saved.value = false
	saveError.value = ''
	try {
		const savedValue = await updateServerSettings(parsedRetentionDays.value)
		retentionDays.value = String(savedValue)
		saved.value = true
	} catch {
		saveError.value = t('usage_statistics_server', 'Could not save Usage Statistics settings.')
	} finally {
		saving.value = false
	}
}
</script>

<style scoped>
.retention-settings {
	max-width: 560px;
}

.retention-settings__range {
	margin: 8px 0 16px;
	color: var(--color-text-maxcontrast);
}
</style>
