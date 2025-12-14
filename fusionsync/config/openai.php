<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAI API configuration
    |--------------------------------------------------------------------------
    |
    | Define the API key, model and other defaults for interacting with
    | OpenAI's API. These values can be overridden in your environment
    | file or elsewhere in your configuration.
    |
    */

    'api_key' => env('OPENAI_API_KEY'),

    // default model to use for chat/completion requests
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),

    // request timeout (seconds)
    'timeout' => env('OPENAI_TIMEOUT', 60),

    // base uri (useful for testing/mocks)
    'base_uri' => env('OPENAI_BASE_URI', 'https://api.openai.com/v1'),
];
