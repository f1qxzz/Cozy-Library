<?php
/*
 * Alur logic PHP:
 * 1) Menjalankan job terjadwal sesuai kebutuhan notifikasi sistem.
 * 2) Mengambil data target dari database berdasarkan kondisi waktu/status.
 * 3) Menyusun dan mengirim pengingat, lalu mencatat hasil proses.
 *//**
 * CRON JOB: PENGINGAT H-1 (H-1 Reminder) via PHPMailer / Native Mail
 * Skrip ini dipanggil secara asinkron dari Crontab / Task Scheduler
 * untuk mendeteksi peminjaman yang akan jatuh tempo pada H+1.
 */

require_once __DIR__ . '/../config/database.php';

// Import spesifikasi PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/../includes/PHPMailer/Exception.php';
require __DIR__ . '/../includes/PHPMailer/PHPMailer.php';
require __DIR__ . '/../includes/PHPMailer/SMTP.php';

$conn = getConnection();
$logFile = __DIR__ . '/cron.log';
$timestamp = date('Y-m-d H:i:s');
$successCount = 0;
$failCount = 0;

// Menarik kredensial SMTP yang ada dari database.php (via $_ENV / .env.local)
$smtpHost = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
$smtpPort = $_ENV['SMTP_PORT'] ?? 587;
$smtpUser = $_ENV['SMTP_USER'] ?? '';
$smtpPass = $_ENV['SMTP_PASS'] ?? '';
$useSmtp  = (!empty($smtpUser) && !empty($smtpPass) && $smtpUser !== 'email.anda@gmail.com');
$dendaPerHari = defined('DENDA_PER_HARI') ? DENDA_PER_HARI : 1000;

// Query transaksi yang masa pinjamnya tinggal 1 hari (H-1)
$sql = "SELECT t.id_transaksi, a.nama_anggota, a.email, b.judul_buku, t.tgl_pinjam, t.tgl_kembali_rencana
        FROM transaksi t
        JOIN anggota a ON t.id_anggota = a.id_anggota
        JOIN buku b ON t.id_buku = b.id_buku
        WHERE t.status_transaksi IN ('Dipinjam', 'Peminjaman')
        AND DATE(t.tgl_kembali_rencana) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";

$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {

    while ($r = $res->fetch_assoc()) {
        $to = $r['email'];
        if (empty($to)) {
            $failCount++;
            continue;
        }

        $nama  = htmlspecialchars($r['nama_anggota']);
        $buku  = htmlspecialchars($r['judul_buku']);
        $jatuh = date('d F Y', strtotime($r['tgl_kembali_rencana']));
        
        $idTrans = 'TRX-' . $r['id_transaksi'];
        $subject = "🔔 [REMINDER] Jatuh Tempo Pengembalian Buku Besok ($idTrans) - Cozy Library";
        $message = "
        <html>
        <head>
          <style>
             body { font-family: 'Inter', sans-serif; background: #f9fafb; padding: 20px; }
             .container { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 600px; margin: 0 auto; }
             h2 { color: #dc2626; margin-top: 0; }
             p { color: #374151; line-height: 1.6; }
             .book-title { font-weight: bold; color: #4f46e5; }
             .footer { margin-top: 30px; font-size: 0.8em; color: #9ca3af; border-top: 1px solid #f3f4f6; padding-top: 20px; text-align: center; }
          </style>
        </head>
        <body>
          <div class='container'>
              <h2>Halo, {$nama}!</h2>
              <p>Ini adalah notifikasi sistem otomatis dari perpustakaan <strong>Cozy-Library</strong> bahwa pengembalian buku peminjaman Anda bernomor TRX-{$r['id_transaksi']} sudah hampir tiba.</p>
              <p>Mohon segera mengembalikan buku: <br>
              <span class='book-title'>\"{$buku}\"</span></p>
              <p>Sebelum atau paling lambat pada: <strong>{$jatuh}</strong> (Besok). Pengembalian melewati pada tanggal tersebut akan dikenai sanksi denda Rp" . number_format($dendaPerHari, 0, ',', '.') . " / hari.</p>
              <p>Terima kasih atas disiplin sirkulasi Anda.<br>-- Cozy-Library Admin Team</p>
              <div class='footer'>Email ini digenerate secara otomatis. Mohon jangan dibalas.</div>
          </div>
        </body>
        </html>
        ";

        if ($useSmtp) {
            // Gunakan metode SMTP (Sangat Reliabel)
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = $smtpHost;
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtpUser;
                $mail->Password   = $smtpPass;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $smtpPort;

                $mail->setFrom($smtpUser, 'Admin Cozy-Library');
                $mail->addAddress($to, $nama);

                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $message;

                $mail->send();
                $successCount++;
            } catch (Exception $e) {
                $failCount++;
                file_put_contents($logFile, "[{$timestamp}] Mailer Error for {$to}: {$mail->ErrorInfo}" . PHP_EOL, FILE_APPEND);
            }
        } else {
            // Fallback: Gunakan fungsi mail() bawaan PHP (Bisa dipakai tanpa daftar apapun di CPanel)
            $from_email = 'no-reply@cozy-library.com';
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: Cozy-Library <{$from_email}>\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            if (@mail($to, $subject, $message, $headers)) {
                $successCount++;
            } else {
                $failCount++;
                file_put_contents($logFile, "[{$timestamp}] Native mail() failed for {$to}." . PHP_EOL, FILE_APPEND);
            }
        }
    }
    
    $modeStr = $useSmtp ? "(Via SMTP)" : "(Via Native Mail)";
    $logMsg = "[{$timestamp}] CRON H-1 SUCCESS {$modeStr}: Processed {$res->num_rows} records. Sent: {$successCount}, Failed/Skipped: {$failCount}";

} else {
    $logMsg = "[{$timestamp}] CRON H-1 INFO: No books are due tomorrow. Processed 0 records.";
}

file_put_contents($logFile, $logMsg . PHP_EOL, FILE_APPEND);
echo "Cron Execution Complete. Status logged to cron.log<br>";
echo $logMsg;

closeConnection($conn);
?>
