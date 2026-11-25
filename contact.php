
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/mail_config.php';

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Protection CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$nom = '';
$email = '';
$sujet = '';
$contenu = '';

$erreurs = [];
$succes = '';
$alerteMail = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $sujet = trim($_POST['sujet'] ?? '');
    $contenu = trim($_POST['contenu'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Vérification CSRF
    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $erreurs[] = "Session expirée. Veuillez actualiser la page.";
    }

    // Validation
    if (mb_strlen($nom) < 2 || mb_strlen($nom) > 100) {
        $erreurs[] = "Le nom doit contenir entre 2 et 100 caractères.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $erreurs[] = "Veuillez saisir une adresse e-mail valide.";
    }

    if (mb_strlen($sujet) < 3 || mb_strlen($sujet) > 150) {
        $erreurs[] = "Le sujet doit contenir entre 3 et 150 caractères.";
    }

    if (mb_strlen($contenu) < 10 || mb_strlen($contenu) > 5000) {
        $erreurs[] = "Le message doit contenir entre 10 et 5000 caractères.";
    }

    // Enregistrement et notification
    if (empty($erreurs)) {

        $sql = "INSERT INTO message
                (nom, email, sujet, contenu, lu, date_envoi)
                VALUES (?, ?, ?, ?, 0, NOW())";

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "ssss",
                $nom,
                $email,
                $sujet,
                $contenu
            );

            if (mysqli_stmt_execute($stmt)) {

                $succes = "Votre message a été envoyé avec succès.";

                // Notification par e-mail
                if (!envoyerNotification($nom, $email, $sujet, $contenu)) {
                    $alerteMail = true;
                }

                $nom = '';
                $email = '';
                $sujet = '';
                $contenu = '';

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            } else {
                $erreurs[] = "Une erreur est survenue. Veuillez réessayer.";
            }

            mysqli_stmt_close($stmt);

        } else {
            $erreurs[] = "Impossible de traiter votre demande.";
        }
    }
}

$pageTitle = "Contact | Patrimoine";

include __DIR__ . '/includes/header.php';
?>

