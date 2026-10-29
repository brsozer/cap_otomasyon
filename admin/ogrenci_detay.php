<?php
// admin/ogrenci_detay.php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
check_access(['koordinator']);

$student_id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT s.*, u.ad_soyad, u.ogrenci_no, u.email, u.id as uid FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();
if (!$student) { die("Öğrenci bulunamadı."); }

// Okunmamış mesajları okundu işaretle
$db->prepare("UPDATE messages SET okundu_mu = 1 WHERE student_id = ? AND alici_user_id = ?")->execute([$student_id, $_SESSION['user_id']]);

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'not_kaydet') {
        $course_id = (int)$_POST['course_id'];
        $yil       = trim($_POST['akademik_yil']);
        $donem     = $_POST['donem'];
        $vize      = $_POST['vize_notu'] !== '' ? (float)$_POST['vize_notu'] : null;
        $final     = $_POST['final_notu'] !== '' ? (float)$_POST['final_notu'] : null;
        $ort       = ($vize !== null && $final !== null) ? round(($vize * 0.4) + ($final * 0.6), 2) : null;
        $harf      = strtoupper(trim($_POST['harf_notu']));
        $durum     = $_POST['durum'];
        $gerekce   = trim($_POST['degisiklik_gerekcesi'] ?? 'Koordinatör tarafından doğrudan işlendi.');

        $cntStmt = $db->prepare("SELECT COUNT(*) FROM student_courses WHERE student_id = ? AND course_id = ?");
        $cntStmt->execute([$student_id, $course_id]);
        $deneme = (int)$cntStmt->fetchColumn() + 1;

        $ins = $db->prepare("INSERT INTO student_courses 
            (student_id, course_id, akademik_yil, donem, deneme_sayisi, vize_notu, final_notu, sayisal_ortalama, harf_notu, durum, onay_durumu, degisiklik_gerekcesi, islem_yapan_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'onaylandi', ?, ?)");
        $ins->execute([$student_id, $course_id, $yil, $donem, $deneme, $vize, $final, $ort, $harf, $durum, $gerekce, $_SESSION['user_id']]);

        log_islem($db, 'DERS_VE_NOT_GIRISI', 'student_courses', $db->lastInsertId(), 
            "Koordinatör {$student['ogrenci_no']} nolu öğrenciye ders/not işledi ($harf - $durum).", null, $_POST);
        $msg = "Ders ve not bilgisi onaylı olarak kaydedildi.";
    }

    if ($action === 'ders_guncelle') {
        $sc_id      = (int)$_POST['sc_id'];
        $eskiStmt   = $db->prepare("SELECT * FROM student_courses WHERE id = ?");
        $eskiStmt->execute([$sc_id]);
        $eski       = $eskiStmt->fetch();

        $vize       = $_POST['vize_notu'] !== '' ? (float)$_POST['vize_notu'] : null;
        $final      = $_POST['final_notu'] !== '' ? (float)$_POST['final_notu'] : null;
        $ort        = ($vize !== null && $final !== null) ? round(($vize * 0.4) + ($final * 0.6), 2) : null;
        $harf       = strtoupper(trim($_POST['harf_notu']));
        $durum      = $_POST['durum'];
        $onay       = $_POST['onay_durumu'];
        $gerekce    = trim($_POST['degisiklik_gerekcesi']);

        $upd = $db->prepare("UPDATE student_courses SET vize_notu=?, final_notu=?, sayisal_ortalama=?, harf_notu=?, durum=?, onay_durumu=?, degisiklik_gerekcesi=?, islem_yapan_id=? WHERE id=?");
        $upd->execute([$vize, $final, $ort, $harf, $durum, $onay, $gerekce, $_SESSION['user_id'], $sc_id]);

        log_islem($db, 'NOT_VE_ONAY_GUNCELLEME', 'student_courses', $sc_id, 
            "Ders/Not kaydı güncellendi. Gerekçe: $gerekce", $eski, $_POST);
        $msg = "Kayıt güncellendi ve denetim günlüğüne işlendi.";
    }
    // Mesajı / Talebi Silme
    if ($action === 'mesaj_sil') {
        $msg_id = (int)$_POST['msg_id'];
        
        // Ekli dosya varsa sunucudan da kaldıralım
        $mStmt = $db->prepare("SELECT ekli_dosya_yolu FROM messages WHERE id = ? AND student_id = ?");
        $mStmt->execute([$msg_id, $student_id]);
        $mKayit = $mStmt->fetch();
        if ($mKayit && !empty($mKayit['ekli_dosya_yolu']) && file_exists($mKayit['ekli_dosya_yolu'])) {
            @unlink($mKayit['ekli_dosya_yolu']);
        }

        $del = $db->prepare("DELETE FROM messages WHERE id = ? AND student_id = ?");
        $del->execute([$msg_id, $student_id]);

        log_islem($db, 'MESAJ_SILME', 'messages', $msg_id, "Koordinatör mesajı/belge talebini sildi.");
        $msg = "Mesaj / Belge talebi silindi.";
    }

    // Belge Talebi Durumunu Güncelleme
    if ($action === 'talep_durum_guncelle') {
        $msg_id  = (int)$_POST['msg_id'];
        $tdurum  = $_POST['talep_durumu'];

        $upd = $db->prepare("UPDATE messages SET talep_durumu = ? WHERE id = ? AND student_id = ?");
        $upd->execute([$tdurum, $msg_id, $student_id]);

        log_islem($db, 'TALEP_DURUM_GUNCELLE', 'messages', $msg_id, "Belge talebi durumu '$tdurum' olarak güncellendi.");
        $msg = "Talep durumu güncellendi.";
    }

    if ($action === 'belge_karar') {
        $doc_id = (int)$_POST['doc_id'];
        $karar  = $_POST['onay_durumu'];
        $not    = trim($_POST['red_veya_talep_notu']);

        $upd = $db->prepare("UPDATE student_documents SET onay_durumu = ?, red_veya_talep_notu = ? WHERE id = ?");
        $upd->execute([$karar, $not, $doc_id]);

        log_islem($db, 'BELGE_DEGERLENDIRME', 'student_documents', $doc_id, "Belge durumu '$karar' yapıldı. Açıklama: $not");
        $msg = "Belge durumu güncellendi.";
    }

    if ($action === 'kart_guncelle') {
        $eski = $student;
        $gano = (float)$_POST['anadal_gano'];
        $kaynak = trim($_POST['anadal_gano_kaynak']);
        $dusme = (int)$_POST['gano_dusme_sayisi'];
        $almama = (int)$_POST['ust_uste_ders_almama'];
        $ozel_not = trim($_POST['koordinator_ozel_notu']);
        $durum = $_POST['durum'];
        $gerekce = trim($_POST['durum_gerekcesi']);

        $upd = $db->prepare("UPDATE students SET anadal_gano=?, anadal_gano_kaynak=?, gano_dusme_sayisi=?, ust_uste_ders_almama=?, koordinator_ozel_notu=?, durum=?, durum_gerekcesi=? WHERE id=?");
        $upd->execute([$gano, $kaynak, $dusme, $almama, $ozel_not, $durum, $gerekce, $student_id]);

        log_islem($db, 'OGRENCI_KARTI_KARAR', 'students', $student_id, "Öğrenci kartı/GANO/durum ($durum) güncellendi.", $eski, $_POST);
        header("Location: ogrenci_detay.php?id=$student_id&ok=1");
        exit;
    }

    if ($action === 'mesaj_gonder') {
        $konu  = trim($_POST['konu']);
        $mesaj = trim($_POST['mesaj']);
        $talep = isset($_POST['belge_talebi_mi']) ? 1 : 0;
        $tdurum = $talep ? 'bekliyor' : 'yok';
        $ek_yol = null;

        if (isset($_FILES['ek_dosya']) && $_FILES['ek_dosya']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['ek_dosya'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (in_array($mime, ['application/pdf', 'image/jpeg', 'image/png']) && $file['size'] <= 5*1024*1024) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $hedef = "../uploads/msg_" . time() . "_" . bin2hex(random_bytes(3)) . "." . $ext;
                if (move_uploaded_file($file['tmp_name'], $hedef)) { $ek_yol = $hedef; }
            }
        }

        $ins = $db->prepare("INSERT INTO messages (student_id, gonderen_user_id, alici_user_id, konu, mesaj, ekli_dosya_yolu, belge_talebi_mi, talep_durumu) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$student_id, $_SESSION['user_id'], $student['uid'], $konu, $mesaj, $ek_yol, $talep, $tdurum]);

        log_islem($db, 'MESAJ_VE_BELGE_TALEBI', 'messages', $db->lastInsertId(), "Koordinatör öğrenciye mesaj/belge talebi gönderdi: $konu");
        $msg = "Mesaj / Belge talebi öğrenciye iletildi.";
    }
}

$analiz = degerlendir_ogrenci_kurallari($db, $student);
$katalog = $db->query("SELECT * FROM course_catalog WHERE is_active = 1 ORDER BY ders_kodu")->fetchAll();

$dersler = $db->prepare("SELECT sc.*, cc.ders_kodu, cc.ders_adi, cc.akts FROM student_courses sc JOIN course_catalog cc ON sc.course_id = cc.id WHERE sc.student_id = ? ORDER BY sc.akademik_yil DESC, sc.id DESC");
$dersler->execute([$student_id]);
$alinan_dersler = $dersler->fetchAll();

$belgeler = $db->prepare("SELECT * FROM student_documents WHERE student_id = ? ORDER BY id DESC");
$belgeler->execute([$student_id]);
$belge_listesi = $belgeler->fetchAll();

$mesajlar = $db->prepare("SELECT m.*, u.ad_soyad FROM messages m JOIN users u ON m.gonderen_user_id = u.id WHERE m.student_id = ? ORDER BY m.id DESC");
$mesajlar->execute([$student_id]);
$mesaj_listesi = $mesajlar->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm mb-1">&larr; Listeye Dön</a>
        <h4 class="fw-bold mb-0"><?= e($student['ad_soyad']) ?> (<?= e($student['ogrenci_no']) ?>)</h4>
        <span class="badge bg-primary"><?= e($student['program_turu']) ?></span>
        <span class="text-muted small ms-2">Anadal: <?= e($student['anadal_bolum']) ?> | Kabul: <?= e($student['kabul_donemi']) ?></span>
    </div>
    <div class="text-end">
        <span class="badge bg-success fs-6">Tamamlanan: <?= $analiz['tamamlanan_akts'] ?> / <?= $analiz['hedef_akts'] ?> AKTS</span>
        <span class="badge bg-danger fs-6 ms-1">Kalan: <?= $analiz['kalan_akts'] ?> AKTS</span>
        <span class="badge bg-dark fs-6 ms-1">Bölüm Ort: <?= $analiz['bolum_ortalamasi'] ?></span>
    </div>
</div>

<?php if ($msg || isset($_GET['ok'])): ?>
    <div class="alert alert-success py-2 small">İşlem başarıyla kaydedildi ve denetim günlüğüne (Audit Log) işlendi.</div>
<?php endif; ?>

<?php foreach ($analiz['uyarilar'] as $u): ?>
    <div class="alert alert-<?= $u['tip'] ?> py-2 small fw-bold mb-2">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= e($u['mesaj']) ?>
    </div>
<?php endforeach; ?>

<div class="row g-4 mt-1">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-dark text-white py-2">
                <h6 class="mb-0 small fw-bold">1. Akademik Durum, GANO & Gizli Not</h6>
            </div>
            <div class="card-body small">
                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="kart_guncelle">
                    <div class="row g-2 mb-2">
                        <div class="col-5">
                            <label class="fw-bold">Anadal GANO</label>
                            <input type="number" step="0.01" name="anadal_gano" class="form-control form-control-sm" value="<?= e($student['anadal_gano']) ?>">
                        </div>
                        <div class="col-7">
                            <label class="fw-bold">GANO Dayanak/Kaynak</label>
                            <input type="text" name="anadal_gano_kaynak" class="form-control form-control-sm" value="<?= e($student['anadal_gano_kaynak']) ?>" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="fw-bold">GANO Düşme Sayısı</label>
                            <input type="number" name="gano_dusme_sayisi" class="form-control form-control-sm" value="<?= (int)$student['gano_dusme_sayisi'] ?>">
                        </div>
                        <div class="col-6">
                            <label class="fw-bold">Ders Almama (Dönem)</label>
                            <input type="number" name="ust_uste_ders_almama" class="form-control form-control-sm" value="<?= (int)$student['ust_uste_ders_almama'] ?>">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="fw-bold text-danger">Koordinatör Gizli Notu (Öğrenci Göremez)</label>
                        <textarea name="koordinator_ozel_notu" class="form-control form-control-sm" rows="2"><?= e($student['koordinator_ozel_notu']) ?></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="fw-bold">Mezuniyet / Sertifika / Kayıt Kararı</label>
                        <select name="durum" class="form-select form-select-sm">
                            <option value="aktif" <?= $student['durum']==='aktif'?'selected':'' ?>>Aktif Öğrenci</option>
                            <option value="mezun" <?= $student['durum']==='mezun'?'selected':'' ?>>Mezuniyet Kararı Verildi (ÇAP)</option>
                            <option value="sertifika_aldi" <?= $student['durum']==='sertifika_aldi'?'selected':'' ?>>Yandal Sertifikası Verildi (YDP)</option>
                            <option value="kaydi_silindi" <?= $student['durum']==='kaydi_silindi'?'selected':'' ?>>Kaydı Silindi / İlişik Kesildi</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Karar / Değişiklik Gerekçesi</label>
                        <input type="text" name="durum_gerekcesi" class="form-control form-control-sm" value="<?= e($student['durum_gerekcesi']) ?>" placeholder="Kurul karar no veya gerekçe">
                    </div>
                    <button type="submit" class="btn btn-dark btn-sm w-100 fw-bold">Kartı ve Kararı Güncelle</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-2">
                <h6 class="mb-0 small fw-bold">2. Bölüm Dersi & Notu İşle (Geriye Dönük Dahil)</h6>
            </div>
            <div class="card-body small">
                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="not_kaydet">
                    <div class="mb-2">
                        <label class="fw-bold">Bilgisayar Müh. Dersi *</label>
                        <select name="course_id" class="form-select form-select-sm" required>
                            <?php foreach ($katalog as $k): ?>
                                <option value="<?= $k['id'] ?>"><?= e($k['ders_kodu']) ?> - <?= e($k['ders_adi']) ?> (<?= $k['akts'] ?> AKTS)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="fw-bold">Akademik Yıl</label>
                            <input type="text" name="akademik_yil" class="form-control form-control-sm" value="2025-2026" required>
                        </div>
                        <div class="col-6">
                            <label class="fw-bold">Dönem</label>
                            <select name="donem" class="form-select form-select-sm">
                                <option value="Guz">Güz</option>
                                <option value="Bahar">Bahar</option>
                                <option value="Yaz">Yaz</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="fw-bold">Vize</label>
                            <input type="number" step="0.1" name="vize_notu" class="form-control form-control-sm">
                        </div>
                        <div class="col-4">
                            <label class="fw-bold">Final</label>
                            <input type="number" step="0.1" name="final_notu" class="form-control form-control-sm">
                        </div>
                        <div class="col-4">
                            <label class="fw-bold">Harf Notu</label>
                            <input type="text" name="harf_notu" class="form-control form-control-sm" placeholder="AA / FF">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="fw-bold">Ders Durumu</label>
                        <select name="durum" class="form-select form-select-sm">
                            <option value="gecti">Geçti</option>
                            <option value="kaldi">Kaldı</option>
                            <option value="muaf">Muaf (Özel Şartlı)</option>
                            <option value="alindi">Alındı (Devam Ediyor)</option>
                            <option value="planlandi">Planlandı</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="fw-bold">İşlem Gerekçesi / Açıklaması</label>
                        <input type="text" name="degisiklik_gerekcesi" class="form-control form-control-sm" value="Transkript kontrolü ile işlendi" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Dersi ve Notu Kaydet</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-2">
                <h6 class="mb-0 fw-bold">3. Öğrencinin ÇAP / YDP Dersleri ve Not Girişi (Yalnızca Koordinatör Düzenler)</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Ders & Deneme</th>
                            <th>Dönem</th>
                            <th>Vize / Final / Harf / Durum / Onay & Gerekçe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alinan_dersler as $d): ?>
                            <tr>
                                <td>
                                    <b><?= e($d['ders_kodu']) ?></b> (<?= $d['akts'] ?> AKTS)<br>
                                    <?= e($d['ders_adi']) ?>
                                    <span class="badge bg-light text-dark border"><?= $d['deneme_sayisi'] ?>. Deneme</span>
                                </td>
                                <td><?= e($d['akademik_yil']) ?><br><?= e($d['donem']) ?></td>
                                <td>
                                    <form method="POST" class="row g-1 align-items-center">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="ders_guncelle">
                                        <input type="hidden" name="sc_id" value="<?= $d['id'] ?>">
                                        <div class="col-2">
                                            <input type="number" step="0.1" name="vize_notu" class="form-control form-control-sm" placeholder="Vize" value="<?= e($d['vize_notu']) ?>">
                                        </div>
                                        <div class="col-2">
                                            <input type="number" step="0.1" name="final_notu" class="form-control form-control-sm" placeholder="Final" value="<?= e($d['final_notu']) ?>">
                                        </div>
                                        <div class="col-2">
                                            <input type="text" name="harf_notu" class="form-control form-control-sm" placeholder="Harf" value="<?= e($d['harf_notu']) ?>">
                                        </div>
                                        <div class="col-3">
                                            <select name="durum" class="form-select form-select-sm">
                                                <?php foreach (['planlandi','alindi','gecti','kaldi','muaf','cikarildi'] as $st): ?>
                                                    <option value="<?= $st ?>" <?= $d['durum']===$st?'selected':'' ?>><?= strtoupper($st) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-3">
                                            <select name="onay_durumu" class="form-select form-select-sm">
                                                <option value="bekliyor" <?= $d['onay_durumu']==='bekliyor'?'selected':'' ?>>BEKLİYOR</option>
                                                <option value="onaylandi" <?= $d['onay_durumu']==='onaylandi'?'selected':'' ?>>ONAYLI (Kilitli)</option>
                                                <option value="reddedildi" <?= $d['onay_durumu']==='reddedildi'?'selected':'' ?>>REDDEDİLDİ</option>
                                                <option value="iptal" <?= $d['onay_durumu']==='iptal'?'selected':'' ?>>İPTAL</option>
                                            </select>
                                        </div>
                                        <div class="col-9 mt-1">
                                            <input type="text" name="degisiklik_gerekcesi" class="form-control form-control-sm" placeholder="Değişiklik / İptal gerekçesi" value="<?= e($d['degisiklik_gerekcesi']) ?>" required>
                                        </div>
                                        <div class="col-3 mt-1">
                                            <button type="submit" class="btn btn-sm btn-success w-100 fw-bold">Kaydet</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-2">
                <h6 class="mb-0 fw-bold">4. Öğrenci Doküman ve Transkript Arşivi (Öğrenci Yükler, Koordinatör Onaylar)</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Belge Türü & Dönem</th>
                            <th>Dosya</th>
                            <th>Durum</th>
                            <th>Onay / Red İşlemi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($belge_listesi)): ?>
                            <tr><td colspan="4" class="text-center py-3 text-muted">Henüz yüklenmiş belge yok.</td></tr>
                        <?php else: foreach ($belge_listesi as $b): ?>
                            <tr>
                                <td>
                                    <b><?= strtoupper(str_replace('_', ' ', e($b['belge_turu']))) ?></b><br>
                                    <span class="text-muted"><?= e($b['akademik_yil']) ?> <?= e($b['donem']) ?> (v<?= $b['versiyon'] ?>)</span>
                                </td>
                                <td>
                                    <a href="<?= e($b['sunucu_dosya_yolu']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-file-earmark-pdf"></i> İncele
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $b['onay_durumu']==='onaylandi'?'success':($b['onay_durumu']==='reddedildi'?'danger':'warning text-dark') ?>">
                                        <?= strtoupper(e($b['onay_durumu'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="d-flex gap-1">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="belge_karar">
                                        <input type="hidden" name="doc_id" value="<?= $b['id'] ?>">
                                        <select name="onay_durumu" class="form-select form-select-sm" style="width:110px;">
                                            <option value="onaylandi" <?= $b['onay_durumu']==='onaylandi'?'selected':'' ?>>Onayla</option>
                                            <option value="reddedildi" <?= $b['onay_durumu']==='reddedildi'?'selected':'' ?>>Reddet</option>
                                        </select>
                                        <input type="text" name="red_veya_talep_notu" class="form-control form-control-sm" placeholder="Açıklama/Gerekçe" value="<?= e($b['red_veya_talep_notu']) ?>">
                                        <button type="submit" class="btn btn-sm btn-dark">Uygula</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-2">
                <h6 class="mb-0 fw-bold">5. Öğrenci İle Kalıcı Yazışma & Ek Belge İsteği</h6>
            </div>
            <div class="card-body small">
                <form method="POST" enctype="multipart/form-data" class="mb-3 border-bottom pb-3">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="mesaj_gonder">
                    <div class="row g-2 mb-2">
                        <div class="col-md-5">
                            <input type="text" name="konu" class="form-control form-control-sm" placeholder="Konu (Örn: Eksik Transkript Belgesi)" required>
                        </div>
                        <div class="col-md-4">
                            <input type="file" name="ek_dosya" class="form-control form-control-sm" accept=".pdf,.jpg,.png">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold">Gönder</button>
                        </div>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="belge_talebi_mi" id="btalep">
                        <label class="form-check-label fw-bold text-danger" for="btalep">Resmi Ek Belge Talebi Olarak İşaretle (İzlenebilir Talep)</label>
                    </div>
                    <textarea name="mesaj" class="form-control form-control-sm" rows="2" placeholder="Mesajınız..." required></textarea>
                </form>

                <?php foreach ($mesaj_listesi as $m): ?>
                    <div class="p-2 mb-2 rounded bg-light border">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong><?= e($m['ad_soyad']) ?> — <?= e($m['konu']) ?></strong>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted small"><?= e($m['gonderim_tarihi']) ?></span>
                                <!-- Mesajı / Talebi Sil Butonu -->
                                <form method="POST" onsubmit="return confirm('Bu mesajı/talebi silmek istediğinize emin misiniz?');" class="d-inline">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="mesaj_sil">
                                    <input type="hidden" name="msg_id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Sil"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        <div class="mt-1"><?= e($m['mesaj']) ?></div>
                        
                        <?php if ($m['ekli_dosya_yolu']): ?>
                            <a href="<?= e($m['ekli_dosya_yolu']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary mt-1"><i class="bi bi-paperclip"></i> Ekli Dosya</a>
                        <?php endif; ?>
                        
                        <?php if ($m['belge_talebi_mi']): ?>
                            <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                                <span class="badge bg-warning text-dark">Talep Durumu: <?= strtoupper(e($m['talep_durumu'])) ?></span>
                                
                                <!-- Hızlı Durum Değiştirme Formu -->
                                <form method="POST" class="d-flex align-items-center gap-1">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="talep_durum_guncelle">
                                    <input type="hidden" name="msg_id" value="<?= $m['id'] ?>">
                                    <select name="talep_durumu" class="form-select form-select-sm" style="font-size: 11px; height: 28px; width: 130px;">
                                        <option value="bekliyor" <?= $m['talep_durumu']==='bekliyor'?'selected':'' ?>>Bekliyor</option>
                                        <option value="iptal" <?= $m['talep_durumu']==='iptal'?'selected':'' ?>>İptal Edildi</option>
                                        <option value="tamamlandi" <?= $m['talep_durumu']==='tamamlandi'?'selected':'' ?>>Tamamlandı</option>
                                    </select>
                                    <button type="submit" class="btn btn-dark btn-sm py-0 px-2" style="font-size: 11px; height: 28px;">Güncelle</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
