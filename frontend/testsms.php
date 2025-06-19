<?php
// testsms.php

// Token API
$token = '528c72957455980e69a81d377d87846d';

// Nomor tujuan (boleh pakai +62 atau 0812—akan disanitasi)
$rawTo = '+628236548490';

// Hapus semua non-digit dan pastikan leading 0 → 62
$to = preg_replace('/\D/', '', $rawTo);
if (strpos($to, '0') === 0) {
    $to = '62' . substr($to, 1);
}

// Pesan ujicoba
$message = 'Test SMS via websms.co.id';
$msg = urlencode($message);

// Bangun URL
$url = "https://websms.co.id/api/smsgateway?token={$token}&to={$to}&msg={$msg}";

// Inisialisasi cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
$result   = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

// Tampilkan hasil untuk debug
echo "HTTP Code: {$httpCode}\n";
if ($error) {
    echo "cURL Error: {$error}\n";
} else {
    echo "Response: {$result}\n";
}
