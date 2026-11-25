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

$pageTitle = "Gestion des images";

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/* =========================================================
   2. PROTECTION CSRF
========================================================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_token'];

/* =========================================================
   3. MESSAGES FLASH
========================================================= */

$messageErreur = $_SESSION['erreur_images'] ?? "";
$messageSucces = $_SESSION['succes_images'] ?? "";

unset(
    $_SESSION['erreur_images'],
    $_SESSION['succes_images']
);

/* =========================================================
   4. DOSSIER DE STOCKAGE
========================================================= */

$cheminCreation = __DIR__ . "/../images/monuments";

if (!is_dir($cheminCreation)) {
    mkdir($cheminCreation, 0755, true);
}

$dossierImages = realpath($cheminCreation);

/* =========================================================
   5. FONCTION D'ENREGISTREMENT D'UNE IMAGE
========================================================= */

function enregistrerImage($fichier, $dossierImages) {

    if (
        !isset($fichier['tmp_name']) ||
        !isset($fichier['error']) ||
        $fichier['error'] !== UPLOAD_ERR_OK
    ) {
        throw new Exception("Erreur lors de l'envoi de l'image.");
    }

    if (!is_uploaded_file($fichier['tmp_name'])) {
        throw new Exception("Fichier envoyé invalide.");
    }

    if ($fichier['size'] > 2 * 1024 * 1024) {
        throw new Exception("L'image ne doit pas dépasser 2 Mo.");
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($fichier['tmp_name']);

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];

    if (!isset($extensions[$mime])) {
        throw new Exception("Format invalide. Utilisez JPG, JPEG ou PNG.");
    }

    $extension = $extensions[$mime];

    $nomFichier = bin2hex(random_bytes(16)) . "." . $extension;

    $destination = $dossierImages . DIRECTORY_SEPARATOR . $nomFichier;

    if (!move_uploaded_file($fichier['tmp_name'], $destination)) {
        throw new Exception("Impossible d'enregistrer l'image.");
    }

    return "images/monuments/" . $nomFichier;
}

