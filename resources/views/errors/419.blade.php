@include('errors.layout', [
    'code' => '419',
    'title' => __('Your session timed out'),
    'message' => __('You were away long enough that we closed the session for safety. Sign in again and your cart will be waiting.'),
])
