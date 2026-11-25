<?php
session_start();
require_once "../includes/config.php";

/* =====================================================
   1. CONFIGURATION ET SÉCURITÉ
===================================================== */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: login.php");
    exit;
}

$pageTitle = "Gestion des catégories";

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

// Protection CSRF
if (empty($_SESSION['csrf_categories'])) {
    $_SESSION['csrf_categories'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_categories'];

// Messages temporaires
$erreur = $_SESSION['erreur_categories'] ?? "";
$succes = $_SESSION['succes_categories'] ?? "";

unset(
    $_SESSION['erreur_categories'],
    $_SESSION['succes_categories']
);


/* =====================================================
   2. TRAITEMENT DES FORMULAIRES
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $messageErreur = "";
    $messageSucces = "";

    // Vérification CSRF
    if (
        !isset($_POST['csrf']) ||
        !hash_equals($csrf, $_POST['csrf'])
    ) {
        $messageErreur = "Requête invalide.";
    }

    /* ---------------- AJOUTER ---------------- */

    elseif (isset($_POST['ajouter'])) {

        $nom = trim($_POST['nom'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($nom === '' || $description === '') {

            $messageErreur = "Veuillez remplir tous les champs.";

        } else {

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO categorie (nom, description)
                 VALUES (?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ss",
                $nom,
                $description
            );

            if (mysqli_stmt_execute($stmt)) {

                $messageSucces = "Catégorie ajoutée avec succès.";

            } else {

                if (mysqli_errno($conn) === 1062) {
                    $messageErreur = "Cette catégorie existe déjà.";
                } else {
                    $messageErreur = "Erreur lors de l'ajout.";
                }
            }

            mysqli_stmt_close($stmt);
        }
    }

    /* ---------------- MODIFIER ---------------- */

    elseif (isset($_POST['modifier'])) {

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $nom = trim($_POST['nom'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!$id || $nom === '' || $description === '') {

            $messageErreur = "Veuillez remplir tous les champs.";

        } else {

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE categorie
                 SET nom = ?, description = ?
                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ssi",
                $nom,
                $description,
                $id
            );

            if (mysqli_stmt_execute($stmt)) {

                $messageSucces = "Catégorie modifiée avec succès.";

            } else {

                if (mysqli_errno($conn) === 1062) {
                    $messageErreur = "Cette catégorie existe déjà.";
                } else {
                    $messageErreur = "Erreur lors de la modification.";
                }
            }

            mysqli_stmt_close($stmt);
        }
    }

    /* ---------------- SUPPRIMER ---------------- */

    elseif (isset($_POST['supprimer'])) {

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (!$id) {

            $messageErreur = "Catégorie invalide.";

        } else {

            // Vérifier l'utilisation de la catégorie
            $stmt = mysqli_prepare(
                $conn,
                "SELECT COUNT(*) AS total
                 FROM monument
                 WHERE categorie_id = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);

            $resultat = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($resultat);

            mysqli_stmt_close($stmt);

            if ((int)$row['total'] > 0) {

                $messageErreur =
                    "Impossible de supprimer cette catégorie : elle est utilisée par des monuments.";

            } else {

                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM categorie WHERE id = ?"
                );

                mysqli_stmt_bind_param($stmt, "i", $id);

                if (mysqli_stmt_execute($stmt)) {
                    $messageSucces = "Catégorie supprimée avec succès.";
                } else {
                    $messageErreur = "Erreur lors de la suppression.";
                }

                mysqli_stmt_close($stmt);
            }
        }
    }

    /* ---------------- REDIRECTION ---------------- */

    if ($messageErreur !== "") {
        $_SESSION['erreur_categories'] = $messageErreur;
    }

    if ($messageSucces !== "") {
        $_SESSION['succes_categories'] = $messageSucces;
    }

    // Retour à la page sans données POST ni mode édition
    header("Location: categories.php");
    exit;
}


/* =====================================================
   3. RÉCUPÉRER LA CATÉGORIE À MODIFIER
===================================================== */

$edition = null;

if (isset($_GET['modifier'])) {

    $editId = filter_input(INPUT_GET, 'modifier', FILTER_VALIDATE_INT);

    if ($editId) {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, nom, description
             FROM categorie
             WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $editId);
        mysqli_stmt_execute($stmt);

        $resultat = mysqli_stmt_get_result($stmt);
        $edition = mysqli_fetch_assoc($resultat);

        mysqli_stmt_close($stmt);
    }
}


/* =====================================================
   4. RÉCUPÉRER LES CATÉGORIES
===================================================== */

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        c.id,
        c.nom,
        c.description,
        COUNT(m.id) AS total_monuments
     FROM categorie c
     LEFT JOIN monument m ON m.categorie_id = c.id
     GROUP BY c.id, c.nom, c.description
     ORDER BY c.id DESC"
);

