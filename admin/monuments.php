<?php
session_start();
require_once "../includes/config.php";

// Vérification de l'administrateur
if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: login.php");
    exit;
}

$pageTitle = "Gestion des monuments";

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$erreur = "";
$succes = "";

// Messages temporaires après redirection
$erreur = $_SESSION['erreur_monuments'] ?? "";
$succes = $_SESSION['succes_monuments'] ?? "";

unset($_SESSION['erreur_monuments'], $_SESSION['succes_monuments']);

// Protection CSRF
if (empty($_SESSION['csrf_monuments'])) {
    $_SESSION['csrf_monuments'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_monuments'];

$dossierImages = "../images/monuments/";
$cheminImages = "images/monuments/";

if (!is_dir($dossierImages)) {
    mkdir($dossierImages, 0755, true);
}

// Fonction d'enregistrement d'une photo
function enregistrerPhoto($conn, $monumentId, $fichier) {

    if (!isset($fichier) || $fichier['error'] === UPLOAD_ERR_NO_FILE) {
        return true;
    }

    if ($fichier['error'] !== UPLOAD_ERR_OK) {
        return "Erreur lors de l'envoi de la photo.";
    }

    if ($fichier['size'] > 2 * 1024 * 1024) {
        return "La photo ne doit pas dépasser 2 Mo.";
    }

    $extension = strtolower(
        pathinfo($fichier['name'], PATHINFO_EXTENSION)
    );

    $types = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png'
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($fichier['tmp_name']);

    if (
        !isset($types[$extension]) ||
        $types[$extension] !== $mime ||
        getimagesize($fichier['tmp_name']) === false
    ) {
        return "Format invalide. Utilisez JPG, JPEG ou PNG.";
    }

    $dossier = "../images/monuments/";

    if (!is_dir($dossier) && !mkdir($dossier, 0755, true)) {
        return "Impossible de créer le dossier des images.";
    }

    $nom = bin2hex(random_bytes(16)) . "." . $extension;
    $chemin = "images/monuments/" . $nom;
    $destination = $dossier . $nom;

    if (!move_uploaded_file($fichier['tmp_name'], $destination)) {
        return "Impossible d'enregistrer la photo.";
    }

    $legende = "Photo de présentation";

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO image (chemin, legende, monument_id)
         VALUES (?, ?, ?)"
    );

    mysqli_stmt_bind_param($stmt, "ssi", $chemin, $legende, $monumentId);

    if (!mysqli_stmt_execute($stmt)) {
        unlink($destination);
        mysqli_stmt_close($stmt);
        return "Erreur lors de l'enregistrement de la photo.";
    }

    mysqli_stmt_close($stmt);

    return true;
}

// TRAITEMENT DES FORMULAIRES
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $messageErreur = "";
    $messageSucces = "";

    if (
        !isset($_POST['csrf']) ||
        !hash_equals($csrf, $_POST['csrf'])
    ) {
        $messageErreur = "Requête invalide.";
    }

    // AJOUTER
    elseif (isset($_POST['ajouter'])) {

        $titre = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categorieId = filter_input(INPUT_POST, 'categorie_id', FILTER_VALIDATE_INT);
        $villeId = filter_input(INPUT_POST, 'ville_id', FILTER_VALIDATE_INT);

        if (
            $titre === '' ||
            $description === '' ||
            !$categorieId ||
            !$villeId
        ) {
            $messageErreur = "Veuillez remplir tous les champs obligatoires.";
        } else {

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO monument
                 (titre, description, categorie_id, ville_id)
                 VALUES (?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ssii",
                $titre,
                $description,
                $categorieId,
                $villeId
            );

            if (mysqli_stmt_execute($stmt)) {

                $id = mysqli_insert_id($conn);

                $photo = enregistrerPhoto(
                    $conn,
                    $id,
                    $_FILES['image'] ?? null
                );

                if ($photo === true) {
                    $messageSucces = "Monument ajouté avec succès.";
                } else {
                    $messageErreur = "Monument ajouté, mais : " . $photo;
                }

            } else {
                $messageErreur = "Erreur lors de l'ajout du monument.";
            }

            mysqli_stmt_close($stmt);
        }
    }

    // MODIFIER
    elseif (isset($_POST['modifier'])) {

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $titre = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categorieId = filter_input(INPUT_POST, 'categorie_id', FILTER_VALIDATE_INT);
        $villeId = filter_input(INPUT_POST, 'ville_id', FILTER_VALIDATE_INT);

        if (
            !$id ||
            $titre === '' ||
            $description === '' ||
            !$categorieId ||
            !$villeId
        ) {
            $messageErreur = "Veuillez remplir tous les champs obligatoires.";
        } else {

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE monument
                 SET titre = ?, description = ?, categorie_id = ?, ville_id = ?
                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ssiii",
                $titre,
                $description,
                $categorieId,
                $villeId,
                $id
            );

            if (mysqli_stmt_execute($stmt)) {

                $photo = enregistrerPhoto(
                    $conn,
                    $id,
                    $_FILES['image'] ?? null
                );

                if ($photo === true) {
                    $messageSucces = "Monument modifié avec succès.";
                } else {
                    $messageErreur = "Monument modifié, mais : " . $photo;
                }

            } else {
                $messageErreur = "Erreur lors de la modification.";
            }

            mysqli_stmt_close($stmt);
        }
    }

    // SUPPRIMER
    elseif (isset($_POST['supprimer'])) {

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (!$id) {
            $messageErreur = "Monument invalide.";
        } else {

            $stmt = mysqli_prepare(
                $conn,
                "SELECT chemin FROM image WHERE monument_id = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);

            $resultat = mysqli_stmt_get_result($stmt);
            $chemins = [];

            while ($row = mysqli_fetch_assoc($resultat)) {
                $chemins[] = $row['chemin'];
            }

            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM monument WHERE id = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id);

            if (mysqli_stmt_execute($stmt)) {

                $dossierReel = realpath($dossierImages);

                foreach ($chemins as $chemin) {

                    if (strpos($chemin, $cheminImages) !== 0) {
                        continue;
                    }

                    $fichier = realpath(__DIR__ . "/../" . $chemin);

                    if (
                        $fichier !== false &&
                        $dossierReel !== false &&
                        strpos(
                            $fichier,
                            $dossierReel . DIRECTORY_SEPARATOR
                        ) === 0 &&
                        is_file($fichier)
                    ) {
                        unlink($fichier);
                    }
                }

                $messageSucces = "Monument et images associées supprimés.";

            } else {
                $messageErreur = "Erreur lors de la suppression.";
            }

            mysqli_stmt_close($stmt);
        }
    }

    // PRG : REDIRECTION APRÈS POST
    if ($messageErreur !== "") {
        $_SESSION['erreur_monuments'] = $messageErreur;
    }

    if ($messageSucces !== "") {
        $_SESSION['succes_monuments'] = $messageSucces;
    }

    header("Location: monuments.php");
    exit;
}

