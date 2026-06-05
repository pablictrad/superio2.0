<!DOCTYPE html>
<html lang="es">

<head>
    @include('partials.head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-zinc-100">

    {{ $slot }}

</body>

</html>