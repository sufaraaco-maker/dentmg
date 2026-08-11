import { createRouter, createWebHistory } from 'vue-router'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import { useAuthStore } from '@/stores/auth'
import type { UserRole } from '@/types/user'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guestOnly?: boolean
    /** Roles allowed to access this route. Omitted = any authenticated role. Enforced here, not just hidden from nav. */
    roles?: UserRole[]
  }
}

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/',
      component: DefaultLayout,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/views/DashboardView.vue'),
        },
        {
          path: 'users',
          name: 'users',
          component: () => import('@/views/UsersView.vue'),
          meta: { roles: ['admin'] },
        },
        {
          path: 'patients',
          name: 'patients',
          component: () => import('@/views/PatientsView.vue'),
        },
        {
          path: 'patients/:id',
          name: 'patient-detail',
          component: () => import('@/views/PatientDetailView.vue'),
        },
        {
          path: 'patients/:id/treatment-plans/:planId',
          name: 'treatment-plan-detail',
          component: () => import('@/views/TreatmentPlanDetailView.vue'),
        },
        {
          path: 'treatment-plans',
          name: 'treatment-plans',
          component: () => import('@/views/TreatmentPlansView.vue'),
        },
        {
          path: 'patients/:id/clinical-notes/:noteId',
          name: 'clinical-note-detail',
          component: () => import('@/views/ClinicalNoteDetailView.vue'),
          // Design doc §10/§15 Decision D: receptionists have no access to Clinical Notes at all —
          // enforced here too (not just the hidden tab), same "backend/route both guard, not just
          // the UI" bar as every other role-restricted route in this file.
          meta: { roles: ['admin', 'dentist'] },
        },
        {
          path: 'patients/:id/invoices/:invoiceId',
          name: 'invoice-detail',
          component: () => import('@/views/InvoiceDetailView.vue'),
        },
        {
          path: 'invoices',
          name: 'invoices',
          component: () => import('@/views/InvoicesView.vue'),
        },
        {
          path: 'appointments',
          name: 'appointments',
          component: () => import('@/views/AppointmentsView.vue'),
        },
        {
          path: 'appointments/types',
          name: 'appointment-types',
          component: () => import('@/views/AppointmentTypesView.vue'),
          meta: { roles: ['admin'] },
        },
        {
          path: 'appointments/schedule',
          name: 'dentist-schedule',
          component: () => import('@/views/DentistScheduleView.vue'),
          meta: { roles: ['admin', 'dentist'] },
        },
        {
          path: 'appointments/:id',
          name: 'appointment-detail',
          component: () => import('@/views/AppointmentDetailView.vue'),
        },
        {
          path: 'dental-chart/conditions',
          name: 'dental-conditions',
          component: () => import('@/views/DentalConditionsView.vue'),
          meta: { roles: ['admin'] },
        },
        {
          path: 'supplies',
          name: 'supplies',
          component: () => import('@/views/SuppliesView.vue'),
        },
        {
          path: 'supplies/:id',
          name: 'supply-detail',
          component: () => import('@/views/SupplyDetailView.vue'),
        },
        {
          path: 'purchase-orders',
          name: 'purchase-orders',
          component: () => import('@/views/PurchaseOrdersView.vue'),
        },
        {
          path: 'purchase-orders/:id',
          name: 'purchase-order-detail',
          component: () => import('@/views/PurchaseOrderDetailView.vue'),
        },
        {
          path: 'inventory/suppliers',
          name: 'suppliers',
          component: () => import('@/views/SuppliersView.vue'),
          meta: { roles: ['admin'] },
        },
        {
          path: 'inventory/categories',
          name: 'supply-categories',
          component: () => import('@/views/SupplyCategoriesView.vue'),
          meta: { roles: ['admin'] },
        },
        {
          path: 'lab-cases',
          name: 'lab-cases',
          component: () => import('@/views/LabCasesView.vue'),
        },
        {
          path: 'lab-cases/:id',
          name: 'lab-case-detail',
          component: () => import('@/views/LabCaseDetailView.vue'),
        },
        {
          path: 'laboratory/labs',
          name: 'labs',
          component: () => import('@/views/LabsView.vue'),
          meta: { roles: ['admin'] },
        },
        {
          path: 'reports',
          name: 'reports',
          component: () => import('@/views/ReportsHomeView.vue'),
        },
        {
          path: 'permissions',
          name: 'permissions',
          component: () => import('@/views/PermissionsView.vue'),
          meta: { roles: ['admin'] },
        },
        {
          path: 'audit-logs',
          name: 'audit-logs',
          component: () => import('@/views/AuditLogsView.vue'),
          meta: { roles: ['admin'] },
        },
        {
          path: 'settings',
          name: 'settings',
          component: () => import('@/views/SettingsHomeView.vue'),
          meta: { roles: ['admin'] },
        },
        {
          path: 'settings/practice',
          name: 'settings-practice',
          component: () => import('@/views/settings/PracticeSettingsView.vue'),
          meta: { roles: ['admin'] },
        },
        {
          path: 'settings/billing',
          name: 'settings-billing',
          component: () => import('@/views/settings/BillingSettingsView.vue'),
          meta: { roles: ['admin'] },
        },
        {
          path: 'settings/ai-assistant',
          name: 'settings-ai-assistant',
          component: () => import('@/views/settings/AiAssistantSettingsView.vue'),
          meta: { roles: ['admin'] },
        },
        {
          // Notification System (Phase 5A) — the full history view behind the header bell's panel.
          // No `meta.roles`: every role has their own notifications and the API is self-scoped
          // (design doc §8.1 / Decision D6), so there is nothing role-specific to gate.
          path: 'notifications',
          name: 'notifications',
          component: () => import('@/views/NotificationsView.vue'),
        },
        {
          path: 'account',
          name: 'account',
          component: () => import('@/views/AccountView.vue'),
        },
        {
          path: 'reports/production',
          name: 'report-production',
          component: () => import('@/views/reports/ProductionReportView.vue'),
          meta: { roles: ['admin'] },
        },
        {
          path: 'reports/collections',
          name: 'report-collections',
          component: () => import('@/views/reports/CollectionsReportView.vue'),
          meta: { roles: ['admin'] },
        },
        {
          path: 'reports/ar-aging',
          name: 'report-ar-aging',
          component: () => import('@/views/reports/ArAgingReportView.vue'),
          meta: { roles: ['admin'] },
        },
        {
          path: 'reports/appointments',
          name: 'report-appointments',
          component: () => import('@/views/reports/AppointmentAnalyticsReportView.vue'),
        },
        {
          path: 'reports/treatment-plan-acceptance',
          name: 'report-treatment-plan-acceptance',
          component: () => import('@/views/reports/TreatmentPlanAcceptanceReportView.vue'),
        },
        {
          path: 'reports/new-patients',
          name: 'report-new-patients',
          component: () => import('@/views/reports/NewPatientsReportView.vue'),
        },
        {
          path: 'forbidden',
          name: 'forbidden',
          component: () => import('@/views/ForbiddenView.vue'),
        },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.initialized) {
    await auth.fetchUser()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  if (to.meta.roles && (!auth.user || !to.meta.roles.includes(auth.user.role))) {
    return { name: 'forbidden' }
  }

  return true
})