/* =========================================================
   6. TRAITEMENT DES FORMULAIRES
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($csrf, $_POST['csrf_token'])
    ) {

        $messageErreur = "Requête invalide. Actualisez la page.";

    } else {

        $action = $_POST['action'] ?? '';

        /* -------------------------------------------------
           AJOUTER UNE IMAGE
        ------------------------------------------------- */

        if ($action === 'ajouter') {

            $monumentId = filter_input(
                INPUT_POST,
                'monument_id',
                FILTER_VALIDATE_INT
            );

            $legende = trim($_POST['legende'] ?? '');

            if (!$monumentId || $monumentId <= 0) {

                $messageErreur = "Veuillez sélectionner un monument.";

            } elseif ($legende === '') {

                $messageErreur = "Veuillez saisir une légende.";

            } elseif (mb_strlen($legende) > 255) {

                $messageErreur = "La légende ne doit pas dépasser 255 caractères.";

            } elseif (!isset($_FILES['image'])) {

                $messageErreur = "Veuillez sélectionner une image.";

            } elseif ($dossierImages === false) {

                $messageErreur = "Le dossier de stockage des images est introuvable.";

            } else {

                // Vérifier l'existence du monument

                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT id FROM monument WHERE id = ?"
                );

                mysqli_stmt_bind_param($stmt, "i", $monumentId);
                mysqli_stmt_execute($stmt);

                $resultat = mysqli_stmt_get_result($stmt);
                $monumentExiste = mysqli_fetch_assoc($resultat);

                mysqli_stmt_close($stmt);

                if (!$monumentExiste) {

                    $messageErreur = "Le monument sélectionné n'existe pas.";

                } else {

                    $cheminImage = null;
                    $stmt = null;

                    try {

                        // Enregistrer le fichier physiquement

                        $cheminImage = enregistrerImage(
                            $_FILES['image'],
                            $dossierImages
                        );

                        // Enregistrer les informations dans MySQL

                        $stmt = mysqli_prepare(
                            $conn,
                            "INSERT INTO image (chemin, legende, monument_id)
                             VALUES (?, ?, ?)"
                        );

                        if (!$stmt) {
                            throw new Exception("Erreur de préparation de la requête SQL.");
                        }

                        mysqli_stmt_bind_param(
                            $stmt,
                            "ssi",
                            $cheminImage,
                            $legende,
                            $monumentId
                        );

                        if (mysqli_stmt_execute($stmt)) {

                            $messageSucces = "Image ajoutée avec succès.";

                        } else {

                            throw new Exception(
                                "Erreur lors de l'enregistrement dans la base de données."
                            );
                        }

                        mysqli_stmt_close($stmt);
                        $stmt = null;

                    } catch (Throwable $e) {

                        if ($stmt !== null) {
                            mysqli_stmt_close($stmt);
                        }

                        // Supprimer le fichier si l'insertion échoue

                        if ($cheminImage !== null) {

                            $nom = basename($cheminImage);

                            $fichierPhysique = $dossierImages .
                                DIRECTORY_SEPARATOR . $nom;

                            if (is_file($fichierPhysique)) {
                                unlink($fichierPhysique);
                            }
                        }

                        $messageErreur = $e->getMessage();
                    }
                }
            }

        /* -------------------------------------------------
           SUPPRIMER UNE IMAGE
        ------------------------------------------------- */

        } elseif ($action === 'supprimer') {

            $id = filter_input(
                INPUT_POST,
                'id',
                FILTER_VALIDATE_INT
            );

            if (!$id || $id <= 0) {

                $messageErreur = "Identifiant de l'image invalide.";

            } else {

                // Récupérer le chemin de l'image

                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT chemin FROM image WHERE id = ?"
                );

                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);

                $resultat = mysqli_stmt_get_result($stmt);
                $image = mysqli_fetch_assoc($resultat);

                mysqli_stmt_close($stmt);

                if (!$image) {

                    $messageErreur = "Image introuvable.";

                } else {

                    // Supprimer de la base de données

                    $stmt = mysqli_prepare(
                        $conn,
                        "DELETE FROM image WHERE id = ?"
                    );

                    mysqli_stmt_bind_param($stmt, "i", $id);

                    if (mysqli_stmt_execute($stmt)) {

                        if (mysqli_stmt_affected_rows($stmt) > 0) {

                            // Supprimer le fichier physique

                            $nomFichier = basename($image['chemin']);

                            $fichierPhysique = $dossierImages !== false
                                ? $dossierImages . DIRECTORY_SEPARATOR . $nomFichier
                                : false;

                            if (
                                $fichierPhysique !== false &&
                                is_file($fichierPhysique)
                            ) {
                                unlink($fichierPhysique);
                            }

                            $messageSucces = "Image supprimée avec succès.";

                        } else {

                            $messageErreur = "Image introuvable.";
                        }

                    } else {

                        $messageErreur = "Erreur lors de la suppression.";
                    }

                    mysqli_stmt_close($stmt);
                }
            }

        } else {

            $messageErreur = "Action inconnue.";
        }
    }

    // PRG : Post -> Redirect -> Get

    if ($messageErreur !== "") {
        $_SESSION['erreur_images'] = $messageErreur;
    }

    if ($messageSucces !== "") {
        $_SESSION['succes_images'] = $messageSucces;
    }

    header("Location: images.php");
    exit;
}

/* =========================================================
   7. STATISTIQUES
========================================================= */

$resultatStats = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total,
        COUNT(DISTINCT monument_id) AS monuments
     FROM image"
);

$stats = mysqli_fetch_assoc($resultatStats);

$totalImages = (int)($stats['total'] ?? 0);
$totalMonumentsAvecImages = (int)($stats['monuments'] ?? 0);

mysqli_free_result($resultatStats);

/* =========================================================
   8. LISTE DES MONUMENTS
========================================================= */

$resultatMonuments = mysqli_query(
    $conn,
    "SELECT id, titre FROM monument ORDER BY titre ASC"
);

$monuments = [];

if ($resultatMonuments) {
    while ($monument = mysqli_fetch_assoc($resultatMonuments)) {
        $monuments[] = $monument;
    }

    mysqli_free_result($resultatMonuments);
}

