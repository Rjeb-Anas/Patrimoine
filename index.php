<?php

require_once __DIR__ . '/includes/config.php';

// Fonction de sécurisation
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// =====================================
// STATISTIQUES
// =====================================

function compter($conn, $table): int
{
    $tables = ['monument', 'categorie', 'ville', 'pays'];

    if (!in_array($table, $tables, true)) {
        return 0;
    }

    $result = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total FROM `$table`"
    );

    if (!$result) {
        return 0;
    }

    return (int) mysqli_fetch_assoc($result)['total'];
}

$totalMonuments = compter($conn, 'monument');
$totalCategories = compter($conn, 'categorie');
$totalVilles = compter($conn, 'ville');
$totalPays = compter($conn, 'pays');

// =====================================
// CATÉGORIES
// =====================================

$sqlCategories = "
    SELECT
        c.id,
        c.nom,
        c.description,
        COUNT(m.id) AS total
    FROM categorie c
    LEFT JOIN monument m ON m.categorie_id = c.id
    GROUP BY c.id, c.nom, c.description
    ORDER BY c.nom ASC
    LIMIT 4
";

$resultCategories = mysqli_query($conn, $sqlCategories);

// =====================================
// DERNIERS MONUMENTS
// =====================================

$sqlMonuments = "
    SELECT
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
            ORDER BY i.id ASC
            LIMIT 1
        ) AS image
    FROM monument m
    LEFT JOIN categorie c ON m.categorie_id = c.id
    LEFT JOIN ville v ON m.ville_id = v.id
    LEFT JOIN pays p ON v.pays_id = p.id
    ORDER BY m.id DESC
    LIMIT 6
";

$resultMonuments = mysqli_query($conn, $sqlMonuments);

// Utilisé uniquement pour l'affichage (le monument en vedette du hero + la grille)
$monuments = $resultMonuments ? mysqli_fetch_all($resultMonuments, MYSQLI_ASSOC) : [];
$vedette = $monuments[0] ?? null;
$cats = array_values(array_unique(array_filter(array_column($monuments, 'categorie'))));

// =====================================
// VILLES LES PLUS RICHES
// =====================================

$sqlVilles = "
    SELECT v.id, v.nom, p.nom AS pays, COUNT(m.id) AS total
    FROM ville v
    LEFT JOIN pays p ON v.pays_id = p.id
    LEFT JOIN monument m ON m.ville_id = v.id
    GROUP BY v.id, v.nom, p.nom
    HAVING total > 0
    ORDER BY total DESC, v.nom ASC
    LIMIT 6
";
$resultVilles = mysqli_query($conn, $sqlVilles);

// =====================================
// HEADER
// =====================================

require_once __DIR__ . '/includes/header.php';

?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,500;6..72,600&display=swap" rel="stylesheet">

