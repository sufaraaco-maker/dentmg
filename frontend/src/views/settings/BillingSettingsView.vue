<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import Skeleton from 'primevue/skeleton'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Message from 'primevue/message'
import Button from 'primevue/button'
import { getBillingSettings, updateBillingSettings } from '@/services/settings'
import type { BillingSetting } from '@/types/settings'

/**
 * Billing Settings (design doc §4.2/§7) — a single-form edit screen for the financial-config
 * singleton. `next_invoice_sequence` is shown read-only: `InvoiceService` owns writing it via its
 * own lock-and-increment logic (design doc §8 decision 3).
 */
const { t } = useI18n()
const toast = useToast()

const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const nextInvoiceSequence = ref(0)

const form = reactive({ currency_code: '', tax_rate: null as number | null, invoice_number_prefix: '' })

function applySettings(settings: BillingSetting) {
  form.currency_code = settings.currency_code
  form.tax_rate = settings.tax_rate !== null ? Number(settings.tax_rate) : null
  form.invoice_number_prefix = settings.invoice_number_prefix
  nextInvoiceSequence.value = settings.next_invoice_sequence
}

async function load() {
  loading.value = true
  try {
    applySettings(await getBillingSettings())
  } catch {
    toast.add({ severity: 'error', summary: t('settings.billing.loadError'), life: 3000 })
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function submit() {
  saving.value = true
  errors.value = {}

  try {
    const saved = await updateBillingSettings({
      currency_code: form.currency_code.trim().toUpperCase(),
      tax_rate: form.tax_rate,
      invoice_number_prefix: form.invoice_number_prefix.trim(),
    })
    applySettings(saved)
    toast.add({ severity: 'success', summary: t('settings.billing.saved'), life: 3000 })
  } catch (err: unknown) {
    const response = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } })
      ?.response

    if (response?.status === 422) {
      errors.value = response.data?.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('settings.billing.saveError'), life: 3000 })
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
      {{ t('settings.billing.title') }}
    </h1>

    <Skeleton v-if="loading" height="20rem" />

    <Card v-else class="max-w-2xl">
      <template #content>
        <form class="flex flex-col gap-4" @submit.prevent="submit">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-2">
              <label for="billing-currency" class="text-sm text-surface-700 dark:text-surface-200">
                {{ t('settings.billing.currencyCode') }}
              </label>
              <InputText
                id="billing-currency"
                v-model="form.currency_code"
                :invalid="!!errors.currency_code"
                maxlength="3"
                fluid
                dir="ltr"
                class="uppercase"
              />
              <Message v-if="errors.currency_code" severity="error" size="small">
                {{ errors.currency_code[0] }}
              </Message>
            </div>

            <div class="flex flex-col gap-2">
              <label for="billing-tax-rate" class="text-sm text-surface-700 dark:text-surface-200">
                {{ t('settings.billing.taxRate') }}
              </label>
              <InputNumber
                v-model="form.tax_rate"
                input-id="billing-tax-rate"
                :min="0"
                :max="100"
                :min-fraction-digits="0"
                :max-fraction-digits="2"
                suffix=" %"
                :invalid="!!errors.tax_rate"
                fluid
              />
              <Message v-if="errors.tax_rate" severity="error" size="small">{{ errors.tax_rate[0] }}</Message>
            </div>
          </div>

          <div class="flex flex-col gap-2">
            <label for="billing-prefix" class="text-sm text-surface-700 dark:text-surface-200">
              {{ t('settings.billing.invoiceNumberPrefix') }}
            </label>
            <InputText
              id="billing-prefix"
              v-model="form.invoice_number_prefix"
              :invalid="!!errors.invoice_number_prefix"
              fluid
              dir="ltr"
            />
            <Message v-if="errors.invoice_number_prefix" severity="error" size="small">
              {{ errors.invoice_number_prefix[0] }}
            </Message>
          </div>

          <div class="flex flex-col gap-2">
            <label for="billing-next-sequence" class="text-sm text-surface-700 dark:text-surface-200">
              {{ t('settings.billing.nextInvoiceSequence') }}
            </label>
            <InputText
              id="billing-next-sequence"
              :model-value="String(nextInvoiceSequence)"
              disabled
              fluid
              dir="ltr"
            />
            <p class="text-xs text-surface-500 dark:text-surface-400">
              {{ t('settings.billing.nextInvoiceSequenceHint') }}
            </p>
          </div>

          <div class="flex justify-end pt-2">
            <Button type="submit" :label="t('common.save')" :loading="saving" />
          </div>
        </form>
      </template>
    </Card>
  </div>
</template>
