<?php
require_once __DIR__ . '/../config/config.php';

class QRGenerator {
    
    /**
     * Generate QR data for subject attendance session
     */
    public static function generateSessionQR($sessionId, $subjectName, $facultyName, $secretKey) {
        $currentTime = time();
        $expiryTime = $currentTime + QR_EXPIRY_TIME;
        
        $data = [
            'session_id' => $sessionId,
            'subject' => $subjectName,
            'faculty' => $facultyName,
            'timestamp' => $currentTime,
            'expiry' => $expiryTime,
            'type' => 'subject_attendance'
        ];
        
        // Create signature
        $dataString = json_encode($data);
        $signature = hash_hmac(HMAC_ALGO, $dataString, $secretKey);
        
        $qrData = [
            'data' => $data,
            'signature' => $signature
        ];
        
        return json_encode($qrData);
    }
    
    /**
     * Generate QR data for campus attendance
     */
    public static function generateCampusQR() {
        $currentTime = time();
        $todayDate = date('Y-m-d');
        
        $data = [
            'type' => 'campus_attendance',
            'date' => $todayDate,
            'timestamp' => $currentTime
        ];
        
        // Create signature using SECRET_KEY
        $dataString = json_encode($data);
        $signature = hash_hmac(HMAC_ALGO, $dataString, SECRET_KEY);
        
        $qrData = [
            'data' => $data,
            'signature' => $signature
        ];
        
        return json_encode($qrData);
    }
    
    /**
     * Validate QR signature
     */
    public static function validateSignature($data, $signature, $secretKey) {
        $dataString = json_encode($data);
        $expectedSignature = hash_hmac(HMAC_ALGO, $dataString, $secretKey);
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Check if QR is expired
     * Small grace period added to avoid edge cases at the exact boundary
     */
    public static function isExpired($expiryTimestamp, $graceSeconds = 5) {
        // Cast to int in case it's passed as string from JSON
        $expiry = (int)$expiryTimestamp;
        return time() > ($expiry + $graceSeconds);
    }
    
    /**
     * Generate QR code image URL using external API
     */
    public static function generateQRImage($data, $size = 300) {
        $encodedData = urlencode($data);
        // Using Google Charts API (or you can use phpqrcode library)
        return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encodedData}";
    }
}
?>

