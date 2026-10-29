<?php
// bolumbaskani/index.php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
check_access(['bolum_baskani']);

$ogrenciler = $db->query("SELECT s.*, u.ad_soyad, u.ogrenci_no FROM students s JOIN users u ON s.user_id = u.id ORDER BY s.id DESC")->fetchAll();
$logs = $db->query("SELECT a.*, u.ad_soyad, u.role FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.id DESC LIMIT 100")->fetchAll();

require_once '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Bölüm Başkanlığı Denetim Paneli</h4>
        <p class="text-muted small mb-0">Kayıtlar ve değiştirilemez işlem geçmişi (Audit Log) salt okunur olarak izlenmektedir.</p>
    </div>
    <span class="badge bg-secondary p-2">Salt Okunur (Read-Only)</span>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-2"><h6 class="mb-0 fw-bold">Bölüm ÇAP & YDP Öğrenci Özeti</h6></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr><th>Öğrenci No</th><th>Ad Soyad</th><th>Program</th><th>Anadal</th><th>GANO</th><th>Tamamlanan / Kalan AKTS</th><th>Durum</th></tr>
            </thead>
            <tbody>
                <?php foreach ($ogrenciler as $o): 
                    $analiz = degerlendir_ogrenci_kurallari($db, $o);
                ?>
                    <tr>
                        <td class="fw-bold"><?= e($o['ogrenci_no']) ?></td>
                        <td><?= e($o['ad_soyad']) ?></td>
                        <td><span class="badge bg-primary"><?= e($o['program_turu']) ?></span></td>
                        <td><?= e($o['anadal_bolum']) ?></td>
                        <td><?= e($o['anadal_gano']) ?></td>
                        <td>Geçilen: <b><?= $analiz['tamamlanan_akts'] ?></b> / Kalan: <b><?= $analiz['kalan_akts'] ?></b> AKTS</td>
                        <td><span class="badge bg-dark"><?= strtoupper(e($o['durum'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-2"><h6 class="mb-0 fw-bold">Değiştirilemez Sistem Denetim Günlüğü (Audit Log)</h6></div>
    <div class="table-responsive">
        <table class="table table-striped align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Tarih / IP</th>
                    <th>Kullanıcı</th>
                    <th>İşlem & Tablo</th>
                    <th>Açıklama</th>
                    <th>Eski / Yeni Değer (JSON)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td class="text-muted"><?= e($l['islem_tarihi']) ?><br><code><?= e($l['ip_adresi']) ?></code></td>
                        <td><b><?= e($l['ad_soyad'] ?: 'Sistem') ?></b><br><span class="badge bg-secondary"><?= e($l['role']) ?></span></td>
                        <td><code><?= e($l['islem_turu']) ?></code><br><small><?= e($l['tablo_adi']) ?> #<?= (int)$l['kayit_id'] ?></small></td>
                        <td><?= e($l['aciklama']) ?></td>
                        <td style="max-width: 280px;" class="text-truncate">
                            <?php if ($l['eski_deger'] || $l['yeni_deger']): ?>
                                <small class="text-danger">Eski: <?= e($l['eski_deger']) ?></small><br>
                                <small class="text-success">Yeni: <?= e($l['yeni_deger']) ?></small>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
