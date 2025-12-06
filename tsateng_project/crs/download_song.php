<?php
// download_song.php - Secure Digital Download Link Processor
// This script validates the temporary token and serves the file.

// Configuration (must match process_kamo_song.php)
$song_config = [
    'song_file' => 'songs/kamo_song.mp3', // Placeholder path to the actual song file
];

// Utility function to validate the secure, temporary download link
function validateDownloadToken($token, $data_encoded) {
    $secret = '3232kloh254fvS568555kmjihhh4565WWQmliQ200'; // CHANGE THIS TO A LONG, RANDOM STRING
    
    $data = base64_decode($data_encoded);
    if ($data === false) return false;
    
    list($email, $songPath, $expiry) = explode('|', $data);
    
    // 1. Check token validity
    $expected_token = hash_hmac('sha256', $data, $secret);
    if (!hash_equals($expected_token, $token)) {
        return false;
    }
    
    // 2. Check expiry
    if (time() > (int)$expiry) {
        return false;
    }
    
    // 3. Check file existence (assuming song file is in the Cloud Run container)
    if (!file_exists($songPath)) {
        // In a real scenario, this file would be served from Cloud Storage or a secure location.
        // For now, we assume it's in the container.
        return false;
    }
    
    return $songPath;
}

if (isset($_GET['token']) && isset($_GET['data'])) {
    $token = $_GET['token'];
    $data = $_GET['data'];
    
    $filePath = validateDownloadToken($token, $data);
    
    if ($filePath) {
        // Serve the file
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        // Token invalid or expired
        http_response_code(403);
        echo "<h1>403 Forbidden</h1>";
        echo "<p>The download link is invalid or has expired. Please contact support.</p>";
        exit;
    }
} else {
    http_response_code(400);
    echo "<h1>400 Bad Request</h1>";
    echo "<p>Missing required parameters.</p>";
    exit;
}
?>
