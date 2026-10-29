<?php
// ogrenciisleri/index.php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
check_access(['ogrenci_isleri']);

$ogrenciler = $db->query("SELECT s.*, u.ad_soyad, u.ogrenci_no FROM students s JOIN users u ON s.user_id = u.id ORDER BY s.id DESC")->fetchAll();
$belgeler = $db->query("SELECT d.*, u.ad_soyad, u.ogrenci_no, s.program_turu FROM student_documents d JOIN students s ON d.student_id = s.id JOIN users u ON s.user_id = u.id ORDER BY d.id DESC")->fetchAll();

require_once '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Fakülte Öğrenci İşleri Bürosu Arşiv ve Takip Paneli</h4>
        <p class="text-muted small mb-0">İmzalı protokoller, ders kayıt formları ve resmi transkript nüshaları.</p>
    </div>
    <span class="badge bg-secondary p-2">Salt Okunur (Arşiv & Doğrulama)</span>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-2"><h6 class="mb-0 fw-bold">Kayıtlı ÇAP ve YDP Öğrencileri</h6></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr><th>Öğrenci No</th><th>Ad Soyad</th><th>Program</th><th>Anadal Bölümü</th><th>Kabul Dönemi</th><th>Anadal GANO</th><th>Durum</th></tr>
            </thead>
            <tbody>
                <?php foreach ($ogrenciler as $o): ?>
                    <tr>
                        <td class="fw-bold"><?= e($o['ogrenci_no']) ?></td>
                        <td><?= e($o['ad_soyad']) ?></td>
                        <td><span class="badge bg-primary"><?= e($o['program_turu']) ?></span></td>
                        <td><?= e($o['anadal_bolum']) ?></td>
                        <td><?= e($o['kabul_donemi']) ?></td>
                        <td><b><?= e($o['anadal_gano']) ?></b></td>
                        <td><span class="badge bg-dark"><?= strtoupper(e($o['durum'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-2"><h6 class="mb-0 fw-bold">Resmi Belge, Protokol ve Transkript Arşivi</h6></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Öğrenci</th>
                    <th>Program</th>
                    <th>Belge Türü</th>
                    <th>Dönem & Sürüm</th>
                    <th>Koordinatör Onayı</th>
                    <th class="text-end">Belge</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($belgeler)): ?>
                    <tr><td colspan="6" class="text-center py-3 text-muted">Arşivde henüz kayıtlı belge bulunmamaktadır.</td></tr>
                <?php else: foreach ($belgeler as $b): ?>
                    <tr>
                        <td class="fw-bold"><?= e($b['ad_soyad']) ?> (<?= e($b['ogrenci_no']) ?>)</td>
                        <td><span class="badge bg-primary"><?= e($b['program_turu']) ?></span></td>
                        <td><?= strtoupper(str_replace('_', ' ', e($b['belge_turu']))) ?></td>
                        <td><?= e($b['akademik_yil']) ?> <?= e($b['donem']) ?> (v<?= $b['versiyon'] ?>)</td>
                        <td><span class="badge bg-<?= $b['onay_durumu']==='onaylandi'?'success':'warning text-dark' ?>"><?= strtoupper(e($b['onay_durumu'])) ?></span></td>
                        <td class="text-end">
                            <a href="<?= e($b['sunucu_dosya_yolu']) ?>" target="_blank" class="btn btn-sm btn-outline-dark">Görüntüle</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