<main class="bg-slate-50 dark:bg-slate-950 min-h-screen transition-colors duration-300">

    <!-- INTRODUCTION -->

    <section class="bg-blue-50 dark:bg-slate-900 border-b border-blue-100 dark:border-slate-800 transition-colors duration-300">

        <div class="max-w-6xl mx-auto px-6 py-16 md:py-20 text-center">

            <span class="inline-block text-sm font-semibold tracking-wider uppercase text-cyan-700 dark:text-cyan-400 mb-4">
                Nous contacter
            </span>

            <h1 class="text-4xl md:text-5xl font-bold text-slate-800 dark:text-white mb-5">
                Parlons de notre patrimoine
            </h1>

            <p class="max-w-2xl mx-auto text-slate-600 dark:text-slate-300 leading-relaxed text-base md:text-lg">
                Une question, une suggestion ou une information à partager ?
                Envoyez-nous votre message. Nous serons ravis de vous lire.
            </p>

        </div>

    </section>

    <!-- CONTENU -->

    <section class="max-w-6xl mx-auto px-6 py-14 md:py-20">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- INFORMATIONS -->

            <div class="lg:col-span-1">

                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 p-7 shadow-sm transition-colors duration-300">

                    <h2 class="text-xl font-bold text-slate-800 dark:text-white mb-3">
                        Restons en contact
                    </h2>

                    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed mb-8">
                        Vos questions et contributions nous aident à enrichir
                        et à valoriser notre patrimoine historique.
                    </p>

                    <!-- EMAIL -->

                    <div class="flex items-start gap-4 mb-7">

                        <div class="w-11 h-11 shrink-0 rounded-lg bg-cyan-50 dark:bg-cyan-950 flex items-center justify-center">
                            <svg class="w-5 h-5 text-cyan-700 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <rect x="3" y="5" width="18" height="14" rx="2" stroke-width="1.8"/>
                                <path d="m3 7 9 6 9-6" stroke-width="1.8"/>
                            </svg>
                        </div>

                        <div>
                            <h3 class="font-semibold text-slate-800 dark:text-white">
                                E-mail
                            </h3>

                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                                Contactez-nous via le formulaire.
                            </p>
                        </div>

                    </div>

                    <!-- LOCALISATION -->

                    <div class="flex items-start gap-4 mb-7">

                        <div class="w-11 h-11 shrink-0 rounded-lg bg-cyan-50 dark:bg-cyan-950 flex items-center justify-center">
                            <svg class="w-5 h-5 text-cyan-700 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" stroke-width="1.8"/>
                                <circle cx="12" cy="10" r="2.5" stroke-width="1.8"/>
                            </svg>
                        </div>

                        <div>
                            <h3 class="font-semibold text-slate-800 dark:text-white">
                                Localisation
                            </h3>

                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                                Fès, Maroc
                            </p>
                        </div>

                    </div>

                    <!-- MISSION -->

                    <div class="flex items-start gap-4">

                        <div class="w-11 h-11 shrink-0 rounded-lg bg-cyan-50 dark:bg-cyan-950 flex items-center justify-center">
                            <svg class="w-5 h-5 text-cyan-700 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M12 3 3 8l9 5 9-5-9-5Z" stroke-width="1.8"/>
                                <path d="m3 12 9 5 9-5M3 16l9 5 9-5" stroke-width="1.8"/>
                            </svg>
                        </div>

                        <div>
                            <h3 class="font-semibold text-slate-800 dark:text-white">
                                Notre mission
                            </h3>

                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                                Préserver et faire découvrir le patrimoine.
                            </p>
                        </div>

                    </div>

                </div>

                <!-- PETIT TEXTE -->

                <div class="mt-5 rounded-xl bg-cyan-50 dark:bg-cyan-950/40 border border-cyan-100 dark:border-cyan-900 p-6 transition-colors">

                    <h3 class="font-bold text-slate-800 dark:text-white mb-2">
                        Votre avis compte
                    </h3>

                    <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        Chaque contribution participe à la transmission
                        de notre héritage culturel.
                    </p>

                </div>

            </div>

            <!-- FORMULAIRE -->

            <div class="lg:col-span-2">

                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 p-7 md:p-9 shadow-sm transition-colors duration-300">

                    <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-2">
                        Envoyer un message
                    </h2>

                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-7">
                        Tous les champs sont obligatoires.
                    </p>

                    <!-- SUCCES -->

                    <?php if ($succes): ?>

                        <div class="mb-6 p-4 rounded-lg bg-green-50 dark:bg-green-950/40 border border-green-200 dark:border-green-900 text-green-700 dark:text-green-300 text-sm">
                            <?= e($succes) ?>
                        </div>

                        <?php if ($alerteMail): ?>

                            <div class="mb-6 p-4 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 text-amber-800 dark:text-amber-300 text-sm">
                                Votre message est enregistré, mais la notification par e-mail n'a pas pu être envoyée.
                            </div>

                        <?php endif; ?>

                    <?php endif; ?>

                    <!-- ERREURS -->

                    <?php if (!empty($erreurs)): ?>

                        <div class="mb-6 p-4 rounded-lg bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900 text-red-700 dark:text-red-300 text-sm">

                            <ul class="list-disc pl-5 space-y-1">

                                <?php foreach ($erreurs as $erreur): ?>

                                    <li><?= e($erreur) ?></li>

                                <?php endforeach; ?>

                            </ul>

                        </div>

                    <?php endif; ?>

                    <!-- FORMULAIRE -->

                    <form method="POST" action="contact.php" class="space-y-5">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($_SESSION['csrf_token']) ?>"
                        >

                        <!-- NOM ET EMAIL -->

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                            <div>

                                <label for="nom" class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2">
                                    Nom complet
                                </label>

                                <input
                                    type="text"
                                    id="nom"
                                    name="nom"
                                    value="<?= e($nom) ?>"
                                    placeholder="Votre nom"
                                    maxlength="100"
                                    required
                                    class="w-full px-4 py-3 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100 dark:focus:ring-cyan-900 transition"
                                >

                            </div>

                            <div>

                                <label for="email" class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2">
                                    Adresse e-mail
                                </label>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="<?= e($email) ?>"
                                    placeholder="exemple@email.com"
                                    maxlength="150"
                                    required
                                    class="w-full px-4 py-3 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100 dark:focus:ring-cyan-900 transition"
                                >

                            </div>

                        </div>

                        <!-- SUJET -->

                        <div>

                            <label for="sujet" class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2">
                                Sujet
                            </label>

                            <input
                                type="text"
                                id="sujet"
                                name="sujet"
                                value="<?= e($sujet) ?>"
                                placeholder="Objet de votre message"
                                maxlength="150"
                                required
                                class="w-full px-4 py-3 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100 dark:focus:ring-cyan-900 transition"
                            >

                        </div>

                        <!-- MESSAGE -->

                        <div>

                            <label for="contenu" class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2">
                                Message
                            </label>

                            <textarea
                                id="contenu"
                                name="contenu"
                                rows="6"
                                maxlength="5000"
                                required
                                placeholder="Écrivez votre message ici..."
                                class="w-full px-4 py-3 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100 dark:focus:ring-cyan-900 transition resize-y"
                            ><?= e($contenu) ?></textarea>

                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-2">
                                Maximum 5000 caractères.
                            </p>

                        </div>

                        <!-- ENVOYER -->

                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded-lg bg-cyan-600 hover:bg-cyan-700 text-white font-semibold transition-colors"
                        >
                            Envoyer le message

                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z"
                                      stroke-width="1.8"
                                      stroke-linecap="round"
                                      stroke-linejoin="round"/>
                            </svg>

                        </button>

                    </form>

                </div>

            </div>

        </div>

    </section>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
