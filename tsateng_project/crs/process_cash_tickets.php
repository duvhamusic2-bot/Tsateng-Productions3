<?php
// process_cash_tickets.php - Cash Ticket Processing for Group Members
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

// Utility function to log cash sales
function logCashSale($ticketData) {
    $logFile = 'cash_sales.json';
    $sales = [];
    if (file_exists($logFile)) {
        $salesData = file_get_contents($logFile);
        if ($salesData) {
            $sales = json_decode($salesData, true) ?: [];
        }
    }
    
    $sales[] = $ticketData;
    
    // Ensure data directory exists
    if (!is_dir('data')) {
        mkdir('data', 0755, true);
    }
    
    return file_put_contents('data/' . $logFile, json_encode($sales, JSON_PRETTY_PRINT));
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
function sendBuyerConfirmationEmail($ticketData) {
    $to = $ticketData['buyerEmail'];
    $subject = "For Christ Worship - Your Cash Sale Tickets Confirmed";
    
    $ticketList = '';
    foreach ($ticketData['ticketNumbers'] as $index => $ticketNumber) {
        $ticketList .= "<li><strong>Ticket #" . ($index + 1) . ":</strong> $ticketNumber</li>";
    }
    
    $message = "
    <html>
    <head><style>body { font-family: Arial, sans-serif; }</style></head>
    <body>
        <h2>✅ Tickets Confirmed!</h2>
        <p>Dear {$ticketData['buyerName']},</p>
        <p>Your cash purchase of {$ticketData['quantity']} tickets has been confirmed by Group Member {$ticketData['memberCode']}.</p>
        <p>Your tickets are:</p>
        <ul>{$ticketList}</ul>
        <p>Please keep this email safe. Your tickets will be scanned at the entrance.</p>
        <p>For questions, contact: <strong>fcw@tsatengproductions.org</strong></p>
    </body>
    </html>";
    
    return sendEmail($to, $subject, $message);
}

// Utility function to send notification email to group member
function sendMemberNotificationEmail($ticketData) {
    // Assuming group members have a dedicated email address (e.g., membercode@tsatengproductions.org)
    // For now, we'll send to a central admin email.
    $to = 'fcw@tsatengproductions.org'; 
    $subject = "Cash Sale Alert: {$ticketData['memberCode']} Confirmed {$ticketData['quantity']} Tickets";
    
    $ticketList = '';
    foreach ($ticketData['ticketNumbers'] as $index => $ticketNumber) {
        $ticketList .= "<li>{$ticketNumber}</li>";
    }
    
    $message = "
    <html>
    <head><style>body { font-family: Arial, sans-serif; }</style></head>
    <body>
        <h2>🔔 New Cash Sale Confirmed</h2>
        <p>Group Member **{$ticketData['memberCode']}** has confirmed a cash sale.</p>
        <p><strong>Buyer:</strong> {$ticketData['buyerName']} ({$ticketData['buyerEmail']})</p>
        <p><strong>Quantity:</strong> {$ticketData['quantity']}</p>
        <p><strong>Total Amount:</strong> R{$ticketData['totalAmount']}</p>
        <p><strong>Tickets Issued:</strong></p>
        <ul>{$ticketList}</ul>
    </body>
    </html>";
    
    return sendEmail($to, $subject, $message);
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $memberCode = strtoupper(trim($_POST["memberCode"]));
    $quantity = intval($_POST["quantity"]);
    $buyerName = filter_var(trim($_POST["buyerName"]), FILTER_SANITIZE_STRING);
    $buyerEmail = filter_var(trim($_POST["buyerEmail"]), FILTER_SANITIZE_EMAIL);
    $buyerPhone = filter_var(trim($_POST["buyerPhone"]), FILTER_SANITIZE_STRING);
    
    // Validate data
    if (empty($memberCode) || empty($buyerName) || empty($buyerEmail) || $quantity < 1) {
        echo json_encode(["success" => false, "message" => "Please fill in all required fields."]);
        exit;
    }
    
    if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "message" => "Please enter a valid email address."]);
        exit;
    }
    
    // Validate member code (FCW001-FCW011)
    $validMemberCodes = ['FCW001', 'FCW002', 'FCW003', 'FCW004', 'FCW005', 
                        'FCW006', 'FCW007', 'FCW008', 'FCW009', 'FCW010', 'FCW011'];
    
    if (!in_array($memberCode, $validMemberCodes)) {
        echo json_encode(["success" => false, "message" => "Invalid member code."]);
        exit;
    }
    
    // Generate ticket data
    $ticketData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'memberCode' => $memberCode,
        'buyerName' => $buyerName,
        'buyerEmail' => $buyerEmail,
        'buyerPhone' => $buyerPhone,
        'quantity' => $quantity,
        'totalAmount' => $quantity * 200,
        'paymentMethod' => 'cash',
        'processedBy' => $memberCode,
        'event' => 'For Christ Worship - Songs of Redemption Album Launch',
        'date' => 'March 28, 2026',
        'venue' => 'Oukerk Klerksdorp (Behind Klerksdorp Magistrate Court)'
    ];
    
    // Generate tickets with images
    $ticketData['ticketNumbers'] = [];
    $ticketData['ticketImages'] = [];
    
    for ($i = 0; $i < $quantity; $i++) {
        $ticketNumber = 'FCW' . date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $ticketData['ticketNumbers'][] = $ticketNumber;
        
        // Generate ticket image (using a simplified version for now)
        $ticketImage = generateTicketImage($ticketNumber, $buyerName, $buyerEmail, 'cash');
        if ($ticketImage) {
            $ticketData['ticketImages'][] = $ticketImage;
        }
    }
    
    // Log the sale
    logCashSale($ticketData);
    
    // Send emails
    $buyerEmailSent = sendBuyerConfirmationEmail($ticketData);
    $memberEmailSent = sendMemberNotificationEmail($ticketData);
    
    if ($buyerEmailSent && $memberEmailSent) {
        echo json_encode(["success" => true, "message" => "Sale confirmed. Tickets and notification emails sent."]);
    } else {
        echo json_encode(["success" => true, "message" => "Sale confirmed, but one or more emails failed to send. Please check logs."]);
    }
    
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}
?>
