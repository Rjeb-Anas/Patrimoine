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

$pageTitle = "Gestion des messages";

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/* =========================================================
   2. PROTECTION CSRF
========================================================= */

if (empty($_SESSION['csrf_messages'])) {
    $_SESSION['csrf_messages'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_messages'];

/* =========================================================
   3. MESSAGES FLASH
========================================================= */

$messageErreur = $_SESSION['erreur_messages'] ?? "";
$messageSucces = $_SESSION['succes_messages'] ?? "";

unset(
    $_SESSION['erreur_messages'],
    $_SESSION['succes_messages']
);

/* =========================================================
   4. TRAITEMENT DES FORMULAIRES
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($csrf, $_POST['csrf_token'])
    ) {
        $_SESSION['erreur_messages'] = "Requête invalide. Actualisez la page.";
        header("Location: messages.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    $id = filter_input(
        INPUT_POST,
        'id',
        FILTER_VALIDATE_INT
    );

    if (!$id || $id <= 0) {

        $_SESSION['erreur_messages'] = "Identifiant du message invalide.";

    } else {

        /* -------------------------------------------------
           MARQUER COMME LU
        ------------------------------------------------- */

        if ($action === 'marquer_lu') {

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE message SET lu = 1 WHERE id = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['succes_messages'] = "Message marqué comme lu.";
            } else {
                $_SESSION['erreur_messages'] = "Erreur lors de la modification du message.";
            }

            mysqli_stmt_close($stmt);

        /* -------------------------------------------------
           MARQUER COMME NON LU
        ------------------------------------------------- */

        } elseif ($action === 'marquer_non_lu') {

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE message SET lu = 0 WHERE id = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['succes_messages'] = "Message marqué comme non lu.";
            } else {
                $_SESSION['erreur_messages'] = "Erreur lors de la modification du message.";
            }

            mysqli_stmt_close($stmt);

        /* -------------------------------------------------
           SUPPRIMER UN MESSAGE
        ------------------------------------------------- */

        } elseif ($action === 'supprimer') {

            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM message WHERE id = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id);

            if (mysqli_stmt_execute($stmt)) {

                if (mysqli_stmt_affected_rows($stmt) > 0) {
                    $_SESSION['succes_messages'] = "Message supprimé avec succès.";
                } else {
                    $_SESSION['erreur_messages'] = "Message introuvable.";
                }

            } else {
                $_SESSION['erreur_messages'] = "Erreur lors de la suppression du message.";
            }

            mysqli_stmt_close($stmt);

        } else {

            $_SESSION['erreur_messages'] = "Action inconnue.";
        }
    }

    header("Location: messages.php");
    exit;
}

/* =========================================================
   5. FILTRES ET RECHERCHE
========================================================= */

$filtre = $_GET['filtre'] ?? 'tous';
$recherche = trim($_GET['recherche'] ?? '');

$conditions = [];
$parametres = [];
$types = '';

if ($filtre === 'non_lus') {
    $conditions[] = "lu = 0";
} elseif ($filtre === 'lus') {
    $conditions[] = "lu = 1";
}

if ($recherche !== '') {

    $conditions[] = "(nom LIKE ? OR email LIKE ? OR sujet LIKE ?)";

    $motCle = "%" . $recherche . "%";

    $parametres[] = $motCle;
    $parametres[] = $motCle;
    $parametres[] = $motCle;

    $types .= "sss";
}

$sqlMessages = "
    SELECT id, nom, email, sujet, contenu, lu, date_envoi
    FROM message
";

if (!empty($conditions)) {
    $sqlMessages .= " WHERE " . implode(" AND ", $conditions);
}

$sqlMessages .= " ORDER BY date_envoi DESC, id DESC";

$stmt = mysqli_prepare($conn, $sqlMessages);

if (!empty($parametres)) {
    mysqli_stmt_bind_param($stmt, $types, ...$parametres);
}

mysqli_stmt_execute($stmt);
$resultatMessages = mysqli_stmt_get_result($stmt);

$messages = [];

while ($ligne = mysqli_fetch_assoc($resultatMessages)) {
    $messages[] = $ligne;
}

mysqli_stmt_close($stmt);

/* =========================================================
   6. STATISTIQUES
========================================================= */

