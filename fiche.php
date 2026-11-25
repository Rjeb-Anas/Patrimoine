
<?php
require_once __DIR__ . '/includes/config.php';

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// =====================================
// 1. VERIFICATION DE L'IDENTIFIANT
// =====================================

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header('Location: catalogue.php');
    exit;
}

// =====================================
// 2. RECUPERATION DU MONUMENT
// =====================================

$sql = "
    SELECT
        m.id,
        m.titre,
        m.description,
        c.nom AS categorie,
        c.id AS categorie_id,
        v.nom AS ville,
        v.description AS description_ville,
        p.nom AS pays,
        p.code_iso

    FROM monument m

    INNER JOIN categorie c ON c.id = m.categorie_id
    INNER JOIN ville v ON v.id = m.ville_id
    INNER JOIN pays p ON p.id = v.pays_id

    WHERE m.id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
$monument = null;

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $monument = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
}

// Monument inexistant
if (!$monument) {
    http_response_code(404);
    $pageTitle = "Monument introuvable | Patrimoine";
    include __DIR__ . '/includes/header.php';
    ?>

    <main class="min-h-screen bg-slate-50 flex items-center justify-center px-6">
        <div class="max-w-lg w-full bg-white border border-slate-200 rounded-xl p-10 text-center">
            <div class="w-16 h-16 mx-auto mb-5 rounded-full bg-blue-50 flex items-center justify-center">
                <svg class="w-8 h-8 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9" stroke-width="1.8"/>
                    <path d="M12 8v5m0 3h.01" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>

            <h1 class="text-2xl font-bold text-slate-800 mb-3">
                Monument introuvable
            </h1>

            <p class="text-slate-500 mb-6">
                Ce monument n'existe pas ou a été supprimé.
            </p>

            <a href="catalogue.php"
               class="inline-block px-6 py-3 rounded-lg bg-cyan-600 text-white font-semibold hover:bg-cyan-700 transition">
                Retour au catalogue
            </a>
        </div>
    </main>

    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// =====================================
// 3. RECUPERATION DES IMAGES
// =====================================

$images = [];

$sqlImages = "
    SELECT id, chemin, legende
    FROM image
    WHERE monument_id = ?
    ORDER BY id ASC
";

$stmtImages = mysqli_prepare($conn, $sqlImages);

if ($stmtImages) {
    mysqli_stmt_bind_param($stmtImages, "i", $id);
    mysqli_stmt_execute($stmtImages);

    $resultImages = mysqli_stmt_get_result($stmtImages);

    while ($row = mysqli_fetch_assoc($resultImages)) {
        $images[] = $row;
    }

    mysqli_stmt_close($stmtImages);
}

// =====================================
// 4. AUTRES MONUMENTS
// =====================================

$autresMonuments = [];

$sqlAutres = "
    SELECT
        m.id,
        m.titre,
        c.nom AS categorie,
        v.nom AS ville,
        p.nom AS pays,
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

    WHERE m.id != ?

    ORDER BY m.id DESC
    LIMIT 3
";

$stmtAutres = mysqli_prepare($conn, $sqlAutres);

if ($stmtAutres) {
    mysqli_stmt_bind_param($stmtAutres, "i", $id);
    mysqli_stmt_execute($stmtAutres);

    $resultAutres = mysqli_stmt_get_result($stmtAutres);

    while ($row = mysqli_fetch_assoc($resultAutres)) {
        $autresMonuments[] = $row;
    }

    mysqli_stmt_close($stmtAutres);
}

$pageTitle = $monument['titre'] . " | Patrimoine";

include __DIR__ . '/includes/header.php';
?>

<main class="bg-slate-50 min-h-screen">

    <!-- ================================= -->
    <!-- FIL D'ARIANE -->
    <!-- ================================= -->

    <section class="bg-white border-b border-slate-200">

        <div class="max-w-6xl mx-auto px-6 py-4">

            <nav class="flex flex-wrap items-center gap-2 text-sm text-slate-500">

                <a href="index.php" class="hover:text-cyan-700 transition">
                    Accueil
                </a>

                <span>/</span>

                <a href="catalogue.php" class="hover:text-cyan-700 transition">
                    Catalogue
                </a>

                <span>/</span>

                <span class="text-cyan-700 font-semibold">
                    <?= e($monument['titre']) ?>
                </span>

            </nav>

        </div>

    </section>

    <!-- ================================= -->
    <!-- PRESENTATION -->
    <!-- ================================= -->

    <section class="max-w-6xl mx-auto px-6 py-12 md:py-16">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">

            <!-- IMAGE PRINCIPALE -->

            <div class="relative">

                <div class="h-72 md:h-[440px] rounded-xl overflow-hidden bg-slate-200 border border-slate-200">

                    <?php if (!empty($images)): ?>

                        <img
                            id="imagePrincipale"
                            src="<?= e($images[0]['chemin']) ?>"
                            alt="<?= e($images[0]['legende'] ?: $monument['titre']) ?>"
                            class="w-full h-full object-cover cursor-pointer"
                            onclick="ouvrirImage(this)"
                        >

                    <?php else: ?>

                        <div class="w-full h-full flex flex-col items-center justify-center text-slate-400">

                            <svg class="w-14 h-14 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <rect x="3" y="3" width="18" height="18" rx="2" stroke-width="1.5"/>
                                <circle cx="8.5" cy="8.5" r="1.5" stroke-width="1.5"/>
                                <path d="m21 15-5-5L5 21" stroke-width="1.5"/>
                            </svg>

                            <p>Aucune image disponible</p>

                        </div>

                    <?php endif; ?>

                </div>

                <?php if (count($images) > 1): ?>

                    <div class="grid grid-cols-4 gap-3 mt-3">

                        <?php foreach ($images as $index => $image): ?>

                            <button
                                type="button"
                                onclick="changerImage(this, <?= $index ?>)"
                                class="h-20 rounded-lg overflow-hidden border-2 border-transparent hover:border-cyan-500 transition"
                            >

                                <img
                                    src="<?= e($image['chemin']) ?>"
                                    alt="<?= e($image['legende'] ?: $monument['titre']) ?>"
                                    class="w-full h-full object-cover"
                                >

                            </button>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

            <!-- INFORMATIONS -->

            <div>

                <span class="inline-block px-4 py-2 rounded-md bg-cyan-50 text-cyan-800 text-sm font-semibold mb-5">
                    <?= e($monument['categorie']) ?>
                </span>

                <h1 class="text-3xl md:text-4xl font-bold text-slate-800 leading-tight mb-6">
                    <?= e($monument['titre']) ?>
                </h1>

                <!-- LOCALISATION -->

                <div class="flex items-center gap-3 mb-7 text-slate-600">

                    <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">

                        <svg class="w-5 h-5 text-cyan-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" stroke-width="1.8"/>
                            <circle cx="12" cy="10" r="2.5" stroke-width="1.8"/>
                        </svg>

                    </div>

                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wider">
                            Localisation
                        </p>

                        <p class="font-semibold text-slate-800">
                            <?= e($monument['ville']) ?>, <?= e($monument['pays']) ?>
                        </p>
                    </div>

                </div>

                <div class="border-t border-slate-200 pt-6">

                    <h2 class="text-lg font-bold text-slate-800 mb-3">
                        À propos du monument
                    </h2>

                    <p class="text-slate-600 leading-relaxed">
                        <?= nl2br(e($monument['description'])) ?>
                    </p>

                </div>

                <div class="flex flex-wrap gap-3 mt-8">

                    <a href="catalogue.php"
                       class="px-6 py-3 rounded-lg bg-cyan-600 hover:bg-cyan-700 text-white font-semibold transition">
                        Retour au catalogue
                    </a>

                    <a href="galerie.php"
                       class="px-6 py-3 rounded-lg border border-slate-200 bg-white text-slate-700 font-semibold hover:bg-slate-100 transition">
                        Voir la galerie
                    </a>

                </div>

            </div>

        </div>

    </section>

    <!-- ================================= -->
    <!-- GALERIE DU MONUMENT -->
    <!-- ================================= -->

    <?php if (!empty($images)): ?>

        <section class="bg-white border-y border-slate-200">

            <div class="max-w-6xl mx-auto px-6 py-14">

                <div class="mb-8">

                    <span class="text-cyan-700 text-sm font-semibold uppercase tracking-wider">
                        Collection photographique
                    </span>

                    <h2 class="text-3xl font-bold text-slate-800 mt-2">
                        Galerie du monument
                    </h2>

                    <p class="text-slate-500 mt-2">
                        Découvrez les différentes photographies de ce monument.
                    </p>

                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

                    <?php foreach ($images as $index => $image): ?>

                        <div class="group rounded-xl overflow-hidden border border-slate-200 bg-slate-50">

                            <div class="h-56 overflow-hidden cursor-pointer"
                                 onclick="ouvrirImageDepuisListe(<?= $index ?>)">

                                <img
                                    src="<?= e($image['chemin']) ?>"
                                    alt="<?= e($image['legende'] ?: $monument['titre']) ?>"
                                    loading="lazy"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                >

                            </div>

                            <div class="p-4">

                                <p class="text-sm font-medium text-slate-700">
                                    <?= e($image['legende'] ?: $monument['titre']) ?>
                                </p>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        </section>

    <?php endif; ?>

    <!-- ================================= -->
    <!-- AUTRES MONUMENTS -->
    <!-- ================================= -->

    <?php if (!empty($autresMonuments)): ?>

        <section class="max-w-6xl mx-auto px-6 py-14 md:py-16">

            <div class="mb-8">

                <span class="text-cyan-700 text-sm font-semibold uppercase tracking-wider">
                    À découvrir également
                </span>

                <h2 class="text-3xl font-bold text-slate-800 mt-2">
                    Autres monuments
                </h2>

            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

                <?php foreach ($autresMonuments as $autre): ?>

                    <article class="bg-white rounded-xl overflow-hidden border border-slate-200 shadow-sm hover:shadow-md transition-shadow">

                        <div class="h-52 bg-slate-100 overflow-hidden">

                            <?php if (!empty($autre['image_path'])): ?>

                                <img
                                    src="<?= e($autre['image_path']) ?>"
                                    alt="<?= e($autre['titre']) ?>"
                                    loading="lazy"
                                    class="w-full h-full object-cover"
                                >

                            <?php else: ?>

                                <div class="w-full h-full flex items-center justify-center text-slate-400">
                                    Image indisponible
                                </div>

                            <?php endif; ?>

                        </div>

                        <div class="p-5">

                            <span class="text-xs font-semibold text-cyan-700 uppercase">
                                <?= e($autre['categorie']) ?>
                            </span>

                            <h3 class="text-lg font-bold text-slate-800 mt-2 mb-2">
                                <?= e($autre['titre']) ?>
                            </h3>

                            <p class="text-sm text-slate-500 mb-4">
                                <?= e($autre['ville']) ?>, <?= e($autre['pays']) ?>
                            </p>

                            <a
                                href="fiche.php?id=<?= (int)$autre['id'] ?>"
                                class="text-sm font-semibold text-cyan-700 hover:text-cyan-900"
                            >
                                Découvrir →
                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>

</main>

<!-- ================================= -->
<!-- MODALE IMAGE -->
<!-- ================================= -->

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

    <button
        type="button"
        onclick="imagePrecedente(event)"
        class="absolute left-3 md:left-8 text-white text-4xl hover:text-cyan-300"
        aria-label="Image précédente"
    >
        &#8249;
    </button>

    <img
        id="imageAgrandie"
        src=""
        alt="Photographie agrandie"
        class="max-w-[85%] max-h-[85vh] object-contain rounded-lg"
    >

    <button
        type="button"
        onclick="imageSuivante(event)"
        class="absolute right-3 md:right-8 text-white text-4xl hover:text-cyan-300"
        aria-label="Image suivante"
    >
        &#8250;
    </button>

</div>

<script>
    // Images du monument
    const imagesMonument = <?= json_encode(
        array_map(
            fn($img) => [
                'src' => $img['chemin'],
                'alt' => $img['legende'] ?: $monument['titre']
            ],
            $images
        ),
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) ?>;

    let imageActuelle = 0;

    // Changer l'image principale
    function changerImage(button, index) {
        imageActuelle = index;

        const principale = document.getElementById('imagePrincipale');

        if (principale && imagesMonument[index]) {
            principale.src = imagesMonument[index].src;
            principale.alt = imagesMonument[index].alt;
        }
    }

    // Ouvrir la modale
    function ouvrirImage(img) {
        const index = imagesMonument.findIndex(
            image => image.src === img.src
        );

        afficherModale(index >= 0 ? index : 0);
    }

    function ouvrirImageDepuisListe(index) {
        afficherModale(index);
    }

    function afficherModale(index) {
        if (!imagesMonument.length) return;

        imageActuelle = index;

        const modal = document.getElementById('imageModal');

        actualiserImageModale();

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        document.body.style.overflow = 'hidden';
    }

    function actualiserImageModale() {
        const image = imagesMonument[imageActuelle];

        if (!image) return;

        document.getElementById('imageAgrandie').src = image.src;
        document.getElementById('imageAgrandie').alt = image.alt;
    }

    function imageSuivante(event) {
        if (event) event.stopPropagation();

        imageActuelle = (imageActuelle + 1) % imagesMonument.length;
        actualiserImageModale();
    }

    function imagePrecedente(event) {
        if (event) event.stopPropagation();

        imageActuelle =
            (imageActuelle - 1 + imagesMonument.length) % imagesMonument.length;

        actualiserImageModale();
    }

    function fermerImage(event) {
        if (event && event.target.id !== 'imageModal') return;

        const modal = document.getElementById('imageModal');

        modal.classList.add('hidden');
        modal.classList.remove('flex');

        document.getElementById('imageAgrandie').src = '';

        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function(event) {
        const modal = document.getElementById('imageModal');

        if (modal.classList.contains('hidden')) return;

        if (event.key === 'Escape') {
            fermerImage();
        }

        if (event.key === 'ArrowRight') {
            imageSuivante();
        }

        if (event.key === 'ArrowLeft') {
            imagePrecedente();
        }
    });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
