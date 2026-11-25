
<?php
require_once __DIR__ . '/includes/config.php';

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Récupération des catégories
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

// Filtres
$categorieId = filter_input(INPUT_GET, 'categorie', FILTER_VALIDATE_INT) ?: 0;
$recherche = trim($_GET['recherche'] ?? '');

if (mb_strlen($recherche) > 100) {
    $recherche = mb_substr($recherche, 0, 100);
}

// Requête des images
$sql = "
    SELECT
        i.id AS image_id,
        i.chemin,
        i.legende,
        m.id AS monument_id,
        m.titre,
        m.description,
        c.id AS categorie_id,
        c.nom AS categorie,
        v.nom AS ville,
        p.nom AS pays
    FROM image i
    INNER JOIN monument m ON m.id = i.monument_id
    INNER JOIN categorie c ON c.id = m.categorie_id
    INNER JOIN ville v ON v.id = m.ville_id
    INNER JOIN pays p ON p.id = v.pays_id
    WHERE 1 = 1
";

$types = '';
$params = [];

if ($categorieId > 0) {
    $sql .= " AND c.id = ?";
    $types .= 'i';
    $params[] = $categorieId;
}

if ($recherche !== '') {
    $sql .= " AND (
        m.titre LIKE ?
        OR v.nom LIKE ?
        OR p.nom LIKE ?
        OR c.nom LIKE ?
    )";

    $motCle = "%$recherche%";

    $types .= 'ssss';
    $params[] = $motCle;
    $params[] = $motCle;
    $params[] = $motCle;
    $params[] = $motCle;
}

$sql .= " ORDER BY i.id DESC";

$stmt = mysqli_prepare($conn, $sql);
$images = [];

if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $images[] = $row;
    }

    mysqli_stmt_close($stmt);
}

$totalImages = count($images);

$pageTitle = "Galerie | Patrimoine";

include __DIR__ . '/includes/header.php';
?>