mysqli_stmt_execute($stmt);

$categories = mysqli_stmt_get_result($stmt);
$totalCategories = mysqli_num_rows($categories);

// Affichage uniquement : liste en mémoire, chiffres de synthèse, badge messages
$listeCategories = mysqli_fetch_all($categories, MYSQLI_ASSOC);
$totalClasses = array_sum(array_map(fn($c) => (int)$c['total_monuments'], $listeCategories));
$totalVides = count(array_filter($listeCategories, fn($c) => (int)$c['total_monuments'] === 0));

$resBadge = mysqli_query($conn, "SELECT COUNT(*) AS total FROM message WHERE lu = 0");
$messagesNonLus = $resBadge ? (int)(mysqli_fetch_assoc($resBadge)['total'] ?? 0) : 0;

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

$champ = "w-full rounded-md border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-950 "
       . "text-slate-900 dark:text-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-cyan-700 "
       . "focus:border-cyan-700 placeholder:text-slate-400";
$etiquette = "block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5";

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> — Administration Patrimoine</title>
<script>
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
    .coupe-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    dialog::backdrop { background: rgba(2, 6, 23, .6); }
    @media (prefers-reduced-motion: reduce) {
        .admin * { transition-duration: .01ms !important; }
    }
</style>
</head>
<body class="bg-stone-50 dark:bg-slate-950">

