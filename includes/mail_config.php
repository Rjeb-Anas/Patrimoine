
<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function envoyerNotification($nom, $email, $sujet, $contenu): bool
{
    $mail = new PHPMailer(true);

    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        // Ton adresse Gmail
        $mail->Username = 'tonadresse@gmail.com';

        // Mot de passe d'application Google
        $mail->Password = 'TON_MOT_DE_PASSE_APPLICATION';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        // Expéditeur
        $mail->setFrom('tonadresse@gmail.com', 'Patrimoine');

        // Destinataire
        $mail->addAddress('tonadresse@gmail.com');

        // Répondre à l'expéditeur du formulaire
        $mail->addReplyTo($email, $nom);

        // Contenu du mail
        $mail->isHTML(true);
        $mail->Subject = 'Nouveau message : ' . $sujet;

        $mail->Body = '
            <h2>Nouveau message depuis Patrimoine</h2>

            <p><strong>Nom :</strong> ' . htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') . '</p>

            <p><strong>Email :</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>

            <p><strong>Sujet :</strong> ' . htmlspecialchars($sujet, ENT_QUOTES, 'UTF-8') . '</p>

            <hr>

            <p>' . nl2br(htmlspecialchars($contenu, ENT_QUOTES, 'UTF-8')) . '</p>
        ';

        $mail->AltBody = "Nom : $nom\nEmail : $email\nSujet : $sujet\n\n$contenu";

        $mail->send();

        return true;

    } catch (Exception $e) {
        error_log('Erreur PHPMailer : ' . $mail->ErrorInfo);
        return false;
    }
}
