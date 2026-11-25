
<?php

session_start();

require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$login = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';

if ($login === '' || $password === '') {
    $_SESSION['erreur'] = "Veuillez remplir tous les champs.";
    header('Location: login.php');
    exit;
}

$sql = "SELECT id, login, mot_de_passe, role
        FROM utilisateur
        WHERE login = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    $_SESSION['erreur'] = "Une erreur est survenue. Réessayez.";
    header('Location: login.php');
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $login);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$admin = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (
    $admin &&
    $admin['role'] === 'admin' &&
    password_verify($password, $admin['mot_de_passe'])
) {
    session_regenerate_id(true);

    $_SESSION['id'] = $admin['id'];
    $_SESSION['login'] = $admin['login'];
    $_SESSION['role'] = $admin['role'];

    header('Location: dashboard.php');
    exit;
}

$_SESSION['erreur'] = "Identifiant ou mot de passe incorrect.";

header('Location: login.php');
exit;
