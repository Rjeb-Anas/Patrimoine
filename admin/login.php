
<?php

session_start();

$baseUrl = '';
$login = '';

$erreur = $_SESSION['erreur'] ?? '';
unset($_SESSION['erreur']);

// Redirection si administrateur déjà connecté
if (
    isset($_SESSION['id']) &&
    ($_SESSION['role'] ?? '') === 'admin'
) {
    header('Location: dashboard.php');
    exit;
}

// Version du fichier CSS
$cssPath = __DIR__ . '/../css/style.css';
$cssVersion = file_exists($cssPath) ? filemtime($cssPath) : time();

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Connexion administrateur | Patrimoine</title>

    <!-- DARK MODE -->
    <script>
        (() => {
            const savedTheme = localStorage.getItem('theme');

            const prefersDark = window.matchMedia(
                '(prefers-color-scheme: dark)'
            ).matches;

            if (
                savedTheme === 'dark' ||
                (!savedTheme && prefersDark)
            ) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <!-- CSS -->
    <link rel="stylesheet"
          href="<?= $baseUrl ?>/css/style.css?v=<?= $cssVersion ?>">

</head>

<body class="min-h-screen flex flex-col
             bg-slate-50 dark:bg-slate-950
             text-slate-800 dark:text-slate-200">

    <!-- HEADER -->
    <header class="sticky top-0 z-50
                   bg-white/95 dark:bg-slate-900/95
                   backdrop-blur-md
                   border-b border-slate-200
                   dark:border-slate-800">

        <nav class="max-w-7xl mx-auto px-5 sm:px-6 lg:px-8
                    h-20 flex items-center justify-between">

            <!-- LOGO -->
            <a href="<?= $baseUrl ?>/index.php"
               class="flex items-center gap-3">

                <div class="w-11 h-11 rounded-xl
                            bg-cyan-800 flex items-center
                            justify-center text-white
                            text-xl font-bold">
                    P
                </div>

                <div>

                    <h1 class="text-xl font-bold
                               text-slate-900 dark:text-white">
                        Patrimoine
                    </h1>

                    <p class="text-xs text-slate-500
                              dark:text-slate-400">
                        Espace administration
                    </p>

                </div>

            </a>

            <!-- ACTIONS HEADER -->
            <div class="flex items-center gap-3">

                <!-- DARK MODE -->
                <button
                    id="themeToggle"
                    type="button"
                    class="w-10 h-10 rounded-lg
                           border border-slate-200
                           dark:border-slate-700
                           flex items-center justify-center
                           hover:bg-slate-100
                           dark:hover:bg-slate-800
                           transition"
                    aria-label="Changer le thème">

                    <svg id="sunIcon"
                         class="hidden w-5 h-5 text-amber-500"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">

                        <circle cx="12" cy="12" r="4"
                                stroke-width="2"/>

                        <path stroke-linecap="round"
                              stroke-width="2"
                              d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42
                              m11.3 11.3 1.42 1.42M2 12h2m16 0h2
                              M4.93 19.07l1.42-1.42m11.3-11.3
                              1.42-1.42"/>

                    </svg>

                    <svg id="moonIcon"
                         class="w-5 h-5 text-slate-700
                                dark:text-slate-200"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">

                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M20.9 13A9 9 0 0 1 11 3.1
                              9 9 0 1 0 20.9 13Z"/>

                    </svg>

                </button>

                <!-- RETOUR AU SITE -->
                <a href="<?= $baseUrl ?>/index.php"
                   class="hidden sm:inline-flex items-center
                          gap-2 text-sm font-medium
                          text-slate-600 dark:text-slate-300
                          hover:text-cyan-700
                          dark:hover:text-cyan-400 transition">

                    <span aria-hidden="true">&larr;</span>
                    Retour au site

                </a>

            </div>

        </nav>

    </header>

    <!-- CONTENU PRINCIPAL -->
    <main class="flex-1 flex items-center justify-center
                 px-5 py-14">

        <div class="w-full max-w-md">

            <!-- TITRE -->
            <div class="text-center mb-8">

                <div class="w-16 h-16 mx-auto rounded-2xl
                            bg-cyan-100 dark:bg-cyan-950
                            flex items-center justify-center
                            text-cyan-800 dark:text-cyan-400
                            mb-5">

                    <svg class="w-8 h-8"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">

                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="1.7"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4"/>

                    </svg>

                </div>

                <h2 class="text-3xl font-bold
                           text-slate-900 dark:text-white">
                    Bienvenue
                </h2>

                <p class="text-slate-500 dark:text-slate-400 mt-3">
                    Connectez-vous à votre espace administrateur.
                </p>

            </div>

            <!-- CARTE CONNEXION -->
            <div class="bg-white dark:bg-slate-900
                        border border-slate-200
                        dark:border-slate-800
                        rounded-2xl shadow-xl p-7 sm:p-9">

                <h3 class="text-xl font-bold
                           text-slate-900 dark:text-white mb-2">
                    Connexion
                </h3>

                <p class="text-sm text-slate-500
                          dark:text-slate-400 mb-7">
                    Entrez vos identifiants pour continuer.
                </p>

                <!-- MESSAGE ERREUR -->
                <?php if ($erreur !== ''): ?>

                    <div class="mb-6 p-4 rounded-lg
                                bg-red-50 dark:bg-red-950/40
                                border border-red-200
                                dark:border-red-900
                                text-red-700 dark:text-red-400
                                text-sm">

                        <?= htmlspecialchars(
                            $erreur,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                <?php endif; ?>

                <!-- FORMULAIRE -->
                <form method="POST"
                      action="traitement.php"
                      class="space-y-5">

                    <!-- IDENTIFIANT -->
                    <div>

                        <label for="login"
                               class="block text-sm font-semibold
                                      text-slate-700
                                      dark:text-slate-300 mb-2">
                            Identifiant
                        </label>

                        <input
                            type="text"
                            id="login"
                            name="login"
                            value="<?= htmlspecialchars(
                                $login,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            placeholder="Votre identifiant"
                            autocomplete="username"
                            required
                            autofocus
                            class="w-full px-4 py-3 rounded-lg
                                   border border-slate-300
                                   dark:border-slate-700
                                   bg-white dark:bg-slate-800
                                   text-slate-900 dark:text-white
                                   placeholder:text-slate-400
                                   focus:outline-none
                                   focus:ring-2 focus:ring-cyan-600
                                   focus:border-transparent transition"
                        >

                    </div>

                    <!-- MOT DE PASSE -->
                    <div>

                        <label for="password"
                               class="block text-sm font-semibold
                                      text-slate-700
                                      dark:text-slate-300 mb-2">
                            Mot de passe
                        </label>

                        <div class="relative">

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Votre mot de passe"
                                autocomplete="current-password"
                                required
                                class="w-full px-4 py-3 pr-12 rounded-lg
                                       border border-slate-300
                                       dark:border-slate-700
                                       bg-white dark:bg-slate-800
                                       text-slate-900 dark:text-white
                                       placeholder:text-slate-400
                                       focus:outline-none
                                       focus:ring-2 focus:ring-cyan-600
                                       focus:border-transparent transition"
                            >

                            <button
                                type="button"
                                id="togglePassword"
                                class="absolute right-4 top-1/2
                                       -translate-y-1/2
                                       text-slate-500
                                       hover:text-cyan-700
                                       dark:hover:text-cyan-400"
                                aria-label="Afficher le mot de passe">

                                <svg id="eyeIcon"
                                     class="w-5 h-5"
                                     fill="none"
                                     stroke="currentColor"
                                     viewBox="0 0 24 24">

                                    <path stroke-linecap="round"
                                          stroke-linejoin="round"
                                          stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0
                                          3 3 0 016 0z"/>

                                    <path stroke-linecap="round"
                                          stroke-linejoin="round"
                                          stroke-width="2"
                                          d="M2.5 12s3.5-7 9.5-7
                                          9.5 7 9.5 7-3.5 7-9.5 7
                                          -9.5-7-9.5-7z"/>

                                </svg>

                            </button>

                        </div>

                    </div>

                    <!-- BOUTON CONNEXION -->
                    <button
                        type="submit"
                        class="w-full py-3.5 rounded-lg
                               bg-cyan-800 hover:bg-cyan-900
                               text-white font-semibold
                               shadow-sm hover:shadow-md
                               transition duration-300">

                        Se connecter

                    </button>

                </form>

                <!-- RETOUR -->
                <div class="border-t border-slate-200
                            dark:border-slate-800
                            mt-7 pt-6 text-center">

                    <a href="<?= $baseUrl ?>/index.php"
                       class="text-sm text-slate-500
                              dark:text-slate-400
                              hover:text-cyan-700
                              dark:hover:text-cyan-400 transition">

                        &larr; Retour à l'accueil

                    </a>

                </div>

            </div>

            <!-- COPYRIGHT -->
            <p class="text-center text-xs
                      text-slate-400 mt-7">

                &copy; <?= date('Y') ?> Patrimoine.
                Tous droits réservés.

            </p>

        </div>

    </main>

    <!-- JAVASCRIPT -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // DARK MODE
            const html = document.documentElement;

            const themeToggle = document.getElementById('themeToggle');
            const sunIcon = document.getElementById('sunIcon');
            const moonIcon = document.getElementById('moonIcon');

            function updateThemeIcon() {
                const isDark = html.classList.contains('dark');

                sunIcon.classList.toggle('hidden', !isDark);
                moonIcon.classList.toggle('hidden', isDark);
            }

            themeToggle.addEventListener('click', function () {

                const isDark = html.classList.toggle('dark');

                localStorage.setItem(
                    'theme',
                    isDark ? 'dark' : 'light'
                );

                updateThemeIcon();
            });

            updateThemeIcon();

            // AFFICHER / MASQUER MOT DE PASSE
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');

            togglePassword.addEventListener('click', function () {

                const isHidden = passwordInput.type === 'password';

                passwordInput.type = isHidden ? 'text' : 'password';

                togglePassword.setAttribute(
                    'aria-label',
                    isHidden
                        ? 'Masquer le mot de passe'
                        : 'Afficher le mot de passe'
                );

            });

        });
    </script>

</body>
</html>
