<?php

if (isset($_POST['send_sms'])) {
    $gsm = trim($_POST['gsm_number']);
    $tasks = json_decode(file_get_contents("tasks.json"), true);
    $message = "";

    foreach ($tasks as $rapor) {
        if (isset($rapor['company']) && isset($rapor['tasks'])) {
            $message .= $rapor['company'] . ":
";
            foreach ($rapor['tasks'] as $t) {
                if (!empty($t['completed'])) {
                    $message .= "- " . $t['text'] . "\n";
                }
            }
            $message .= "\n";
        }
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<mainbody>
    <header>
        <company>NETGSM</company>
        <usercode>5336082353</usercode>
        <password>Murat4412*</password>
        <type>1:n</type>
        <msgheader>POLATLAR</msgheader>
    </header>
    <body>
        <msg><![CDATA[' . $message . ']]></msg>
        <no>' . $gsm . '</no>
    </body>
</mainbody>';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.netgsm.com.tr/sms/send/xml");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/xml']);
    $result = curl_exec($ch);
    curl_close($ch);
}


session_start();

// Kullanıcılar (şifreler basit metin, gerçek projede hash'lenmeli)
$users = [
    'murat' => '1111',
    'burak' => 'b1235',
    'soner' => 's1235',
    'azizcan' => 'a1235'
];

// Eğer session users boşsa $users dizisini kopyala
if (!isset($_SESSION['users'])) {
    $_SESSION['users'] = $users;
}

