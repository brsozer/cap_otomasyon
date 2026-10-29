# Selçuk Üniversitesi Bilgisayar Mühendisliği Bölümü - ÇAP ve YDP Öğrenci Takip Otomasyonu

## 1. Kurulum (Mac MAMP / XAMPP)
1. `cap_otomasyon` klasörünü `/Applications/MAMP/htdocs/` içine kopyalayın.
2. `http://localhost:8888/phpMyAdmin` adresine girip `database_setup.sql` dosyasını içe aktarın (Import).
3. Tarayıcıdan `http://localhost:8888/cap_otomasyon/login.php` adresini açın.

## 2. Canlı Ortama (Railway / Render) Alma
1. Bu klasörü GitHub'da yeni bir depoya (repository) yükleyin.
2. [Railway.app](https://railway.app) üzerinde **New Project -> Provision MySQL** seçerek veritabanını açın ve `database_setup.sql` içeriğini çalıştırın.
3. Aynı projede **New -> GitHub Repo** seçerek kodunuzu bağlayın. İçerisindeki `Dockerfile` sayesinde otomatik derlenip canlı URL üretecektir.

## 3. Test Hesapları (Şifre: 123456)
- **Koordinatör:** `koordinator@selcuk.edu.tr`
- **ÇAP Öğrencisi:** `203311041`
- **YDP Öğrencisi:** `223301099`
- **Bölüm Başkanı (Salt Okunur & Denetim):** `bolumbaskani@selcuk.edu.tr`
- **Öğrenci İşleri (Salt Okunur & Arşiv):** `ogrenciisleri@selcuk.edu.tr`
