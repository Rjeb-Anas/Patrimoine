
<?php
require_once __DIR__ . '/includes/config.php';

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// =====================================
// 1. RECUPERATION DES CATEGORIES
// =====================================

$categories = [];

$resultCategories = mysqli_query(
    $conn,
    "SELECT id, nom FROM categorie ORDER BY nom ASC"
);

if ($resultCategories) {
    while ($row = mysqli_fetch_assoc($resultCategories)) {
        $categories[] = $row;
    }
}

// =====================================
// 2. RECUPERATION DES PAYS
// =====================================

$paysListe = [];

$resultPays = mysqli_query(
    $conn,
    "SELECT id, nom FROM pays ORDER BY nom ASC"
);

if ($resultPays) {
    while ($row = mysqli_fetch_assoc($resultPays)) {
        $paysListe[] = $row;
    }
}

// =====================================
// 3. FILTRES
// =====================================

$recherche = trim($_GET['recherche'] ?? '');

if (mb_strlen($recherche) > 100) {
    $recherche = mb_substr($recherche, 0, 100);
}

$categorieId = filter_input(INPUT_GET, 'categorie', FILTER_VALIDATE_INT) ?: 0;
$paysId = filter_input(INPUT_GET, 'pays', FILTER_VALIDATE_INT) ?: 0;

// =====================================
// 4. REQUETE DES MONUMENTS
// =====================================

$sql = "
    SELECT
        m.id,
        m.titre,
        m.description,
        c.nom AS categorie,
        c.id AS categorie_id,
        v.nom AS ville,
        p.nom AS pays,
        p.id AS pays_id,
        (
            SELECT i.chemin
            FROM image i
            WHERE i.monument_id = m.id
            ORDER BY i.id ASC
            LIMIT 1
        ) AS image_path
    FROM monument m
    INNER JOIN categorie c ON c.id = m.categorie_id
    INNER JOIN ville v ON v.id = m.ville_id
    INNER JOIN pays p ON p.id = v.pays_id
    WHERE 1 = 1
";

$types = '';
$params = [];

// Filtre catégorie
if ($categorieId > 0) {
    $sql .= " AND c.id = ?";
    $types .= 'i';
    $params[] = $categorieId;
}

// Filtre pays
if ($paysId > 0) {
    $sql .= " AND p.id = ?";
    $types .= 'i';
    $params[] = $paysId;
}

// Recherche
if ($recherche !== '') {
    $sql .= "
        AND (
            m.titre LIKE ?
            OR m.description LIKE ?
            OR v.nom LIKE ?
            OR p.nom LIKE ?
            OR c.nom LIKE ?
        )
    ";

    $motCle = "%{$recherche}%";

    $types .= 'sssss';

    $params[] = $motCle;
    $params[] = $motCle;
    $params[] = $motCle;
    $params[] = $motCle;
    $params[] = $motCle;
}

$sql .= " ORDER BY m.id DESC";

$stmt = mysqli_prepare($conn, $sql);
$monuments = [];

if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $monuments[] = $row;
    }

    mysqli_stmt_close($stmt);
}

$totalMonuments = count($monuments);

$pageTitle = "Catalogue | Patrimoine";

include __DIR__ . '/includes/header.php';
?>

