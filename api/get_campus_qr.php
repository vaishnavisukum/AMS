<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/qr_generator.php';

// Anyone authenticated can get campus QR (usually displayed on campus)
Auth::require();

// Generate campus QR data
$qrData = QRGenerator::generateCampusQR();

// Generate QR image URL
$qrImageUrl = QRGenerator::generateQRImage($qrData);

echo json_encode([
    'success' => true,
    'qr_data' => $qrData,
    'qr_image_url' => $qrImageUrl,
    'type' => 'campus_attendance',
    'date' => date('Y-m-d')
]);
?>

