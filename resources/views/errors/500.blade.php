<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur serveur — Cyber Manager</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 p-6">
    <div class="text-center">
        <h1 class="text-9xl font-extrabold text-rose-600">500</h1>
        <p class="mt-4 text-2xl font-bold text-slate-900">Erreur serveur</p>
        <p class="mt-2 text-slate-500">Une erreur inattendue s'est produite. Veuillez réessayer plus tard ou contacter l'administrateur.</p>
        <a href="{{ route('dashboard') }}" class="mt-8 inline-flex h-11 items-center gap-2 rounded-xl bg-violet-600 px-6 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
            Retour au tableau de bord
        </a>
    </div>
</body>
</html>