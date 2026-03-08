<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <script>
        (() => {
            const themePreference = @json(auth()->user()?->theme_preference ?? 'system');
            const root = document.documentElement;
            const applyThemePreference = (preference) => {
                const resolvedTheme = preference === 'system'
                    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                    : preference;

                root.dataset.themePreference = preference;
                root.classList.toggle('dark', resolvedTheme === 'dark');
            };

            window.applyThemePreference = applyThemePreference;
            applyThemePreference(themePreference);
        })();
    </script>

    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>