$resultatStats = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN lu = 0 THEN 1 ELSE 0 END) AS non_lus,
        SUM(CASE WHEN lu = 1 THEN 1 ELSE 0 END) AS lus
     FROM message"
);

$stats = mysqli_fetch_assoc($resultatStats);

$totalMessages = (int)($stats['total'] ?? 0);
$totalNonLus = (int)($stats['non_lus'] ?? 0);
$totalLus = (int)($stats['lus'] ?? 0);

mysqli_free_result($resultatStats);

/* =========================================================
   7. MESSAGE À CONSULTER
========================================================= */

$messageDetail = null;

$voir = filter_input(
    INPUT_GET,
    'voir',
    FILTER_VALIDATE_INT
);

if ($voir && $voir > 0) {

    $stmtDetail = mysqli_prepare(
        $conn,
        "SELECT id, nom, email, sujet, contenu, lu, date_envoi
         FROM message
         WHERE id = ?"
    );

    mysqli_stmt_bind_param($stmtDetail, "i", $voir);
    mysqli_stmt_execute($stmtDetail);

    $resultatDetail = mysqli_stmt_get_result($stmtDetail);
    $messageDetail = mysqli_fetch_assoc($resultatDetail);

    mysqli_stmt_close($stmtDetail);

    // Marquer automatiquement le message comme lu à sa consultation

    if ($messageDetail && (int)$messageDetail['lu'] === 0) {

        $stmtLu = mysqli_prepare(
            $conn,
            "UPDATE message SET lu = 1 WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmtLu, "i", $voir);
        mysqli_stmt_execute($stmtLu);

        mysqli_stmt_close($stmtLu);

        $messageDetail['lu'] = 1;
        $totalNonLus = max(0, $totalNonLus - 1);
        $totalLus++;

        // Affichage : la liste (déjà chargée) doit refléter le nouveau statut
        foreach ($messages as &$msgListe) {
            if ((int)$msgListe['id'] === (int)$voir) {
                $msgListe['lu'] = 1;
            }
        }
        unset($msgListe);
    }
}

/* =========================================================
   8. NAVIGATION ADMINISTRATION
========================================================= */

$messagesNonLus = $totalNonLus;

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

// Affichage : liens qui conservent le filtre et la recherche en cours
function lienMessages(array $extra, string $filtre, string $recherche): string {
    $p = [];
    if ($filtre !== 'tous') { $p['filtre'] = $filtre; }
    if ($recherche !== '')  { $p['recherche'] = $recherche; }
    $p = array_merge($p, $extra);
    return 'messages.php' . ($p ? '?' . http_build_query($p) : '');
}

$onglets = [
    ['tous',    'Tous',    $totalMessages],
    ['non_lus', 'Non lus', $totalNonLus],
    ['lus',     'Lus',     $totalLus],
];

$champ = "w-full rounded-md border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-950 "
       . "text-slate-900 dark:text-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-cyan-700 "
       . "focus:border-cyan-700 placeholder:text-slate-400";

$boutonSecondaire = "px-4 py-2 rounded-md border border-stone-300 dark:border-slate-700 text-sm font-medium "
                  . "text-slate-700 dark:text-slate-200 hover:bg-stone-100 dark:hover:bg-slate-800 transition-colors";

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
                                     <?= $actif
                                         ? 'bg-white text-cyan-900'
                                         : 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300' ?>">
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

            <header class="mb-8">
                <h1 class="titre-serif text-3xl sm:text-4xl text-slate-900 dark:text-white">
                    Gestion des messages
                </h1>
                <p class="text-slate-500 dark:text-slate-400 mt-2">
                    Les messages envoyés par les visiteurs depuis le formulaire de contact.
                </p>
            </header>

            <!-- CHIFFRES -->
            <dl class="grid grid-cols-3 gap-4 mb-8">
                <?php foreach ([
                    ['Messages', $totalMessages, 'text-slate-900 dark:text-white'],
                    ['Non lus', $totalNonLus, 'text-orange-700 dark:text-orange-400'],
                    ['Lus', $totalLus, 'text-slate-900 dark:text-white'],
                ] as [$lib, $val, $couleur]): ?>
                    <div class="rounded-lg bg-white dark:bg-slate-900 border border-stone-200 dark:border-slate-800 p-4 sm:p-5">
                        <dd class="titre-serif text-3xl sm:text-4xl tabular-nums <?= $couleur ?>">
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

            <!-- DÉTAIL DU MESSAGE -->
            <?php if ($voir !== false && $voir !== null): ?>

                <section id="detail" aria-labelledby="titre-detail"
                         class="scroll-mt-6 rounded-lg bg-white dark:bg-slate-900
                                border border-stone-200 dark:border-slate-800 p-5 sm:p-7 mb-8">

                    <?php if (!$messageDetail): ?>

                        <div class="text-center py-8">
                            <p class="text-slate-500 dark:text-slate-400 mb-4">
                                Ce message n'existe pas ou a été supprimé.
                            </p>
                            <a href="<?= h(lienMessages([], $filtre, $recherche)) ?>"
                               class="inline-flex items-center px-4 py-2 rounded-md bg-cyan-800 text-white
                                      hover:bg-cyan-900 transition-colors">
                                Retour aux messages
                            </a>
                        </div>

                    <?php else: ?>

                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
                            <div class="min-w-0">
                                <h2 id="titre-detail" class="titre-serif text-2xl sm:text-3xl text-slate-900 dark:text-white break-words">
                                    <?= h($messageDetail['sujet']) ?>
                                </h2>
                                <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">
                                    De <span class="font-medium text-slate-800 dark:text-slate-200"><?= h($messageDetail['nom']) ?></span>
                                    · <?= h(date('d/m/Y à H:i', strtotime($messageDetail['date_envoi']))) ?>
                                    ·
                                    <?php if ((int)$messageDetail['lu'] === 1): ?>
                                        <span class="font-medium text-green-700 dark:text-green-400">Lu</span>
                                    <?php else: ?>
                                        <span class="font-medium text-orange-700 dark:text-orange-400">Non lu</span>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <a href="<?= h(lienMessages([], $filtre, $recherche)) ?>"
                               class="shrink-0 inline-flex items-center justify-center <?= $boutonSecondaire ?>">
                                Fermer
                            </a>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 rounded-md bg-stone-50 dark:bg-slate-950
                                    border border-stone-200 dark:border-slate-800 px-4 py-3 mb-6">
                            <span class="text-sm text-slate-500 dark:text-slate-400">E-mail</span>
                            <a href="mailto:<?= h($messageDetail['email']) ?>"
                               id="email-detail"
                               class="font-medium text-cyan-800 dark:text-cyan-400 hover:underline break-all">
                                <?= h($messageDetail['email']) ?>
                            </a>
                            <button type="button" id="copier-email"
                                    class="ml-auto text-sm font-medium text-slate-600 dark:text-slate-300
                                           hover:text-cyan-800 dark:hover:text-cyan-400">
                                Copier
                            </button>
                        </div>

                        <div class="rounded-md border border-stone-200 dark:border-slate-800
                                    bg-white dark:bg-slate-950 p-5 mb-7">
                            <p class="text-sm leading-7 text-slate-700 dark:text-slate-300
                                      whitespace-pre-wrap break-words"><?= h($messageDetail['contenu']) ?></p>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

                            <a href="mailto:<?= h($messageDetail['email']) ?>?subject=<?= rawurlencode('Re: ' . $messageDetail['sujet']) ?>"
                               class="inline-flex items-center justify-center px-5 py-2.5 rounded-md bg-cyan-800
                                      text-white text-sm font-semibold hover:bg-cyan-900 transition-colors">
                                Répondre par e-mail
                            </a>

                            <div class="flex flex-col sm:flex-row gap-2">

                                <form method="POST" action="messages.php">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$messageDetail['id'] ?>">
                                    <input type="hidden" name="action"
                                           value="<?= (int)$messageDetail['lu'] === 1 ? 'marquer_non_lu' : 'marquer_lu' ?>">
                                    <button type="submit" class="w-full <?= $boutonSecondaire ?>">
                                        <?= (int)$messageDetail['lu'] === 1 ? 'Marquer comme non lu' : 'Marquer comme lu' ?>
                                    </button>
                                </form>

                                <button type="button" data-supprimer="<?= (int)$messageDetail['id'] ?>"
                                        data-titre="<?= h($messageDetail['sujet']) ?>"
                                        class="px-4 py-2 rounded-md text-sm font-medium text-red-700 dark:text-red-400
                                               hover:bg-red-50 dark:hover:bg-red-950/30 transition-colors">
                                    Supprimer
                                </button>
                            </div>
                        </div>

                    <?php endif; ?>
                </section>

            <?php endif; ?>

            <!-- FILTRES ET RECHERCHE -->
            <section aria-label="Filtres" class="mb-6">

                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">

                    <div class="flex gap-1 p-1 rounded-md bg-stone-200/60 dark:bg-slate-900 self-start" role="group" aria-label="Filtrer par statut">
                        <?php foreach ($onglets as [$cle, $libelleOnglet, $nbOnglet]): ?>
                            <?php $ongletActif = ($filtre === $cle) || ($cle === 'tous' && !in_array($filtre, ['non_lus', 'lus'], true)); ?>
                            <a href="<?= h(lienMessages(['filtre' => $cle] + ($cle === 'tous' ? [] : []), 'tous', $recherche)) ?>"
                               <?= $ongletActif ? 'aria-current="true"' : '' ?>
                               class="px-4 py-1.5 rounded text-sm font-medium transition-colors
                                      <?= $ongletActif
                                          ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm'
                                          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?>">
                                <?= h($libelleOnglet) ?>
                                <span class="ml-1 text-xs tabular-nums text-slate-400"><?= (int)$nbOnglet ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <form method="GET" action="messages.php" role="search" class="flex gap-2 lg:w-[28rem]">
                        <input type="hidden" name="filtre" value="<?= h($filtre) ?>">
                        <label class="sr-only" for="recherche">Rechercher un message</label>
                        <input type="search" id="recherche" name="recherche"
                               value="<?= h($recherche) ?>"
                               placeholder="Nom, e-mail ou sujet…"
                               class="<?= $champ ?>">
                        <button type="submit"
                                class="px-5 rounded-md bg-cyan-800 hover:bg-cyan-900 text-white text-sm font-semibold transition-colors">
                            Rechercher
                        </button>
                    </form>
                </div>

                <?php if ($recherche !== '' || in_array($filtre, ['non_lus', 'lus'], true)): ?>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-3">
                        Filtres actifs.
                        <a href="messages.php" class="font-medium text-cyan-800 dark:text-cyan-400 hover:underline">
                            Réinitialiser
                        </a>
                    </p>
                <?php endif; ?>
            </section>

            <!-- LISTE DES MESSAGES -->
            <section aria-labelledby="titre-liste">

                <div class="mb-4">
                    <h2 id="titre-liste" class="titre-serif text-xl text-slate-900 dark:text-white">
                        Boîte de réception
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        <?= count($messages) ?> message<?= count($messages) > 1 ? 's' : '' ?> trouvé<?= count($messages) > 1 ? 's' : '' ?>
                    </p>
                </div>

                <?php if (empty($messages)): ?>

                    <div class="rounded-lg border border-dashed border-stone-300 dark:border-slate-700
                                bg-white dark:bg-slate-900 p-12 text-center">
                        <h3 class="titre-serif text-xl text-slate-900 dark:text-white">Aucun message</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">
                            Aucun message ne correspond à votre recherche.
                        </p>
                        <?php if ($filtre !== 'tous' || $recherche !== ''): ?>
                            <a href="messages.php"
                               class="inline-flex mt-5 px-4 py-2 rounded-md bg-cyan-800 text-white text-sm
                                      hover:bg-cyan-900 transition-colors">
                                Afficher tous les messages
                            </a>
                        <?php endif; ?>
                    </div>

                <?php else: ?>

                    <ul class="rounded-lg bg-white dark:bg-slate-900 border border-stone-200 dark:border-slate-800
                               divide-y divide-stone-200 dark:divide-slate-800 overflow-hidden">

                        <?php foreach ($messages as $message): ?>
                            <?php
                            $nonLu = ((int)$message['lu'] === 0);
                            $courant = ($messageDetail && (int)$messageDetail['id'] === (int)$message['id']);
                            ?>
                            <li class="<?= $courant ? 'bg-cyan-50/60 dark:bg-cyan-950/20' : '' ?>">
                                <div class="flex flex-col lg:flex-row lg:items-center gap-3 lg:gap-6 px-5 py-4">

                                    <a href="<?= h(lienMessages(['voir' => (int)$message['id']], $filtre, $recherche)) ?>#detail"
                                       class="group flex gap-4 min-w-0 flex-1">

                                        <span class="mt-2 shrink-0 w-2 h-2 rounded-full
                                                     <?= $nonLu ? 'bg-orange-500' : 'bg-transparent' ?>"
                                              <?= $nonLu ? 'title="Non lu"' : '' ?>></span>

                                        <span class="min-w-0">
                                            <span class="flex flex-wrap items-baseline gap-x-3">
                                                <span class="<?= $nonLu ? 'font-semibold text-slate-900 dark:text-white' : 'font-medium text-slate-700 dark:text-slate-300' ?>">
                                                    <?= h($message['nom']) ?>
                                                </span>
                                                <span class="text-xs text-slate-400 dark:text-slate-500">
                                                    <?= h(date('d/m/Y à H:i', strtotime($message['date_envoi']))) ?>
                                                </span>
                                            </span>
                                            <span class="block text-sm mt-0.5 group-hover:underline
                                                         <?= $nonLu ? 'font-semibold text-slate-800 dark:text-slate-100' : 'text-slate-700 dark:text-slate-300' ?>">
                                                <?= h($message['sujet']) ?>
                                            </span>
                                            <span class="block text-sm text-slate-500 dark:text-slate-400 mt-0.5 coupe-2">
                                                <?= h($message['contenu']) ?>
                                            </span>
                                            <span class="block text-xs text-cyan-800 dark:text-cyan-400 mt-1 break-all">
                                                <?= h($message['email']) ?>
                                            </span>
                                        </span>
                                    </a>

                                    <div class="flex items-center gap-1 shrink-0 lg:justify-end">

                                        <form method="POST" action="messages.php">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
                                            <input type="hidden" name="action" value="<?= $nonLu ? 'marquer_lu' : 'marquer_non_lu' ?>">
                                            <button type="submit"
                                                    class="px-3 py-2 rounded-md text-sm font-medium text-slate-600 dark:text-slate-300
                                                           hover:bg-stone-100 dark:hover:bg-slate-800 transition-colors">
                                                <?= $nonLu ? 'Marquer lu' : 'Marquer non lu' ?>
                                            </button>
                                        </form>

                                        <button type="button" data-supprimer="<?= (int)$message['id'] ?>"
                                                data-titre="<?= h($message['sujet']) ?>"
                                                class="px-3 py-2 rounded-md text-sm font-medium text-red-700 dark:text-red-400
                                                       hover:bg-red-50 dark:hover:bg-red-950/30 transition-colors">
                                            Supprimer
                                        </button>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<!-- Confirmation de suppression -->
<dialog id="dialogue"
        class="rounded-lg p-0 w-[calc(100%-2rem)] max-w-md bg-white dark:bg-slate-900
               text-slate-900 dark:text-white border border-stone-200 dark:border-slate-800 shadow-xl">
    <form method="POST" action="messages.php" class="p-6">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="supprimer">
        <input type="hidden" name="id" id="dialogue-id" value="">

        <h2 class="titre-serif text-xl">Supprimer ce message ?</h2>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-3">
            Le message « <span id="dialogue-titre" class="font-medium break-words"></span> »
            sera supprimé définitivement.
        </p>

        <div class="flex justify-end gap-3 mt-6">
            <button type="button" id="dialogue-annuler" class="<?= $boutonSecondaire ?>">Annuler</button>
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

        // Confirmation de suppression (liste et détail)
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

        // Copier l'adresse e-mail
        var copier = document.getElementById('copier-email');
        if (copier) {
            copier.addEventListener('click', function () {
                var adresse = document.getElementById('email-detail').textContent.trim();
                var ok = function () {
                    copier.textContent = 'Copié';
                    setTimeout(function () { copier.textContent = 'Copier'; }, 1800);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(adresse).then(ok, function () {});
                }
            });
        }
    })();
</script>
</body>
</html>
