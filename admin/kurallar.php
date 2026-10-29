<?php
// admin/kurallar.php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
check_access(['koordinator']);

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($_POST['action'] === 'kural_guncelle') {
        $id = (int)$_POST['rule_id'];
        $yeni_deger = (float)$_POST['deger'];

        $eskiStmt = $db->prepare("SELECT * FROM regulation_rules WHERE id = ?");
        $eskiStmt->execute([$id]);
        $eski = $eskiStmt->fetch();

        if ($eski && (float)$eski['deger'] !== $yeni_deger) {
            $db->prepare("UPDATE regulation_rules SET is_active = 0 WHERE id = ?")->execute([$id]);
            $ins = $db->prepare("INSERT INTO regulation_rules (program_turu, kural_kodu, kural_adi, deger, birim, versiyon, is_active, guncelleyen_id) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
            $ins->execute([$eski['program_turu'], $eski['kural_kodu'], $eski['kural_adi'], $yeni_deger, $eski['birim'], $eski['versiyon'] + 1, $_SESSION['user_id']]);

            log_islem($db, 'YONERGE_KURAL_GUNCELLEME', 'regulation_rules', $db->lastInsertId(), "Kural güncellendi (v" . ($eski['versiyon']+1) . "): " . $eski['kural_kodu'], $eski, ['yeni_deger' => $yeni_deger]);
            $msg = "Yönerge kuralı yeni versiyon oluşturularak güncellendi (Eski sürüm arşivde korundu).";
        }
    }

    if ($_POST['action'] === 'ders_ekle') {
        $kod  = trim($_POST['ders_kodu']);
        $ad   = trim($_POST['ders_adi']);
        $akts = (int)$_POST['akts'];
        $kr   = (int)$_POST['kredi'];

        $ins = $db->prepare("INSERT INTO course_catalog (ders_kodu, ders_adi, akts, kredi) VALUES (?, ?, ?, ?)");
        $ins->execute([$kod, $ad, $akts, $kr]);
        log_islem($db, 'KATALOG_DERS_EKLEME', 'course_catalog', $db->lastInsertId(), "Kataloğa yeni ders eklendi: $kod - $ad");
        $msg = "Ders kataloğa eklendi.";
    }

    if ($_POST['action'] === 'katalog_durum') {
        $cid = (int)$_POST['course_id'];
        $aktif = (int)$_POST['is_active'];
        $db->prepare("UPDATE course_catalog SET is_active = ? WHERE id = ?")->execute([$aktif, $cid]);
        log_islem($db, 'KATALOG_DERS_DURUM', 'course_catalog', $cid, "Katalog ders durumu değiştirildi: $aktif");
        $msg = "Katalog dersi durumu güncellendi.";
    }
}

$kurallar = $db->query("SELECT * FROM regulation_rules WHERE is_active = 1 ORDER BY program_turu, id")->fetchAll();
$katalog  = $db->query("SELECT * FROM course_catalog ORDER BY ders_kodu")->fetchAll();

require_once '../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-success py-2 small"><?= e($msg) ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Dinamik Yönerge Eşikleri (Eski Sürümler Silinmez)</h6>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Program</th>
                            <th>Yönerge Kuralı</th>
                            <th>Sürüm</th>
                            <th>Değer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kurallar as $r): ?>
                            <tr>
                                <td><span class="badge bg-primary"><?= e($r['program_turu']) ?></span></td>
                                <td><?= e($r['kural_adi']) ?></td>
                                <td><span class="badge bg-secondary">v<?= $r['versiyon'] ?></span></td>
                                <td>
                                    <form method="POST" class="d-flex gap-1">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="kural_guncelle">
                                        <input type="hidden" name="rule_id" value="<?= $r['id'] ?>">
                                        <input type="number" step="0.01" name="deger" class="form-control form-control-sm" style="width:90px;" value="<?= e($r['deger']) ?>">
                                        <button type="submit" class="btn btn-sm btn-dark">Güncelle</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2">
                <h6 class="mb-0 fw-bold">Ders Kataloğuna Yeni Ders Ekle</h6>
            </div>
            <div class="card-body small">
                <form method="POST" class="row g-2">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="ders_ekle">
                    <div class="col-4"><input type="text" name="ders_kodu" class="form-control form-control-sm" placeholder="Ders Kodu" required></div>
                    <div class="col-8"><input type="text" name="ders_adi" class="form-control form-control-sm" placeholder="Ders Adı" required></div>
                    <div class="col-6"><input type="number" name="akts" class="form-control form-control-sm" placeholder="AKTS" required></div>
                    <div class="col-6"><input type="number" name="kredi" class="form-control form-control-sm" placeholder="Kredi" required></div>
                    <div class="col-12"><button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Kataloğa Ekle</button></div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-2">
                <h6 class="mb-0 fw-bold">Mevcut Ders Kataloğu (Aktif / Pasif Yönetimi)</h6>
            </div>
            <div class="table-responsive" style="max-height: 360px;">
                <table class="table table-sm align-middle mb-0 small">
                    <thead class="table-light">
                        <tr><th>Kod</th><th>Ders Adı</th><th>AKTS</th><th>Durum</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($katalog as $k): ?>
                            <tr>
                                <td class="fw-bold"><?= e($k['ders_kodu']) ?></td>
                                <td><?= e($k['ders_adi']) ?></td>
                                <td><?= $k['akts'] ?></td>
                                <td>
                                    <form method="POST">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="katalog_durum">
                                        <input type="hidden" name="course_id" value="<?= $k['id'] ?>">
                                        <input type="hidden" name="is_active" value="<?= $k['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-sm btn-<?= $k['is_active'] ? 'success' : 'secondary' ?> py-0">
                                            <?= $k['is_active'] ? 'Aktif' : 'Pasif' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
