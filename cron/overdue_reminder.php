<?php
/*
 * Alur logic PHP:
 * 1) Menjalankan job terjadwal sesuai kebutuhan notifikasi sistem.
 * 2) Mengambil data target dari database berdasarkan kondisi waktu/status.
 * 3) Menyusun dan mengirim pengingat, lalu mencatat hasil proses.
 *//**
 * CRON JOB: TAGIHAN KETERLAMBATAN (Overdue Reminder)
 * Skrip profesional untuk menagih denda peminjaman yang telah lewat batas waktu (Telat).
 * Dibangun dengan sistem "Smart Error Handling" dan "Pro Billing Design".
 */

require_once __DIR__ . '/../config/database.php';

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

// Menarik kredensial SMTP dan aturan denda dari Environment Variables
$smtpHost = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
$smtpPort = $_ENV['SMTP_PORT'] ?? 587;
$smtpUser = $_ENV['SMTP_USER'] ?? '';
$smtpPass = $_ENV['SMTP_PASS'] ?? '';
$useSmtp  = (!empty($smtpUser) && !empty($smtpPass) && $smtpUser !== 'email.anda@gmail.com');

// Default aturan denda di Cozy-Library
$dendaPerHari = defined('DENDA_PER_HARI') ? DENDA_PER_HARI : 1000;

// Query: Mencari buku yang masih 'Dipinjam' tetapi batas kembalinya SUDAH LEWAT hari ini.
$sql = "SELECT t.id_transaksi, a.nama_anggota, a.email, b.judul_buku, t.tgl_pinjam, t.tgl_kembali_rencana,
        DATEDIFF(CURDATE(), DATE(t.tgl_kembali_rencana)) AS jumlah_hari_telat
        FROM transaksi t
        JOIN anggota a ON t.id_anggota = a.id_anggota
        JOIN buku b ON t.id_buku = b.id_buku
        WHERE t.status_transaksi IN ('Dipinjam', 'Peminjaman')
        AND DATE(t.tgl_kembali_rencana) < CURDATE()";

