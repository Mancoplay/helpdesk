<?php

return [
    'chat_attachments' => [
        // Use "public" to serve files via /storage URL. Use "local" for private storage.
        'disk' => env('HELPDESK_CHAT_ATTACHMENTS_DISK', 'public'),

        // Base folder inside the selected disk.
        'directory' => env('HELPDESK_CHAT_ATTACHMENTS_DIR', 'ticket-mensajes'),
    ],
];

