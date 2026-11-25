<?php
session_start();
require_once "../includes/config.php";

/* =========================================================
   1. AUTHENTIFICATION
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: login.php");
    exit;
}

$pageTitle = "Gestion des pays";

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}


/* =========================================================
   2. PROTECTION CSRF
========================================================= */

if (empty($_SESSION['csrf_pays'])) {
    $_SESSION['csrf_pays'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_pays'];


/* =========================================================
   3. MESSAGES FLASH
========================================================= */

$erreur = $_SESSION['erreur_pays'] ?? "";
$succes = $_SESSION['succes_pays'] ?? "";

unset(
    $_SESSION['erreur_pays'],
    $_SESSION['succes_pays']
);


/* =========================================================
   4. TRAITEMENT DES FORMULAIRES
========================================================= */

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

    /* -----------------------------------------------------
       AJOUTER UN PAYS
    ----------------------------------------------------- */

    elseif (isset($_POST['ajouter'])) {

        $nom = trim($_POST['nom'] ?? '');
        $codeIso = strtoupper(trim($_POST['code_iso'] ?? ''));

        if ($nom === '' || $codeIso === '') {

            $messageErreur = "Veuillez remplir tous les champs.";

        } elseif (!preg_match('/^[A-Z]{2}$/', $codeIso)) {

            $messageErreur = "Le code ISO doit contenir exactement deux lettres.";

        } else {

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO pays (nom, code_iso)
                 VALUES (?, ?)"
            );

            mysqli_stmt_bind_param($stmt, "ss", $nom, $codeIso);

            if (mysqli_stmt_execute($stmt)) {

                $messageSucces = "Pays ajouté avec succès.";

            } else {

                if (mysqli_errno($conn) === 1062) {
                    $messageErreur = "Ce pays ou ce code ISO existe déjà.";
                } else {
                    $messageErreur = "Erreur lors de l'ajout du pays.";
                }
            }

            mysqli_stmt_close($stmt);
        }
    }


    /* -----------------------------------------------------
       MODIFIER UN PAYS
    ----------------------------------------------------- */

    elseif (isset($_POST['modifier'])) {

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $nom = trim($_POST['nom'] ?? '');
        $codeIso = strtoupper(trim($_POST['code_iso'] ?? ''));

        if (!$id || $nom === '' || $codeIso === '') {

            $messageErreur = "Veuillez remplir tous les champs.";

        } elseif (!preg_match('/^[A-Z]{2}$/', $codeIso)) {

            $messageErreur = "Le code ISO doit contenir exactement deux lettres.";

        } else {

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE pays
                 SET nom = ?, code_iso = ?
                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ssi",
                $nom,
                $codeIso,
                $id
            );

            if (mysqli_stmt_execute($stmt)) {

                $messageSucces = "Pays modifié avec succès.";

            } else {

                if (mysqli_errno($conn) === 1062) {
                    $messageErreur = "Ce pays ou ce code ISO existe déjà.";
                } else {
                    $messageErreur = "Erreur lors de la modification.";
                }
            }

            mysqli_stmt_close($stmt);
        }
    }


    /* -----------------------------------------------------
       SUPPRIMER UN PAYS
    ----------------------------------------------------- */

    elseif (isset($_POST['supprimer'])) {

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (!$id) {

            $messageErreur = "Pays invalide.";

        } else {

            // Vérifier si le pays possède des villes
            $stmt = mysqli_prepare(
                $conn,
                "SELECT COUNT(*) AS total
                 FROM ville
                 WHERE pays_id = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);

            $resultat = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($resultat);

            mysqli_stmt_close($stmt);

            if ((int)$row['total'] > 0) {

                $messageErreur = "Impossible de supprimer ce pays : il possède des villes.";

            } else {

                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM pays WHERE id = ?"
                );

                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);

                if (mysqli_stmt_affected_rows($stmt) > 0) {
                    $messageSucces = "Pays supprimé avec succès.";
                } else {
                    $messageErreur = "Ce pays n'existe pas.";
                }

                mysqli_stmt_close($stmt);
            }
        }
    }


    /* -----------------------------------------------------
       REDIRECTION POST → GET
    ----------------------------------------------------- */

    if ($messageErreur !== "") {
        $_SESSION['erreur_pays'] = $messageErreur;
    }

    if ($messageSucces !== "") {
        $_SESSION['succes_pays'] = $messageSucces;
    }

    header("Location: pays.php");
    exit;
}


