<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Participation Point Weights
    |--------------------------------------------------------------------------
    |
    | Points awarded per engagement activity. Quiz completion is the highest
    | contributor, followed by creating topics, then replying to discussions,
    | with daily login providing a smaller baseline contribution.
    |
    */

    'weights' => [
        'quiz_completed' => (float) env('PARTICIPATION_QUIZ_COMPLETED', 10),
        'topic_created' => (float) env('PARTICIPATION_TOPIC_CREATED', 5),
        'reply_posted' => (float) env('PARTICIPATION_REPLY_POSTED', 3),
        'daily_login' => (float) env('PARTICIPATION_DAILY_LOGIN', 1),
    ],

];
