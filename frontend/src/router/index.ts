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