<div class="admin flex min-h-screen">

    <!-- =====================================
         BARRE LATÉRALE (toujours fixe à gauche)
    ====================================== -->
    <aside class="fixed inset-y-0 left-0 z-40 w-16 lg:w-64 flex flex-col
                  bg-white dark:bg-slate-900
                  border-r border-stone-200 dark:border-slate-800"
           aria-label="Navigation de l'administration">

        <div class="px-3 lg:px-6 py-6 border-b border-stone-200 dark:border-slate-800 text-center lg:text-left">
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
                                     bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300">
                            <?= (int)$badge ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="px-2 lg:px-3 py-4 border-t border-stone-200 dark:border-slate-800 space-y-0.5">
            <a href="../index.php" title="Voir le site"
               class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-md text-sm
                      text-slate-600 dark:text-slate-300 hover:bg-stone-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 14v5H5V6h5"/></svg>
                <span class="hidden lg:inline">Voir le site</span>
            </a>
            <button type="button" id="theme" title="Changer de thème" aria-label="Changer de thème"
                    class="w-full flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-md text-sm
                           text-slate-600 dark:text-slate-300 hover:bg-stone-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v2M12 19v2M5 12H3M21 12h-2M6 6l1.5 1.5M16.5 16.5L18 18M18 6l-1.5 1.5M7.5 16.5L6 18M12 8a4 4 0 100 8 4 4 0 000-8z"/></svg>
                <span class="hidden lg:inline">Thème clair / sombre</span>
            </button>
            <a href="logout.php" title="Déconnexion"
               class="flex items-center justify-center lg:justify-start gap-3 px-3 py-2.5 rounded-md text-sm
                      text-slate-600 dark:text-slate-300 hover:bg-stone-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/></svg>
                <span class="hidden lg:inline">Déconnexion</span>
            </a>
        </div>
    </aside>


    <!-- =====================================
         CONTENU
    ====================================== -->
    <main class="flex-1 min-w-0 pl-16 lg:pl-64">
        <div class="max-w-6xl mx-auto px-4 sm:px-8 py-8">

            <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-8">
                <div>
                    <h1 class="titre-serif text-3xl sm:text-4xl text-slate-900 dark:text-white">
                        Gestion des catégories
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 mt-2">
                        Organisez les monuments par thème.
                    </p>
                </div>
                <?php if ($edition): ?>
                    <a href="categories.php"
                       class="inline-flex items-center px-4 py-2.5 rounded-md bg-cyan-800 hover:bg-cyan-900
                              text-white text-sm font-semibold transition-colors">
                        Ajouter une nouvelle catégorie
                    </a>
                <?php else: ?>
                    <a href="#formulaire"
                       class="inline-flex items-center px-4 py-2.5 rounded-md bg-cyan-800 hover:bg-cyan-900
                              text-white text-sm font-semibold transition-colors">
                        Ajouter une catégorie
                    </a>
                <?php endif; ?>
            </header>

            <!-- CHIFFRES -->
            <dl class="grid grid-cols-3 gap-4 mb-8">
                <?php foreach ([
                    ['Catégories', $totalCategories],
                    ['Monuments classés', $totalClasses],
                    ['Catégories vides', $totalVides],
                ] as [$lib, $val]): ?>
                    <div class="rounded-lg bg-white dark:bg-slate-900 border border-stone-200 dark:border-slate-800 p-4 sm:p-5">
                        <dd class="titre-serif text-3xl sm:text-4xl tabular-nums text-slate-900 dark:text-white">
                            <?= (int)$val ?>
                        </dd>
                        <dt class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1"><?= h($lib) ?></dt>
                    </div>
                <?php endforeach; ?>
            </dl>

            <!-- MESSAGES -->
            <?php if ($erreur): ?>
                <div data-alerte role="alert"
                     class="mb-6 flex items-start justify-between gap-4 rounded-md border border-red-200 dark:border-red-900
                            bg-red-50 dark:bg-red-950/40 px-4 py-3 text-sm text-red-800 dark:text-red-300">
                    <span><?= h($erreur) ?></span>
                    <button type="button" data-fermer aria-label="Fermer le message" class="text-lg leading-none">&times;</button>
                </div>
            <?php endif; ?>

            <?php if ($succes): ?>
                <div data-alerte data-auto role="status"
                     class="mb-6 flex items-start justify-between gap-4 rounded-md border border-green-200 dark:border-green-900
                            bg-green-50 dark:bg-green-950/40 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                    <span><?= h($succes) ?></span>
                    <button type="button" data-fermer aria-label="Fermer le message" class="text-lg leading-none">&times;</button>
                </div>
            <?php endif; ?>

            <!-- FORMULAIRE AJOUT / MODIFICATION -->
            <section id="formulaire"
                     class="scroll-mt-6 rounded-lg bg-white dark:bg-slate-900
                            border border-stone-200 dark:border-slate-800 p-5 sm:p-6 mb-10">

                <div class="flex items-center justify-between gap-3 mb-6">
                    <div>
                        <h2 class="titre-serif text-xl text-slate-900 dark:text-white">
                            <?= $edition ? "Modifier la catégorie" : "Ajouter une catégorie" ?>
                        </h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            <?= $edition
                                ? "Modifiez les informations de cette catégorie."
                                : "Renseignez les informations de la nouvelle catégorie."
                            ?>
                        </p>
                    </div>

                    <?php if ($edition): ?>
                        <span class="shrink-0 text-xs font-medium text-cyan-800 dark:text-cyan-300
                                     bg-cyan-50 dark:bg-cyan-950/50 px-3 py-1.5 rounded-md">
                            Mode modification
                        </span>
                    <?php endif; ?>
                </div>

                <form method="POST" action="categories.php" class="space-y-5">

                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

                    <?php if ($edition): ?>
                        <input type="hidden" name="id" value="<?= (int)$edition['id'] ?>">
                    <?php endif; ?>

                    <div>
                        <label for="nom" class="<?= $etiquette ?>">Nom de la catégorie</label>
                        <input type="text" id="nom" name="nom" maxlength="255" required
                               value="<?= h($edition['nom'] ?? '') ?>"
                               placeholder="Ex : Monuments historiques"
                               class="<?= $champ ?>">
                    </div>

                    <div>
                        <div class="flex items-baseline justify-between">
                            <label for="description" class="<?= $etiquette ?>">Description</label>
                            <span id="compteur" class="text-xs text-slate-400 tabular-nums"></span>
                        </div>
                        <textarea id="description" name="description" rows="4" required
                                  placeholder="Description de la catégorie..."
                                  class="<?= $champ ?>"><?= h($edition['description'] ?? '') ?></textarea>
                    </div>

                    <div class="flex flex-wrap justify-end gap-3">
                        <?php if ($edition): ?>
                            <a href="categories.php"
                               class="px-5 py-2.5 rounded-md border border-stone-300 dark:border-slate-700
                                      text-slate-700 dark:text-slate-300 hover:bg-stone-100 dark:hover:bg-slate-800
                                      transition-colors">
                                Annuler
                            </a>
                        <?php endif; ?>

                        <button type="submit" name="<?= $edition ? 'modifier' : 'ajouter' ?>"
                                class="px-5 py-2.5 rounded-md bg-cyan-800 hover:bg-cyan-900 text-white font-semibold transition-colors">
                            <?= $edition ? "Enregistrer les modifications" : "Ajouter la catégorie" ?>
                        </button>
                    </div>
                </form>
            </section>

            <!-- LISTE DES CATÉGORIES -->
            <section aria-labelledby="titre-liste">

                <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 mb-6">
                    <div>
                        <h2 id="titre-liste" class="titre-serif text-xl text-slate-900 dark:text-white">
                            Liste des catégories
                        </h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1" aria-live="polite">
                            <span id="nb-resultats"><?= (int)$totalCategories ?></span>
                            résultat<?= $totalCategories > 1 ? 's' : '' ?>
                        </p>
                    </div>

                    <?php if ($totalCategories > 0): ?>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                            <label class="sr-only" for="recherche">Rechercher une catégorie</label>
                            <input type="search" id="recherche" placeholder="Rechercher une catégorie…"
                                   class="<?= $champ ?> sm:w-72">

                            <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300 cursor-pointer">
                                <input type="checkbox" id="filtre-vides"
                                       class="w-4 h-4 rounded border-stone-300 text-cyan-800 focus:ring-cyan-700">
                                Vides seulement
                            </label>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($totalCategories === 0): ?>

                    <div class="rounded-lg border border-dashed border-stone-300 dark:border-slate-700 p-12 text-center">
                        <h3 class="font-semibold text-slate-800 dark:text-white">Aucune catégorie disponible</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">
                            Les catégories ajoutées apparaîtront ici.
                        </p>
                    </div>

                <?php else: ?>

                    <div class="rounded-lg bg-white dark:bg-slate-900 border border-stone-200 dark:border-slate-800 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-stone-50 dark:bg-slate-800/60 text-slate-600 dark:text-slate-300">
                                    <tr>
                                        <th scope="col" class="px-5 py-3.5 font-semibold">Catégorie</th>
                                        <th scope="col" class="px-5 py-3.5 font-semibold">Description</th>
                                        <th scope="col" class="px-5 py-3.5 font-semibold text-center">Monuments</th>
                                        <th scope="col" class="px-5 py-3.5 font-semibold text-right">Actions</th>
                                    </tr>
                                </thead>

                                <tbody id="corps" class="divide-y divide-stone-200 dark:divide-slate-800">
                                    <?php foreach ($listeCategories as $cat): ?>
                                        <?php $nbMonuments = (int)$cat['total_monuments']; ?>
                                        <tr class="hover:bg-stone-50 dark:hover:bg-slate-800/40 transition-colors"
                                            data-nb="<?= $nbMonuments ?>"
                                            data-texte="<?= h($cat['nom'] . ' ' . $cat['description']) ?>">

                                            <td class="px-5 py-4 align-top">
                                                <span class="font-medium text-slate-900 dark:text-white">
                                                    <?= h($cat['nom']) ?>
                                                </span>
                                            </td>

                                            <td class="px-5 py-4 align-top text-slate-500 dark:text-slate-400 max-w-md">
                                                <p class="coupe-2"><?= h($cat['description']) ?></p>
                                            </td>

                                            <td class="px-5 py-4 align-top text-center">
                                                <span class="inline-flex items-center justify-center min-w-8 px-3 py-1 rounded-full
                                                             font-medium tabular-nums
                                                             <?= $nbMonuments > 0
                                                                 ? 'bg-cyan-50 dark:bg-cyan-950/50 text-cyan-800 dark:text-cyan-300'
                                                                 : 'bg-stone-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400' ?>">
                                                    <?= $nbMonuments ?>
                                                </span>
                                            </td>

                                            <td class="px-5 py-4 align-top">
                                                <div class="flex items-center justify-end gap-4 whitespace-nowrap">
                                                    <a href="categories.php?modifier=<?= (int)$cat['id'] ?>#formulaire"
                                                       class="font-medium text-cyan-800 dark:text-cyan-400 hover:underline">
                                                        Modifier
                                                    </a>

                                                    <?php if ($nbMonuments === 0): ?>
                                                        <button type="button"
                                                                data-supprimer="<?= (int)$cat['id'] ?>"
                                                                data-titre="<?= h($cat['nom']) ?>"
                                                                class="font-medium text-red-700 dark:text-red-400 hover:underline">
                                                            Supprimer
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-slate-400 cursor-not-allowed"
                                                              title="Cette catégorie est utilisée par des monuments.">
                                                            Supprimer
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <p id="aucun" class="hidden p-10 text-center text-sm text-slate-500 dark:text-slate-400">
                            Aucune catégorie ne correspond à votre recherche.
                        </p>
                    </div>

                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<!-- Confirmation de suppression -->