// POST işlemleri ve yönlendirmeler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Giriş işlemi
    if (isset($_POST['login'])) {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        if (isset($_SESSION['users'][$username]) && $_SESSION['users'][$username] === $password) {
            $_SESSION['user'] = $username;
            header("Location: index.php");
            exit();
        } else {
            $error = "Hatalı kullanıcı adı veya şifre!";
        }
    }

    // Rapor gönderme
    if (isset($_POST['submitReport'])) {
        $company = trim($_POST['companyName'] ?? '');
        $tasks = $_POST['tasks'] ?? [];

        if ($company === '' || count($tasks) === 0) {
            $error = 'Şirket adı girin ve en az bir görev seçin.';
        } else {
            $file = 'reports.json';

            if (file_exists($file)) {
                $reportsData = json_decode(file_get_contents($file), true);
                if (!is_array($reportsData)) {
                    $reportsData = [];
                }
            } else {
                $reportsData = [];
            }

            $reportsData[] = [
                'company' => $company,
                'tasks' => $tasks,
                'added_by' => $_SESSION['user']
            ];

            file_put_contents($file, json_encode($reportsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Global bildirim sayacını güncelle
            $notificationsFile = 'notifications.json';
            $notifications = file_exists($notificationsFile) ? json_decode(file_get_contents($notificationsFile), true) : [];
            $notifications['global_count'] = ($notifications['global_count'] ?? 0) + 1;
            file_put_contents($notificationsFile, json_encode($notifications, JSON_PRETTY_PRINT));

            header("Location: index.php");
            exit();
        }
    }

    // Yeni görev ekleme
    if (isset($_POST['addTask'])) {
        $newTask = trim($_POST['newTask'] ?? '');
        if ($newTask === '') {
            $error = 'Görev girin.';
        } else {
            if (!isset($_SESSION['extraTasks'])) {
                $_SESSION['extraTasks'] = [];
            }
            $_SESSION['extraTasks'][] = $newTask;
            header("Location: index.php");
            exit();
        }
    }

    // Yeni kullanıcı ekleme
    if (isset($_POST['addUser'])) {
        if (!isset($_SESSION['user']) || $_SESSION['user'] !== 'burak') {
            $error = 'Yetkisiz işlem.';
        } else {
            $newUser = trim($_POST['newUsername'] ?? '');
            $newPass = trim($_POST['newPassword'] ?? '');
            if ($newUser === '' || $newPass === '') {
                $error = 'Boş bırakmayın.';
            } else {
                if (!isset($_SESSION['users'])) {
                    $_SESSION['users'] = $users;
                }
                if (isset($_SESSION['users'][$newUser])) {
                    $error = 'Kullanıcı zaten var.';
                } else {
                    $_SESSION['users'][$newUser] = $newPass;
                    header("Location: index.php");
                    exit();
                }
            }
        }
    }
}

// Silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deleteReport'])) {
    if (isset($_SESSION['user']) && $_SESSION['user'] === 'burak') {
        $file = 'reports.json';
        if (file_exists($file)) {
            $reportsData = json_decode(file_get_contents($file), true);
            $indexToDelete = intval($_POST['reportIndex']);
            if (isset($reportsData[$indexToDelete])) {
                array_splice($reportsData, $indexToDelete, 1);
                file_put_contents($file, json_encode($reportsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
        header("Location: index.php#gecmis-raporlar");
        exit();
    }
}

// Bildirimleri sıfırlama
if (isset($_GET['reset_notifications'])) {
    $notificationsFile = 'notifications.json';
    if (file_exists($notificationsFile)) {
        $notifications = json_decode(file_get_contents($notificationsFile), true);
        $notifications['global_count'] = 0;
        file_put_contents($notificationsFile, json_encode($notifications, JSON_PRETTY_PRINT));
    }
    header("Location: index.php#gecmis-raporlar");
    exit();
}

// Çıkış işlemi
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Global bildirim sayacını al
$notificationCount = 0;
$notificationsFile = 'notifications.json';
if (file_exists($notificationsFile)) {
    $notifications = json_decode(file_get_contents($notificationsFile), true);
    $notificationCount = $notifications['global_count'] ?? 0;
}

// Giriş yapılmamışsa giriş formunu göster
if (!isset($_SESSION['user'])) {
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Giriş Yap</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        form {
            background: white;
            padding: 20px;
            border-radius: 5px;
            width: 300px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        input[type=text], input[type=password] {
            width: 100%;
            padding: 8px;
            margin: 5px 0 10px 0;
            box-sizing: border-box;
        }
        button {
            padding: 10px;
            width: 100%;
            background: #333;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background: #555;
        }
        .error {
            color: red;
            margin-bottom: 10px;
        }
        .password-container {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 35px;
            cursor: pointer;
            transform: translateY(-50%);
        }
    </style>
</head>
<body>
    <form method="post" action="index.php">
        <div style="text-align: center; margin-bottom: 15px;">
        <div class="container">
            <img src="logo.png" alt="Resim" style="max-width: 300px; height: auto;">
        </div>
        <h2>Giriş Yap</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <label>Kullanıcı Adı:<br><input type="text" name="username" required></label><br>
        <div class="password-container">
            <label>Şifre:<br><input type="password" name="password" id="password" required></label>
            <span class="toggle-password" onclick="togglePassword()">👁️</span>
        </div>
        <button type="submit" name="login">Giriş Yap</button>
    </form>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
            } else {
                passwordInput.type = 'password';
            }
        }
    </script>
</body>
</html>
<?php
    exit();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Görev Takip Sistemi</title>
    <style>
        body { font-family: Arial, sans-serif; background-color:rgb(247, 242, 242); margin: 0; padding: 0; }
        nav { background-color: #333; }
        nav ul { list-style-type: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap; }
        nav li { flex: 1; min-width: 120px; }
        nav a { display: block; padding: 15px; color: white; text-align: center; text-decoration: none; cursor: pointer; }
        nav a:hover { background-color: #575757; }
        section { padding: 20px; display: none; }
        section.active { display: block; }
        h2 { color: #333; }
        .task-list label { display: block; margin: 10px 0; cursor: pointer; }
        .task-list input[type="checkbox"]:checked + span { text-decoration: line-through; color: gray; }
        .submit-btn, .add-btn, .logout-btn, .add-user-btn { margin-top: 10px; padding: 10px 15px; background-color: #333; color: white; border: none; cursor: pointer; }
        .submit-btn:hover, .add-btn:hover, .logout-btn:hover, .add-user-btn:hover { background-color: #575757; }
        .history-item { background: #fff; padding: 10px; margin: 10px 0; border-radius: 5px; cursor: pointer; }
        .modal { display: none; position: fixed; top: 20%; left: 50%; transform: translate(-50%, -20%); background: white; padding: 20px; border: 1px solid #333; border-radius: 10px; z-index: 1000; max-width: 90%; max-height: 70%; overflow-y: auto; }
        .error { background-color: #fdd; border: 1px solid #f00; padding: 10px; margin: 10px 0; border-radius: 5px; }
        .search-container { 
            position: absolute; 
            top: 20px; 
            right: 20px; 
            width: 200px;
        }
        .search-container input { 
            width: 100%; 
            padding: 6px 10px; 
            box-sizing: border-box;
            border-radius: 15px;
            border: 1px solid #ccc;
        }
        .notification-badge {
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            margin-left: 5px;
        }
        .header { 
            display: flex; 
            align-items: center;
            justify-content: center;
            height: 50px;
            gap: 15px;
            padding: 5px 0;
            margin-top: 5px;
        }
        .header img { 
            max-width: 60px;
            height: auto;
        }
        .header .text { 
            font-weight: bold; 
            font-size: 18px; 
            text-align: center;
        }
        @media (max-width: 600px) {
            nav li { flex: 100%; }
            .header { flex-direction: column; height: auto; margin-top: 60px; }
            .header img { margin: 5px 0; }
            .search-container {
                position: static;
                width: 90%;
                margin: 10px auto;
            }
        }
        .footer-icons {
            position: fixed;
            bottom: 5px;
            left: 5px;
            display: flex;
            gap: 8px;
            align-items: center;
            z-index: 9999;
        }
        .footer-icons a {
            display: inline-block;
            width: 18px;
            height: 18px;
        }
        .footer-icons img {
            width: 100%;
            height: 100%;
            display: block;
        }
        footer {
            position: fixed;
            bottom: 5px;
            right: 10px;
            background: rgba(0,0,0,0.3);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
            user-select: none;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <div class="search-container">
        <input type="text" id="searchInput" placeholder="Görev ara..." onkeyup="searchTasks()">
    </div>

    <title>Polatlar Yazılım Görev Tamamlama Listesi</title>
    <div class="header">

        <div class="text">GÖREV KONTROL LİSTESİ</div>

    </div>
    
    <nav id="menuBar">
        <ul id="navMenu">
            <li><a href="#" onclick="showSection('raporlar')">GÖREVLER</a></li>
            <li><a href="#" onclick="showSection('gorev-ekle')">GÖREV EKLE</a></li>
             <li><a href="index.php#gecmis-raporlar" onclick="showSection('gecmis-raporlar')">TAMAMLANAN GÖREVLER <?php if ($notificationCount > 0): ?><span class="notification-badge"><?= $notificationCount ?></span><?php endif; ?></a></li>
            <?php if ($_SESSION['user'] === 'burak'): ?>
                <li><a href="#" onclick="showSection('yetki-girisi')">YETKİ GİRİŞİ</a></li>
            <?php endif; ?>
            <li><a href="index.php?logout=1">GÜVENLİ ÇIKIŞ</a></li>
        </ul>
    </nav>

    <?php if (isset($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section id="raporlar" class="active">
        <h2>Raporlar</h2>
        <form id="reportForm" method="post" action="">
            <label>Şirket İsmi: <input type="text" name="companyName" id="companyName"></label>
            <div class="task-list" id="taskList">
                <?php
                $defaultTasks = [
                    "İkinci ekranda ürünler görünüyor mu?",
                    "Restoran değilse pos yetki tanımlarından masa ve adisyon kaldırıldı mı?",
                    "Varsa çekmece için fiş yazıcı aygıt ayarlarından after Cash ayarı yapıldı mı?",
                    "Varsa terazinin com ayarı PC POS'ta yapıldı mı? Yapıldıysa denendi mi?",
                    "Varsa Barkod okuyucu denendi mi?",
                    "Varsa barkod yazıcı PC'ye tanıtıldı mı? İnternet bağlantısı ile bağlanacaksa IP verildi mi?",
                    "Varsa fiş yazıcı PC'ye tanıtıldı mı? İnternet bağlantısı ile bağlanacaksa IP verildi mi?",
                    "Uyku modu kapatıldı mı?",
                    "SQL Management olan PC'lerde 1433 PORT açıldı mı?",
                    "Anydesk grupta paylaşıldı mı?",
                    "WUB çalıştırıldı mı?",
                    "IP Scanner yüklendi mi?",
                    "Tablet PC ayarı yapıldı mı?"
                ];

                $extraTasks = $_SESSION['extraTasks'] ?? [];
                $allTasks = array_merge($defaultTasks, $extraTasks);

                foreach ($allTasks as $task) {
                    echo '<label><input type="checkbox" name="tasks[]" value="' . htmlspecialchars($task) . '"><span>' . htmlspecialchars($task) . '</span></label>';
                }
                ?>
            </div>
            <button type="submit" name="submitReport" class="submit-btn">Gönder</button>
        </form>
    </section>

    <section id="gorev-ekle">
        <h2>Yeni Görev Ekle</h2>
        <form method="post" action="">
            <label>Görev İsmi: <input type="text" name="newTask" id="newTaskInput"></label>
            <button type="submit" name="addTask" class="add-btn">Ekle</button>
        </form>
    </section>

    <section id="gecmis-raporlar">
        <h2>Geçmiş Raporlar</h2>
        <a href="index.php?reset_notifications=1" style="color: red; float: right;">Bildirimleri Temizle</a>
        <div class="history-list" id="historyList">
            <?php
            $file = 'reports.json';
            $reportsData = [];
            if (file_exists($file)) {
                $reportsData = json_decode(file_get_contents($file), true);
            }
            if (is_array($reportsData) && count($reportsData) > 0) {
                foreach ($reportsData as $index => $rep) {
                    echo '<div class="history-item">';
                    echo '<strong><a href="#" onclick="showDetail(' . $index . ');return false;">' . htmlspecialchars($rep['company']) . ' (Ekleyen: ' . htmlspecialchars($rep['added_by'] ?? 'Bilinmiyor') . ')</a></strong>';

                    if (isset($_SESSION['user']) && $_SESSION['user'] === 'burak') {
                        echo '<form method="post" action="" style="display:inline;margin-left:10px;">';
                        echo '<input type="hidden" name="reportIndex" value="' . $index . '">';
                        echo '<button type="submit" name="deleteReport" onclick="return confirm(\'Bu raporu silmek istediğine emin misin?\');" style="color:red;cursor:pointer;">Sil</button>';
                        echo '</form>';
                    }

                    echo '</div>';
                }
            } else {
                echo '<p>Henüz geçmiş rapor yok...</p>';
            }
            ?>
        </div>
    </section>

    <?php if ($_SESSION['user'] === 'burak'): ?>
    <section id="yetki-girisi">
        <h2>Yeni Kullanıcı Ekle</h2>
        <form method="post" action="">
            <label>Kullanıcı Adı: <input type="text" name="newUsername"></label><br>
            <label>Şifre: <input type="password" name="newPassword"></label><br>
            <button type="submit" name="addUser" class="add-user-btn">Kullanıcı Ekle</button>
        </form>
    </section>
<section id="rapor-gonder" style="display: none;">
    <h2>Rapor Gönder</h2>
    <form method="post">
        <input type="text" name="gsm_number" placeholder="Telefon Numarası (5XX...)" required style="padding:8px; width:300px;">
        <button type="submit" name="send_sms" style="padding:8px 20px;">GÖNDER</button>
    </form>
</section>

    <?php endif; ?>

    <div class="modal" id="modal" style="display:none;">
        <h3>Görev Detayı</h3>
        <div id="modalContent"></div>
        <button onclick="closeModal()">Kapat</button>
    </div>

    <script>
        function showSection(sectionId) {
            const sections = document.querySelectorAll('section');
            sections.forEach(sec => sec.style.display = 'none');
            document.getElementById(sectionId).style.display = 'block';
            document.getElementById('modal').style.display = 'none';
        }

        function showDetail(index) {
            const reports = <?php echo json_encode($reportsData ?? []); ?>;
            const tasks = reports[index].tasks;
            document.getElementById('modalContent').innerHTML = "<ul>" + tasks.map(t => `<li>${t}</li>`).join('') + "</ul>";
            document.getElementById('modal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }

        function searchTasks() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const taskList = document.getElementById('taskList');
            const labels = taskList.getElementsByTagName('label');

            for (let i = 0; i < labels.length; i++) {
                const span = labels[i].getElementsByTagName('span')[0];
                if (span) {
                    const txtValue = span.textContent || span.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        labels[i].style.display = '';
                    } else {
                        labels[i].style.display = 'none';
                    }
                }
            }
        }

        function resetNotificationCounter() {
            fetch('index.php?reset_notifications=1')
                .then(() => {
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        badge.remove();
                    }
                });
        }

        showSection('raporlar');
    </script>

    <div class="footer-icons">
        <a href="https://www.instagram.com/polatlaryazilim/" target="_blank" title="Instagram">
            <img src="https://cdn-icons-png.flaticon.com/512/2111/2111463.png" alt="Instagram Icon" />
        </a>
        <a href="https://polatlaryazilim.com/" target="_blank" title="Website">
            <img src="https://cdn-icons-png.flaticon.com/512/841/841364.png" alt="Website Icon" />
        </a>
        <a href="https://wa.me/905539120542" target="_blank" title="WhatsApp">
            <img src="https://cdn-icons-png.flaticon.com/512/733/733585.png" alt="WhatsApp Icon" />
        </a>
        <a href="https://mail.google.com/mail" target="_blank" title="info@polatlaryazilim.com">
            <img src="https://www.cdnlogo.com/logos/o/14/official-gmail-icon-2020.svg" alt="Gmail Icon" />
        </a>
    </div>

    <footer>
        <svg xmlns="http://www.w3.org/2000/svg" fill="white" height="16" viewBox="0 0 24 24" width="16">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-.5-13h-1v6l5.25 3.15.75-1.23-4.5-2.67z"/>
        </svg>
        © 2025 BURAK AĞCAKAYA. Tüm hakları saklıdır.
    </footer>
</body>
</html>