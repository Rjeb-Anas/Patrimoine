<?php
session_start();

require_once "../includes/config.php";

// Vérification administrateur
if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: login.php");
    exit;
}

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

// Récupération des statistiques
function obtenirNombre($conn, $sql) {
    $resultat = mysqli_query($conn, $sql);

    if (!$resultat) {
        return 0;
    }

    $ligne = mysqli_fetch_assoc($resultat);
    mysqli_free_result($resultat);

    return (int)($ligne['total'] ?? 0);
}

$totalMonuments = obtenirNombre($conn, "SELECT COUNT(*) AS total FROM monument");
$totalCategories = obtenirNombre($conn, "SELECT COUNT(*) AS total FROM categorie");
$totalPays = obtenirNombre($conn, "SELECT COUNT(*) AS total FROM pays");
$totalVilles = obtenirNombre($conn, "SELECT COUNT(*) AS total FROM ville");
$totalImages = obtenirNombre($conn, "SELECT COUNT(*) AS total FROM image");
$totalMessages = obtenirNombre($conn, "SELECT COUNT(*) AS total FROM message");
$messagesNonLus = obtenirNombre($conn, "SELECT COUNT(*) AS total FROM message WHERE lu = 0");

// Nouveau : monuments sans aucune image
$sansImage = obtenirNombre(
    $conn,
    "SELECT COUNT(*) AS total FROM monument m
     WHERE NOT EXISTS (SELECT 1 FROM image i WHERE i.monument_id = m.id)"
);

// Répartition par catégorie
$categories = mysqli_query(
    $conn,
    "SELECT c.nom, COUNT(m.id) AS total
     FROM categorie c
     LEFT JOIN monument m ON m.categorie_id = c.id
     GROUP BY c.id, c.nom
     ORDER BY total DESC
     LIMIT 5"
);

// Derniers monuments
$monuments = mysqli_query(
    $conn,
    "SELECT
        m.id,
        m.titre,
        c.nom AS categorie,
        v.nom AS ville,
        (
            SELECT i.chemin
            FROM image i
            WHERE i.monument_id = m.id
            ORDER BY i.id DESC
            LIMIT 1
        ) AS image_principale
     FROM monument m
     LEFT JOIN categorie c ON c.id = m.categorie_id
     LEFT JOIN ville v ON v.id = m.ville_id
     ORDER BY m.id DESC
     LIMIT 5"
);

// Derniers messages
$messages = mysqli_query(
    $conn,
    "SELECT id, nom, sujet, lu, date_envoi
     FROM message
     ORDER BY date_envoi DESC
     LIMIT 5"
);