<style>
    .titre-serif {
        font-family: 'Newsreader', Georgia, 'Times New Roman', serif;
        letter-spacing: -0.015em;
        font-weight: 600;
    }

    /* Focus clavier visible */
    main a:focus-visible {
        outline: 2px solid #0e7490;
        outline-offset: 3px;
        border-radius: 6px;
    }

    /* Un seul moment animé : l'apparition du hero au chargement */
    @keyframes entree {
        from { opacity: 0; transform: translateY(14px); }
        to   { opacity: 1; transform: none; }
    }
    .hero-entree > * {
        opacity: 0;
        animation: entree .7s cubic-bezier(.2, .7, .2, 1) forwards;
    }
    .hero-entree > *:nth-child(2) { animation-delay: .08s; }
    .hero-entree > *:nth-child(3) { animation-delay: .16s; }
    .hero-entree > *:nth-child(4) { animation-delay: .24s; }

    #progression { transform-origin: 0 50%; transform: scaleX(0); }
    details > summary { list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
    details[open] .faq-icone { transform: rotate(45deg); }
    .faq-icone { transition: transform .2s; }

    @media (prefers-reduced-motion: reduce) {
        .hero-entree > * { animation: none; opacity: 1; }
        main * { transition-duration: .01ms !important; }
    }
</style>

<main class="flex-1 bg-stone-50 dark:bg-slate-950">

    <!-- =====================================
         HERO
    ====================================== -->
    <section class="border-b border-stone-200 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-5 sm:px-6 lg:px-8
                    py-14 md:py-20
                    grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">

            <div class="lg:col-span-6 hero-entree">

                <p class="text-sm font-medium text-cyan-800 dark:text-cyan-400">
                    Catalogue du patrimoine historique
                </p>

                <h1 class="titre-serif mt-4 text-4xl sm:text-5xl lg:text-[3.5rem]
                           leading-[1.08] text-slate-900 dark:text-white">
                    Les monuments qui racontent notre histoire
                </h1>

                <p class="mt-6 text-lg leading-relaxed max-w-xl
                          text-slate-600 dark:text-slate-400">
                    Parcourez des fiches documentées sur les lieux historiques
                    et les trésors architecturaux, classés par catégorie,
                    ville et pays.
                </p>

                <form action="catalogue.php" method="get" role="search"
                      class="mt-8 flex max-w-xl rounded-lg overflow-hidden
                             border border-slate-300 dark:border-slate-700
                             bg-white dark:bg-slate-900 shadow-sm
                             focus-within:ring-2 focus-within:ring-cyan-700">
                    <label for="recherche" class="sr-only">Rechercher un monument</label>
                    <input id="recherche" type="search" name="q"
                           placeholder="Rechercher un monument, une ville…"
                           class="flex-1 min-w-0 px-4 py-3 bg-transparent outline-none
                                  text-slate-900 dark:text-white
                                  placeholder:text-slate-400">
                    <button type="submit"
                            class="px-5 bg-cyan-800 hover:bg-cyan-900 text-white
                                   font-semibold transition-colors">
                        Rechercher
                    </button>
                </form>

                <div class="flex flex-wrap gap-3 mt-4">
                    <a href="catalogue.php"
                       class="inline-flex items-center px-6 py-3 rounded-md
                              bg-cyan-800 hover:bg-cyan-900 text-white
                              font-semibold shadow-sm transition-colors">
                        Parcourir le catalogue
                    </a>
                    <a href="galerie.php"
                       class="inline-flex items-center px-6 py-3 rounded-md
                              border border-slate-300 dark:border-slate-700
                              text-slate-800 dark:text-slate-200
                              hover:bg-white dark:hover:bg-slate-900
                              font-semibold transition-colors">
                        Voir la galerie
                    </a>
                </div>
            </div>

            <!-- Monument en vedette : le dernier ajouté -->
            <div class="lg:col-span-6">
                <?php if ($vedette): ?>
                    <a href="fiche.php?id=<?= (int) $vedette['id'] ?>"
                       class="group relative block rounded-xl overflow-hidden
                              bg-slate-200 dark:bg-slate-800
                              aspect-[4/3] shadow-lg">

                        <?php if (!empty($vedette['image'])): ?>
                            <img src="<?= e($vedette['image']) ?>"
                                 alt="<?= e($vedette['titre']) ?>"
                                 class="absolute inset-0 w-full h-full object-cover
                                        transition-transform duration-700
                                        group-hover:scale-[1.03]">
                        <?php endif; ?>

                        <div class="absolute inset-0 bg-gradient-to-t
                                    from-slate-950/85 via-slate-950/20 to-transparent"></div>

                        <div class="absolute bottom-0 left-0 right-0 p-6 sm:p-8">
                            <p class="text-sm text-cyan-200">
                                Dernier ajout
                                <?php if (!empty($vedette['categorie'])): ?>
                                    — <?= e($vedette['categorie']) ?>
                                <?php endif; ?>
                            </p>
                            <h2 class="titre-serif text-2xl sm:text-3xl text-white mt-1">
                                <?= e($vedette['titre']) ?>
                            </h2>
                            <p class="text-sm text-slate-200 mt-2">
                                <?= e($vedette['ville'] ?? '') ?><?= !empty($vedette['pays']) ? ', ' . e($vedette['pays']) : '' ?>
                            </p>
                        </div>
                    </a>
                <?php else: ?>
                    <div class="aspect-[4/3] rounded-xl bg-slate-200 dark:bg-slate-800
                                flex items-center justify-center
                                text-sm text-slate-500 dark:text-slate-400">
                        Aucun monument à afficher pour le moment.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>


    <!-- =====================================
         STATISTIQUES
    ====================================== -->
    <section class="bg-white dark:bg-slate-900
                    border-b border-stone-200 dark:border-slate-800"
             aria-label="Chiffres clés">
        <dl class="max-w-7xl mx-auto px-5 sm:px-6 lg:px-8 py-10
                   grid grid-cols-2 lg:grid-cols-4 gap-y-8 gap-x-6">

            <?php
            $stats = [
                ['Monuments référencés', $totalMonuments],
                ['Catégories', $totalCategories],
                ['Villes', $totalVilles],
                ['Pays', $totalPays],
            ];
            foreach ($stats as [$libelle, $valeur]):
            ?>
                <div class="lg:border-l lg:first:border-l-0 lg:pl-8 lg:first:pl-0
                            border-stone-200 dark:border-slate-800">
                    <dd class="titre-serif text-4xl text-slate-900 dark:text-white
                               tabular-nums"
                        data-compteur="<?= (int) $valeur ?>">
                        <?= (int) $valeur ?>
                    </dd>
                    <dt class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        <?= e($libelle) ?>
                    </dt>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>


    <!-- =====================================
         PRÉSENTATION
    ====================================== -->
    <section class="max-w-7xl mx-auto px-5 sm:px-6 lg:px-8 py-20">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">

            <div class="lg:col-span-5">
                <h2 class="titre-serif text-3xl sm:text-4xl leading-tight
                           text-slate-900 dark:text-white">
                    Préserver l'histoire, la rendre accessible
                </h2>
                <p class="mt-5 leading-relaxed text-slate-600 dark:text-slate-400 max-w-md">
                    Patrimoine rassemble des monuments historiques dans un
                    catalogue clair : chaque fiche présente le lieu, son
                    contexte et sa valeur culturelle.
                </p>
            </div>

            <div class="lg:col-span-7 grid sm:grid-cols-2 gap-x-10 gap-y-8">
                <?php
                $valeurs = [
                    ['Histoire', 'Des siècles de mémoire, documentés fiche par fiche.'],
                    ['Culture', 'Des traditions et savoir-faire à transmettre.'],
                    ['Architecture', 'Un héritage bâti à observer de près.'],
                    ['Découverte', 'Filtrez par catégorie, ville ou pays.'],
                ];
                foreach ($valeurs as [$titre, $texte]):
                ?>
                    <div class="border-l-2 border-cyan-700 dark:border-cyan-500 pl-5">
                        <h3 class="font-semibold text-lg text-slate-900 dark:text-white">
                            <?= e($titre) ?>
                        </h3>
                        <p class="text-sm leading-relaxed mt-1.5
                                  text-slate-600 dark:text-slate-400">
                            <?= e($texte) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>


    <!-- =====================================
         CATÉGORIES
    ====================================== -->
    <section class="bg-white dark:bg-slate-900
                    border-y border-stone-200 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-5 sm:px-6 lg:px-8 py-20">

            <div class="flex flex-wrap items-end justify-between gap-4 mb-10">
                <div>
                    <h2 class="titre-serif text-3xl text-slate-900 dark:text-white">
                        Parcourir par catégorie
                    </h2>
                    <p class="text-slate-500 dark:text-slate-400 mt-2">
                        Explorez le patrimoine selon différents thèmes.
                    </p>
                </div>
                <a href="catalogue.php"
                   class="text-sm font-semibold text-cyan-800 dark:text-cyan-400 hover:underline">
                    Toutes les catégories
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4
                        border border-stone-200 dark:border-slate-800
                        rounded-xl overflow-hidden
                        divide-y sm:divide-y-0 sm:divide-x lg:divide-x
                        divide-stone-200 dark:divide-slate-800">

                <?php if ($resultCategories && mysqli_num_rows($resultCategories) > 0): ?>
                    <?php while ($categorie = mysqli_fetch_assoc($resultCategories)): ?>

                        <a href="catalogue.php?categorie=<?= (int) $categorie['id'] ?>"
                           class="group flex flex-col p-6 min-h-[13rem]
                                  hover:bg-stone-50 dark:hover:bg-slate-800/60
                                  transition-colors">

                            <h3 class="titre-serif text-xl text-slate-900 dark:text-white
                                       group-hover:text-cyan-800
                                       dark:group-hover:text-cyan-400 transition-colors">
                                <?= e($categorie['nom']) ?>
                            </h3>

                            <p class="text-sm leading-relaxed mt-3 flex-1
                                      text-slate-500 dark:text-slate-400">
                                <?= e(mb_strimwidth(
                                    $categorie['description'] ?? '',
                                    0,
                                    100,
                                    '...',
                                    'UTF-8'
                                )) ?>
                            </p>

                            <p class="text-sm font-semibold mt-5
                                      text-cyan-800 dark:text-cyan-400">
                                <?= (int) $categorie['total'] ?>
                                monument<?= (int) $categorie['total'] > 1 ? 's' : '' ?>
                            </p>
                        </a>

                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="p-6 text-slate-500 dark:text-slate-400">
                        Aucune catégorie disponible.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </section>


    <!-- =====================================
         VILLES
    ====================================== -->
    <?php if ($resultVilles && mysqli_num_rows($resultVilles) > 0): ?>
    <section class="max-w-7xl mx-auto px-5 sm:px-6 lg:px-8 pt-20">
        <h2 class="titre-serif text-3xl text-slate-900 dark:text-white">
            Villes à explorer
        </h2>
        <p class="text-slate-500 dark:text-slate-400 mt-2 mb-8">
            Les destinations qui comptent le plus de monuments.
        </p>

        <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8">
            <?php while ($ville = mysqli_fetch_assoc($resultVilles)): ?>
                <li class="border-t border-stone-200 dark:border-slate-800">
                    <a href="catalogue.php?ville=<?= (int) $ville['id'] ?>"
                       class="group flex items-baseline justify-between gap-4 py-5">
                        <span>
                            <span class="titre-serif text-xl text-slate-900 dark:text-white
                                         group-hover:text-cyan-800 dark:group-hover:text-cyan-400
                                         transition-colors">
                                <?= e($ville['nom']) ?>
                            </span>
                            <?php if (!empty($ville['pays'])): ?>
                                <span class="block text-sm text-slate-500 dark:text-slate-400">
                                    <?= e($ville['pays']) ?>
                                </span>
                            <?php endif; ?>
                        </span>
                        <span class="text-sm font-semibold text-cyan-800 dark:text-cyan-400 whitespace-nowrap">
                            <?= (int) $ville['total'] ?> monument<?= (int) $ville['total'] > 1 ? 's' : '' ?>
                        </span>
                    </a>
                </li>
            <?php endwhile; ?>
        </ul>
    </section>
    <?php endif; ?>


    <!-- =====================================
         DERNIERS MONUMENTS
    ====================================== -->
    <section class="max-w-7xl mx-auto px-5 sm:px-6 lg:px-8 py-20">

        <div class="flex flex-wrap items-end justify-between gap-4 mb-10">
            <div>
                <h2 class="titre-serif text-3xl text-slate-900 dark:text-white">
                    Derniers monuments ajoutés
                </h2>
                <p class="text-slate-500 dark:text-slate-400 mt-2">
                    Les fiches les plus récentes du catalogue.
                </p>
            </div>
            <a href="catalogue.php"
               class="text-sm font-semibold text-cyan-800 dark:text-cyan-400 hover:underline">
                Voir tout le catalogue
            </a>
        </div>

        <?php if (count($cats) > 1): ?>
            <div class="flex flex-wrap gap-2 mb-8" role="group" aria-label="Filtrer par catégorie">
                <button type="button" data-filtre="*" aria-pressed="true"
                        class="filtre px-4 py-1.5 rounded-full text-sm font-medium border transition-colors">
                    Tous
                </button>
                <?php foreach ($cats as $cat): ?>
                    <button type="button" data-filtre="<?= e($cat) ?>" aria-pressed="false"
                            class="filtre px-4 py-1.5 rounded-full text-sm font-medium border transition-colors">
                        <?= e($cat) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div id="grille-monuments"
             class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-10">

            <?php if (!empty($monuments)): ?>
                <?php foreach ($monuments as $monument): ?>

                    <article class="group" data-cat="<?= e($monument['categorie'] ?? 'Non classé') ?>">

                        <a href="fiche.php?id=<?= (int) $monument['id'] ?>"
                           class="block overflow-hidden rounded-lg aspect-[3/2]
                                  bg-slate-200 dark:bg-slate-800">
                            <?php if (!empty($monument['image'])): ?>
                                <img src="<?= e($monument['image']) ?>"
                                     alt="<?= e($monument['titre']) ?>"
                                     loading="lazy"
                                     class="w-full h-full object-cover
                                            transition-transform duration-500
                                            group-hover:scale-[1.04]">
                            <?php else: ?>
                                <span class="w-full h-full flex items-center justify-center
                                             text-sm text-slate-400">
                                    Aucune image disponible
                                </span>
                            <?php endif; ?>
                        </a>

                        <div class="pt-5">
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                <span class="font-medium text-cyan-800 dark:text-cyan-400">
                                    <?= e($monument['categorie'] ?? 'Non classé') ?>
                                </span>
                                <?php if (!empty($monument['ville'])): ?>
                                    — <?= e($monument['ville']) ?><?= !empty($monument['pays']) ? ', ' . e($monument['pays']) : '' ?>
                                <?php endif; ?>
                            </p>

                            <h3 class="titre-serif text-2xl mt-2
                                       text-slate-900 dark:text-white">
                                <a href="fiche.php?id=<?= (int) $monument['id'] ?>"
                                   class="hover:text-cyan-800 dark:hover:text-cyan-400
                                          transition-colors">
                                    <?= e($monument['titre']) ?>
                                </a>
                            </h3>

                            <p class="text-sm leading-relaxed mt-3
                                      text-slate-600 dark:text-slate-400">
                                <?= e(mb_strimwidth(
                                    strip_tags($monument['description'] ?? ''),
                                    0,
                                    110,
                                    '...',
                                    'UTF-8'
                                )) ?>
                            </p>

                            <a href="fiche.php?id=<?= (int) $monument['id'] ?>"
                               class="inline-block mt-4 text-sm font-semibold
                                      text-cyan-800 dark:text-cyan-400 hover:underline">
                                Lire la fiche
                            </a>
                        </div>
                    </article>

                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-slate-500 dark:text-slate-400">
                    Aucun monument disponible pour le moment.
                </p>
            <?php endif; ?>
        </div>
    </section>


    <!-- =====================================
         COMMENT ÇA MARCHE
    ====================================== -->
    <section class="bg-white dark:bg-slate-900
                    border-y border-stone-200 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-5 sm:px-6 lg:px-8 py-20">
            <h2 class="titre-serif text-3xl text-slate-900 dark:text-white">
                Comment explorer le catalogue
            </h2>

            <ol class="mt-10 grid md:grid-cols-3 gap-10">
                <?php
                $etapes = [
                    ['Chercher', 'Saisissez un nom ou filtrez par catégorie, ville ou pays.'],
                    ['Consulter la fiche', 'Lisez la description, le lieu et regardez les images du monument.'],
                    ['Comparer et continuer', 'Passez d\'un monument à l\'autre et parcourez la galerie.'],
                ];
                foreach ($etapes as $i => [$titre, $texte]):
                ?>
                    <li class="flex gap-4">
                        <span class="titre-serif text-3xl text-cyan-700 dark:text-cyan-400 leading-none">
                            <?= $i + 1 ?>
                        </span>
                        <div>
                            <h3 class="font-semibold text-lg text-slate-900 dark:text-white"><?= e($titre) ?></h3>
                            <p class="text-sm leading-relaxed mt-1.5 text-slate-600 dark:text-slate-400"><?= e($texte) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>


    <!-- =====================================
         FAQ
    ====================================== -->
    <section class="max-w-3xl mx-auto px-5 sm:px-6 lg:px-8 py-20">
        <h2 class="titre-serif text-3xl text-slate-900 dark:text-white">Questions fréquentes</h2>

        <div class="mt-8 divide-y divide-stone-200 dark:divide-slate-800
                    border-y border-stone-200 dark:border-slate-800">
            <?php
            $faq = [
                ['Que contient une fiche monument ?', 'Une description, la catégorie, la ville et le pays, ainsi que les images associées.'],
                ['Comment trouver un monument précis ?', 'Utilisez la barre de recherche ou le catalogue, puis filtrez par catégorie, ville ou pays.'],
                ['Où voir toutes les images ?', 'La galerie rassemble les visuels de l\'ensemble des monuments.'],
            ];
            foreach ($faq as [$q, $r]):
            ?>
                <details class="group py-5">
                    <summary class="flex items-center justify-between gap-4 cursor-pointer
                                    font-semibold text-slate-900 dark:text-white">
                        <?= e($q) ?>
                        <span class="faq-icone text-2xl text-cyan-700 dark:text-cyan-400" aria-hidden="true">+</span>
                    </summary>
                    <p class="mt-3 leading-relaxed text-slate-600 dark:text-slate-400"><?= e($r) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </section>


    <!-- =====================================
         SECTION FINALE
    ====================================== -->
    <section class="max-w-7xl mx-auto px-5 sm:px-6 lg:px-8 pb-20">
        <div class="bg-slate-900 dark:bg-slate-900
                    border border-slate-800
                    rounded-xl p-8 sm:p-12
                    flex flex-col md:flex-row items-start md:items-center
                    justify-between gap-8">

            <div class="max-w-2xl">
                <h2 class="titre-serif text-2xl sm:text-3xl text-white">
                    Le patrimoine mérite d'être découvert
                </h2>
                <p class="text-slate-300 mt-3 leading-relaxed">
                    Consultez le catalogue complet et trouvez les monuments
                    qui vous intéressent.
                </p>
            </div>

            <a href="catalogue.php"
               class="shrink-0 inline-flex items-center px-6 py-3 rounded-md
                      bg-white text-slate-900 font-semibold
                      hover:bg-cyan-50 transition-colors">
                Ouvrir le catalogue
            </a>
        </div>
    </section>

</main>

<div class="fixed top-0 inset-x-0 h-0.5 z-50 pointer-events-none" aria-hidden="true">
    <div id="progression" class="h-full bg-cyan-600"></div>
</div>

<button id="haut" type="button" aria-label="Retour en haut de page"
        class="fixed bottom-6 right-6 z-40 w-11 h-11 rounded-full
               bg-cyan-800 hover:bg-cyan-900 text-white shadow-lg
               opacity-0 pointer-events-none transition-opacity">
    <svg class="w-5 h-5 mx-auto" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5-5 5 5"/></svg>
</button>

<script>
    // Compteurs animés (une seule fois, quand la bande de chiffres devient visible)
    (function () {
        var els = document.querySelectorAll('[data-compteur]');
        if (!els.length) return;

        var reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduit || !('IntersectionObserver' in window)) return;

        function animer(el) {
            var cible = parseInt(el.getAttribute('data-compteur'), 10) || 0;
            var duree = 900;
            var debut = null;

            function pas(t) {
                if (debut === null) debut = t;
                var p = Math.min((t - debut) / duree, 1);
                var ease = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(cible * ease).toLocaleString('fr-FR');
                if (p < 1) requestAnimationFrame(pas);
            }
            requestAnimationFrame(pas);
        }

        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animer(entry.target);
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });

        els.forEach(function (el) { obs.observe(el); });
    })();

    // Filtre par catégorie (côté client)
    (function () {
        var boutons = document.querySelectorAll('.filtre');
        var cartes = document.querySelectorAll('#grille-monuments [data-cat]');
        if (!boutons.length) return;

        var actif = ['bg-cyan-800', 'text-white', 'border-cyan-800'];
        var inactif = ['border-slate-300', 'text-slate-700', 'hover:bg-slate-100',
                       'dark:border-slate-700', 'dark:text-slate-300', 'dark:hover:bg-slate-800'];

        function style(btn, on) {
            actif.forEach(function (c) { btn.classList.toggle(c, on); });
            inactif.forEach(function (c) { btn.classList.toggle(c, !on); });
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        }

        boutons.forEach(function (btn) {
            style(btn, btn.getAttribute('data-filtre') === '*');
            btn.addEventListener('click', function () {
                var f = btn.getAttribute('data-filtre');
                boutons.forEach(function (b) { style(b, b === btn); });
                cartes.forEach(function (c) {
                    c.hidden = !(f === '*' || c.getAttribute('data-cat') === f);
                });
            });
        });
    })();

    // Barre de progression + bouton retour en haut
    (function () {
        var barre = document.getElementById('progression');
        var haut = document.getElementById('haut');
        var ticking = false;

        function maj() {
            var h = document.documentElement;
            var max = h.scrollHeight - h.clientHeight;
            var y = window.scrollY;
            barre.style.transform = 'scaleX(' + (max > 0 ? y / max : 0) + ')';
            var visible = y > 600;
            haut.classList.toggle('opacity-0', !visible);
            haut.classList.toggle('pointer-events-none', !visible);
            ticking = false;
        }

        window.addEventListener('scroll', function () {
            if (!ticking) { ticking = true; requestAnimationFrame(maj); }
        }, { passive: true });

        haut.addEventListener('click', function () {
            var reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            window.scrollTo({ top: 0, behavior: reduit ? 'auto' : 'smooth' });
        });
        maj();
    })();

    // FAQ : un seul élément ouvert à la fois
    document.querySelectorAll('details').forEach(function (d) {
        d.addEventListener('toggle', function () {
            if (!d.open) return;
            document.querySelectorAll('details[open]').forEach(function (o) { if (o !== d) o.open = false; });
        });
    });
</script>

<!-- FOOTER -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>