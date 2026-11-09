<?php
// login.php
require_once __DIR__ . '/config/db.php';

if (!empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? $_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Lütfen kullanıcı bilgilerinizi ve şifrenizi girin.';
    } else {
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = :id1 OR ogrenci_no = :id2 LIMIT 1");
            $stmt->execute([
                ':id1' => $identifier,
                ':id2' => $identifier
            ]);
            $user = $stmt->fetch();

            $storedHash = $user['password_hash'] ?? $user['password'] ?? $user['sifre'] ?? '';
            $isValid = false;

            if ($user && $storedHash !== '') {
                if (password_verify($password, $storedHash) || $password === $storedHash) {
                    $isValid = true;
                }
            }

            if ($isValid) {
                session_regenerate_id(true);
                $userRole = $user['rol'] ?? $user['role'] ?? 'ogrenci';

                $_SESSION['user_id']    = $user['id'];
                $_SESSION['id']         = $user['id'];
                $_SESSION['ad_soyad']   = $user['ad_soyad'] ?? '';
                $_SESSION['email']      = $user['email'] ?? '';
                $_SESSION['ogrenci_no'] = $user['ogrenci_no'] ?? '';
                $_SESSION['rol']        = $userRole;
                $_SESSION['role']       = $userRole;

                header("Location: index.php");
                exit;
            } else {
                $error = 'E-posta/Öğrenci No veya şifre hatalı.';
            }
        } catch (PDOException $e) {
            $error = 'Sistem hatası: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - S.Ü. ÇAP ve Yandal (YDP) Takip Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .login-card {
            max-width: 420px;
            width: 100%;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            padding: 2.25rem;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <h4 class="fw-bold mb-1">S.Ü. Bilgisayar Mühendisliği</h4>
            <div class="text-primary fw-medium">ÇAP ve Yandal (YDP) Öğrenci Takip Sistemi</div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2 small">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="off">
            <div class="mb-3">
                <label for="identifier" class="form-label small fw-semibold">E-posta veya Öğrenci No</label>
                <input type="text" class="form-control" id="identifier" name="identifier" required autofocus
                       placeholder="ornek@selcuk.edu.tr veya Öğrenci No"
                       value="<?= htmlspecialchars($_POST['identifier'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label small fw-semibold">Şifre</label>
                <input type="password" class="form-control" id="password" name="password" required
                       placeholder="••••••••">
            </div>

            <button type="submit" class="btn btn-primary w-100 fw-semibold py-2 mt-2">
                Sisteme Giriş Yap
            </button>
        </form>
    </div>
</body>
</html>