/* =========================================================
   9. LISTE DES IMAGES
========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        i.id,
        i.chemin,
        i.legende,
        i.monument_id,
        m.titre AS monument
     FROM image i
     INNER JOIN monument m ON i.monument_id = m.id
     ORDER BY i.id DESC"
);

mysqli_stmt_execute($stmt);
$resultatImages = mysqli_stmt_get_result($stmt);

$totalImagesListe = mysqli_num_rows($resultatImages);

// Affichage uniquement : liste en mémoire + monuments présents (filtre)
$listeImages = mysqli_fetch_all($resultatImages, MYSQLI_ASSOC);
$monumentsFiltre = array_values(array_unique(array_column($listeImages, 'monument')));
sort($monumentsFiltre, SORT_NATURAL | SORT_FLAG_CASE);
$monumentsSansImage = max(0, count($monuments) - $totalMonumentsAvecImages);

/* =========================================================
   10. NAVIGATION
========================================================= */

$messagesNonLus = 0;

$resultatNonLus = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM message WHERE lu = 0"
);

if ($resultatNonLus) {
    $ligneNonLus = mysqli_fetch_assoc($resultatNonLus);
    $messagesNonLus = (int)$ligneNonLus['total'];
    mysqli_free_result($resultatNonLus);
}

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
                        Gestion des images
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 mt-2">
                        La galerie visuelle des monuments du catalogue.
                    </p>
                </div>
                <a href="#form-image"
                   class="inline-flex items-center px-4 py-2.5 rounded-md bg-cyan-800 hover:bg-cyan-900
                          text-white text-sm font-semibold transition-colors">
                    Ajouter une image
                </a>
            </header>

            <!-- CHIFFRES -->
            <dl class="grid grid-cols-3 gap-4 mb-8">
                <?php foreach ([
                    ['Images', $totalImages],
                    ['Monuments illustrés', $totalMonumentsAvecImages],
                    ['Monuments sans image', $monumentsSansImage],
                ] as [$lib, $val]): ?>
                    <div class="rounded-lg bg-white dark:bg-slate-900 border border-stone-200 dark:border-slate-800 p-4 sm:p-5">
                        <dd class="titre-serif text-3xl sm:text-4xl tabular-nums text-slate-900 dark:text-white">
                            <?= (int)$val ?>
                        </dd>
                        <dt class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1"><?= h($lib) ?></dt>
                    </div>
                <?php endforeach; ?>
            </dl>

            <!-- NOTIFICATIONS -->
            <?php if ($messageSucces !== ""): ?>
                <div data-alerte data-auto role="status"
                     class="mb-6 flex items-start justify-between gap-4 rounded-md border border-green-200 dark:border-green-900
                            bg-green-50 dark:bg-green-950/40 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                    <span><?= h($messageSucces) ?></span>
                    <button type="button" data-fermer aria-label="Fermer le message" class="text-lg leading-none">&times;</button>
                </div>
            <?php endif; ?>

            <?php if ($messageErreur !== ""): ?>
                <div data-alerte role="alert"
                     class="mb-6 flex items-start justify-between gap-4 rounded-md border border-red-200 dark:border-red-900
                            bg-red-50 dark:bg-red-950/40 px-4 py-3 text-sm text-red-800 dark:text-red-300">
                    <span><?= h($messageErreur) ?></span>
                    <button type="button" data-fermer aria-label="Fermer le message" class="text-lg leading-none">&times;</button>
                </div>
            <?php endif; ?>

            <!-- FORMULAIRE D'AJOUT -->
            <section id="form-image"
                     class="scroll-mt-6 rounded-lg bg-white dark:bg-slate-900
                            border border-stone-200 dark:border-slate-800 p-5 sm:p-6 mb-10">

                <div class="mb-6">
                    <h2 class="titre-serif text-xl text-slate-900 dark:text-white">Ajouter une image</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Formats acceptés : JPG, JPEG et PNG. Taille maximale : 2 Mo.
                    </p>
                </div>

                <?php if (count($monuments) === 0): ?>

                    <div class="rounded-md border border-orange-200 dark:border-orange-900
                                bg-orange-50 dark:bg-orange-950/30 p-4 text-sm text-orange-800 dark:text-orange-300">
                        Ajoutez d'abord un monument avant d'ajouter des images.
                        <a href="monuments.php" class="font-semibold underline ml-1">Ajouter un monument</a>
                    </div>

                <?php else: ?>

                    <form method="POST" enctype="multipart/form-data" action="images.php"
                          class="grid grid-cols-1 md:grid-cols-2 gap-5">

                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="ajouter">

                        <div>
                            <label for="monument_id" class="<?= $etiquette ?>">Monument</label>
                            <select name="monument_id" id="monument_id" required class="<?= $champ ?>">
                                <option value="">Sélectionner un monument</option>
                                <?php foreach ($monuments as $monument): ?>
                                    <option value="<?= (int)$monument['id'] ?>"><?= h($monument['titre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="legende" class="<?= $etiquette ?>">Légende</label>
                            <input type="text" id="legende" name="legende" required maxlength="255"
                                   placeholder="Ex : Vue principale du monument"
                                   class="<?= $champ ?>">
                        </div>

                        <!-- ZONE DE DÉPÔT -->
                        <div class="md:col-span-2">
                            <span class="<?= $etiquette ?>" id="etiquette-image">Image</span>

                            <label id="zone" for="image"
                                   class="flex flex-col items-center justify-center gap-2 text-center cursor-pointer
                                          rounded-md border-2 border-dashed border-stone-300 dark:border-slate-700
                                          bg-stone-50 dark:bg-slate-950/50 px-4 py-8
                                          hover:border-cyan-700 dark:hover:border-cyan-500
                                          focus-within:ring-2 focus-within:ring-cyan-700 transition-colors">
                                <svg class="w-8 h-8 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 16V4M7 9l5-5 5 5M4 20h16"/>
                                </svg>
                                <span class="text-sm text-slate-700 dark:text-slate-300">
                                    <span class="font-semibold text-cyan-800 dark:text-cyan-400">Choisir un fichier</span>
                                    ou le glisser ici
                                </span>
                                <span id="info-fichier" class="text-xs text-slate-500 dark:text-slate-400">
                                    JPG, JPEG ou PNG — 2 Mo maximum
                                </span>
                                <input type="file" id="image" name="image" required
                                       aria-labelledby="etiquette-image"
                                       accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                       class="sr-only">
                            </label>

                            <p id="erreur-fichier" class="hidden text-sm text-red-700 dark:text-red-400 mt-2" role="alert"></p>
                        </div>

                        <!-- APERÇU -->
                        <div id="preview-container" class="hidden md:col-span-2">
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Aperçu</p>
                            <img id="preview-image" src="" alt="Aperçu de l'image sélectionnée"
                                 class="max-h-64 rounded-md border border-stone-200 dark:border-slate-700">
                        </div>

                        <div class="md:col-span-2 flex justify-end">
                            <button type="submit"
                                    class="px-6 py-2.5 rounded-md bg-cyan-800 hover:bg-cyan-900
                                           text-white font-semibold transition-colors">
                                Ajouter l'image
                            </button>
                        </div>
                    </form>

                <?php endif; ?>
            </section>

            <!-- GALERIE -->
            <section aria-labelledby="titre-liste">

                <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 mb-6">
                    <div>
                        <h2 id="titre-liste" class="titre-serif text-xl text-slate-900 dark:text-white">
                            Galerie des images
                        </h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1" aria-live="polite">
                            <span id="nb-resultats"><?= (int)$totalImagesListe ?></span>
                            image<?= $totalImagesListe > 1 ? 's' : '' ?> affichée<?= $totalImagesListe > 1 ? 's' : '' ?>
                        </p>
                    </div>

                    <?php if ($totalImagesListe > 0): ?>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <label class="sr-only" for="recherche">Rechercher une image</label>
                            <input type="search" id="recherche" placeholder="Rechercher une légende…"
                                   class="<?= $champ ?> sm:w-64">

                            <label class="sr-only" for="filtre-monument">Filtrer par monument</label>
                            <select id="filtre-monument" class="<?= $champ ?> sm:w-56">
                                <option value="">Tous les monuments</option>
                                <?php foreach ($monumentsFiltre as $mf): ?>
                                    <option value="<?= h($mf) ?>"><?= h($mf) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($totalImagesListe === 0): ?>

                    <div class="rounded-lg border border-dashed border-stone-300 dark:border-slate-700
                                bg-white dark:bg-slate-900 p-12 text-center">
                        <h3 class="titre-serif text-xl text-slate-900 dark:text-white">Aucune image</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">
                            Les images ajoutées apparaîtront ici.
                        </p>
                    </div>

                <?php else: ?>

                    <div id="grille" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">

                        <?php foreach ($listeImages as $image): ?>

                            <article class="flex flex-col rounded-lg overflow-hidden bg-white dark:bg-slate-900
                                            border border-stone-200 dark:border-slate-800
                                            hover:border-cyan-600 dark:hover:border-cyan-500 transition-colors"
                                     data-monument="<?= h($image['monument']) ?>"
                                     data-texte="<?= h($image['legende'] . ' ' . $image['monument']) ?>">

                                <button type="button" data-voir
                                        data-src="../<?= h($image['chemin']) ?>"
                                        data-legende="<?= h($image['legende']) ?>"
                                        aria-label="Agrandir l'image : <?= h($image['legende']) ?>"
                                        class="block aspect-[3/2] bg-stone-100 dark:bg-slate-800 overflow-hidden cursor-zoom-in">
                                    <img src="../<?= h($image['chemin']) ?>"
                                         alt="<?= h($image['legende']) ?>" loading="lazy"
                                         class="w-full h-full object-cover">
                                </button>

                                <div class="flex flex-col flex-1 p-4">
                                    <p class="text-xs font-medium text-cyan-800 dark:text-cyan-400">
                                        <?= h($image['monument']) ?>
                                    </p>
                                    <h3 class="font-semibold text-slate-900 dark:text-white mt-1 coupe-2 flex-1">
                                        <?= h($image['legende']) ?>
                                    </h3>
                                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-2">
                                        Image n° <?= (int)$image['id'] ?>
                                    </p>

                                    <div class="border-t border-stone-200 dark:border-slate-800 mt-4 pt-3">
                                        <button type="button" data-supprimer="<?= (int)$image['id'] ?>"
                                                data-titre="<?= h($image['legende']) ?>"
                                                data-src="../<?= h($image['chemin']) ?>"
                                                class="w-full text-sm font-medium text-red-700 dark:text-red-400
                                                       hover:bg-red-50 dark:hover:bg-red-950/30 rounded-md py-2 transition-colors">
                                            Supprimer l'image
                                        </button>
                                    </div>
                                </div>
                            </article>

                        <?php endforeach; ?>
                    </div>

                    <p id="aucun" class="hidden rounded-lg border border-dashed border-stone-300 dark:border-slate-700
                                         p-10 text-center text-sm text-slate-500 dark:text-slate-400">
                        Aucune image ne correspond à votre recherche.
                    </p>

                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<!-- Visionneuse -->
<dialog id="visionneuse"
        class="rounded-lg p-0 w-[calc(100%-2rem)] max-w-4xl bg-white dark:bg-slate-900
               text-slate-900 dark:text-white border border-stone-200 dark:border-slate-800 shadow-xl">
    <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-stone-200 dark:border-slate-800">
        <p id="visionneuse-legende" class="font-semibold"></p>
        <button type="button" id="visionneuse-fermer" aria-label="Fermer"
                class="text-2xl leading-none text-slate-500 hover:text-slate-900 dark:hover:text-white">&times;</button>
    </div>
    <div class="p-4 bg-stone-100 dark:bg-slate-950">
        <img id="visionneuse-image" src="" alt="" class="mx-auto max-h-[75vh] w-auto rounded">
    </div>
</dialog>

<!-- Confirmation de suppression -->
<dialog id="dialogue"
        class="rounded-lg p-0 w-[calc(100%-2rem)] max-w-md bg-white dark:bg-slate-900
               text-slate-900 dark:text-white border border-stone-200 dark:border-slate-800 shadow-xl">
    <form method="POST" action="images.php" class="p-6">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="supprimer">
        <input type="hidden" name="id" id="dialogue-id" value="">

        <h2 class="titre-serif text-xl">Supprimer cette image ?</h2>
        <img id="dialogue-image" src="" alt="" class="mt-4 h-32 w-full object-cover rounded-md">
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-3">
            « <span id="dialogue-titre" class="font-medium"></span> » sera supprimée définitivement,
            fichier compris.
        </p>

        <div class="flex justify-end gap-3 mt-6">
            <button type="button" id="dialogue-annuler"
                    class="px-4 py-2 rounded-md border border-stone-300 dark:border-slate-700
                           text-slate-700 dark:text-slate-300 hover:bg-stone-100 dark:hover:bg-slate-800 transition-colors">
                Annuler
            </button>
            <button type="submit"
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

        // Dépôt d'image : glisser-déposer, aperçu et contrôles
        var input = document.getElementById('image');
        if (input) {
            var zone = document.getElementById('zone');
            var info = document.getElementById('info-fichier');
            var infoInitiale = info.textContent;
            var erreur = document.getElementById('erreur-fichier');
            var bloc = document.getElementById('preview-container');
            var apercu = document.getElementById('preview-image');
            var url = null;

            function taille(o) { return o < 1048576 ? Math.round(o / 1024) + ' Ko' : (o / 1048576).toFixed(1).replace('.', ',') + ' Mo'; }

            function refuser(msg) {
                input.value = '';
                bloc.classList.add('hidden');
                info.textContent = infoInitiale;
                erreur.textContent = msg;
                erreur.classList.remove('hidden');
            }

            function verifier() {
                if (url) { URL.revokeObjectURL(url); url = null; }
                erreur.classList.add('hidden');
                var f = input.files[0];
                if (!f) { bloc.classList.add('hidden'); info.textContent = infoInitiale; return; }
                if (['image/jpeg', 'image/png'].indexOf(f.type) === -1) { refuser('Format invalide. Utilisez JPG, JPEG ou PNG.'); return; }
                if (f.size > 2 * 1024 * 1024) { refuser('L\u2019image ne doit pas dépasser 2 Mo (' + taille(f.size) + ').'); return; }
                url = URL.createObjectURL(f);
                apercu.src = url;
                bloc.classList.remove('hidden');
                info.textContent = f.name + ' — ' + taille(f.size);
            }

            input.addEventListener('change', verifier);

            ['dragenter', 'dragover'].forEach(function (ev) {
                zone.addEventListener(ev, function (e) {
                    e.preventDefault();
                    zone.classList.add('border-cyan-700', 'bg-cyan-50', 'dark:bg-cyan-950/30');
                });
            });
            ['dragleave', 'drop'].forEach(function (ev) {
                zone.addEventListener(ev, function () {
                    zone.classList.remove('border-cyan-700', 'bg-cyan-50', 'dark:bg-cyan-950/30');
                });
            });
            zone.addEventListener('drop', function (e) {
                e.preventDefault();
                if (e.dataTransfer && e.dataTransfer.files.length) {
                    input.files = e.dataTransfer.files;
                    verifier();
                }
            });
        }

        // Recherche + filtre par monument (côté client)
        var champ = document.getElementById('recherche');
        var filtre = document.getElementById('filtre-monument');
        var cartes = document.querySelectorAll('#grille [data-monument]');
        var nb = document.getElementById('nb-resultats');
        var aucun = document.getElementById('aucun');

        function norm(t) { return t.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); }
        function filtrer() {
            var q = norm(champ.value.trim());
            var n = 0;
            cartes.forEach(function (c) {
                var ok = (!filtre.value || c.getAttribute('data-monument') === filtre.value) &&
                         (!q || norm(c.getAttribute('data-texte')).indexOf(q) !== -1);
                c.hidden = !ok;
                if (ok) n++;
            });
            nb.textContent = n;
            aucun.classList.toggle('hidden', n !== 0);
        }
        if (champ && filtre) {
            champ.addEventListener('input', filtrer);
            filtre.addEventListener('change', filtrer);
        }

        // Visionneuse
        var vis = document.getElementById('visionneuse');
        document.querySelectorAll('[data-voir]').forEach(function (b) {
            b.addEventListener('click', function () {
                var img = document.getElementById('visionneuse-image');
                img.src = b.getAttribute('data-src');
                img.alt = b.getAttribute('data-legende');
                document.getElementById('visionneuse-legende').textContent = b.getAttribute('data-legende');
                vis.showModal();
            });
        });
        document.getElementById('visionneuse-fermer').addEventListener('click', function () { vis.close(); });
        vis.addEventListener('click', function (e) { if (e.target === vis) vis.close(); });

        // Confirmation de suppression
        var dlg = document.getElementById('dialogue');
        document.querySelectorAll('[data-supprimer]').forEach(function (b) {
            b.addEventListener('click', function () {
                document.getElementById('dialogue-id').value = b.getAttribute('data-supprimer');
                document.getElementById('dialogue-titre').textContent = b.getAttribute('data-titre');
                document.getElementById('dialogue-image').src = b.getAttribute('data-src');
                dlg.showModal();
            });
        });
        document.getElementById('dialogue-annuler').addEventListener('click', function () { dlg.close(); });
        dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
    })();
</script>

<?php
mysqli_stmt_close($stmt);
?>
</body>
</html>