<main class="min-h-screen bg-slate-50 dark:bg-slate-950 transition-colors duration-300">

    <!-- INTRODUCTION -->

    <section class="bg-blue-50 dark:bg-slate-900 border-b border-blue-100 dark:border-slate-800 transition-colors duration-300">

        <div class="max-w-6xl mx-auto px-6 py-16 md:py-20 text-center">

            <span class="inline-block text-sm font-semibold tracking-wider uppercase text-cyan-700 dark:text-cyan-400 mb-4">
                Notre patrimoine
            </span>

            <h1 class="text-4xl md:text-5xl font-bold text-slate-800 dark:text-white mb-5">
                Catalogue des monuments
            </h1>

            <p class="max-w-2xl mx-auto text-slate-600 dark:text-slate-300 leading-relaxed text-base md:text-lg">
                Partez à la découverte des monuments historiques du Maroc
                et d'Afrique. Explorez leur histoire, leur architecture
                et leur richesse culturelle.
            </p>

        </div>

    </section>

    <!-- CATALOGUE -->

    <section class="max-w-6xl mx-auto px-6 py-14 md:py-16">

        <!-- EN-TETE -->

        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-8">

            <div>

                <span class="text-cyan-700 dark:text-cyan-400 text-sm font-semibold uppercase tracking-wider">
                    Explorez notre collection
                </span>

                <h2 class="text-3xl font-bold text-slate-800 dark:text-white mt-2">
                    Tous les monuments
                </h2>

                <p class="text-slate-500 dark:text-slate-400 mt-2">
                    Retrouvez les monuments et sites historiques répertoriés.
                </p>

            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-5 py-3 transition-colors">

                <span class="text-2xl font-bold text-cyan-700 dark:text-cyan-400">
                    <?= $totalMonuments ?>
                </span>

                <span class="text-sm text-slate-500 dark:text-slate-400 ml-1">
                    monument(s)
                </span>

            </div>

        </div>

        <!-- RECHERCHE ET FILTRES -->

        <form method="GET" action="catalogue.php"
              class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-5 mb-9 transition-colors duration-300">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                <!-- RECHERCHE -->

                <div>

                    <label for="recherche"
                           class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2">
                        Rechercher
                    </label>

                    <input
                        type="search"
                        id="recherche"
                        name="recherche"
                        value="<?= e($recherche) ?>"
                        placeholder="Nom, ville, monument..."
                        class="w-full px-4 py-3 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100 dark:focus:ring-cyan-900 transition"
                    >

                </div>

                <!-- CATEGORIE -->

                <div>

                    <label for="categorie"
                           class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2">
                        Catégorie
                    </label>

                    <select
                        id="categorie"
                        name="categorie"
                        class="w-full px-4 py-3 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-white outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100 dark:focus:ring-cyan-900 transition"
                    >

                        <option value="0">Toutes les catégories</option>

                        <?php foreach ($categories as $cat): ?>

                            <option
                                value="<?= (int)$cat['id'] ?>"
                                <?= $categorieId === (int)$cat['id'] ? 'selected' : '' ?>
                            >
                                <?= e($cat['nom']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- PAYS -->

                <div>

                    <label for="pays"
                           class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2">
                        Pays
                    </label>

                    <select
                        id="pays"
                        name="pays"
                        class="w-full px-4 py-3 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-white outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100 dark:focus:ring-cyan-900 transition"
                    >

                        <option value="0">Tous les pays</option>

                        <?php foreach ($paysListe as $p): ?>

                            <option
                                value="<?= (int)$p['id'] ?>"
                                <?= $paysId === (int)$p['id'] ? 'selected' : '' ?>
                            >
                                <?= e($p['nom']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>

            <!-- BOUTONS -->

            <div class="flex flex-wrap gap-3 mt-5">

                <button
                    type="submit"
                    class="px-6 py-3 rounded-lg bg-cyan-600 hover:bg-cyan-700 text-white font-semibold transition-colors"
                >
                    Rechercher
                </button>

                <a
                    href="catalogue.php"
                    class="px-6 py-3 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 font-semibold transition-colors"
                >
                    Réinitialiser
                </a>

            </div>

        </form>

        <!-- LISTE DES MONUMENTS -->

        <?php if ($totalMonuments > 0): ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

                <?php foreach ($monuments as $monument): ?>

                    <article class="group bg-white dark:bg-slate-900 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md dark:hover:shadow-black/20 transition-all duration-300">

                        <!-- IMAGE -->

                        <div class="relative h-60 bg-slate-100 dark:bg-slate-800 overflow-hidden">

                            <?php if (!empty($monument['image_path'])): ?>

                                <img
                                    src="<?= e($monument['image_path']) ?>"
                                    alt="<?= e($monument['titre']) ?>"
                                    loading="lazy"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                >

                            <?php else: ?>

                                <div class="w-full h-full flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">

                                    <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <rect x="3" y="3" width="18" height="18" rx="2" stroke-width="1.5"/>
                                        <circle cx="8.5" cy="8.5" r="1.5" stroke-width="1.5"/>
                                        <path d="m21 15-5-5L5 21" stroke-width="1.5"/>
                                    </svg>

                                    <span class="text-sm">
                                        Image indisponible
                                    </span>

                                </div>

                            <?php endif; ?>

                            <span class="absolute top-4 left-4 px-3 py-1.5 rounded-md bg-white/95 dark:bg-slate-900/95 text-cyan-800 dark:text-cyan-400 text-xs font-semibold shadow-sm">
                                <?= e($monument['categorie']) ?>
                            </span>

                        </div>

                        <!-- INFORMATIONS -->

                        <div class="p-5">

                            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-cyan-700 dark:text-cyan-400 mb-3">

                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" stroke-width="1.8"/>
                                    <circle cx="12" cy="10" r="2.5" stroke-width="1.8"/>
                                </svg>

                                <?= e($monument['ville']) ?>, <?= e($monument['pays']) ?>

                            </div>

                            <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-3">
                                <?= e($monument['titre']) ?>
                            </h3>

                            <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed mb-5 line-clamp-3">
                                <?= e($monument['description']) ?>
                            </p>

                            <div class="pt-4 border-t border-slate-100 dark:border-slate-700">

                                <a
                                    href="fiche.php?id=<?= (int)$monument['id'] ?>"
                                    class="inline-flex items-center gap-2 text-sm font-semibold text-cyan-700 dark:text-cyan-400 hover:text-cyan-900 dark:hover:text-cyan-300 transition-colors"
                                >
                                    Découvrir le monument
                                    <span>→</span>
                                </a>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <!-- AUCUN RESULTAT -->

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-12 text-center transition-colors">

                <div class="w-16 h-16 mx-auto mb-5 rounded-full bg-blue-50 dark:bg-slate-800 flex items-center justify-center">

                    <svg class="w-8 h-8 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7" stroke-width="2"/>
                        <path d="m16 16 4 4" stroke-width="2" stroke-linecap="round"/>
                    </svg>

                </div>

                <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-2">
                    Aucun monument trouvé
                </h3>

                <p class="text-slate-500 dark:text-slate-400 mb-6">
                    Aucun monument ne correspond à vos critères de recherche.
                </p>

                <a
                    href="catalogue.php"
                    class="inline-block px-6 py-3 rounded-lg bg-cyan-600 text-white font-semibold hover:bg-cyan-700 transition-colors"
                >
                    Afficher tous les monuments
                </a>

            </div>

        <?php endif; ?>

    </section>

    <!-- BANDEAU FINAL -->

    <section class="bg-blue-50 dark:bg-slate-900 border-y border-blue-100 dark:border-slate-800 transition-colors duration-300">

        <div class="max-w-6xl mx-auto px-6 py-14 text-center">

            <h2 class="text-2xl md:text-3xl font-bold text-slate-800 dark:text-white mb-4">
                Découvrez le patrimoine autrement
            </h2>

            <p class="max-w-2xl mx-auto text-slate-600 dark:text-slate-300 leading-relaxed mb-7">
                Chaque monument représente une partie de notre histoire.
                Parcourez notre galerie pour découvrir ces lieux à travers
                leurs photographies.
            </p>

            <a
                href="galerie.php"
                class="inline-block px-7 py-3 rounded-lg bg-cyan-600 hover:bg-cyan-700 text-white font-semibold transition-colors"
            >
                Voir la galerie
            </a>

        </div>

    </section>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
