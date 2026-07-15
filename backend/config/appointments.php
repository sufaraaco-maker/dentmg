<?php

return [

    /*
     * Granularity, in minutes, used when slicing a dentist's free working-hour ranges into
     * candidate appointment start times in AppointmentService::availableSlots().
     * See docs/modules/appointments-design-draft.md §6/§9.
     */
    'slot_interval_minutes' => env('APPOINTMENTS_SLOT_INTERVAL_MINUTES', 15),

];
