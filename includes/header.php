
<?php

// ============================================
// INITIALISATION
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);

$isLoggedIn = isset($_SESSION['id']);
$isAdmin = $isLoggedIn && ($_SESSION['role'] ?? '') === 'admin';

$userName = $_SESSION['login'] ?? 'Utilisateur';

$baseUrl = '';

$pageTitle = $pageTitle ?? 'Patrimoine | Monuments historiques';

$cssPath = __DIR__ . '/../css/style.css';
$cssVersion = file_exists($cssPath) ? filemtime($cssPath) : time();

if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?></title>

    <!-- Appliquer le thème avant le chargement de la page -->
    <script>
        (function () {
            try {
                const savedTheme = localStorage.getItem('theme');

                const theme = savedTheme === 'dark' || savedTheme === 'light'
                    ? savedTheme
                    : 'light';

                document.documentElement.classList.toggle(
                    'dark',
                    theme === 'dark'
                );
            } catch (error) {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>

    <!-- CSS -->
    <link
        rel="stylesheet"
        href="<?= $baseUrl ?>/css/style.css?v=<?= $cssVersion ?>"
    >

</head>

<body class="min-h-screen flex flex-col bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-200 transition-colors duration-300">

<!-- ============================================ -->
<!-- HEADER -->
<!-- ============================================ -->

<header class="sticky top-0 z-50 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 shadow-sm">

    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="h-20 flex items-center justify-between">

            <!-- LOGO -->

            <a href="<?= $baseUrl ?>/index.php"
               class="flex items-center gap-3 shrink-0">

                <div class="w-11 h-11 rounded-xl bg-cyan-800 flex items-center justify-center text-white font-bold text-xl">
                    P
                </div>

                <div>
                    <span class="block text-xl font-bold text-slate-900 dark:text-white">
                        Patrimoine
                    </span>

                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                        Monuments historiques
                    </span>
                </div>

            </a>

            <!-- NAVIGATION DESKTOP -->

            <div class="hidden md:flex items-center gap-7">

                <a href="<?= $baseUrl ?>/index.php"
                   class="<?= $currentPage === 'index.php'
                       ? 'text-cyan-700 dark:text-cyan-400 font-semibold'
                       : 'text-slate-600 dark:text-slate-300 hover:text-cyan-700 dark:hover:text-cyan-400' ?>">
                    Accueil
                </a>

                <a href="<?= $baseUrl ?>/catalogue.php"
                   class="<?= $currentPage === 'catalogue.php'
                       ? 'text-cyan-700 dark:text-cyan-400 font-semibold'
                       : 'text-slate-600 dark:text-slate-300 hover:text-cyan-700 dark:hover:text-cyan-400' ?>">
                    Catalogue
                </a>

                <a href="<?= $baseUrl ?>/galerie.php"
                   class="<?= $currentPage === 'galerie.php'
                       ? 'text-cyan-700 dark:text-cyan-400 font-semibold'
                       : 'text-slate-600 dark:text-slate-300 hover:text-cyan-700 dark:hover:text-cyan-400' ?>">
                    Galerie
                </a>

                <a href="<?= $baseUrl ?>/contact.php"
                   class="<?= $currentPage === 'contact.php'
                       ? 'text-cyan-700 dark:text-cyan-400 font-semibold'
                       : 'text-slate-600 dark:text-slate-300 hover:text-cyan-700 dark:hover:text-cyan-400' ?>">
                    Contact
                </a>

            </div>

            <!-- ACTIONS DESKTOP -->

            <div class="hidden md:flex items-center gap-3">

                <!-- BOUTON THEME -->

                <button
                    type="button"
                    id="themeToggle"
                    class="w-10 h-10 flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                    aria-label="Activer le mode sombre"
                    title="Changer le thème"
                >

                    <!-- Icône soleil -->

                    <svg
                        id="sunIcon"
                        class="hidden w-5 h-5 text-amber-500"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <circle cx="12" cy="12" r="4" stroke-width="2"/>
                        <path
                            stroke-width="2"
                            stroke-linecap="round"
                            d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"
                        />
                    </svg>

                    <!-- Icône lune -->

                    <svg
                        id="moonIcon"
                        class="w-5 h-5 text-slate-700 dark:text-slate-200"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M20.9 13A9 9 0 0 1 11 3.1 9 9 0 1 0 20.9 13Z"
                        />
                    </svg>

                </button>

                <!-- UTILISATEUR ADMIN -->

                <?php if ($isAdmin): ?>

                    <a href="<?= $baseUrl ?>/admin/dashboard.php"
                       class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 transition">

                        <div class="w-9 h-9 rounded-full bg-cyan-800 text-white flex items-center justify-center text-sm font-semibold">
                            <?= e(strtoupper(substr($userName, 0, 1))) ?>
                        </div>

                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">
                            <?= e($userName) ?>
                        </span>

                    </a>

                    <a href="<?= $baseUrl ?>/admin/logout.php"
                       class="px-4 py-2 rounded-lg text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950 transition">
                        Déconnexion
                    </a>

                <?php else: ?>

                    <a href="<?= $baseUrl ?>/admin/login.php"
                       class="px-5 py-2.5 rounded-lg bg-cyan-800 text-white text-sm font-medium hover:bg-cyan-900 transition">
                        Connexion
                    </a>

                <?php endif; ?>

            </div>

            <!-- BOUTON MENU MOBILE -->

            <button
                type="button"
                id="menuButton"
                class="md:hidden p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"
                aria-label="Ouvrir le menu"
                aria-expanded="false"
                aria-controls="mobileMenu"
            >

                <svg
                    id="menuIcon"
                    class="w-6 h-6 text-slate-700 dark:text-white"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 6h16M4 12h16M4 18h16"
                    />
                </svg>

            </button>

        </div>

        <!-- MENU MOBILE -->

        <div id="mobileMenu" class="hidden md:hidden pb-5">

            <div class="flex flex-col gap-1">

                <a href="<?= $baseUrl ?>/index.php"
                   class="px-4 py-3 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">
                    Accueil
                </a>

                <a href="<?= $baseUrl ?>/catalogue.php"
                   class="px-4 py-3 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">
                    Catalogue
                </a>

                <a href="<?= $baseUrl ?>/galerie.php"
                   class="px-4 py-3 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">
                    Galerie
                </a>

                <a href="<?= $baseUrl ?>/contact.php"
                   class="px-4 py-3 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">
                    Contact
                </a>

                <div class="border-t border-slate-200 dark:border-slate-700 my-2"></div>

                <!-- THEME MOBILE -->

                <button
                    type="button"
                    id="themeToggleMobile"
                    class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800"
                >

                    <span id="mobileThemeLabel">Mode sombre</span>

                </button>

                <!-- COMPTE MOBILE -->

                <?php if ($isAdmin): ?>

                    <a href="<?= $baseUrl ?>/admin/dashboard.php"
                       class="px-4 py-3 rounded-lg text-slate-700 dark:text-slate-200">
                        Administration : <?= e($userName) ?>
                    </a>

                    <a href="<?= $baseUrl ?>/admin/logout.php"
                       class="px-4 py-3 rounded-lg text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950">
                        Déconnexion
                    </a>

                <?php else: ?>

                    <a href="<?= $baseUrl ?>/admin/login.php"
                       class="px-4 py-3 rounded-lg bg-cyan-800 text-white text-center font-medium">
                        Connexion
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </nav>

</header>

<!-- LE CONTENU DE LA PAGE COMMENCE ICI -->
