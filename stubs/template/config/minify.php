<?php

/*
|--------------------------------------------------------------------------
| Minify
|--------------------------------------------------------------------------
|
| Two changes to the package defaults, both so the test suite stands on its own.
|
| The import paths are absolute. The defaults are relative ("../resources/css/"), which
| only resolve when the working directory is public/ — true for a web request, false for
| anything run through artisan. So a compile from the CLI looked for the stylesheets one
| directory above the project and threw "minireset.css not found within importPaths".
|
| And "testing" is gone from skip_environment. With it there, Minify does not compile
| during tests at all: it simply points at public/css/builds/app.css and hopes it is
| there. That made the suite depend on a build left behind by an earlier browser request
| — green on a developer's machine, five hundred errors on a fresh CI checkout, where no
| such file exists. Worse, the tests that read the compiled CSS were checking whatever a
| dev build had produced, not the sources in the repository.
|
*/

return [
    'output' => [
        'css' => 'css/builds/app.css',
        'js' => 'js/builds/app.js',
    ],

    'skip_environment' => [
        'production',
    ],

    'scssImportPaths' => [
        resource_path('sass').'/',
        resource_path('scss').'/',
        resource_path('css').'/',
        public_path('css').'/',
    ],

    'jsImportPaths' => [
        resource_path('js').'/',
        public_path('js').'/',
    ],

    'compressed' => true,
    'jsFlaggedComments' => false,
];