// RÉCUPÉRER LE MONUMENT À MODIFIER
$edition = null;

if (isset($_GET['modifier'])) {

    $editId = filter_input(INPUT_GET, 'modifier', FILTER_VALIDATE_INT);

    if ($editId) {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT * FROM monument WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $editId);
        mysqli_stmt_execute($stmt);

        $resultat = mysqli_stmt_get_result($stmt);
        $edition = mysqli_fetch_assoc($resultat);

        mysqli_stmt_close($stmt);
    }
}

// CATÉGORIES
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, nom FROM categorie ORDER BY nom"
);
mysqli_stmt_execute($stmt);
$categories = mysqli_stmt_get_result($stmt);

// VILLES
$stmtVilles = mysqli_prepare(
    $conn,
    "SELECT v.id, v.nom, p.nom AS pays
     FROM ville v
     INNER JOIN pays p ON p.id = v.pays_id
     ORDER BY v.nom"
);
mysqli_stmt_execute($stmtVilles);
$villes = mysqli_stmt_get_result($stmtVilles);

// MONUMENTS ET LEUR DERNIÈRE IMAGE
$stmtMonuments = mysqli_prepare(
    $conn,
    "SELECT
        m.id,
        m.titre,
        m.description,
        c.nom AS categorie,
        v.nom AS ville,
        p.nom AS pays,
        (
            SELECT i.chemin
            FROM image i
            WHERE i.monument_id = m.id
            ORDER BY i.id DESC
            LIMIT 1
        ) AS image_principale
     FROM monument m
     INNER JOIN categorie c ON c.id = m.categorie_id
     INNER JOIN ville v ON v.id = m.ville_id
     INNER JOIN pays p ON p.id = v.pays_id
     ORDER BY m.id DESC"
);

mysqli_stmt_execute($stmtMonuments);
$monuments = mysqli_stmt_get_result($stmtMonuments);

$totalMonuments = mysqli_num_rows($monuments);

