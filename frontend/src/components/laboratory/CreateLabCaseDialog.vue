<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { api } from '@/lib/api'
import PatientSearchSelect from '@/components/appointments/PatientSearchSelect.vue'
import DentistSelect from '@/components/appointments/DentistSelect.vue'
import { useLabsStore } from '@/stores/labs'
import { usePatientLabCasesStore } from '@/stores/patientLabCases'
import { isLabCaseError } from '@/services/laboratory'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import { TOOTH_CODES, toothDisplayName } from '@/lib/teeth'
import type { LabCase } from '@/types/laboratory'
import type { Patient } from '@/types/patient'

/**
 * Creates a new draft Lab Case (design doc §3/§5/§6) — patient/lab are the two fields required up
 * front; everything else can be filled in or edited later while still Draft.
 *
 * `patientId` (Phase 2.4, patient-laboratory-redesign-design.md §7 decision 1): optional — when the
 * standalone Lab Cases page opens this dialog, the patient isn't known yet, so `PatientSearchSelect`
 * renders as before. When the Patient Profile Laboratory tab opens it, the patient is already fixed
 * by context, so the search step is skipped and the field is pre-filled — same precedent already set
 * by `CreateTreatmentPlanDialog.vue`'s own required `patientId` prop for its (always patient-context)
 * call site.
 */
const props = defineProps<{ visible: boolean; patientId?: string }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  created: [labCase: LabCase]
}>()

const { t } = useI18n()
const toast = useToast()
const labsStore = useLabsStore()
const patientLabCasesStore = usePatientLabCasesStore()

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const toothOptions = TOOTH_CODES.map((code) => ({
  label: `${code} — ${toothDisplayName(code)}`,
  value: code,
}))

function emptyForm() {
  return {
    patient_id: props.patientId ?? (null as string | null),
    lab_id: null as string | null,
    dentist_id: null as string | null,
    tooth_numbers: [] as string[],
    case_type: '',
    shade: '',
    material: '',
    instructions: '',
    fee: null as number | null,
    tracking_number: '',
  }
}

const form = reactive(emptyForm())

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return
    errors.value = {}
    Object.assign(form, emptyForm())
  },
  { immediate: true },
)

onMounted(() => {
  labsStore.fetchAll()
})

useDialogFocusRestore(() => props.visible)

function onPatientSelected(patient: Patient) {
  form.patient_id = patient.id
}

function validate(): boolean {
  const nextErrors: Record<string, string[]> = {}

  if (!form.patient_id) nextErrors.patient_id = [t('laboratory.labCases.fieldRequired')]
  if (!form.lab_id) nextErrors.lab_id = [t('laboratory.labCases.fieldRequired')]

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (!validate()) return

  saving.value = true
  errors.value = {}

  try {
    const payload = {
      // Guaranteed non-null past validate() above.
      patient_id: form.patient_id!,
      lab_id: form.lab_id!,
      dentist_id: form.dentist_id,
      tooth_numbers: form.tooth_numbers.length ? form.tooth_numbers : null,
      case_type: form.case_type.trim() || null,
      shade: form.shade.trim() || null,
      material: form.material.trim() || null,
      instructions: form.instructions.trim() || null,
      fee: form.fee,
      tracking_number: form.tracking_number.trim() || null,
    }

    // Phase 2.4 (patient-laboratory-redesign-design.md §7 decision 1): when opened with a fixed
    // patientId (the Patient Profile tab), route the mutation through `patientLabCases.ts` so its
    // cache picks up the new case immediately — same convention as
    // `CreateTreatmentPlanDialog.vue` calling `treatmentPlansStore.create()`. The standalone
    // Lab Cases page (no patientId) keeps calling the API directly, unchanged — it has no
    // patient-scoped store to update.
    const data = props.patientId
      ? await patientLabCasesStore.create(props.patientId, payload)
      : (await api.post<LabCase>('/lab-cases', payload)).data

    emit('created', data)
    emit('update:visible', false)
  } catch (err: unknown) {
    if (isLabCaseError(err)) {
      toast.add({ severity: 'error', summary: err.message, life: 4000 })
      return
    }

    const response = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } })
      ?.response

    if (response?.status === 422) {
      errors.value = response.data?.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('laboratory.labCases.createError'), life: 3000 })
    }
  } finally {
    saving.value = false
  }
}

const activeLabs = computed(() => labsStore.items.filter((lab) => lab.is_active))
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    :header="t('laboratory.labCases.createTitle')"
    class="w-full max-w-lg"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div v-if="!patientId" class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('laboratory.labCases.patient') }}
        </label>
        <PatientSearchSelect v-model="form.patient_id" autofocus @patient-selected="onPatientSelected" />
        <Message v-if="errors.patient_id" severity="error" size="small">{{ errors.patient_id[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label for="lab-case-lab" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('laboratory.labCases.lab') }}
        </label>
        <Select
          v-model="form.lab_id"
          input-id="lab-case-lab"
          :options="activeLabs"
          option-label="name"
          option-value="id"
          filter
          :placeholder="t('laboratory.labCases.selectLab')"
          :invalid="!!errors.lab_id"
          fluid
        />
        <Message v-if="errors.lab_id" severity="error" size="small">{{ errors.lab_id[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('laboratory.labCases.dentist') }}
        </label>
        <DentistSelect v-model="form.dentist_id" />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label for="lab-case-type" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('laboratory.labCases.caseType') }}
          </label>
          <InputText
            id="lab-case-type"
            v-model="form.case_type"
            :placeholder="t('laboratory.labCases.caseTypePlaceholder')"
            fluid
          />
        </div>
        <div class="flex flex-col gap-2">
          <label for="lab-case-shade" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('laboratory.labCases.shade') }}
          </label>
          <InputText id="lab-case-shade" v-model="form.shade" fluid dir="ltr" />
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <label for="lab-case-teeth" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('laboratory.labCases.toothNumbers') }}
        </label>
        <MultiSelect
          v-model="form.tooth_numbers"
          input-id="lab-case-teeth"
          :options="toothOptions"
          option-label="label"
          option-value="value"
          filter
          display="chip"
          :placeholder="t('laboratory.labCases.selectTeeth')"
          fluid
        />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label for="lab-case-material" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('laboratory.labCases.material') }}
          </label>
          <InputText id="lab-case-material" v-model="form.material" fluid />
        </div>
        <div class="flex flex-col gap-2">
          <label for="lab-case-fee" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('laboratory.labCases.fee') }}
          </label>
          <InputNumber
            v-model="form.fee"
            input-id="lab-case-fee"
            mode="currency"
            currency="USD"
            :min="0"
            fluid
          />
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <label for="lab-case-instructions" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('laboratory.labCases.instructions') }}
        </label>
        <Textarea id="lab-case-instructions" v-model="form.instructions" auto-resize rows="3" fluid />
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.create')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
