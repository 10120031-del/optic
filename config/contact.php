<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shop contact details
    |--------------------------------------------------------------------------
    |
    | Everything the About page prints about how to reach the shop. Kept in
    | config rather than hard-coded in the Blade view so a change of phone
    | number or a new social account is an .env edit on the server, not a
    | redeploy — and so the footer, the About page and any future contact
    | block all read the same single source.
    |
    | The WhatsApp number deliberately is NOT repeated here: it already lives
    | in config/whatsapp.php for the floating chat button, and the About page
    | reads it from there so the two can never drift apart.
    |
    */

    'phone' => env('CONTACT_PHONE', '+961 1 785 400'),

    'email' => env('CONTACT_EMAIL', 'hello@lucentoptics.com'),

    // Where returns, prescriptions and order questions should be sent if the
    // customer would rather write than use the form. Falls back to the main
    // address so a shop with one inbox does not have to set it.
    'support_email' => env('CONTACT_SUPPORT_EMAIL', env('CONTACT_EMAIL', 'hello@lucentoptics.com')),

    'address' => env('CONTACT_ADDRESS', 'Verdun Street, Ras Beirut'),

    'city' => env('CONTACT_CITY', 'Beirut'),

    'country' => env('CONTACT_COUNTRY', 'Lebanon'),

    // Printed as-is under the address. Two lines at most — the layout gives
    // it a narrow column.
    'hours' => env('CONTACT_HOURS', 'Mon–Fri 9:00–18:00 · Sat 10:00–16:00'),

    /*
    | Optional deep link to the shop on a map. Left empty by default because a
    | wrong pin is worse than none; set it to whatever "share" URL your map
    | provider gives you and the address block turns into a link.
    */
    'map_url' => env('CONTACT_MAP_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Social accounts
    |--------------------------------------------------------------------------
    |
    | Any entry left empty is skipped entirely — no dead icon, no link to a
    | profile that does not exist. The defaults below are placeholders for a
    | fictional shop; point them at the real accounts (or blank them) before
    | going live.
    |
    */

    'social' => [
        'facebook' => env('CONTACT_FACEBOOK', 'https://www.facebook.com/lucentoptics'),
        'instagram' => env('CONTACT_INSTAGRAM', 'https://www.instagram.com/lucentoptics'),
        'twitter' => env('CONTACT_TWITTER', 'https://x.com/lucentoptics'),
        'youtube' => env('CONTACT_YOUTUBE', 'https://www.youtube.com/@lucentoptics'),
        'tiktok' => env('CONTACT_TIKTOK', ''),
        'linkedin' => env('CONTACT_LINKEDIN', ''),
    ],

];
