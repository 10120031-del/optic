@include('errors.layout', [
    'code' => '500',
    'title' => __('Something went wrong on our side'),
    'message' => __('The fault is ours, not yours. It has been logged and we are looking at it — please try again shortly.'),
])
