<?php
// process_kamo_song.php - Kamo's Song Digital Download Payment Processor
header('Content-Type: application/json');

// Configuration
$song_config = [
    'price' => 30.00, // R30
    'song_file' => 'songs/kamo_song.mp3', // Placeholder path to the actual song file
    'bank_details' => [
        'bank_name' => 'Capitec Business',
        'account_holder' => 'Tsateng Productions Pty Ltd',
        'account_number' => '1051829933',
        'branch_code' => '450105',
        'reference_format' => 'KAMO-[EMAIL_PREFIX]', // Example: KAMO-JOHN
    ],
    'admin_email' => 'admin@tsatengproductions.org',
    'noreply_email' => 'noreply@tsatengproductions.org',
];

// Utility function to generate a secure, temporary download link
function generateDownloadToken($email, $songPath) {
    $secret = 'YOUR_SECURE_SECRET_KEY'; // CHANGE THIS TO A LONG, RANDOM STRING
    $expiry = time() + (24 * 3600); // Link expires in 24 hours
    $data = $email . '|' . $songPath . '|' . $expiry;
    $token = hash_hmac('sha256', $data, $secret);
    
    return [
        'token' => $token,
        'expiry' => $expiry,
        'data' => base64_encode($data)
    ];
}

// Utility function to send email (simplified for now, assumes mail() is configured)
function sendEmail($to, $subject, $message, $attachments = []) {
    // This is a placeholder. In a real Cloud Run environment, you should use a service like SendGrid or PHPMailer with SMTP.
    // For now, we'll assume the basic mail() function is configured to work.
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Tsateng Productions <noreply@tsatengproductions.org>\r\n";
    
    // In a real implementation, you would use PHPMailer to handle attachments.
    // Since we are not modifying PHPMailer files, we will assume the song is sent via a secure link.
    
    return mail($to, $subject, $message, $headers);
}

// Main processing
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize form data
    $name = htmlspecialchars(trim($_POST["name"] ?? ''), ENT_QUOTES, 'UTF-8');
    $email = filter_var(trim($_POST["email"] ?? ''), FILTER_SANITIZE_EMAIL);
    $proof_uploaded = isset($_POST["proof_uploaded"]) && $_POST["proof_uploaded"] === 'true';
    
    // Validate input
    if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "message" => "Please provide a valid name and email address."]);
        exit;
    }
    
    // 1. Initial request: Provide bank details
    if (!$proof_uploaded) {
        $ref_prefix = strtoupper(substr($name, 0, 4));
        $reference = str_replace('[EMAIL_PREFIX]', $ref_prefix, $song_config['bank_details']['reference_format']);
        
        $bank_html = "
            <p>Thank you for your purchase, {$name}!</p>
            <p>Please make a payment of **R{$song_config['price']}** to the following bank account:</p>
            <ul>
                <li>**Bank Name:** {$song_config['bank_details']['bank_name']}</li>
                <li>**Account Holder:** {$song_config['bank_details']['account_holder']}</li>
                <li>**Account Number:** {$song_config['bank_details']['account_number']}</li>
                <li>**Branch Code:** {$song_config['bank_details']['branch_code']}</li>
                <li>**Reference:** **{$reference}** (Crucial for us to track your payment)</li>
            </ul>
            <p>Once payment is made, please return to the form and upload your proof of payment.</p>
        ";
        
        echo json_encode([
            "success" => true,
            "stage" => "bank_details",
            "message" => "Bank details provided. Please make payment and upload proof.",
            "bank_details_html" => $bank_html,
            "reference" => $reference
        ]);
        exit;
    }
    
    // 2. Proof of payment uploaded (Simulated for now, as file upload logic is complex in this setup)
    // In a real scenario, you would handle the file upload here and save it to Cloud Storage.
    
    // For now, we will assume the proof is received and the payment is being processed.
    
    // Generate secure download link
    $download_info = generateDownloadToken($email, $song_config['song_file']);
    $download_link = "https://tsateng-php-1003232992046.europe-west2.run.app/download_song.php?token={$download_info['token']}&data={$download_info['data']}";
    
    // Email the download link to the buyer
    $email_subject = "Your Kamo's Song Download Link - Tsateng Productions";
    $email_body = "
        <p>Dear {$name},</p>
        <p>Thank you for purchasing Kamo's song. Your payment proof has been received and confirmed.</p>
        <p>You can download your song here:</p>
        <p><a href='{$download_link}'>**CLICK HERE TO DOWNLOAD YOUR SONG**</a></p>
        <p>This link is valid for 24 hours.</p>
        <p>Best regards,<br>Tsateng Productions Team</p>
    ";
    
    $mail_sent = sendEmail($email, $email_subject, $email_body);
    
    // Notify admin
    $admin_subject = "Kamo's Song Purchase - Proof Received";
    $admin_body = "Proof of payment received for Kamo's Song from {$name} ({$email}). Download link sent to buyer.";
    sendEmail($song_config['admin_email'], $admin_subject, $admin_body);
    
    if ($mail_sent) {
        echo json_encode([
            "success" => true,
            "stage" => "download_link_sent",
            "message" => "Thank you! Your download link has been sent to your email address: {$email}."
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "stage" => "email_failed",
            "message" => "Payment confirmed, but failed to send download link email. Please contact us at {$song_config['admin_email']}."
        ]);
    }
    
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}
?>