<dialog id="dialogue"
        class="rounded-lg p-0 w-[calc(100%-2rem)] max-w-md bg-white dark:bg-slate-900
               text-slate-900 dark:text-white border border-stone-200 dark:border-slate-800 shadow-xl">
    <form method="POST" action="categories.php" class="p-6">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="id" id="dialogue-id" value="">

        <h2 class="titre-serif text-xl">Supprimer cette catégorie ?</h2>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-3">
            La catégorie « <span id="dialogue-titre" class="font-medium"></span> » sera supprimée définitivement.
        </p>

        <div class="flex justify-end gap-3 mt-6">
            <button type="button" id="dialogue-annuler"
                    class="px-4 py-2 rounded-md border border-stone-300 dark:border-slate-700
                           text-slate-700 dark:text-slate-300 hover:bg-stone-100 dark:hover:bg-slate-800 transition-colors">
                Annuler
            </button>
            <button type="submit" name="supprimer"
                    class="px-4 py-2 rounded-md bg-red-700 hover:bg-red-800 text-white font-semibold transition-colors">
                Supprimer
            </button>
        </div>
    </form>
</dialog>

<script>
    (function () {
        // Thème clair / sombre
        document.getElementById('theme').addEventListener('click', function () {
            var sombre = document.documentElement.classList.toggle('dark');
            try { localStorage.setItem('theme', sombre ? 'dark' : 'light'); } catch (e) {}
        });

        // Alertes : fermeture + disparition automatique du succès
        document.querySelectorAll('[data-alerte]').forEach(function (a) {
            a.querySelector('[data-fermer]').addEventListener('click', function () { a.remove(); });
            if (a.hasAttribute('data-auto')) setTimeout(function () { a.remove(); }, 6000);
        });

        // Compteur de caractères
        var desc = document.getElementById('description');
        var cpt = document.getElementById('compteur');
        function majCompteur() { cpt.textContent = desc.value.length.toLocaleString('fr-FR') + ' caractères'; }
        desc.addEventListener('input', majCompteur);
        majCompteur();

        // Recherche + filtre « vides seulement » (côté client)
        var champ = document.getElementById('recherche');
        var vides = document.getElementById('filtre-vides');
        var lignes = document.querySelectorAll('#corps tr');
        var nb = document.getElementById('nb-resultats');
        var aucun = document.getElementById('aucun');

        function norm(t) {
            return t.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        function filtrer() {
            var q = norm(champ.value.trim());
            var n = 0;
            lignes.forEach(function (l) {
                var ok = (!vides.checked || l.getAttribute('data-nb') === '0') &&
                         (!q || norm(l.getAttribute('data-texte')).indexOf(q) !== -1);
                l.hidden = !ok;
                if (ok) n++;
            });
            nb.textContent = n;
            aucun.classList.toggle('hidden', n !== 0);
        }
        if (champ && vides) {
            champ.addEventListener('input', filtrer);
            vides.addEventListener('change', filtrer);
        }

        // Confirmation de suppression
        var dlg = document.getElementById('dialogue');
        document.querySelectorAll('[data-supprimer]').forEach(function (b) {
            b.addEventListener('click', function () {
                document.getElementById('dialogue-id').value = b.getAttribute('data-supprimer');
                document.getElementById('dialogue-titre').textContent = b.getAttribute('data-titre');
                dlg.showModal();
            });
        });
        document.getElementById('dialogue-annuler').addEventListener('click', function () { dlg.close(); });
        dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });

        // En mode modification : placer le curseur dans le nom
        <?php if ($edition): ?>
        var n = document.getElementById('nom');
        n.focus();
        n.setSelectionRange(n.value.length, n.value.length);
        <?php endif; ?>
    })();
</script>

<?php
mysqli_stmt_close($stmt);
?>
</body>
</html>
