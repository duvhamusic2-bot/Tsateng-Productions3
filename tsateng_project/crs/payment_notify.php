<?php
// payment_notify.php - PayFast Instant Transaction Notification (ITN) Handler
// This file receives server-to-server notifications from PayFast

// Log the notification
file_put_contents('payfast_itn_log.txt', date('Y-m-d H:i:s') . " - ITN Received\n" . file_get_contents('php://input') . "\n\n", FILE_APPEND);

// Get the POST data from PayFast
$pfData = $_POST;

// Debug logging
error_log("PayFast ITN Received: " . print_r($pfData, true));

// Verify the signature (important for security)
$pfParamString = '';
foreach($pfData as $key => $val) {
    if($key != 'signature') {
        $pfParamString .= $key .'='. urlencode($val) .'&';
    }
}
$pfParamString = substr($pfParamString, 0, -1);

// Calculate security signature
$tempParamString = $pfParamString;
$passphrase = ''; // Your PayFast passphrase if set
if(!empty($passphrase)) {
    $tempParamString .= '&passphrase=' . urlencode($passphrase);
}
$calculatedSignature = md5($tempParamString);
$receivedSignature = $pfData['signature'] ?? '';

// Verify signature
if($calculatedSignature !== $receivedSignature) {
    error_log("PayFast ITN Signature Mismatch: Calculated: $calculatedSignature, Received: $receivedSignature");
    header('HTTP/1.0 400 Bad Request');
    echo 'Signature mismatch';
    exit;
}

// Check payment status
$paymentStatus = $pfData['payment_status'] ?? '';
$mPaymentId = $pfData['m_payment_id'] ?? '';
$amountGross = $pfData['amount_gross'] ?? '0.00';

if($paymentStatus == 'COMPLETE') {
    // Payment successful - update ticket status
    handleSuccessfulPayment($mPaymentId, $pfData);
    
    // Send confirmation email if not already sent
    sendTicketEmailAfterPayment($mPaymentId);
    
    // Log success
    file_put_contents('successful_payments.txt', date('Y-m-d H:i:s') . " - $mPaymentId - R$amountGross\n", FILE_APPEND);
} elseif($paymentStatus == 'FAILED' || $paymentStatus == 'CANCELLED') {
    // Payment failed
    handleFailedPayment($mPaymentId);
}

// Always return 200 OK to PayFast
header('HTTP/1.0 200 OK');
echo 'ITN received successfully';
exit;

// Helper Functions
function handleSuccessfulPayment($paymentId, $paymentData) {
    // Load pending payments
    $pendingPayments = [];
    if (file_exists('pending_payments.json')) {
        $pendingData = file_get_contents('pending_payments.json');
        if ($pendingData) {
            $pendingPayments = json_decode($pendingData, true) ?: [];
        }
    }
    
    // Find this payment
    if (isset($pendingPayments[$paymentId])) {
        $ticketData = $pendingPayments[$paymentId];
        $ticketData['paymentStatus'] = 'completed';
        $ticketData['payfastData'] = $paymentData;
        $ticketData['completedAt'] = date('Y-m-d H:i:s');
        
        // Generate tickets
        $ticketData['ticketNumbers'] = [];
        $ticketData['qrCodes'] = [];
        
        for ($i = 0; $i < $ticketData['quantity']; $i++) {
            $ticketNumber = 'FCW' . date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $ticketData['ticketNumbers'][] = $ticketNumber;
            
            // Generate QR code
            $qrCodePath = generateQRCode($ticketNumber, $ticketData['fullName'], $ticketData['email']);
            $ticketData['qrCodes'][] = $qrCodePath;
        }
        
        // Save to completed tickets
        $completedTickets = [];
        if (file_exists('tickets.json')) {
            $ticketsData = file_get_contents('tickets.json');
            if ($ticketsData) {
                $completedTickets = json_decode($ticketsData, true) ?: [];
            }
        }
        
        $completedTickets[] = $ticketData;
        file_put_contents('tickets.json', json_encode($completedTickets, JSON_PRETTY_PRINT));
        
        // Remove from pending
        unset($pendingPayments[$paymentId]);
        file_put_contents('pending_payments.json', json_encode($pendingPayments, JSON_PRETTY_PRINT));
        
        return true;
    }
    
    return false;
}

function sendTicketEmailAfterPayment($paymentId) {
    // Load tickets
    $tickets = [];
    if (file_exists('tickets.json')) {
        $ticketsData = file_get_contents('tickets.json');
        if ($ticketsData) {
            $tickets = json_decode($ticketsData, true) ?: [];
        }
    }
    
    // Find this payment's ticket
    foreach ($tickets as $ticket) {
        if (isset($ticket['paymentId']) && $ticket['paymentId'] == $paymentId && $ticket['paymentMethod'] == 'payfast') {
            // Send email
            sendPayFastTicketEmail($ticket);
            return true;
        }
    }
    
    return false;
}

