<?php

return [
    'chat_attachments' => [
        // Use "public" to serve files via /storage URL. Use "local" for private storage.
        'disk' => env('HELPDESK_CHAT_ATTACHMENTS_DISK', 'local'),

        // Base folder inside the selected disk.
        'directory' => env('HELPDESK_CHAT_ATTACHMENTS_DIR', 'ticket-mensajes'),
    ],

    'pending_ticket_reminders' => [
        'interval_minutes' => (int) env('HELPDESK_PENDING_TICKET_REMINDER_MINUTES', 5),
        'web_fallback_enabled' => filter_var(env('HELPDESK_PENDING_TICKET_REMINDER_WEB_FALLBACK', true), FILTER_VALIDATE_BOOLEAN),
        'fallback_check_seconds' => (int) env('HELPDESK_PENDING_TICKET_REMINDER_FALLBACK_CHECK_SECONDS', 60),
    ],

    'notifications' => [
        'retention_days' => (int) env('HELPDESK_NOTIFICATIONS_RETENTION_DAYS', 30),
    ],
];