// Affichage uniquement : liste des catégories pour le filtre + badge messages
$listeMonuments = mysqli_fetch_all($monuments, MYSQLI_ASSOC);
$filtresCat = array_values(array_unique(array_column($listeMonuments, 'categorie')));
sort($filtresCat, SORT_NATURAL | SORT_FLAG_CASE);

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
    .admin a:focus-visible, .admin button:focus-visible, .admin summary:focus-visible {
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
                        Gestion des monuments
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 mt-2">
                        <?= (int)$totalMonuments ?> monument<?= $totalMonuments > 1 ? 's' : '' ?> enregistré<?= $totalMonuments > 1 ? 's' : '' ?>.
                    </p>
                </div>
                <?php if ($edition): ?>
                    <a href="monuments.php"
                       class="inline-flex items-center px-4 py-2.5 rounded-md bg-cyan-800 hover:bg-cyan-900
                              text-white text-sm font-semibold transition-colors">
                        Ajouter un nouveau monument
                    </a>
                <?php else: ?>
                    <a href="#formulaire"
                       class="inline-flex items-center px-4 py-2.5 rounded-md bg-cyan-800 hover:bg-cyan-900
                              text-white text-sm font-semibold transition-colors">
                        Ajouter un monument
                    </a>
                <?php endif; ?>
            </header>

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

            <!-- FORMULAIRE -->
            <section id="formulaire"
                     class="scroll-mt-6 rounded-lg bg-white dark:bg-slate-900
                            border border-stone-200 dark:border-slate-800 p-5 sm:p-6 mb-10">

                <div class="mb-6">
                    <h2 class="titre-serif text-xl text-slate-900 dark:text-white">
                        <?= $edition ? "Modifier le monument" : "Ajouter un monument" ?>
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Renseignez les informations du monument et sa photo.
                    </p>
                </div>

                <form method="POST" enctype="multipart/form-data"
                      class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

                    <?php if ($edition): ?>
                        <input type="hidden" name="id" value="<?= (int)$edition['id'] ?>">
                    <?php endif; ?>

                    <div class="md:col-span-2">
                        <label for="titre" class="<?= $etiquette ?>">Titre du monument</label>
                        <input type="text" id="titre" name="titre" required maxlength="255"
                               value="<?= h($edition['titre'] ?? '') ?>"
                               placeholder="Nom du monument"
                               class="<?= $champ ?>">
                    </div>

                    <div class="md:col-span-2">
                        <div class="flex items-baseline justify-between">
                            <label for="description" class="<?= $etiquette ?>">Description</label>
                            <span id="compteur" class="text-xs text-slate-400 tabular-nums"></span>
                        </div>
                        <textarea id="description" name="description" rows="5" required
                                  placeholder="Description du monument..."
                                  class="<?= $champ ?>"><?= h($edition['description'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label for="categorie_id" class="<?= $etiquette ?>">Catégorie</label>
                        <select id="categorie_id" name="categorie_id" required class="<?= $champ ?>">
                            <option value="">Sélectionner une catégorie</option>
                            <?php while ($c = mysqli_fetch_assoc($categories)): ?>
                                <option value="<?= (int)$c['id'] ?>"
                                    <?= isset($edition['categorie_id']) && $edition['categorie_id'] == $c['id'] ? 'selected' : '' ?>>
                                    <?= h($c['nom']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label for="ville_id" class="<?= $etiquette ?>">Ville</label>
                        <select id="ville_id" name="ville_id" required class="<?= $champ ?>">
                            <option value="">Sélectionner une ville</option>
                            <?php while ($v = mysqli_fetch_assoc($villes)): ?>
                                <option value="<?= (int)$v['id'] ?>"
                                    <?= isset($edition['ville_id']) && $edition['ville_id'] == $v['id'] ? 'selected' : '' ?>>
                                    <?= h($v['nom']) ?> (<?= h($v['pays']) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- PHOTO -->
                    <div class="md:col-span-2">
                        <label for="image" class="<?= $etiquette ?>">Photo du monument</label>

                        <div class="rounded-md border border-dashed border-stone-300 dark:border-slate-700
                                    bg-stone-50 dark:bg-slate-950/50 p-4">

                            <?php
                            $photoActuelle = null;
                            if ($edition) {
                                $stmtPhoto = mysqli_prepare(
                                    $conn,
                                    "SELECT chemin FROM image
                                     WHERE monument_id = ?
                                     ORDER BY id DESC LIMIT 1"
                                );

                                mysqli_stmt_bind_param(
                                    $stmtPhoto,
                                    "i",
                                    $edition['id']
                                );

                                mysqli_stmt_execute($stmtPhoto);
                                $resultatPhoto = mysqli_stmt_get_result($stmtPhoto);
                                $photoActuelle = mysqli_fetch_assoc($resultatPhoto);
                                mysqli_stmt_close($stmtPhoto);
                            }
                            ?>

                            <div class="flex flex-wrap gap-6 mb-4">
                                <?php if ($photoActuelle): ?>
                                    <div>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">Photo actuelle</p>
                                        <img src="../<?= h($photoActuelle['chemin']) ?>" alt="Photo actuelle"
                                             class="w-40 h-28 object-cover rounded-md">
                                    </div>
                                <?php endif; ?>

                                <div id="apercu-bloc" class="hidden">
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">Nouvelle photo</p>
                                    <img id="apercu" alt="Aperçu de la nouvelle photo"
                                         class="w-40 h-28 object-cover rounded-md">
                                </div>
                            </div>

                            <input type="file" id="image" name="image"
                                   accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                   class="block w-full text-sm text-slate-600 dark:text-slate-300
                                          file:mr-4 file:py-2 file:px-4 file:border-0 file:rounded-md
                                          file:bg-white dark:file:bg-slate-800 file:text-slate-700 dark:file:text-white
                                          file:font-medium file:cursor-pointer">

                            <p id="aide-photo" class="text-xs text-slate-500 dark:text-slate-400 mt-3">
                                JPG, JPEG ou PNG — 2 Mo maximum. Facultatif.
                            </p>
                        </div>
                    </div>

                    <!-- BOUTONS -->
                    <div class="md:col-span-2 flex justify-end gap-3">
                        <?php if ($edition): ?>
                            <a href="monuments.php"
                               class="px-5 py-2.5 rounded-md border border-stone-300 dark:border-slate-700
                                      text-slate-700 dark:text-slate-300 hover:bg-stone-100 dark:hover:bg-slate-800
                                      transition-colors">
                                Annuler
                            </a>
                        <?php endif; ?>

                        <button type="submit" name="<?= $edition ? 'modifier' : 'ajouter' ?>"
                                class="px-5 py-2.5 rounded-md bg-cyan-800 hover:bg-cyan-900 text-white font-semibold transition-colors">
                            <?= $edition ? "Enregistrer les modifications" : "Ajouter le monument" ?>
                        </button>
                    </div>
                </form>
            </section>

            <!-- LISTE DES MONUMENTS -->
            <section aria-labelledby="titre-liste">

                <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 mb-6">
                    <div>
                        <h2 id="titre-liste" class="titre-serif text-xl text-slate-900 dark:text-white">
                            Liste des monuments
                        </h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1" aria-live="polite">
                            <span id="nb-resultats"><?= (int)$totalMonuments ?></span>
                            résultat<?= $totalMonuments > 1 ? 's' : '' ?>
                        </p>
                    </div>

                    <?php if ($totalMonuments > 0): ?>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <label class="sr-only" for="recherche">Rechercher un monument</label>
                            <input type="search" id="recherche" placeholder="Rechercher un titre, une ville…"
                                   class="<?= $champ ?> sm:w-72">

                            <label class="sr-only" for="filtre-cat">Filtrer par catégorie</label>
                            <select id="filtre-cat" class="<?= $champ ?> sm:w-52">
                                <option value="">Toutes les catégories</option>
                                <?php foreach ($filtresCat as $fc): ?>
                                    <option value="<?= h($fc) ?>"><?= h($fc) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($totalMonuments === 0): ?>

                    <div class="rounded-lg border border-dashed border-stone-300 dark:border-slate-700 p-12 text-center">
                        <h3 class="font-semibold text-slate-800 dark:text-white">Aucun monument disponible</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">
                            Les monuments ajoutés apparaîtront ici.
                        </p>
                    </div>

                <?php else: ?>

                    <div id="grille" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">

                        <?php foreach ($listeMonuments as $m): ?>

                            <article class="group flex flex-col rounded-lg overflow-hidden
                                            bg-white dark:bg-slate-900
                                            border border-stone-200 dark:border-slate-800"
                                     data-cat="<?= h($m['categorie']) ?>"
                                     data-texte="<?= h($m['titre'] . ' ' . $m['ville'] . ' ' . $m['pays'] . ' ' . $m['categorie']) ?>">

                                <div class="relative aspect-[3/2] bg-stone-100 dark:bg-slate-800 overflow-hidden">
                                    <?php if (!empty($m['image_principale'])): ?>
                                        <img src="../<?= h($m['image_principale']) ?>"
                                             alt="<?= h($m['titre']) ?>" loading="lazy"
                                             class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-[1.04]">
                                    <?php else: ?>
                                        <div class="w-full h-full flex flex-col items-center justify-center text-slate-400">
                                            <span class="text-4xl">▧</span>
                                            <span class="text-sm mt-2">Aucune image</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="flex flex-col flex-1 p-4">
                                    <p class="text-xs font-medium text-cyan-800 dark:text-cyan-400">
                                        <?= h($m['categorie']) ?>
                                    </p>

                                    <h3 class="titre-serif text-xl mt-1 text-slate-900 dark:text-white truncate">
                                        <?= h($m['titre']) ?>
                                    </h3>

                                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                                        <?= h($m['ville']) ?>, <?= h($m['pays']) ?>
                                    </p>

                                    <p class="text-sm text-slate-600 dark:text-slate-400 mt-3 coupe-2 flex-1">
                                        <?= h($m['description']) ?>
                                    </p>

                                    <div class="flex items-center gap-2 border-t border-stone-200 dark:border-slate-800 mt-4 pt-3">
                                        <a href="monuments.php?modifier=<?= (int)$m['id'] ?>#formulaire"
                                           class="flex-1 text-center text-sm font-medium text-cyan-800 dark:text-cyan-400
                                                  hover:bg-cyan-50 dark:hover:bg-cyan-950/40 rounded-md py-2 transition-colors">
                                            Modifier
                                        </a>

                                        <button type="button" data-supprimer="<?= (int)$m['id'] ?>"
                                                data-titre="<?= h($m['titre']) ?>"
                                                class="flex-1 text-sm font-medium text-red-700 dark:text-red-400
                                                       hover:bg-red-50 dark:hover:bg-red-950/30 rounded-md py-2 transition-colors">
                                            Supprimer
                                        </button>
                                    </div>
                                </div>
                            </article>

                        <?php endforeach; ?>
                    </div>

                    <p id="aucun" class="hidden rounded-lg border border-dashed border-stone-300 dark:border-slate-700
                                         p-10 text-center text-sm text-slate-500 dark:text-slate-400">
                        Aucun monument ne correspond à votre recherche.
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
    <form method="POST" class="p-6">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="id" id="dialogue-id" value="">

        <h2 class="titre-serif text-xl">Supprimer ce monument ?</h2>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-3">
            « <span id="dialogue-titre" class="font-medium"></span> » et toutes ses images
            seront supprimés définitivement.
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

        // Aperçu de la nouvelle photo + contrôle de taille
        var fichier = document.getElementById('image');
        var apercu = document.getElementById('apercu');
        var bloc = document.getElementById('apercu-bloc');
        var aide = document.getElementById('aide-photo');
        var aideInitiale = aide.textContent;
        var url = null;

        fichier.addEventListener('change', function () {
            if (url) { URL.revokeObjectURL(url); url = null; }
            var f = fichier.files[0];
            aide.classList.remove('text-red-600');

            if (!f) { bloc.classList.add('hidden'); aide.textContent = aideInitiale; return; }

            if (f.size > 2 * 1024 * 1024) {
                fichier.value = '';
                bloc.classList.add('hidden');
                aide.textContent = 'Cette photo dépasse 2 Mo. Choisissez un fichier plus léger.';
                aide.classList.add('text-red-600');
                return;
            }
            url = URL.createObjectURL(f);
            apercu.src = url;
            bloc.classList.remove('hidden');
            aide.textContent = f.name;
        });

        // Recherche + filtre par catégorie (côté client)
        var champ = document.getElementById('recherche');
        var filtre = document.getElementById('filtre-cat');
        var cartes = document.querySelectorAll('#grille [data-cat]');
        var nb = document.getElementById('nb-resultats');
        var aucun = document.getElementById('aucun');

        function norm(t) {
            return t.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        function filtrer() {
            var q = norm(champ.value.trim());
            var cat = filtre.value;
            var n = 0;
            cartes.forEach(function (c) {
                var ok = (!cat || c.getAttribute('data-cat') === cat) &&
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

        // En mode modification : placer le curseur dans le titre
        <?php if ($edition): ?>
        var t = document.getElementById('titre');
        t.focus();
        t.setSelectionRange(t.value.length, t.value.length);
        <?php endif; ?>
    })();
</script>

<?php
mysqli_stmt_close($stmtMonuments);
mysqli_stmt_close($stmtVilles);
mysqli_stmt_close($stmt);
?>
</body>
</html>