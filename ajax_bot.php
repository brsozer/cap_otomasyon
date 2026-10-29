<?php
// ajax_bot.php - Selçuk Üniversitesi ÇAP & YDP Asistan Botu
require_once 'config/db.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$mesaj = strtolower(trim($input['mesaj'] ?? ''));
$rol = $_SESSION['role'] ?? 'ogrenci';

$mesaj = str_replace(['İ', 'I', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'], ['i', 'i', 'ğ', 'ü', 'ş', 'ö', 'ç'], $mesaj);
$mesaj = preg_replace('/[^\p{L}\p{N}\s]/u', '', $mesaj);

$cevap = "";

// -------------------------------------------------------------------------
// 1. İKİLLİ / ÇAKIŞAN DURUMLAR (Kullanıcı Teyidi)
// -------------------------------------------------------------------------

// ÇAKIŞMA 1: DERS EKLEME
if ($mesaj === 'ders ekleme' || $mesaj === 'ders ekle' || $mesaj === 'ders nasıl eklenir' || ($mesaj === '1' && ($_SESSION['bekleyen_secim'] ?? '') == 'ders')) {
    if ($mesaj === '1' && ($_SESSION['bekleyen_secim'] ?? '') == 'ders') {
        $cevap = "📚 **Ders Kataloğuna Yeni Ders Ekleme:**\nKoordinatör hesabıyla **'Yönerge & Katalog Ayarları'** (`admin/kurallar.php`) sayfasına giderek fakülte protokol dersini havuza ekleyebilirsiniz.";
        unset($_SESSION['bekleyen_secim']);
    } else {
        $_SESSION['bekleyen_secim'] = 'ders';
        $cevap = "❓ **Hangi ders işleminden bahsediyorsun?**\n1) Fakülte katalog havuzuna yeni ders eklemek\n2) Öğrenci kartına ders ve not işlemek\n\n*(Lütfen **1** veya **2** yazarak belirtin)*";
    }
}
elseif ($mesaj === '2' && ($_SESSION['bekleyen_secim'] ?? '') == 'ders') {
    $cevap = "💻 **Öğrenciye Ders ve Not İşleme:**\nKoordinatör panelinden öğrenci listesine girip **'Öğrenci Kartı & Notlar'** butonuna tıklayarak sol alttaki formdan dersin vize, final ve harf notunu işleyebilirsiniz.";
    unset($_SESSION['bekleyen_secim']);
}

// ÇAKIŞMA 2: TRANSKRİPT / BELGE İŞLEMLERİ
elseif (strpos($mesaj, 'transkript') !== false || strpos($mesaj, 'belge') !== false || strpos($mesaj, 'pdf') !== false || strpos($mesaj, 'indir') !== false) {
    if (strpos($mesaj, 'indir') !== false || strpos($mesaj, 'goruntule') !== false) {
        $cevap = "📄 **Transkript ve Belge İndirme / Görüntüleme:**\n• **Öğrenci Paneli:** Kendi yüklediği transkripti PDF olarak görüntüleyebilir.\n• **Koordinatör / Öğrenci İşleri:** Öğrenci kartındaki 'Arşiv Belgeleri' alanından yüklenen tüm transkript ve imzalı kabul protokollerini indirebilir / inceleyebilir.";
    } else {
        $_SESSION['bekleyen_secim'] = 'belge';
        $cevap = "❓ **Hangi belge işleminden bahsediyorsun?**\n1) Öğrencinin sisteme transkript veya kabul protokolü yüklemesi\n2) Transkript / Belge indirme ve arşivden görüntüleme\n3) Koordinatörün eksik evrak için 'Belge Talebi' oluşturması\n\n*(Lütfen **1**, **2** veya **3** yazarak belirtin)*";
    }
}
elseif ($mesaj === '1' && ($_SESSION['bekleyen_secim'] ?? '') == 'belge') {
    $cevap = "📁 **Öğrenci Belge Yükleme:**\nÖğrenci kendi panelinden (`ogrenci/index.php`) dosya yükleme alanını kullanarak PDF formatındaki transkriptini sisteme yükler.";
    unset($_SESSION['bekleyen_secim']);
}
elseif ($mesaj === '2' && ($_SESSION['bekleyen_secim'] ?? '') == 'belge') {
    $cevap = "📄 **Belge İndirme:** Öğrenci kartındaki arşiv bölümünden yüklenen tüm PDF belgeler versiyonlu (`v1`, `v2`) olarak indirilebilir.";
    unset($_SESSION['bekleyen_secim']);
}
elseif ($mesaj === '3' && ($_SESSION['bekleyen_secim'] ?? '') == 'belge') {
    $cevap = "✉️ **Ek Belge Talebi:** Koordinatör öğrenci detay sayfasındaki mesajlaşma alanından resmi 'Belge Talebi' oluşturarak öğrenciden eksik evrak isteyebilir.";
    unset($_SESSION['bekleyen_secim']);
}

// -------------------------------------------------------------------------
// 2. TEK ANLAMLI ÖZELLİK HAVUZU
// -------------------------------------------------------------------------
else {
    unset($_SESSION['bekleyen_secim']);

    // A. Öğrenci Ekleme & Yönetimi
    if (strpos($mesaj, 'ogrenci ekle') !== false || strpos($mesaj, 'yeni ogrenci') !== false || strpos($mesaj, 'ogrenci kayit') !== false) {
        $cevap = "👨‍💻 **Yeni Öğrenci Tanımlama:**\n1. Koordinatör paneline girin.\n2. Üst menüden **'Öğrenci Ekle'** (`admin/ogrenci_ekle.php`) sayfasına tıklayın.\n3. Öğrenci No, Ad Soyad, E-posta, Program (ÇAP/YDP) ve Anadal bilgilerini girip kaydedin. Şifre otomatik `123456` olur.";
    }
    // B. GANO ve Yönerge Kuralları
    elseif (strpos($mesaj, 'gano') !== false || strpos($mesaj, 'baraj') !== false || strpos($mesaj, 'ortalama') !== false || strpos($mesaj, 'yonerge') !== false) {
        $cevap = "📌 **Resmi Yönerge Eşikleri ve Kurallar:**\n• **ÇAP:** Anadal GANO en az **2.72** olmalıdır. Bir defaya mahsus **2.50**'ye düşebilir; ikinci kez düşerse kayıt silme riski doğar.\n• **YDP:** Anadal GANO en az **2.30** olmalıdır.\n• **AKTS:** ÇAP için toplam 135 AKTS, YDP için 30 AKTS tamamlanmalıdır.";
    }
    // C. Test Hesapları ve Giriş
    elseif (strpos($mesaj, 'sifre') !== false || strpos($mesaj, 'hesap') !== false || strpos($mesaj, 'giris') !== false || strpos($mesaj, 'mail') !== false) {
        $cevap = "🔑 **Ara Sınav Demo Test Hesapları (Tüm Şifreler: 123456):**\n• **Koordinatör:** `koordinator@selcuk.edu.tr`\n• **ÇAP Öğrencisi:** `203311041`\n• **YDP Öğrencisi:** `223301099`\n• **Bölüm Başkanı:** `bolumbaskani@selcuk.edu.tr`\n• **Öğrenci İşleri:** `ogrenciisleri@selcuk.edu.tr`";
    }
    // D. Audit Log (Denetim Günlüğü)
    elseif (strpos($mesaj, 'log') !== false || strpos($mesaj, 'audit') !== false || strpos($mesaj, 'denetim') !== false || strpos($mesaj, 'gecmis') !== false) {
        $cevap = "🛡️ **Değiştirilemez Denetim Günlüğü (Audit Log):**\nSistemde yapılan her kritik işlem; kimin yaptığı, hangi IP adresinden bağlandığı, işlem türü ve eski/yeni verilerin `JSON` halleriyle `audit_logs` tablosunda saklanır. **Bölüm Başkanı** kendi panelinden bu geçmişi şeffafçe izleyebilir.";
    }
    // E. Güvenlik Mimarisi
    elseif (strpos($mesaj, 'guvenlik') !== false || strpos($mesaj, 'csrf') !== false || strpos($mesaj, 'xss') !== false || strpos($mesaj, 'sizma') !== false) {
        $cevap = "🔒 **Güvenlik Altyapısı:**\n• **SQL Injection:** PDO Prepared Statements\n• **XSS:** `htmlspecialchars()` çıktı filtrelemesi\n• **CSRF:** Benzersiz form token koruması\n• **Dosya:** İkili MIME type (`finfo_file`) ve `.htaccess` betik engelleme koruması tam aktiftir.";
    }
    // F. Mesajlaşma ve İletişim
    elseif (strpos($mesaj, 'mesaj') !== false || strpos($mesaj, 'iletisim') !== false || strpos($mesaj, 'yazisma') !== false || strpos($mesaj, 'talep') !== false) {
        $cevap = "💬 **Sistem İçi Mesajlaşma Altyapısı:**\nKoordinatör ve öğrenci panellerinde karşılıklı kalıcı mesajlaşma alanı bulunur. Öğrenci sorununu iletebilir, koordinatör ise buradan resmi 'Belge Talebi' gönderebilir.";
    }
    // G. Canlıya Alma / Deployment
    elseif (strpos($mesaj, 'canli') !== false || strpos($mesaj, 'deploy') !== false || strpos($mesaj, 'docker') !== false || strpos($mesaj, 'render') !== false) {
        $cevap = "🚀 **Canlı Ortama Aktarım (Deployment):**\nProje kök dizinindeki **`Dockerfile`** sayesinde projeyi Railway, Render veya Docker destekli herhangi bir bulut sunucusuna tek tıkla canlıya alabilirsiniz.";
    }
    // H. Öğrenci Paneli / Ders Seçimi
    elseif (strpos($mesaj, 'ogrenci paneli') !== false || strpos($mesaj, 'nasil ders alacagim') !== false || strpos($mesaj, 'ders secimi') !== false) {
        $cevap = "📝 **Öğrenci Ders Seçim İşlemi:**\nÖğrenci kendi paneline (`ogrenci/index.php`) giriş yapar, sol menüden **'Katalogdan Dönem Dersi Seç'** butonuna tıklayarak dersini seçer. Seçilen ders koordinatör onay kuyruğuna düşer.";
    }
    // İ. Şifre Değiştirme / Profil
    elseif (strpos($mesaj, 'sifre degis') !== false || strpos($mesaj, 'profil') !== false || strpos($mesaj, 'cikis') !== false) {
        $cevap = "👤 **Hesap ve Oturum İşlemleri:**\nÜst menüdeki sağ köşede bulunan **'Güvenli Çıkış'** butonuna tıklayarak oturumunuzu güvenle sonlandırabilirsiniz. Şifre işlemleri veritabanında `password_hash()` güvenliğiyle saklanır.";
    }
    // J. Karşılama / Selamlama
    elseif (strpos($mesaj, 'merhaba') !== false || strpos($mesaj, 'selam') !== false || strpos($mesaj, 'hey') !== false || strpos($mesaj, 'yardim') !== false) {
        $cevap = "Selam! Ben Selçuk Üniversitesi ÇAP & YDP Süper Asistanıyım. Transkript yüklemeden not girişine, GANO barajlarından Audit Log denetimlerine, ders katalog eklemeden şifre ve test hesaplarına kadar uygulamadaki **her click ve her özelliği** biliyorum. Ne öğrenmek istersin?";
    }
    // K. Bilinmeyen Durum
    else {
        $cevap = "Bunu tam olarak yakalayamadım :) Sistemde **ders ekleme, transkript indirme/yükleme, GANO barajları, yeni öğrenci kaydı, not girme, test hesapları, belge arşivi, mesajlaşma veya Audit Log** hakkında ne sorsan anlarım. Bir kez daha farklı bir cümleyle dener misin?";
    }
}

// Rol kısıtlaması koruması
if ($rol === 'ogrenci' && (strpos($mesaj, 'katalog') !== false || strpos($mesaj, 'ogrenci ekle') !== false || strpos($mesaj, 'audit') !== false)) {
    $cevap = "⚠️ **Yetki Sınırı (Öğrenci Paneli):**\nBu işlem (katalog yönetimi, öğrenci hesabı açma veya audit log denetimi) yalnızca Koordinatör yetkisindedir.";
}

echo json_encode(['cevap' => $cevap], JSON_UNESCAPED_UNICODE);
exit;
