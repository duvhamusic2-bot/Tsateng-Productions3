<?php
// process_bank_transfer.php - Bank Transfer Processing with Ticket Image Generation
session_start();
header('Content-Type: application/json');

// Utility function to send email (placeholder)
function sendEmail($to, $subject, $message, $fromEmail = 'fcw@tsatengproductions.org') {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: For Christ Worship <{$fromEmail}>" . "\r\n";
    $headers .= "Reply-To: fcw@tsatengproductions.org" . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Utility function to log bank transfers
function logBankTransfer($ticketData) {
    $logFile = 'bank_transfers.json';
    $transfers = [];
    if (file_exists('data/' . $logFile)) {
        $transfersData = file_get_contents('data/' . $logFile);
        if ($transfersData) {
            $transfers = json_decode($transfersData, true) ?: [];
        }
    }
    
    $transfers[] = $ticketData;
    
    // Ensure data directory exists
    if (!is_dir('data')) {
        mkdir('data', 0755, true);
    }
    
    return file_put_contents('data/' . $logFile, json_encode($transfers, JSON_PRETTY_PRINT));
}

// Utility function to generate ticket image (using the logic from generate_ticket.php)
function generateTicketImage($ticketNumber, $name, $email, $type) {
    // This function should be implemented or included from generate_ticket.php
    // For now, we'll use a simplified version that just returns a path.
    // In a real deployment, you would include the full generate_ticket.php logic here.
    
    // Placeholder for actual ticket generation logic
    $outputDir = 'generated_tickets/';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    $outputPath = $outputDir . $ticketNumber . '.jpg';
    
    // Simulate ticket generation success
    file_put_contents($outputPath, "Simulated Ticket Image for {$ticketNumber}");
    
    return $outputPath;
}

// Utility function to send confirmation email to buyer
function sendBankTransferConfirmationEmail($ticketData) {
    $to = $ticketData['email'];
    $subject = "For Christ Worship - Bank Transfer Proof Received";
    
    $ticketList = '';
    foreach ($ticketData['ticketNumbers'] as $index => $ticketNumber) {
        $ticketList .= "<li><strong>Ticket #" . ($index + 1) . ":</strong> $ticketNumber</li>";
    }
    
    $message = "
    <html>
    <head><style>body { font-family: Arial, sans-serif; }</style></head>
    <body>
        <h2>🏦 Proof Received - Pending Verification</h2>
        <p>Dear {$ticketData['fullName']},</p>
        <p>Thank you for submitting your proof of payment for {$ticketData['quantity']} tickets.</p>
        <p>Your payment is now being verified by our team. This usually takes <strong>2-24 hours</strong>.</p>
        <p>Once verified, your full tickets will be emailed to you automatically.</p>
        <p><strong>Reserved Ticket Numbers:</strong></p>
        <ul>{$ticketList}</ul>
        <p>For questions, contact: <strong>fcw@tsatengproductions.org</strong></p>
    </body>
    </html>";
    
    return sendEmail($to, $subject, $message);
}

// Utility function to send notification email to admin/member
function sendAdminNotificationEmail($ticketData) {
    // Send to a central admin email for verification
    $to = 'fcw@tsatengproductions.org'; 
    $subject = "Bank Transfer Proof Received: {$ticketData['fullName']} ({$ticketData['quantity']} Tickets)";
    
    $ticketList = '';
    foreach ($ticketData['ticketNumbers'] as $index => $ticketNumber) {
        $ticketList .= "<li>{$ticketNumber}</li>";
    }
    
    $message = "
    <html>
    <head><style>body { font-family: Arial, sans-serif; }</style></head>
    <body>
        <h2>🔔 New Bank Transfer Proof Received</h2>
        <p><strong>Buyer:</strong> {$ticketData['fullName']} ({$ticketData['email']})</p>
        <p><strong>Quantity:</strong> {$ticketData['quantity']}</p>
        <p><strong>Total Amount:</strong> R{$ticketData['totalAmount']}</p>
        <p><strong>Proof of Payment:</strong> <a href='{$ticketData['proofOfPayment']}'>View Proof</a></p>
        <p><strong>Reserved Tickets:</strong></p>
        <ul>{$ticketList}</ul>
        <p>Please verify the payment and send the final tickets.</p>
    </body>
    </html>";
    
    return sendEmail($to, $subject, $message);
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input
    $fullName = filter_var(trim($_POST["fullName"]), FILTER_SANITIZE_STRING);
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $phone = filter_var(trim($_POST["phone"]), FILTER_SANITIZE_STRING);
    $quantity = intval($_POST["quantity"]);
    $referralCode = isset($_POST["referralCode"]) ? strtoupper(filter_var(trim($_POST["referralCode"]), FILTER_SANITIZE_STRING)) : '';
    
    // Validate
    if (empty($fullName) || empty($email) || empty($phone) || $quantity < 1) {
        echo json_encode(["success" => false, "message" => "Please fill in all required fields."]);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "message" => "Please enter a valid email address."]);
        exit;
    }
    
    // Validate referral code
    $validReferralCodes = ['FCW001', 'FCW002', 'FCW003', 'FCW004', 'FCW005', 
                          'FCW006', 'FCW007', 'FCW008', 'FCW009', 'FCW010', 'FCW011'];
    
    if (!empty($referralCode) && !in_array($referralCode, $validReferralCodes)) {
        echo json_encode(["success" => false, "message" => "Invalid referral code."]);
        exit;
    }
    
    // Handle file upload
    $proofPath = '';
    if (isset($_FILES['proofOfPayment']) && $_FILES['proofOfPayment']['error'] === UPLOAD_ERR_OK) {
        // File validation
        $uploadDir = 'proofs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Check file size (5MB max)
        if ($_FILES['proofOfPayment']['size'] > 5 * 1024 * 1024) {
            echo json_encode(["success" => false, "message" => "File too large. Maximum 5MB."]);
            exit;
        }
        
        $fileExt = strtolower(pathinfo($_FILES['proofOfPayment']['name'], PATHINFO_EXTENSION));
        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
        
        if (!in_array($fileExt, $allowedExt)) {
            echo json_encode(["success" => false, "message" => "Invalid file type. Use PDF, JPG, or PNG."]);
            exit;
        }
        
        // Generate secure filename
        $fileName = 'POP_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $fileExt;
        $proofPath = $uploadDir . $fileName;
        
        if (!move_uploaded_file($_FILES['proofOfPayment']['tmp_name'], $proofPath)) {
            echo json_encode(["success" => false, "message" => "Failed to upload proof."]);
            exit;
        }
    } else {
        echo json_encode(["success" => false, "message" => "Please upload proof of payment."]);
        exit;
    }
    
    // Generate ticket data
    $ticketData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'fullName' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'quantity' => $quantity,
        'totalAmount' => $quantity * 200,
        'referralCode' => $referralCode,
        'paymentMethod' => 'banktransfer',
        'paymentStatus' => 'pending_verification',
        'proofOfPayment' => $proofPath,
        'event' => 'For Christ Worship - March 28, 2026',
        'venue' => 'Oukerk Klerksdorp (Behind Klerksdorp Magistrate Court)'
    ];
    
    // Generate tickets
    $ticketData['ticketNumbers'] = [];
    $ticketData['ticketImages'] = [];
    
    for ($i = 0; $i < $quantity; $i++) {
        $ticketNumber = 'FCW' . date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $ticketData['ticketNumbers'][] = $ticketNumber;
        
        // Generate ticket image (using a simplified version for now)
        $ticketImage = generateTicketImage($ticketNumber, $fullName, $email, 'banktransfer');
        if ($ticketImage) {
            $ticketData['ticketImages'][] = $ticketImage;
        }
    }
    
    // Log the transfer
    logBankTransfer($ticketData);
    
    // Send emails
    $buyerEmailSent = sendBankTransferConfirmationEmail($ticketData);
    $adminEmailSent = sendAdminNotificationEmail($ticketData);
    
    if ($buyerEmailSent && $adminEmailSent) {
        echo json_encode(["success" => true, "message" => "Proof submitted! Tickets will be emailed after verification."]);
    } else {
        echo json_encode(["success" => true, "message" => "Submission received but one or more emails failed. We'll contact you."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}
?>
