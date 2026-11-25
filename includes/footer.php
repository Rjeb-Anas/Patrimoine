
<!-- =====================================
     FOOTER
===================================== -->

<footer class="mt-auto bg-slate-900 dark:bg-black text-slate-300">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10">

            <!-- IDENTITÉ -->

            <div class="lg:col-span-2">

                <a href="<?= $baseUrl ?>/index.php"
                   class="inline-flex items-center gap-3 mb-5">

                    <div class="w-11 h-11 rounded-xl bg-cyan-700
                                flex items-center justify-center
                                text-white font-bold text-xl">
                        P
                    </div>

                    <span class="text-2xl font-bold text-white">
                        Patrimoine
                    </span>

                </a>

                <p class="text-sm text-slate-400 leading-7 max-w-md">
                    Une plateforme dédiée à la découverte et à la
                    valorisation des monuments historiques du Maroc
                    et d'Afrique. Explorez leur histoire, leur
                    architecture et leur richesse culturelle.
                </p>

            </div>


            <!-- NAVIGATION -->

            <div>

                <h3 class="text-white font-semibold text-lg mb-5">
                    Navigation
                </h3>

                <ul class="space-y-3 text-sm">

                    <li>
                        <a href="<?= $baseUrl ?>/index.php"
                           class="hover:text-cyan-400 transition">
                            Accueil
                        </a>
                    </li>

                    <li>
                        <a href="<?= $baseUrl ?>/catalogue.php"
                           class="hover:text-cyan-400 transition">
                            Catalogue
                        </a>
                    </li>

                    <li>
                        <a href="<?= $baseUrl ?>/galerie.php"
                           class="hover:text-cyan-400 transition">
                            Galerie
                        </a>
                    </li>

                    <li>
                        <a href="<?= $baseUrl ?>/contact.php"
                           class="hover:text-cyan-400 transition">
                            Contact
                        </a>
                    </li>

                </ul>

            </div>


            <!-- INFORMATIONS -->

            <div>

                <h3 class="text-white font-semibold text-lg mb-5">
                    À propos
                </h3>

                <p class="text-sm text-slate-400 leading-7">
                    Notre objectif est de faire connaître les
                    monuments historiques et de contribuer à
                    la préservation du patrimoine culturel.
                </p>

                <a href="<?= $baseUrl ?>/contact.php"
                   class="inline-flex items-center gap-2 mt-4
                          text-cyan-400 text-sm font-semibold
                          hover:text-cyan-300 transition">
                    Nous contacter
                    <span aria-hidden="true">&rarr;</span>
                </a>

            </div>

        </div>


        <!-- SÉPARATION -->

        <div class="border-t border-slate-700 mt-12 pt-7">

            <div class="flex flex-col sm:flex-row
                        justify-between items-center gap-4">

                <p class="text-sm text-slate-400 text-center">
                    &copy; <?= date('Y') ?> Patrimoine.
                    Tous droits réservés.
                </p>

                <p class="text-xs text-slate-500 text-center">
                    Monuments historiques du Maroc et d'Afrique
                </p>

            </div>

        </div>

    </div>

</footer>


<!-- =====================================
     JAVASCRIPT
===================================== -->

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* =====================================
       DARK MODE
    ===================================== */

    const themeToggle = document.getElementById('themeToggle');
    const themeToggleMobile = document.getElementById('themeToggleMobile');

    const sunIcon = document.getElementById('sunIcon');
    const moonIcon = document.getElementById('moonIcon');
    const mobileThemeIcon = document.getElementById('mobileThemeIcon');

    function updateTheme() {

        const isDark = document.documentElement.classList.contains('dark');

        sunIcon?.classList.toggle('hidden', !isDark);
        moonIcon?.classList.toggle('hidden', isDark);

        if (mobileThemeIcon) {
            mobileThemeIcon.textContent = isDark
                ? 'Passer au mode clair'
                : 'Passer au mode sombre';
        }
    }

    function toggleTheme() {

        const isDark = document.documentElement.classList.toggle('dark');

        localStorage.setItem('theme', isDark ? 'dark' : 'light');

        updateTheme();
    }

    themeToggle?.addEventListener('click', toggleTheme);
    themeToggleMobile?.addEventListener('click', toggleTheme);

    updateTheme();


    /* =====================================
       MENU MOBILE
    ===================================== */

    const menuButton = document.getElementById('menuButton');
    const mobileMenu = document.getElementById('mobileMenu');

    if (menuButton && mobileMenu) {

        menuButton.addEventListener('click', function () {

            const isOpen = !mobileMenu.classList.contains('hidden');

            mobileMenu.classList.toggle('hidden');

            menuButton.setAttribute(
                'aria-expanded',
                String(!isOpen)
            );

        });

    }

});
</script>


<!-- FERMETURE DU DOCUMENT -->

</body>
</html>
