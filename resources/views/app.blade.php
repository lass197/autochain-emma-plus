<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Autochain Emma+ — double numérique infalsifiable de chaque véhicule">
        <title>{{ config('autochain.name', config('app.name')) }}</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body>
        <div id="root"></div>
    </body>
</html>
