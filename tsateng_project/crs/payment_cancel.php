<?php
// payment_cancel.php - PayFast Payment Cancel Page
session_start();

// Log cancelled payment
file_put_contents('payment_log.txt', date('Y-m-d H:i:s') . " - CANCELLED - IP: " . $_SERVER['REMOTE_ADDR'] . "\n", FILE_APPEND);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled - Tsateng Productions</title>
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
        }
        .payment-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        .info-box {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin: 2rem 0;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="animated-gradient"></div>
    
    <div class="payment-result">
        <div class="payment-icon">⚠️</div>
        <h1 style="color: #856404;">Payment Cancelled</h1>
        <p>You cancelled the payment process. No charges were made to your account.</p>
        
        <div class="info-box">
            <h3>ℹ️ What Happened?</h3>
            <ul>
                <li>You chose to cancel the payment</li>
                <li><strong>No money was deducted</strong> from your account</li>
                <li>Your ticket reservation has been released</li>
                <li>You can try again anytime</li>
            </ul>
        </div>
        
        <div style="background: #fff3cd; padding: 1.5rem; border-radius: 10px; margin: 1.5rem 0;">
            <h4>🎫 Other Payment Options</h4>
            <p>If you prefer a different payment method:</p>
            <ul>
                <li><strong>Bank Transfer</strong> - Pay directly to our account</li>
                <li><strong>Cash Payment</strong> - Contact a group member</li>
                <li><strong>Try PayFast again</strong> - Different card/account</li>
            </ul>
        </div>
        
        <div class="action-buttons">
            <a href="for_christ_worship.html" class="btn-primary" style="text-decoration: none;">
                ← Try Payment Again
            </a>
            <a href="home.html" class="btn-secondary" style="text-decoration: none;">
                Go to Homepage
            </a>
        </div>
        
        <p style="margin-top: 2rem; font-size: 0.9rem; color: #666;">
            Need help? Call: <strong>064 653 4658</strong> or Email: <strong>fcw@tsatengproductions.org</strong>
        </p>
    </div>
</body>
</html>