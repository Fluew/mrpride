<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Force light mode background --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }
        </style>

        <link rel="icon" type="image/png" href="/LOGO_MR_PRIDE.png">
        <link rel="shortcut icon" type="image/png" href="/LOGO_MR_PRIDE.png">
        <link rel="apple-touch-icon" href="/LOGO_MR_PRIDE.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