/* =========================================================
   5. RÉCUPÉRER LE PAYS À MODIFIER
========================================================= */

$edition = null;

if (isset($_GET['modifier'])) {

    $editId = filter_input(INPUT_GET, 'modifier', FILTER_VALIDATE_INT);

    if ($editId) {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, nom, code_iso
             FROM pays
             WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $editId);
        mysqli_stmt_execute($stmt);

        $resultat = mysqli_stmt_get_result($stmt);
        $edition = mysqli_fetch_assoc($resultat);

        mysqli_stmt_close($stmt);
    }
}


/* =========================================================
   6. RÉCUPÉRER LES PAYS ET LEURS STATISTIQUES
========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        p.id,
        p.nom,
        p.code_iso,
        COUNT(v.id) AS total_villes
     FROM pays p
     LEFT JOIN ville v ON v.pays_id = p.id
     GROUP BY p.id, p.nom, p.code_iso
     ORDER BY p.nom ASC"
);

mysqli_stmt_execute($stmt);

$pays = mysqli_stmt_get_result($stmt);

$totalPays = mysqli_num_rows($pays);

// Affichage uniquement : liste en mémoire, chiffres de synthèse, badge messages
$listePays = mysqli_fetch_all($pays, MYSQLI_ASSOC);
$totalVillesLiees = array_sum(array_map(fn($p) => (int)$p['total_villes'], $listePays));
$paysSansVille = count(array_filter($listePays, fn($p) => (int)$p['total_villes'] === 0));

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
                        Gestion des pays
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 mt-2">
                        Les pays auxquels sont rattachées les villes et les monuments.
                    </p>
                </div>
                <?php if ($edition): ?>
                    <a href="pays.php"
                       class="inline-flex items-center px-4 py-2.5 rounded-md bg-cyan-800 hover:bg-cyan-900
                              text-white text-sm font-semibold transition-colors">
                        Ajouter un nouveau pays
                    </a>
                <?php else: ?>
                    <a href="#form-pays"
                       class="inline-flex items-center px-4 py-2.5 rounded-md bg-cyan-800 hover:bg-cyan-900
                              text-white text-sm font-semibold transition-colors">
                        Ajouter un pays
                    </a>
                <?php endif; ?>
            </header>

            <!-- CHIFFRES -->
            <dl class="grid grid-cols-3 gap-4 mb-8">
                <?php foreach ([
                    ['Pays', $totalPays],
                    ['Villes rattachées', $totalVillesLiees],
                    ['Pays sans ville', $paysSansVille],
                ] as [$lib, $val]): ?>
                    <div class="rounded-lg bg-white dark:bg-slate-900 border border-stone-200 dark:border-slate-800 p-4 sm:p-5">
                        <dd class="titre-serif text-3xl sm:text-4xl tabular-nums text-slate-900 dark:text-white">
                            <?= (int)$val ?>
                        </dd>
                        <dt class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1"><?= h($lib) ?></dt>
                    </div>
                <?php endforeach; ?>
            </dl>

            <!-- MESSAGES FLASH -->
            <?php if ($erreur !== ""): ?>
                <div data-alerte role="alert"
                     class="mb-6 flex items-start justify-between gap-4 rounded-md border border-red-200 dark:border-red-900
                            bg-red-50 dark:bg-red-950/40 px-4 py-3 text-sm text-red-800 dark:text-red-300">
                    <span><?= h($erreur) ?></span>
                    <button type="button" data-fermer aria-label="Fermer le message" class="text-lg leading-none">&times;</button>
                </div>
            <?php endif; ?>

            <?php if ($succes !== ""): ?>
                <div data-alerte data-auto role="status"
                     class="mb-6 flex items-start justify-between gap-4 rounded-md border border-green-200 dark:border-green-900
                            bg-green-50 dark:bg-green-950/40 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                    <span><?= h($succes) ?></span>
                    <button type="button" data-fermer aria-label="Fermer le message" class="text-lg leading-none">&times;</button>
                </div>
            <?php endif; ?>

            <!-- FORMULAIRE -->
            <section id="form-pays"
                     class="scroll-mt-6 rounded-lg bg-white dark:bg-slate-900
                            border border-stone-200 dark:border-slate-800 p-5 sm:p-6 mb-10">

                <div class="flex items-center justify-between gap-3 mb-6">
                    <div>
                        <h2 class="titre-serif text-xl text-slate-900 dark:text-white">
                            <?= $edition ? "Modifier le pays" : "Ajouter un pays" ?>
                        </h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            <?= $edition
                                ? "Modifiez les informations du pays."
                                : "Ajoutez un nouveau pays au patrimoine."
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

                <form method="POST" action="pays.php" class="space-y-5">

                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

                    <?php if ($edition): ?>
                        <input type="hidden" name="id" value="<?= (int)$edition['id'] ?>">
                    <?php endif; ?>

                    <div class="grid sm:grid-cols-3 gap-5">
                        <div class="sm:col-span-2">
                            <label for="nom" class="<?= $etiquette ?>">Nom du pays</label>
                            <input type="text" id="nom" name="nom" maxlength="255" required
                                   value="<?= h($edition['nom'] ?? '') ?>"
                                   placeholder="Ex : Maroc"
                                   class="<?= $champ ?>">
                        </div>

                        <div>
                            <label for="code_iso" class="<?= $etiquette ?>">Code ISO</label>
                            <input type="text" id="code_iso" name="code_iso"
                                   minlength="2" maxlength="2" pattern="[A-Za-z]{2}" required
                                   autocomplete="off" spellcheck="false"
                                   value="<?= h($edition['code_iso'] ?? '') ?>"
                                   placeholder="Ex : MA"
                                   aria-describedby="aide-iso"
                                   class="<?= $champ ?> uppercase font-mono tracking-widest">
                        </div>
                    </div>

                    <p id="aide-iso" class="text-xs text-slate-500 dark:text-slate-400 -mt-2">
                        Code ISO à deux lettres, converti automatiquement en majuscules. Exemple : MA pour le Maroc.
                    </p>

                    <div class="flex flex-wrap justify-end gap-3">
                        <?php if ($edition): ?>
                            <a href="pays.php"
                               class="px-5 py-2.5 rounded-md border border-stone-300 dark:border-slate-700
                                      text-slate-700 dark:text-slate-300 hover:bg-stone-100 dark:hover:bg-slate-800
                                      transition-colors">
                                Annuler
                            </a>
                        <?php endif; ?>

                        <button type="submit" name="<?= $edition ? 'modifier' : 'ajouter' ?>"
                                class="px-5 py-2.5 rounded-md bg-cyan-800 hover:bg-cyan-900 text-white font-semibold transition-colors">
                            <?= $edition ? "Enregistrer les modifications" : "Ajouter le pays" ?>
                        </button>
                    </div>
                </form>
            </section>

            <!-- LISTE DES PAYS -->
            <section aria-labelledby="titre-liste">

                <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 mb-6">
                    <div>
                        <h2 id="titre-liste" class="titre-serif text-xl text-slate-900 dark:text-white">
                            Liste des pays
                        </h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1" aria-live="polite">
                            <span id="nb-resultats"><?= (int)$totalPays ?></span>
                            pays affiché<?= $totalPays > 1 ? 's' : '' ?>
                        </p>
                    </div>

                    <?php if ($totalPays > 0): ?>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                            <label class="sr-only" for="recherche">Rechercher un pays</label>
                            <input type="search" id="recherche" placeholder="Rechercher un nom ou un code…"
                                   class="<?= $champ ?> sm:w-72">

                            <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300 cursor-pointer">
                                <input type="checkbox" id="filtre-vides"
                                       class="w-4 h-4 rounded border-stone-300 text-cyan-800 focus:ring-cyan-700">
                                Sans ville seulement
                            </label>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($totalPays === 0): ?>

                    <div class="rounded-lg border border-dashed border-stone-300 dark:border-slate-700 p-12 text-center">
                        <h3 class="font-semibold text-slate-800 dark:text-white">Aucun pays disponible</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">
                            Les pays ajoutés apparaîtront ici.
                        </p>
                    </div>

                <?php else: ?>

                    <div id="grille" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

                        <?php foreach ($listePays as $country): ?>
                            <?php $nbVilles = (int)$country['total_villes']; ?>

                            <article class="flex flex-col rounded-lg bg-white dark:bg-slate-900
                                            border border-stone-200 dark:border-slate-800 p-5
                                            hover:border-cyan-600 dark:hover:border-cyan-500 transition-colors"
                                     data-nb="<?= $nbVilles ?>"
                                     data-texte="<?= h($country['nom'] . ' ' . $country['code_iso']) ?>">

                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="titre-serif text-2xl text-slate-900 dark:text-white truncate">
                                        <?= h($country['nom']) ?>
                                    </h3>
                                    <span class="shrink-0 px-2.5 py-1 rounded-md bg-stone-100 dark:bg-slate-800
                                                 text-slate-700 dark:text-slate-300 font-mono text-xs font-bold tracking-wider">
                                        <?= h($country['code_iso']) ?>
                                    </span>
                                </div>

                                <p class="mt-4 text-sm text-slate-500 dark:text-slate-400 flex-1">
                                    <span class="titre-serif text-2xl tabular-nums text-slate-900 dark:text-white">
                                        <?= $nbVilles ?>
                                    </span>
                                    ville<?= $nbVilles > 1 ? 's' : '' ?> associée<?= $nbVilles > 1 ? 's' : '' ?>
                                </p>

                                <div class="flex items-center gap-2 border-t border-stone-200 dark:border-slate-800 mt-5 pt-3">
                                    <a href="pays.php?modifier=<?= (int)$country['id'] ?>#form-pays"
                                       class="flex-1 text-center text-sm font-medium text-cyan-800 dark:text-cyan-400
                                              hover:bg-cyan-50 dark:hover:bg-cyan-950/40 rounded-md py-2 transition-colors">
                                        Modifier
                                    </a>

                                    <?php if ($nbVilles === 0): ?>
                                        <button type="button" data-supprimer="<?= (int)$country['id'] ?>"
                                                data-titre="<?= h($country['nom']) ?>"
                                                class="flex-1 text-sm font-medium text-red-700 dark:text-red-400
                                                       hover:bg-red-50 dark:hover:bg-red-950/30 rounded-md py-2 transition-colors">
                                            Supprimer
                                        </button>
                                    <?php else: ?>
                                        <button type="button" disabled
                                                title="Ce pays possède des villes."
                                                class="flex-1 text-sm font-medium text-slate-400 dark:text-slate-500
                                                       rounded-md py-2 cursor-not-allowed">
                                            Supprimer
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <?php if ($nbVilles > 0): ?>
                                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-2">
                                        Suppression impossible : ce pays possède des villes.
                                    </p>
                                <?php endif; ?>
                            </article>

                        <?php endforeach; ?>
                    </div>

                    <p id="aucun" class="hidden rounded-lg border border-dashed border-stone-300 dark:border-slate-700
                                         p-10 text-center text-sm text-slate-500 dark:text-slate-400">
                        Aucun pays ne correspond à votre recherche.
                    </p>

                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<!-- Confirmation de suppression -->
<dialog id="dialogue"
        class="rounded-lg p-0 w-[calc(100%-2rem)] max-w-md bg-white dark:bg-slate-900
               text-slate-900 dark:text-white border border-stone-200 dark:border-slate-800 shadow-xl">
    <form method="POST" action="pays.php" class="p-6">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="id" id="dialogue-id" value="">

        <h2 class="titre-serif text-xl">Supprimer ce pays ?</h2>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-3">
            Le pays « <span id="dialogue-titre" class="font-medium"></span> » sera supprimé définitivement.
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

        // Code ISO : lettres uniquement, en majuscules
        var iso = document.getElementById('code_iso');
        iso.addEventListener('input', function () {
            iso.value = iso.value.replace(/[^a-zA-Z]/g, '').toUpperCase().slice(0, 2);
        });

        // Recherche + filtre « sans ville » (côté client)
        var champ = document.getElementById('recherche');
        var vides = document.getElementById('filtre-vides');
        var cartes = document.querySelectorAll('#grille [data-nb]');
        var nb = document.getElementById('nb-resultats');
        var aucun = document.getElementById('aucun');

        function norm(t) {
            return t.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        function filtrer() {
            var q = norm(champ.value.trim());
            var n = 0;
            cartes.forEach(function (c) {
                var ok = (!vides.checked || c.getAttribute('data-nb') === '0') &&
                         (!q || norm(c.getAttribute('data-texte')).indexOf(q) !== -1);
                c.hidden = !ok;
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
