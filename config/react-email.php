<?php

// config for MsCodePL/LaravelReactEmail
return [

    /**
     * The path to the React Email templates (source .tsx files).
     *
     * Defaults to `resources/react-email`.
     */
    'path' => env('REACT_EMAIL_PATH', resource_path('react-email')),

    /**
     * The path to the built HTML output (Blade templates).
     *
     * Defaults to `resources/views/react-email`.
     */
    'build_path' => env('REACT_EMAIL_BUILD_PATH', resource_path('views/react-email')),

];