<main class="bg-slate-50 dark:bg-slate-950 min-h-screen transition-colors duration-300">

    <!-- INTRODUCTION -->

    <section class="bg-blue-50 dark:bg-slate-900 border-b border-blue-100 dark:border-slate-800 transition-colors duration-300">

        <div class="max-w-6xl mx-auto px-6 py-16 md:py-20 text-center">

            <span class="inline-block text-sm font-semibold tracking-wider uppercase text-cyan-700 dark:text-cyan-400 mb-4">
                Collection photographique
            </span>

            <h1 class="text-4xl md:text-5xl font-bold text-slate-800 dark:text-white mb-5">
                Galerie du patrimoine
            </h1>

            <p class="max-w-2xl mx-auto text-slate-600 dark:text-slate-300 leading-relaxed text-base md:text-lg">
                Découvrez la richesse architecturale et culturelle du Maroc
                et de l'Afrique à travers une collection de photographies
                consacrées aux monuments historiques.
            </p>

        </div>

    </section>

    <!-- GALERIE -->

    <section id="galerie" class="max-w-6xl mx-auto px-6 py-14 md:py-16">

        <!-- EN-TETE -->

        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-8">

            <div>

                <span class="text-cyan-700 dark:text-cyan-400 text-sm font-semibold uppercase tracking-wider">
                    Explorez notre collection
                </span>

                <h2 class="text-3xl font-bold text-slate-800 dark:text-white mt-2">
                    Nos photographies
                </h2>

                <p class="text-slate-500 dark:text-slate-400 mt-2">
                    Des lieux historiques, des architectures et des histoires à découvrir.
                </p>

            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-5 py-3 transition-colors">

                <span class="text-2xl font-bold text-cyan-700 dark:text-cyan-400">
                    <?= $totalImages ?>
                </span>

                <span class="text-sm text-slate-500 dark:text-slate-400 ml-1">
                    photographie(s)
                </span>

            </div>

        </div>

        <!-- RECHERCHE ET FILTRE -->

        <form method="GET" action="galerie.php"
              class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-5 mb-9 transition-colors duration-300">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                <!-- RECHERCHE -->

                <div class="md:col-span-2">

                    <label for="recherche"
                           class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2">
                        Rechercher
                    </label>

                    <input
                        type="search"
                        id="recherche"
                        name="recherche"
                        value="<?= e($recherche) ?>"
                        placeholder="Nom du monument, ville ou pays..."
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
                        name="categorie"
                        id="categorie"
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
                    href="galerie.php"
                    class="px-6 py-3 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 font-semibold transition-colors"
                >
                    Réinitialiser
                </a>

            </div>

        </form>

        <!-- RESULTATS -->

        <?php if ($totalImages > 0): ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

                <?php foreach ($images as $image): ?>

                    <article class="group bg-white dark:bg-slate-900 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md dark:hover:shadow-black/20 transition-all duration-300">

                        <!-- IMAGE -->

                        <div class="relative h-64 overflow-hidden bg-slate-100 dark:bg-slate-800">

                            <img
                                src="<?= e($image['chemin']) ?>"
                                alt="<?= e($image['legende'] ?: $image['titre']) ?>"
                                loading="lazy"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 cursor-pointer"
                                onclick="ouvrirImage(this)"
                                onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');"
                            >

                            <div class="hidden absolute inset-0 flex items-center justify-center text-slate-400 dark:text-slate-500 text-sm">
                                Image indisponible
                            </div>

                            <span class="absolute top-4 left-4 px-3 py-1.5 rounded-md bg-white/95 dark:bg-slate-900/95 text-cyan-800 dark:text-cyan-400 text-xs font-semibold shadow-sm">
                                <?= e($image['categorie']) ?>
                            </span>

                            <button
                                type="button"
                                onclick="ouvrirImageDepuisCarte(this)"
                                class="absolute bottom-4 right-4 w-10 h-10 rounded-full bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 shadow-md hover:bg-cyan-600 hover:text-white transition-colors flex items-center justify-center"
                                aria-label="Agrandir l'image"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"
                                          stroke-width="2"
                                          stroke-linecap="round"
                                          stroke-linejoin="round"/>
                                </svg>
                            </button>

                        </div>

                        <!-- INFORMATIONS -->

                        <div class="p-5">

                            <p class="text-xs font-semibold uppercase tracking-wider text-cyan-700 dark:text-cyan-400 mb-2">
                                <?= e($image['pays']) ?> · <?= e($image['ville']) ?>
                            </p>

                            <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-3">
                                <?= e($image['titre']) ?>
                            </h3>

                            <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed mb-5 line-clamp-2">
                                <?= e($image['legende'] ?: $image['description']) ?>
                            </p>

                            <div class="pt-4 border-t border-slate-100 dark:border-slate-700">

                                <a
                                    href="fiche.php?id=<?= (int)$image['monument_id'] ?>"
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
                        <rect x="3" y="3" width="18" height="18" rx="2" stroke-width="1.8"/>
                        <circle cx="8.5" cy="8.5" r="1.5" stroke-width="1.8"/>
                        <path d="m21 15-5-5L5 21" stroke-width="1.8"/>
                    </svg>

                </div>

                <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-2">
                    Aucune photographie trouvée
                </h3>

                <p class="text-slate-500 dark:text-slate-400 mb-6">
                    Aucune image ne correspond à votre recherche.
                </p>

                <a
                    href="galerie.php"
                    class="inline-block px-6 py-3 rounded-lg bg-cyan-600 text-white font-semibold hover:bg-cyan-700 transition-colors"
                >
                    Voir toutes les photographies
                </a>

            </div>

        <?php endif; ?>

    </section>

    <!-- BANDEAU FINAL -->

    <section class="bg-blue-50 dark:bg-slate-900 border-y border-blue-100 dark:border-slate-800 transition-colors duration-300">

        <div class="max-w-6xl mx-auto px-6 py-14 text-center">

            <h2 class="text-2xl md:text-3xl font-bold text-slate-800 dark:text-white mb-4">
                Le patrimoine, une richesse à préserver
            </h2>

            <p class="max-w-2xl mx-auto text-slate-600 dark:text-slate-300 leading-relaxed mb-7">
                Chaque monument raconte une partie de notre histoire.
                Découvrez notre catalogue pour approfondir vos connaissances.
            </p>

            <a
                href="catalogue.php"
                class="inline-block px-7 py-3 rounded-lg bg-cyan-600 hover:bg-cyan-700 text-white font-semibold transition-colors"
            >
                Explorer le catalogue
            </a>

        </div>

    </section>

</main>

<!-- MODALE IMAGE -->

<div
    id="imageModal"
    class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/90 p-4"
    onclick="fermerImage(event)"
>

    <button
        type="button"
        onclick="fermerImage()"
        class="absolute top-5 right-6 text-white text-4xl hover:text-cyan-300 transition"
        aria-label="Fermer"
    >
        &times;
    </button>

    <img
        id="imageAgrandie"
        src=""
        alt="Photographie agrandie"
        class="max-w-full max-h-[85vh] object-contain rounded-lg"
    >

</div>

<script>
    function ouvrirImage(img) {
        const modal = document.getElementById('imageModal');
        const imageAgrandie = document.getElementById('imageAgrandie');

        imageAgrandie.src = img.src;
        imageAgrandie.alt = img.alt;

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        document.body.style.overflow = 'hidden';
    }

    function ouvrirImageDepuisCarte(button) {
        const img = button.closest('article').querySelector('img');

        if (img && img.style.display !== 'none') {
            ouvrirImage(img);
        }
    }

    function fermerImage(event) {
        if (event && event.target.id !== 'imageModal') {
            return;
        }

        const modal = document.getElementById('imageModal');
        const imageAgrandie = document.getElementById('imageAgrandie');

        modal.classList.add('hidden');
        modal.classList.remove('flex');

        imageAgrandie.src = '';
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            fermerImage();
        }
    });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
