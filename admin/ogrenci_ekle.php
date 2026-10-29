<?php
// admin/ogrenci_ekle.php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
check_access(['koordinator']);

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $ogrenci_no   = trim($_POST['ogrenci_no'] ?? '');
    $ad_soyad     = trim($_POST['ad_soyad'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $password     = trim($_POST['password'] ?? '123456');
    $program_turu = $_POST['program_turu'] ?? 'CAP';
    $anadal_bolum = trim($_POST['anadal_bolum'] ?? '');
    $kabul_donemi = trim($_POST['kabul_donemi'] ?? '');
    $gano         = (float)($_POST['anadal_gano'] ?? 0);
    $ozel_not     = trim($_POST['koordinator_ozel_notu'] ?? '');

    try {
        $db->beginTransaction();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (ogrenci_no, email, password_hash, ad_soyad, role) VALUES (?, ?, ?, ?, 'ogrenci')");
        $stmt->execute([$ogrenci_no, $email, $hash, $ad_soyad]);
        $uid = $db->lastInsertId();

        $stmt2 = $db->prepare("INSERT INTO students (user_id, program_turu, anadal_bolum, kabul_donemi, anadal_gano, koordinator_ozel_notu) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt2->execute([$uid, $program_turu, $anadal_bolum, $kabul_donemi, $gano, $ozel_not]);
        $sid = $db->lastInsertId();

        log_islem($db, 'OGRENCI_EKLEME', 'students', $sid, "Koordinatör yeni öğrenci ekledi: $ad_soyad ($ogrenci_no)", null, $_POST);
        $db->commit();
        $success = "Öğrenci başarıyla eklendi. Öğrenci Detay sayfasından geçmiş/mevcut derslerini onay kilidine takılmadan girebilirsiniz.";
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Kayıt hatası: Öğrenci numarası veya e-posta zaten kayıtlı olabilir.";
    }
}

require_once '../includes/header.php';
?>
<div class="card border-0 shadow-sm mx-auto" style="max-width: 650px;">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold">Bölüme Kabul Edilen ÇAP / YDP Öğrencisini Ekle</h5>
    </div>
    <div class="card-body p-4 small">
        <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success py-2"><?= e($success) ?></div><?php endif; ?>

        <form method="POST">
            <?= csrf_input() ?>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="fw-bold">Öğrenci Numarası (Benzersiz Anahtar) *</label>
                    <input type="text" name="ogrenci_no" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="fw-bold">Ad Soyad *</label>
                    <input type="text" name="ad_soyad" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="fw-bold">Okul E-postası *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="fw-bold">Geçici Giriş Şifresi *</label>
                    <input type="text" name="password" class="form-control" value="123456" required>
                </div>
                <div class="col-md-4">
                    <label class="fw-bold">Program Türü *</label>
                    <select name="program_turu" class="form-select">
                        <option value="CAP">Çift Anadal (ÇAP)</option>
                        <option value="YDP">Yandal (YDP)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="fw-bold">Kabul Dönemi *</label>
                    <input type="text" name="kabul_donemi" class="form-control" value="2026-2027 Güz" required>
                </div>
                <div class="col-md-4">
                    <label class="fw-bold">Anadal GANO</label>
                    <input type="number" step="0.01" name="anadal_gano" class="form-control" value="3.00">
                </div>
                <div class="col-12">
                    <label class="fw-bold">Anadal Bölümü *</label>
                    <input type="text" name="anadal_bolum" class="form-control" value="Elektrik-Elektronik Mühendisliği" required>
                </div>
                <div class="col-12">
                    <label class="fw-bold">Koordinatör Özel Notu (Öğrenci Göremez)</label>
                    <textarea name="koordinator_ozel_notu" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary fw-bold w-100">Öğrenciyi Sisteme Tanımla</button>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
