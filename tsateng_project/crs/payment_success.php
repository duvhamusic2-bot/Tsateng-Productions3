<?php
// payment_success.php - PayFast Payment Success Page
session_start();

// Log successful payment
file_put_contents('payment_log.txt', date('Y-m-d H:i:s') . " - SUCCESS - IP: " . $_SERVER['REMOTE_ADDR'] . "\n", FILE_APPEND);

// Check if we have payment data
$paymentId = $_GET['m_payment_id'] ?? 'Unknown';
$amount = $_GET['amount_gross'] ?? '0.00';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Tsateng Productions</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .payment-result {
            max-width: 600px;
            margin: 50px auto;
            padding: 3rem;
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            text-align: center;
            border: 2px solid #7b0c0c;
            box-shadow: 0 10px 30px rgba(123, 12, 12, 0.2);
        }
        .payment-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: bounce 1s infinite alternate;
        }
        @keyframes bounce {
            from { transform: translateY(0); }
            to { transform: translateY(-10px); }
        }
        .payment-details {
            background: #d4edda;
            padding: 1.5rem;
            border-radius: 10px;
            margin: 2rem 0;
            text-align: left;
        }
        .next-steps {
            background: #fff3cd;
            padding: 1.5rem;
            border-radius: 10px;
            margin: 1.5rem 0;
            text-align: left;
        }
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="animated-gradient"></div>
    
    <div class="payment-result">
        <div class="payment-icon">✅</div>
        <h1 style="color: #155724;">Payment Successful!</h1>
        <p>Thank you for your purchase. Your payment has been confirmed.</p>
        
        <div class="payment-details">
            <h3>📋 Payment Details</h3>
            <p><strong>Reference:</strong> <?php echo htmlspecialchars($paymentId); ?></p>
            <p><strong>Amount Paid:</strong> R<?php echo htmlspecialchars($amount); ?></p>
            <p><strong>Status:</strong> <span style="color: #155724; font-weight: bold;">Confirmed</span></p>
        </div>
        
        <div class="next-steps">
            <h3>🎫 Next Steps</h3>
            <ul>
                <li><strong>Check your email</strong> (including spam folder) for tickets</li>
                <li>Each ticket has a <strong>unique QR code</strong> for scanning</li>
                <li>Bring <strong>printed copy or show on phone</strong> at entrance</li>
                <li><strong>Photoshoot starts at 17:00</strong> - Don't miss it!</li>
                <li>Worship experience begins at <strong>18:00</strong></li>
                <li>Venue: <strong>Oukerk Klerksdorp</strong> (Behind Magistrate Court)</li>
            </ul>
        </div>
        
        <div style="background: #e2e3e5; padding: 1rem; border-radius: 8px; margin: 1.5rem 0;">
            <p><strong>📧 Email not received?</strong></p>
            <p>Check spam folder or contact: <strong>fcw@tsatengproductions.org</strong></p>
            <p>Phone: <strong>064 653 4658</strong></p>
        </div>
        
        <div class="action-buttons">
            <a href="for_christ_worship.html" class="btn-primary" style="text-decoration: none;">
                Back to Event Page
            </a>
            <a href="home.html" class="btn-secondary" style="text-decoration: none;">
                Go to Homepage
            </a>
            <button onclick="window.print()" class="btn-secondary" style="cursor: pointer;">
                Print This Page
            </button>
        </div>
        
        <p style="margin-top: 2rem; font-size: 0.9rem; color: #666;">
            Transaction processed securely by PayFast • Tsateng Productions © 2026
        </p>
    </div>
    
    <script>
        // Auto-check for page refresh (prevents duplicate submissions)
        if (window.performance && window.performance.navigation.type === window.performance.navigation.TYPE_RELOAD) {
            window.location.href = 'for_christ_worship.html';
        }
        
        // Log successful payment view
        fetch('log_payment_view.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                paymentId: '<?php echo $paymentId; ?>',
                type: 'success_view',
                timestamp: new Date().toISOString()
            })
        });
    </script>
</body>
</html>