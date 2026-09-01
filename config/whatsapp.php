<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp click-to-chat
    |--------------------------------------------------------------------------
    |
    | The storefront's chat button is a plain wa.me deep link — no API key, no
    | provider, no SDK. The visitor composes a message on our page and it opens
    | in *their* WhatsApp (app on mobile, web.whatsapp.com on desktop) already
    | addressed to the shop with the text filled in. Replies happen in the shop's
    | own WhatsApp, not on this site; hosting the conversation inside the page
    | would need the WhatsApp Business API and a paid BSP.
    |
    */

    'enabled' => filter_var(env('WHATSAPP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Full international number. Anything that is not a digit is stripped before
    | the link is built, so "+961 71 234 567" and "96171234567" both work — but
    | the country code is required and a leading zero must be dropped: a local
    | "071 234 567" will not resolve. Leave empty to hide the button entirely.
    */
    'number' => env('WHATSAPP_NUMBER', ''),

    'label' => env('WHATSAPP_LABEL', 'Lucent Optics'),

    'hours' => env('WHATSAPP_HOURS', 'Mon–Sat, 9:00–18:00'),

    // Shown as the shop's opening bubble in the panel. Never sent.
    'greeting' => env('WHATSAPP_GREETING', 'Hi! Questions about frames, lenses or an order? Send us a message and we will get back to you shortly.'),

];
