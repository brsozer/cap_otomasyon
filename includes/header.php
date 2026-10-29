<?php
// includes/header.php
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S.Ü. Bilgisayar Müh. ÇAP & YDP Takip Otomasyonu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../index.php">
            <i class="bi bi-cpu-fill text-info me-2"></i>S.Ü. Bilgisayar Müh. ÇAP/YDP
        </a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="navbar-nav me-auto ms-3">
                <?php if ($_SESSION['role'] === 'koordinator'): ?>
                    <a class="nav-link" href="../admin/index.php">Öğrenci Listesi</a>
                    <a class="nav-link" href="../admin/ogrenci_ekle.php">Öğrenci Ekle</a>
                    <a class="nav-link" href="../admin/kurallar.php">Yönerge & Katalog Ayarları</a>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center">
                <span class="badge bg-secondary me-3 p-2">
                    <i class="bi bi-person-badge me-1"></i><?= e($_SESSION['ad_soyad']) ?> (<?= strtoupper(e($_SESSION['role'])) ?>)
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">Güvenli Çıkış</a>
            </div>
        <?php endif; ?>
    </div>
</nav>
<div class="container pb-5 flex-grow-1">

<!-- ÇAP & YDP Akıllı Asistan Chatbot Arayüzü -->
<div id="capBotContainer" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; font-family: sans-serif;">
    <button id="botToggleBtn" onclick="toggleChat()" style="background: #0d6efd; color: white; border: none; border-radius: 50px; padding: 12px 20px; font-weight: bold; box-shadow: 0 4px 12px rgba(0,0,0,0.2); cursor: pointer;">
        💬 ÇAP & YDP Asistanı
    </button>
    
    <div id="botWindow" style="display: none; width: 320px; height: 420px; background: white; border-radius: 12px; box-shadow: 0 6px 20px rgba(0,0,0,0.15); flex-direction: column; overflow: hidden; margin-bottom: 10px; border: 1px solid #ddd;">
        <div style="background: #0d6efd; color: white; padding: 12px; font-weight: bold; display: flex; justify-content: space-between; align-items: center;">
            <span>🤖 Yönerge & SOP Asistanı</span>
            <button onclick="toggleChat()" style="background: none; border: none; color: white; font-size: 16px; cursor: pointer;">&times;</button>
        </div>
        <div id="botChatArea" style="flex: 1; padding: 12px; overflow-y: auto; background: #f8f9fa; font-size: 13px; display: flex; flex-direction: column; gap: 8px;">
            <div style="background: #e2e3e5; padding: 8px 12px; border-radius: 8px; max-width: 85%;">Merhaba! Size ÇAP/YDP kuralları ve sistem kullanımı hakkında yardımcı olmaktan mutluluk duyarım. Nasıl soru sormak istersiniz?</div>
        </div>
        <div style="padding: 10px; background: white; border-top: 1px solid #ddd; display: flex; gap: 6px;">
            <input type="text" id="botInput" placeholder="Soru sorun (Örn: GANO barajı kaç?)..." style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 6px; font-size: 12px;" onkeypress="if(event.key === 'Enter') sendBotMessage()">
            <button onclick="sendBotMessage()" style="background: #0d6efd; color: white; border: none; padding: 8px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;">Gönder</button>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const savedChat = localStorage.getItem('cap_bot_history');
    if (savedChat) {
        document.getElementById('botChatArea').innerHTML = savedChat;
    }
    
    const savedState = localStorage.getItem('cap_bot_state');
    if (savedState === 'open') {
        document.getElementById('botWindow').style.display = 'flex';
    }
});

function toggleChat() {
    const win = document.getElementById('botWindow');
    if (win.style.display === 'none' || win.style.display === '') {
        win.style.display = 'flex';
        localStorage.setItem('cap_bot_state', 'open');
    } else {
        win.style.display = 'none';
        localStorage.setItem('cap_bot_state', 'closed');
    }
}

function sendBotMessage() {
    const input = document.getElementById('botInput');
    const msg = input.value.trim();
    if (!msg) return;

    const chatArea = document.getElementById('botChatArea');
    chatArea.innerHTML += `<div style="background: #0d6efd; color: white; padding: 8px 12px; border-radius: 8px; max-width: 85%; align-self: flex-end; margin-bottom: 6px;">${msg}</div>`;
    input.value = '';
    chatArea.scrollTop = chatArea.scrollHeight;
    
    localStorage.setItem('cap_bot_history', chatArea.innerHTML);

    fetch('../ajax_bot.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mesaj: msg })
    })
    .then(res => res.json())
    .then(data => {
        chatArea.innerHTML += `<div style="background: #e2e3e5; color: #333; padding: 8px 12px; border-radius: 8px; max-width: 85%; white-space: pre-line; margin-bottom: 6px;">${data.cevap}</div>`;
        chatArea.scrollTop = chatArea.scrollHeight;
        localStorage.setItem('cap_bot_history', chatArea.innerHTML);
    })
    .catch(err => {
        chatArea.innerHTML += `<div style="background: #f8d7da; color: #721c24; padding: 8px 12px; border-radius: 8px; max-width: 85%; margin-bottom: 6px;">Bağlantı hatası oluştu.</div>`;
        localStorage.setItem('cap_bot_history', chatArea.innerHTML);
    });
}
</script>