// Navigation latérale
$pageCourante = basename($_SERVER['PHP_SELF']);
$navigation = [
    ['Tableau de bord', 'dashboard.php',  'M4 5h6v6H4zM14 5h6v6h-6zM4 15h6v4H4zM14 15h6v4h-6z', 0],
    ['Monuments',       'monuments.php',  'M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6', 0],
    ['Catégories',      'categories.php', 'M4 6h16M4 12h16M4 18h10', 0],
    ['Pays',            'pays.php',       'M12 3a9 9 0 100 18 9 9 0 000-18zM3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18', 0],
    ['Villes',          'villes.php',     'M4 21V8l6-3v16M10 21V11h10v10M7 11v.01M7 15v.01', 0],
    ['Images',          'images.php',     'M4 5h16v14H4zM4 16l5-5 4 4 3-3 4 4M9 9v.01', 0],
    ['Messages',        'messages.php',   'M4 6h16v12H4zM4 7l8 6 8-6', $messagesNonLus],
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tableau de bord — Administration Patrimoine</title>
<script>
    // Thème appliqué avant l'affichage (évite le flash)
    (function () {
        var sombre = false;
        try {
            var t = localStorage.getItem('theme');
            sombre = t ? t === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        } catch (e) {}
        document.documentElement.classList.toggle('dark', sombre);
    })();
</script>
<!-- Si vous avez votre propre fichier CSS Tailwind compilé, remplacez ces 2 lignes par son <link> -->
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { darkMode: 'class' };</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,500;6..72,600&display=swap" rel="stylesheet">

<style>
    .titre-serif {
        font-family: 'Newsreader', Georgia, 'Times New Roman', serif;
        letter-spacing: -0.015em;
        font-weight: 600;
    }
    .admin a:focus-visible, .admin button:focus-visible {
        outline: 2px solid #0e7490;
        outline-offset: 2px;
        border-radius: 6px;
    }
    .barre { transition: width .8s cubic-bezier(.2, .7, .2, 1); }
    @media (prefers-reduced-motion: reduce) {
        .admin * { transition-duration: .01ms !important; }
    }
</style>
</head>
<body class="bg-stone-50 dark:bg-slate-950">

<div class="admin flex min-h-screen">

    <!-- =====================================
         BARRE LATÉRALE
    ====================================== -->
    <aside id="menu"
           class="fixed inset-y-0 left-0 z-40 w-16 lg:w-64 flex flex-col
                  bg-white dark:bg-slate-900
                  border-r border-stone-200 dark:border-slate-800"
           aria-label="Navigation de l'administration">

        <div class="px-3 lg:px-6 py-6 border-b border-stone-200 dark:border-slate-800
                    text-center lg:text-left">
            <p class="titre-serif text-xl text-slate-900 dark:text-white">
                <span class="lg:hidden">P</span><span class="hidden lg:inline">Patrimoine</span>
            </p>
            <p class="hidden lg:block text-xs text-slate-500 dark:text-slate-400 mt-0.5">Espace d'administration</p>
        </div>

        <nav class="flex-1 overflow-y-auto px-2 lg:px-3 py-4 space-y-0.5">
            <?php foreach ($navigation as [$libelle, $lien, $icone, $badge]): ?>
                <?php $actif = ($pageCourante === $lien); ?>
                <a href="<?= h($lien) ?>" title="<?= h($libelle) ?>"
                   <?= $actif ? 'aria-current="page"' : '' ?>
                   class="relative flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition-colors
                          <?= $actif
                              ? 'bg-cyan-800 text-white'
                              : 'text-slate-600 dark:text-slate-300 hover:bg-stone-100 dark:hover:bg-slate-800' ?>">
                    <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.7"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="<?= h($icone) ?>"/>
                    </svg>
                    <span class="flex-1 hidden lg:inline"><?= h($libelle) ?></span>
                    <?php if ((int)$badge > 0): ?>
                        <span class="lg:hidden absolute top-1.5 right-2 w-2 h-2 rounded-full bg-orange-500"></span>
                        <span class="hidden lg:inline-block text-xs font-semibold px-2 py-0.5 rounded-full
                                     <?= $actif ? 'bg-white text-cyan-900' : 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300' ?>">
                            <?= (int)$badge ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="px-2 lg:px-3 py-4 border-t border-stone-200 dark:border-slate-800 space-y-0.5">
            <a href="../index.php"
               class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-md text-sm
                      text-slate-600 dark:text-slate-300
                      hover:bg-stone-100 dark:hover:bg-slate-800 transition-colors" title="Voir le site">
                <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 4h6v6M20 4l-9 9M18 14v5H5V6h5"/></svg>
                <span class="hidden lg:inline">Voir le site</span>
            </a>
            <button type="button" id="theme"
               class="w-full flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-md text-sm
                      text-slate-600 dark:text-slate-300
                      hover:bg-stone-100 dark:hover:bg-slate-800 transition-colors" title="Changer de thème" aria-label="Changer de thème">
                <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 3v2M12 19v2M5 12H3M21 12h-2M6 6l1.5 1.5M16.5 16.5L18 18M18 6l-1.5 1.5M7.5 16.5L6 18M12 8a4 4 0 100 8 4 4 0 000-8z"/></svg>
                <span class="hidden lg:inline">Thème clair / sombre</span>
            </button>
            <a href="logout.php"
               class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-md text-sm
                      text-slate-600 dark:text-slate-300
                      hover:bg-stone-100 dark:hover:bg-slate-800 transition-colors" title="Déconnexion">
                <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/></svg>
                <span class="hidden lg:inline">Déconnexion</span>
            </a>
        </div>
    </aside>


    <!-- =====================================
         CONTENU
    ====================================== -->
    <main class="flex-1 min-w-0 pl-16 lg:pl-64">

        <!-- Barre supérieure -->
        <div class="flex items-center justify-between gap-4 px-4 sm:px-8 py-4
                    bg-white dark:bg-slate-900
                    border-b border-stone-200 dark:border-slate-800">

            <div class="flex items-center gap-3">
                <p id="date-jour" class="text-sm text-slate-500 dark:text-slate-400 first-letter:uppercase"></p>
            </div>

            <a href="monuments.php"
               class="inline-flex items-center px-4 py-2 rounded-md bg-cyan-800 hover:bg-cyan-900
                      text-white text-sm font-semibold transition-colors">
                Gérer les monuments
            </a>
        </div>

        <div class="max-w-6xl mx-auto px-4 sm:px-8 py-8">

            <header class="mb-8">
                <h1 class="titre-serif text-3xl sm:text-4xl text-slate-900 dark:text-white">
                    Tableau de bord
                </h1>
                <p class="text-slate-500 dark:text-slate-400 mt-2">
                    Suivez l'activité de la plateforme Patrimoine.
                </p>
            </header>

            <!-- À traiter -->
            <section class="mb-8" aria-label="Éléments à traiter">
                <?php if ($messagesNonLus > 0 || $sansImage > 0): ?>
                    <div class="grid sm:grid-cols-2 gap-4">

                        <?php if ($messagesNonLus > 0): ?>
                            <a href="messages.php"
                               class="flex items-center justify-between gap-4 p-5 rounded-lg
                                      bg-orange-50 dark:bg-orange-950/30
                                      border border-orange-200 dark:border-orange-900
                                      hover:border-orange-400 transition-colors">
                                <div>
                                    <p class="font-semibold text-orange-900 dark:text-orange-200">
                                        <?= $messagesNonLus ?> message<?= $messagesNonLus > 1 ? 's' : '' ?> non lu<?= $messagesNonLus > 1 ? 's' : '' ?>
                                    </p>
                                    <p class="text-sm text-orange-800/80 dark:text-orange-300/80 mt-0.5">
                                        Des visiteurs attendent une réponse.
                                    </p>
                                </div>
                                <span class="text-sm font-semibold text-orange-900 dark:text-orange-200">Consulter</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($sansImage > 0): ?>
                            <a href="images.php"
                               class="flex items-center justify-between gap-4 p-5 rounded-lg
                                      bg-white dark:bg-slate-900
                                      border border-stone-200 dark:border-slate-800
                                      hover:border-cyan-600 transition-colors">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-white">
                                        <?= $sansImage ?> monument<?= $sansImage > 1 ? 's' : '' ?> sans image
                                    </p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                                        Ajoutez un visuel pour améliorer les fiches.
                                    </p>
                                </div>
                                <span class="text-sm font-semibold text-cyan-800 dark:text-cyan-400">Ajouter</span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="p-5 rounded-lg bg-white dark:bg-slate-900
                                border border-stone-200 dark:border-slate-800
                                text-sm text-slate-600 dark:text-slate-300">
                        Tout est à jour : aucun message en attente et chaque monument a une image.
                    </div>
                <?php endif; ?>
            </section>

            <!-- Statistiques -->
            <?php
            $statistiques = [
                ['label' => 'Monuments', 'total' => $totalMonuments, 'lien' => 'monuments.php'],
                ['label' => 'Catégories', 'total' => $totalCategories, 'lien' => 'categories.php'],
                ['label' => 'Pays', 'total' => $totalPays, 'lien' => 'pays.php'],
                ['label' => 'Villes', 'total' => $totalVilles, 'lien' => 'villes.php'],
                ['label' => 'Images', 'total' => $totalImages, 'lien' => 'images.php'],
                ['label' => 'Messages', 'total' => $totalMessages, 'lien' => 'messages.php']
            ];
            ?>

            <section class="mb-10" aria-label="Chiffres clés">
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($statistiques as $stat): ?>
                        <a href="<?= h($stat['lien']) ?>"
                           class="group p-5 rounded-lg bg-white dark:bg-slate-900
                                  border border-stone-200 dark:border-slate-800
                                  hover:border-cyan-600 dark:hover:border-cyan-500 transition-colors">
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                <?= h($stat['label']) ?>
                            </p>
                            <p class="titre-serif text-4xl mt-2 tabular-nums text-slate-900 dark:text-white"
                               data-compteur="<?= (int)$stat['total'] ?>">
                                <?= (int)$stat['total'] ?>
                            </p>
                            <p class="text-xs mt-3 text-slate-400 group-hover:text-cyan-700
                                      dark:group-hover:text-cyan-400 transition-colors">
                                Ouvrir la liste
                            </p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Activité récente -->
            <section class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-10">

                <!-- Monuments -->
                <div class="rounded-lg bg-white dark:bg-slate-900
                            border border-stone-200 dark:border-slate-800">

                    <div class="flex items-center justify-between gap-4 px-5 py-4
                                border-b border-stone-200 dark:border-slate-800">
                        <div>
                            <h2 class="titre-serif text-xl text-slate-900 dark:text-white">Monuments récents</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Les dernières fiches ajoutées.</p>
                        </div>
                        <a href="monuments.php"
                           class="text-sm font-semibold text-cyan-800 dark:text-cyan-400 hover:underline">
                            Tout voir
                        </a>
                    </div>

                    <div class="px-5">
                        <?php if ($monuments && mysqli_num_rows($monuments) > 0): ?>
                            <ul class="divide-y divide-stone-200 dark:divide-slate-800">
                                <?php while ($monument = mysqli_fetch_assoc($monuments)): ?>
                                    <li class="flex items-center gap-4 py-4">

                                        <div class="w-14 h-14 shrink-0 rounded-md overflow-hidden
                                                    bg-stone-100 dark:bg-slate-800">
                                            <?php if (!empty($monument['image_principale'])): ?>
                                                <img src="../<?= h($monument['image_principale']) ?>"
                                                     alt="<?= h($monument['titre']) ?>"
                                                     class="w-full h-full object-cover" loading="lazy">
                                            <?php else: ?>
                                                <span class="w-full h-full flex items-center justify-center
                                                             text-[10px] text-slate-400 text-center leading-tight">
                                                    Sans image
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold truncate text-slate-900 dark:text-white">
                                                <?= h($monument['titre']) ?>
                                            </p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 truncate">
                                                <?= h($monument['categorie'] ?? 'Sans catégorie') ?>
                                                ·
                                                <?= h($monument['ville'] ?? 'Ville inconnue') ?>
                                            </p>
                                        </div>

                                        <a href="monuments.php?modifier=<?= (int)$monument['id'] ?>"
                                           class="text-sm font-medium text-cyan-800 dark:text-cyan-400 hover:underline">
                                            Modifier
                                        </a>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <p class="py-6 text-sm text-slate-500 dark:text-slate-400">
                                Aucun monument enregistré.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Messages -->
                <div class="rounded-lg bg-white dark:bg-slate-900
                            border border-stone-200 dark:border-slate-800">

                    <div class="flex items-center justify-between gap-4 px-5 py-4
                                border-b border-stone-200 dark:border-slate-800">
                        <div>
                            <h2 class="titre-serif text-xl text-slate-900 dark:text-white">Messages récents</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Les derniers messages des visiteurs.</p>
                        </div>
                        <a href="messages.php"
                           class="text-sm font-semibold text-cyan-800 dark:text-cyan-400 hover:underline">
                            Tout voir
                        </a>
                    </div>

                    <div class="px-5">
                        <?php if ($messages && mysqli_num_rows($messages) > 0): ?>
                            <ul class="divide-y divide-stone-200 dark:divide-slate-800">
                                <?php while ($message = mysqli_fetch_assoc($messages)): ?>
                                    <?php $nonLu = ((int)$message['lu'] === 0); ?>
                                    <li class="py-4">
                                        <a href="messages.php?voir=<?= (int)$message['id'] ?>"
                                           class="flex items-start justify-between gap-4 group">

                                            <div class="min-w-0">
                                                <p class="text-sm truncate group-hover:underline
                                                          <?= $nonLu ? 'font-semibold text-slate-900 dark:text-white' : 'text-slate-700 dark:text-slate-300' ?>">
                                                    <?= h($message['sujet']) ?>
                                                </p>
                                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                                    <?= h($message['nom']) ?>
                                                    ·
                                                    <?= h(date('d/m/Y à H:i', strtotime($message['date_envoi']))) ?>
                                                </p>
                                            </div>

                                            <?php if ($nonLu): ?>
                                                <span class="shrink-0 text-xs font-medium px-2 py-1 rounded
                                                             bg-orange-100 text-orange-800
                                                             dark:bg-orange-950 dark:text-orange-300">
                                                    Non lu
                                                </span>
                                            <?php else: ?>
                                                <span class="shrink-0 text-xs px-2 py-1 rounded
                                                             bg-stone-100 text-slate-600
                                                             dark:bg-slate-800 dark:text-slate-400">
                                                    Lu
                                                </span>
                                            <?php endif; ?>
                                        </a>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <p class="py-6 text-sm text-slate-500 dark:text-slate-400">
                                Aucun message reçu.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Répartition -->
            <section class="rounded-lg bg-white dark:bg-slate-900
                            border border-stone-200 dark:border-slate-800 p-5 sm:p-6">

                <div class="mb-6">
                    <h2 class="titre-serif text-xl text-slate-900 dark:text-white">
                        Répartition par catégorie
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Nombre de monuments associés à chaque catégorie (top 5).
                    </p>
                </div>

                <?php if ($categories && mysqli_num_rows($categories) > 0): ?>

                    <?php
                    $lignesCategories = [];
                    $maxCategorie = 0;

                    while ($cat = mysqli_fetch_assoc($categories)) {
                        $lignesCategories[] = $cat;
                        $maxCategorie = max($maxCategorie, (int)$cat['total']);
                    }
                    ?>

                    <div class="space-y-5">
                        <?php foreach ($lignesCategories as $cat): ?>
                            <?php
                            $nombre = (int)$cat['total'];
                            $largeur = $maxCategorie > 0
                                ? round(($nombre / $maxCategorie) * 100, 1)
                                : 0;
                            $part = $totalMonuments > 0
                                ? round(($nombre / $totalMonuments) * 100)
                                : 0;
                            ?>
                            <div>
                                <div class="flex justify-between items-baseline mb-2 gap-3">
                                    <span class="text-sm text-slate-700 dark:text-slate-300">
                                        <?= h($cat['nom']) ?>
                                    </span>
                                    <span class="text-sm text-slate-900 dark:text-white tabular-nums">
                                        <span class="font-semibold"><?= $nombre ?></span>
                                        <span class="text-xs text-slate-400 ml-1"><?= $part ?> %</span>
                                    </span>
                                </div>
                                <div class="h-2 rounded-full overflow-hidden bg-stone-100 dark:bg-slate-800"
                                     role="img" aria-label="<?= h($cat['nom']) ?> : <?= $nombre ?> monument(s)">
                                    <div class="barre h-full rounded-full bg-cyan-700"
                                         style="width: <?= $largeur ?>%"
                                         data-largeur="<?= $largeur ?>"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php else: ?>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Aucune catégorie disponible.
                    </p>
                <?php endif; ?>
            </section>

        </div>
    </main>
</div>

<script>
    (function () {
        var reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // Date du jour
        var d = document.getElementById('date-jour');
        if (d) {
            d.textContent = new Date().toLocaleDateString('fr-FR', {
                weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
            });
        }

        // Bascule du thème clair / sombre
        document.getElementById('theme').addEventListener('click', function () {
            var sombre = document.documentElement.classList.toggle('dark');
            try { localStorage.setItem('theme', sombre ? 'dark' : 'light'); } catch (e) {}
        });

        if (reduit) return;

        // Compteurs animés
        document.querySelectorAll('[data-compteur]').forEach(function (el) {
            var cible = parseInt(el.getAttribute('data-compteur'), 10) || 0;
            var debut = null;
            function pas(t) {
                if (debut === null) debut = t;
                var p = Math.min((t - debut) / 800, 1);
                el.textContent = Math.round(cible * (1 - Math.pow(1 - p, 3))).toLocaleString('fr-FR');
                if (p < 1) requestAnimationFrame(pas);
            }
            requestAnimationFrame(pas);
        });

        // Barres de répartition : de 0 à leur valeur
        var barres = document.querySelectorAll('.barre');
        barres.forEach(function (b) { b.style.width = '0%'; });
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                barres.forEach(function (b) { b.style.width = b.getAttribute('data-largeur') + '%'; });
            });
        });
    })();
</script>

<?php
if ($categories) {
    mysqli_free_result($categories);
}

if ($monuments) {
    mysqli_free_result($monuments);
}

if ($messages) {
    mysqli_free_result($messages);
}

?>
</body>
</html>