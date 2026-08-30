@include('errors.layout', [
    'code' => '429',
    'title' => __('Too many requests'),
    'message' => __('That came through faster than we can serve it. Give it a moment and try again.'),
])