function handleFailedPayment($paymentId) {
    // Log failed payment
    file_put_contents('failed_payments.txt', date('Y-m-d H:i:s') . " - $paymentId\n", FILE_APPEND);
    
    // Optionally send failure email
    // sendPaymentFailedEmail($paymentId);
}

function generateQRCode($ticketNumber, $name, $email) {
    // Create QR code directory
    if (!is_dir('qrcodes')) {
        mkdir('qrcodes', 0755, true);
    }
    
    $qrData = json_encode([
        'ticket' => $ticketNumber,
        'event' => 'For Christ Worship',
        'date' => '2026-03-28',
        'name' => $name,
        'email' => $email,
        'timestamp' => time()
    ]);
    
    $qrPath = "qrcodes/{$ticketNumber}.png";
    $url = "https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=" . urlencode($qrData);
    
    $context = stream_context_create(['ssl' => ['verify_peer' => false]]);
    $qrImage = @file_get_contents($url, false, $context);
    
    if ($qrImage !== false) {
        file_put_contents($qrPath, $qrImage);
        return $qrPath;
    }
    
    return false;
}

function sendPayFastTicketEmail($ticketData) {
    $to = $ticketData['email'];
    $subject = "🎵 Your For Christ Worship Tickets - Payment Confirmed";
    
    // Generate ticket HTML
    $ticketsHtml = '';
    foreach ($ticketData['ticketNumbers'] as $index => $ticketNumber) {
        $ticketsHtml .= generateTicketEmailHTML($ticketNumber, $ticketData['fullName'], $index + 1);
    }
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
            .email-container { max-width: 600px; margin: 0 auto; background: white; border-radius: 15px; overflow: hidden; }
            .email-header { background: #7b0c0c; color: white; padding: 2rem; text-align: center; }
            .email-content { padding: 2rem; }
            .ticket { background: white; border: 3px solid #7b0c0c; border-radius: 15px; padding: 2rem; margin: 2rem 0; }
            .success-badge { background: #d4edda; color: #155724; padding: 1rem; border-radius: 10px; margin: 1rem 0; }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='email-header'>
                <h1>✅ Payment Confirmed!</h1>
                <p>Your For Christ Worship Tickets</p>
            </div>
            
            <div class='email-content'>
                <div class='success-badge'>
                    <h3>🎉 Payment Successful!</h3>
                    <p>Your payment of <strong>R{$ticketData['totalAmount']}</strong> has been confirmed.</p>
                </div>
                
                <h3>Event Details:</h3>
                <p><strong>Date:</strong> March 28, 2026</p>
                <p><strong>Time:</strong> Photoshoot 17:00 | Worship 18:00</p>
                <p><strong>Venue:</strong> Oukerk Klerksdorp (Behind Magistrate Court)</p>
                <p><strong>Tickets:</strong> {$ticketData['quantity']}</p>
                
                <h3>Your Tickets:</h3>
                {$ticketsHtml}
                
                <div style='background: #fff3cd; padding: 1.5rem; border-radius: 10px; margin: 2rem 0;'>
                    <h4>📋 Important Information</h4>
                    <ul>
                        <li>Bring printed ticket or show on phone</li>
                        <li>Each QR code is unique - scan at entrance</li>
                        <li>Photoshoot starts at 17:00 - Don't miss it!</li>
                        <li>Contact: 064 653 4658 or fcw@tsatengproductions.org</li>
                    </ul>
                </div>
                
                <p><em>Thank you for supporting For Christ Worship!</em></p>
                <p><strong>The Tsateng Productions Team</strong></p>
            </div>
        </div>
    </body>
    </html>";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: For Christ Worship <fcw@tsatengproductions.org>" . "\r\n";
    $headers .= "Reply-To: fcw@tsatengproductions.org" . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

function generateTicketEmailHTML($ticketNumber, $name, $index) {
    return "
    <div class='ticket'>
        <h3>Ticket #{$index}</h3>
        <div style='font-family: monospace; font-size: 1.3rem; font-weight: bold; color: #7b0c0c; margin: 1rem 0;'>
            {$ticketNumber}
        </div>
        <div style='margin: 1rem 0;'>
            <p><strong>Name:</strong> {$name}</p>
            <p><strong>Event:</strong> For Christ Worship</p>
            <p><strong>Date:</strong> March 28, 2026</p>
            <p><strong>Type:</strong> General Admission</p>
        </div>
        <div style='text-align: center; margin: 1.5rem 0;'>
            <div style='background: #f0f0f0; padding: 1rem; border-radius: 8px; display: inline-block;'>
                <strong>QR CODE WILL BE SCANNED AT ENTRANCE</strong><br>
                Ticket: {$ticketNumber}
            </div>
        </div>
    </div>";
}
?>