$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {
        $to = $r['email'];
        if (empty($to)) {
            $failCount++;
            continue;
        }

        $nama        = htmlspecialchars($r['nama_anggota']);
        $buku        = htmlspecialchars($r['judul_buku']);
        $hariTelat   = (int)$r['jumlah_hari_telat'];
        $dendaEstimasi = $hariTelat * $dendaPerHari;
        
        // Format Rupiah standar Indonesia
        $formatDenda = 'Rp' . number_format($dendaEstimasi, 0, ',', '.');
        $idTrans     = 'TRX-' . $r['id_transaksi'];
        $tglBatas    = date('d F Y', strtotime($r['tgl_kembali_rencana']));

        $subject = "🔴 [ACTION REQUIRED] Surat Tagihan Denda Keterlambatan ($idTrans) - Cozy Library";
        
        // Templat HTML Tingkat Profesional (Invoice Style)
        $message = "
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Roboto, Helvetica, sans-serif; background-color: #f8fafc; padding: 40px 15px; margin: 0; }
                .wrapper { background-color: #ffffff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); max-width: 600px; margin: auto; overflow: hidden; }
                .header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); padding: 30px; text-align: center; color: white; }
                .header h1 { margin: 0; font-size: 24px; letter-spacing: 0.5px; }
                .content { padding: 35px; }
                .intro { color: #334155; line-height: 1.7; font-size: 15px; }
                .highlight-box { background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 20px; margin: 30px 0; }
                table { width: 100%; border-collapse: collapse; font-size: 14px; }
                table th { text-align: left; padding-bottom: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; }
                table td { padding: 16px 0; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
                .denda { color: #dc2626; font-weight: 800; font-size: 22px; text-align: right; }
                .alert-text { display: block; text-align: center; color: #dc2626; font-weight: bold; background: #fee2e2; padding: 12px; border-radius: 6px; margin-top: 25px; }
                .footer { margin-top: 40px; font-size: 12px; color: #94a3b8; text-align: center; border-top: 1px solid #f1f5f9; padding-top: 20px; }
            </style>
        </head>
        <body>
            <div class='wrapper'>
                <div class='header'>
                    <h1>⚠️ SURAT PENAGIHAN DENDA</h1>
                </div>
                <div class='content'>
                    <p class='intro'>Yth. <strong>{$nama}</strong>,</p>
                    <p class='intro'>Sistem sirkulasi <strong>Cozy-Library</strong> mendeteksi bahwa Anda telah melewati batas waktu pengembalian buku. Menunjuk pada peraturan perpustakaan, denda keterlambatan kini resmi berjalan pada sirkulasi akun Anda.</p>
                    
                    <div class='highlight-box'>
                        <table>
                            <tr><th>Detail Peminjaman</th><th style='text-align:right'>Status</th></tr>
                            <tr>
                                <td>
                                    <strong>{$buku}</strong><br>
                                    <span style='color:#64748b; font-size:13px; line-height: 1.5;'>ID Trx: {$idTrans} <br> Jatuh Tempo: {$tglBatas}</span>
                                </td>
                                <td style='text-align:right;'>
                                    <span style='color:#dc2626; font-weight:700; background: #fee2e2; padding: 4px 8px; border-radius: 4px;'>Telat {$hariTelat} Hari</span>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding-top:20px; border:none; font-weight:700; color:#334155; font-size: 16px;'>Total Tagihan Saat Ini:</td>
                                <td class='denda' style='padding-top:20px; border:none;'>{$formatDenda}</td>
                            </tr>
                        </table>
                    </div>

                    <span class='alert-text'>Penting: Denda bertambah Rp1.000 setiap harinya!</span>
                    <p class='intro' style='font-size: 14px; margin-top: 20px;'>Harap segera kunjungi perpustakaan kami untuk menyerahkan kembali fisik buku beserta penyelesaian biaya administrasi di atas.</p>
                    
                    <div class='footer'>
                        &copy; Cozy-Library Management.<br>
                        Invoice ini dihasilkan secara otomatis oleh sistem (Autogenerated). Dimohon untuk tidak membalas email ini.
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";

        if ($useSmtp) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = $smtpHost;
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtpUser;
                $mail->Password   = $smtpPass;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $smtpPort;

                $mail->setFrom($smtpUser, 'Billing Cozy-Library');
                $mail->addAddress($to, $nama);

                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $message;
                // Add importance header explicitly
                $mail->addCustomHeader('X-Priority', '1 (Highest)');

                $mail->send();
                $successCount++;
            } catch (Exception $e) {
                $failCount++;
                file_put_contents($logFile, "[{$timestamp}] OVERDUE Mailer Error for {$to}: {$mail->ErrorInfo}" . PHP_EOL, FILE_APPEND);
            }
        } else {
            $from_email = 'billing@cozy-library.com';
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: Cozy-Library <{$from_email}>\r\n";
            $headers .= "X-Priority: 1 (Highest)\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            if (@mail($to, $subject, $message, $headers)) {
                $successCount++;
            } else {
                $failCount++;
                file_put_contents($logFile, "[{$timestamp}] OVERDUE Native mail() failed for {$to}." . PHP_EOL, FILE_APPEND);
            }
        }
    }
    
    $modeStr = $useSmtp ? "(Via SMTP)" : "(Via Native Mail)";
    $logMsg = "[{$timestamp}] CRON OVERDUE SUCCESS {$modeStr}: Processed {$res->num_rows} late records. Sent: {$successCount}, Failed/Skipped: {$failCount}";

} else {
    $logMsg = "[{$timestamp}] CRON OVERDUE INFO: Excellent! No members are currently late. Processed 0 records.";
}

file_put_contents($logFile, $logMsg . PHP_EOL, FILE_APPEND);
echo "Overdue CRON Complete. Status: <br>";
echo $logMsg;

closeConnection($conn);
?>
