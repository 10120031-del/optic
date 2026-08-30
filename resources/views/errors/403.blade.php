@include('errors.layout', [
    'code' => '403',
    'title' => __('That area isn\'t yours to open'),
    'message' => $exception?->getMessage() ?: __('You are signed in, but this page belongs to a different kind of account.'),
])
