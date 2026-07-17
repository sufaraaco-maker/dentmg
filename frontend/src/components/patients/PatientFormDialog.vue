<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { api } from '@/lib/api'
import { BLOOD_TYPES, PATIENT_GENDERS, type Patient } from '@/types/patient'
import { parseLocalDate, toLocalDateString } from '@/lib/date'

const props = defineProps<{
  visible: boolean
  patient: Patient | null
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: [patient: Patient]
}>()

const { t } = useI18n()
const toast = useToast()

const genderOptions = PATIENT_GENDERS.map((gender) => ({
  value: gender,
  label: t(`patients.genders.${gender}`),
}))
const bloodTypeOptions = BLOOD_TYPES.map((type) => ({ value: type, label: type }))

const today = new Date()
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

function emptyForm() {
  return {
    first_name: '',
    last_name: '',
    date_of_birth: null as Date | null,
    gender: 'male' as (typeof PATIENT_GENDERS)[number],
    phone: '',
    email: '',
    address: '',
    national_id: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    blood_type: null as (typeof BLOOD_TYPES)[number] | null,
    allergies: '',
    medical_history: '',
    insurance_provider: '',
    insurance_number: '',
    notes: '',
  }
}

const form = reactive(emptyForm())

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return

    errors.value = {}
    const source = props.patient

    if (source) {
      Object.assign(form, {
        first_name: source.first_name,
        last_name: source.last_name,
        date_of_birth: parseLocalDate(source.date_of_birth),
        gender: source.gender,
        phone: source.phone,
        email: source.email ?? '',
        address: source.address ?? '',
        national_id: source.national_id ?? '',
        emergency_contact_name: source.emergency_contact_name ?? '',
        emergency_contact_phone: source.emergency_contact_phone ?? '',
        blood_type: source.blood_type,
        allergies: source.allergies ?? '',
        medical_history: source.medical_history ?? '',
        insurance_provider: source.insurance_provider ?? '',
        insurance_number: source.insurance_number ?? '',
        notes: source.notes ?? '',
      })
    } else {
      Object.assign(form, emptyForm())
    }
  },
)

function toPayload() {
  return {
    ...form,
    date_of_birth: form.date_of_birth ? toLocalDateString(form.date_of_birth) : null,
    email: form.email || null,
    address: form.address || null,
    national_id: form.national_id || null,
    emergency_contact_name: form.emergency_contact_name || null,
    emergency_contact_phone: form.emergency_contact_phone || null,
    allergies: form.allergies || null,
    medical_history: form.medical_history || null,
    insurance_provider: form.insurance_provider || null,
    insurance_number: form.insurance_number || null,
    notes: form.notes || null,
  }
}

async function onSave() {
  saving.value = true
  errors.value = {}

  try {
    const payload = toPayload()
    const { data } = props.patient
      ? await api.put<Patient>(`/patients/${props.patient.id}`, payload)
      : await api.post<Patient>('/patients', payload)

    emit('saved', data)
    emit('update:visible', false)
  } catch (err: any) {
    if (err?.response?.status === 422) {
      errors.value = err.response.data.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('patients.saveError'), life: 3000 })
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    :header="patient ? t('patients.editTitle') : t('patients.createTitle')"
    class="w-full max-w-2xl"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="onSave">
      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('patients.firstName') }}</label>
        <InputText v-model="form.first_name" required />
        <Message v-if="errors.first_name" severity="error" size="small">{{ errors.first_name[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('patients.lastName') }}</label>
        <InputText v-model="form.last_name" required />
        <Message v-if="errors.last_name" severity="error" size="small">{{ errors.last_name[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('patients.dateOfBirth') }}</label>
        <DatePicker
          v-model="form.date_of_birth"
          date-format="yy-mm-dd"
          :max-date="today"
          show-icon
          fluid
          required
        />
        <Message v-if="errors.date_of_birth" severity="error" size="small">{{
          errors.date_of_birth[0]
        }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('patients.gender') }}</label>
        <Select
          v-model="form.gender"
          :options="genderOptions"
          option-label="label"
          option-value="value"
          fluid
        />
        <Message v-if="errors.gender" severity="error" size="small">{{ errors.gender[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('patients.phone') }}</label>
        <InputText v-model="form.phone" required />
        <Message v-if="errors.phone" severity="error" size="small">{{ errors.phone[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('patients.email') }}</label>
        <InputText v-model="form.email" type="email" />
        <Message v-if="errors.email" severity="error" size="small">{{ errors.email[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2 sm:col-span-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('patients.address') }}</label>
        <Textarea v-model="form.address" rows="2" auto-resize />
        <Message v-if="errors.address" severity="error" size="small">{{ errors.address[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('patients.nationalId') }}</label>
        <InputText v-model="form.national_id" />
        <Message v-if="errors.national_id" severity="error" size="small">{{ errors.national_id[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('patients.bloodType') }}</label>
        <Select
          v-model="form.blood_type"
          :options="bloodTypeOptions"
          option-label="label"
          option-value="value"
          show-clear
          fluid
        />
        <Message v-if="errors.blood_type" severity="error" size="small">{{ errors.blood_type[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('patients.emergencyContactName') }}
        </label>
        <InputText v-model="form.emergency_contact_name" />
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('patients.emergencyContactPhone') }}
        </label>
        <InputText v-model="form.emergency_contact_phone" />
      </div>

      <div class="flex flex-col gap-2 sm:col-span-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('patients.allergies') }}</label>
        <Textarea v-model="form.allergies" rows="2" auto-resize />
      </div>

      <div class="flex flex-col gap-2 sm:col-span-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('patients.medicalHistory')
        }}</label>
        <Textarea v-model="form.medical_history" rows="2" auto-resize />
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('patients.insuranceProvider') }}
        </label>
        <InputText v-model="form.insurance_provider" />
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('patients.insuranceNumber') }}
        </label>
        <InputText v-model="form.insurance_number" />
      </div>

      <div class="flex flex-col gap-2 sm:col-span-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('patients.notes') }}</label>
        <Textarea v-model="form.notes" rows="2" auto-resize />
      </div>

      <div class="flex justify-end gap-2 pt-2 sm:col-span-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
