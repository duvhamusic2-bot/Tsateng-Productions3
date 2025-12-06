<?php
// email.php - Enhanced Contact Form Handler
header('Content-Type: application/json');

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production

// Configuration
$config = [
    'company_name' => 'Tsateng Productions',
    'company_email' => 'info@tsatengproductions.org',
    'emails' => [
        'general' => 'info@tsatengproductions.org',
        'music' => 'music@tsatengproductions.org',
        'transport' => 'transport@tsatengproductions.org',
        'supply' => 'supplies@tsatengproductions.org'
    ],
    'rate_limit' => [
        'max_requests' => 5,
        'time_window' => 3600 // 1 hour in seconds
    ],
    'save_submissions' => true,
    'send_confirmation' => true
];

// Get client IP address (works behind proxies)
function getClientIP() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Check rate limiting
function checkRateLimit($ip, $config) {
    $limit_file = 'rate_limits.json';
    $current_time = time();
    $window = $config['rate_limit']['time_window'];
    $max_requests = $config['rate_limit']['max_requests'];
    
    if (!file_exists($limit_file)) {
        $limits = [];
    } else {
        $limits_data = file_get_contents($limit_file);
        $limits = $limits_data ? json_decode($limits_data, true) : [];
    }
    
    // Clean old entries
    $limits = array_filter($limits, function($entry) use ($current_time, $window) {
        return ($current_time - $entry['time']) < $window;
    });
    
    // Count submissions from this IP
    $ip_submissions = array_filter($limits, function($entry) use ($ip) {
        return $entry['ip'] === $ip;
    });
    
    if (count($ip_submissions) >= $max_requests) {
        return false;
    }
    
    // Add new submission
    $limits[] = ['ip' => $ip, 'time' => $current_time];
    file_put_contents($limit_file, json_encode(array_values($limits)));
    
    return true;
}

// Get recipient email by department
function getRecipientByDepartment($department, $config) {
    return $config['emails'][$department] ?? $config['emails']['general'];
}

// Log function
function logContact($data, $status) {
    $logEntry = date('Y-m-d H:i:s') . " | Status: {$status} | ";
    $logEntry .= "Name: " . (isset($data['name']) ? substr($data['name'], 0, 50) : 'N/A') . " | ";
    $logEntry .= "Email: " . (isset($data['email']) ? $data['email'] : 'N/A') . " | ";
    $logEntry .= "Department: " . (isset($data['department']) ? $data['department'] : 'general') . " | ";
    $logEntry .= "IP: " . getClientIP() . " | ";
    $logEntry .= "Referer: " . ($_SERVER['HTTP_REFERER'] ?? 'direct') . "\n";
    
    // Ensure logs directory exists
    if (!is_dir('logs')) {
        mkdir('logs', 0755, true);
    }
    
    file_put_contents('logs/contact_logs.txt', $logEntry, FILE_APPEND);
}

// Validate input
function validateInput($name, $email, $phone, $message, $department) {
    $errors = [];
    
    // Honeypot check
    if (!empty($_POST['website'])) {
        return []; // Bot detected - pretend success
    }
    
    // Name validation
    $name = trim($name);
    if (empty($name)) {
        $errors[] = "Name is required.";
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $errors[] = "Name must be between 2 and 100 characters.";
    } elseif (preg_match('/[0-9]/', $name)) {
        $errors[] = "Name should not contain numbers.";
    }
    
    // Email validation
    $email = trim($email);
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        $errors[] = "Invalid email format.";
    }
    
    // Phone validation (optional)
    if (!empty($phone)) {
        $phone = trim($phone);
        if (!preg_match('/^[\d\s\-\+\(\)]{10,20}$/', $phone)) {
            $errors[] = "Please enter a valid phone number (10-20 digits).";
        }
    }
    
    // Message validation
    $message = trim($message);
    if (empty($message)) {
        $errors[] = "Message is required.";
    } elseif (strlen($message) < 10) {
        $errors[] = "Message must be at least 10 characters.";
    } elseif (strlen($message) > 2000) {
        $errors[] = "Message cannot exceed 2000 characters.";
    }
    
    // Department validation
    $valid_departments = ['general', 'music', 'transport', 'supply'];
    if (!in_array($department, $valid_departments)) {
        $errors[] = "Invalid department selected.";
    }
    
    return $errors;
}

// Send email with retry
function sendEmail($to, $subject, $message, $headers, $retries = 2) {
    for ($i = 0; $i <= $retries; $i++) {
        if (mail($to, $subject, $message, $headers)) {
            return true;
        }
        if ($i < $retries) {
            usleep(500000); // Wait 0.5 seconds before retry
        }
    }
    return false;
}

// Main processing
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get client IP
    $client_ip = getClientIP();
    
    // Check rate limit
    if (!checkRateLimit($client_ip, $config)) {
        logContact([], 'rate_limit_exceeded');
        echo json_encode([
            "success" => false, 
            "message" => "Too many submissions. Please try again in an hour."
        ]);
        exit;
    }
    
    // Get and sanitize form data
    $name = htmlspecialchars(trim($_POST["name"] ?? ''), ENT_QUOTES, 'UTF-8');
    $email = filter_var(trim($_POST["email"] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone = htmlspecialchars(trim($_POST["phone"] ?? ''), ENT_QUOTES, 'UTF-8');
    $subject = htmlspecialchars(trim($_POST["subject"] ?? 'Contact Form Submission'), ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars(trim($_POST["message"] ?? ''), ENT_QUOTES, 'UTF-8');
    $department = htmlspecialchars(trim($_POST["department"] ?? 'general'), ENT_QUOTES, 'UTF-8');
    
    // Validate input
    $validationErrors = validateInput($name, $email, $phone, $message, $department);
    
    if (!empty($validationErrors)) {
        logContact(compact('name', 'email', 'department', 'subject'), 'validation_failed: ' . implode(', ', $validationErrors));
        echo json_encode([
            "success" => false, 
            "message" => implode(' ', $validationErrors)
        ]);
        exit;
    }
    
    // Honeypot check (silent success for bots)
    if (!empty($_POST['website'])) {
        logContact([], 'bot_detected');
        echo json_encode(["success" => true, "message" => "Thank you for your message!"]);
        exit;
    }
    
    // Determine recipient email
    $recipientEmail = getRecipientByDepartment($department, $config);
    
    // Prepare email content
    $emailSubject = "[Tsateng Productions] " . ($subject ?: "Contact Form Submission");
    $timestamp = date('Y-m-d H:i:s');
    
    // Main email to recipient
    $emailMessage = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <meta charset='UTF-8'>
        <title>Contact Form Submission</title>
        <style>
            body { margin:0; padding:0; background:#f5f5f5; font-family:Arial,sans-serif; }
            .container { max-width:600px; margin:0 auto; background:white; border-radius:10px; overflow:hidden; }
            .header { background:#7b0c0c; color:white; padding:20px; text-align:center; }
            .content { padding:20px; }
            .info-box { background:#f8f9fa; padding:15px; border-radius:8px; margin:15px 0; }
            @media only screen and (max-width: 600px) {
                .container { width:100% !important; }
                .header { padding:15px !important; }
                .content { padding:15px !important; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin:0;'>📨 New Contact Form Submission</h1>
                <p style='margin:5px 0 0 0;'>Tsateng Productions - " . ucfirst($department) . " Department</p>
            </div>
            
            <div class='content'>
                <div class='info-box'>
                    <h3 style='margin-top:0; color:#7b0c0c;'>Contact Information</h3>
                    <p><strong>Name:</strong> {$name}</p>
                    <p><strong>Email:</strong> {$email}</p>
                    <p><strong>Phone:</strong> " . ($phone ?: 'Not provided') . "</p>
                    <p><strong>Subject:</strong> {$subject}</p>
                    <p><strong>Department:</strong> " . ucfirst($department) . "</p>
                    <p><strong>Submitted:</strong> {$timestamp}</p>
                    <p><strong>IP Address:</strong> {$client_ip}</p>
                    <p><strong>User Agent:</strong> " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "</p>
                </div>
                
                <div style='margin:20px 0;'>
                    <h3 style='color:#7b0c0c;'>Message:</h3>
                    <div style='background:#f5f5f5; padding:15px; border-radius:8px; border-left:4px solid #7b0c0c;'>
                        " . nl2br(htmlspecialchars($message)) . "
                    </div>
                </div>
                
                <div style='margin-top:30px; padding-top:15px; border-top:1px solid #ddd;'>
                    <p style='color:#666; font-size:12px;'>
                        This email was sent from the contact form on Tsateng Productions website.<br>
                        Reply directly to: <a href='mailto:{$email}'>{$email}</a>
                    </p>
                </div>
            </div>
        </div>
    </body>
    </html>";
    
    // Headers for main email
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Tsateng Productions Website <noreply@tsatengproductions.org>\r\n";
    $headers .= "Reply-To: {$email}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    $headers .= "X-Contact-Form: true\r\n";
    $headers .= "X-Department: {$department}\r\n";
    
    // Send main email
    $mailSent = sendEmail($recipientEmail, $emailSubject, $emailMessage, $headers);
    
    // Send confirmation to sender if enabled
    $confirmationSent = false;
    if ($config['send_confirmation'] && $mailSent) {
        $confirmationSubject = "Thank you for contacting Tsateng Productions";
        $confirmationMessage = "
        <html>
        <head><meta name='viewport' content='width=device-width, initial-scale=1.0'></head>
        <body style='font-family:Arial,sans-serif;'>
            <div style='max-width:600px; margin:0 auto; padding:20px;'>
                <h2 style='color:#7b0c0c;'>Thank you for contacting Tsateng Productions!</h2>
                <p>Dear {$name},</p>
                <p>We have received your message and our " . ucfirst($department) . " team will respond as soon as possible.</p>
                
                <div style='background:#f8f9fa; padding:15px; border-radius:8px; margin:20px 0;'>
                    <h4 style='margin-top:0;'>Your Message Details:</h4>
                    <p><strong>Subject:</strong> {$subject}</p>
                    <p><strong>Submitted:</strong> " . date('F j, Y \a\t g:i a') . "</p>
                    <p><strong>Reference:</strong> #" . substr(md5($timestamp . $email), 0, 8) . "</p>
                </div>
                
                <div style='background:#fff3cd; padding:15px; border-radius:8px; margin:20px 0;'>
                    <h4 style='margin-top:0; color:#856404;'>📞 Need Immediate Assistance?</h4>
                    <p><strong>Phone:</strong> 064 653 4658</p>
                    <p><strong>Emergency:</strong> 064 653 4658</p>
                    <p><strong>Address:</strong> 25 Mafohla Street, Kanana, Orkney, 2619</p>
                    <p><strong>Business Hours:</strong> Mon-Fri 8:00 AM - 5:00 PM</p>
                </div>
                
                <p>We appreciate your interest in our services:</p>
                <ul>
                    <li>🎵 <strong>Music Division:</strong> Recording, production, artist development</li>
                    <li>🚗 <strong>Transport Division:</strong> School transport, corporate shuttles</li>
                    <li>👕 <strong>Supply Division:</strong> Clothing sourcing and delivery</li>
                    <li>🙏 <strong>For Christ Worship:</strong> Gospel music group and events</li>
                </ul>
                
                <div style='text-align:center; margin:30px 0;'>
                    <a href='https://tsatengproductions.org/home.html' style='background:#7b0c0c; color:white; padding:12px 24px; text-decoration:none; border-radius:5px; display:inline-block;'>
                        Visit Our Website
                    </a>
                </div>
                
                <p>Best regards,<br><strong>The Tsateng Productions Team</strong></p>
                
                <hr style='margin:30px 0;'>
                <p style='font-size:12px; color:#666;'>
                    This is an automated confirmation. Please do not reply to this email.<br>
                    Tsateng Productions Pty Ltd | Reg: 2019/380354/07
                </p>
            </div>
        </body>
        </html>";
        
        $confirmationHeaders = "MIME-Version: 1.0\r\n";
        $confirmationHeaders .= "Content-Type: text/html; charset=UTF-8\r\n";
        $confirmationHeaders .= "From: Tsateng Productions <info@tsatengproductions.org>\r\n";
        
        $confirmationSent = sendEmail($email, $confirmationSubject, $confirmationMessage, $confirmationHeaders);
    }
    
    // Save submission to JSON file if enabled
    if ($config['save_submissions']) {
        $submission = [
            'timestamp' => $timestamp,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'subject' => $subject,
            'message' => $message,
            'department' => $department,
            'recipient' => $recipientEmail,
            'ip' => $client_ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'direct',
            'email_sent' => $mailSent,
            'confirmation_sent' => $confirmationSent
        ];
        
        $submissions_file = 'data/contact_submissions.json';
        
        // Ensure data directory exists
        if (!is_dir('data')) {
            mkdir('data', 0755, true);
        }
        
        $submissions = [];
        if (file_exists($submissions_file)) {
            $submissions_data = file_get_contents($submissions_file);
            if ($submissions_data) {
                $submissions = json_decode($submissions_data, true) ?: [];
            }
        }
        
        $submissions[] = $submission;
        
        // Keep only last 1000 submissions
        if (count($submissions) > 1000) {
            $submissions = array_slice($submissions, -1000);
        }
        
        file_put_contents($submissions_file, json_encode($submissions, JSON_PRETTY_PRINT));
    }
    
    // Log result
    if ($mailSent) {
        logContact(compact('name', 'email', 'department', 'subject'), 'success');
        echo json_encode([
            "success" => true,
            "message" => "Thank you! Your message has been sent. We'll contact you soon.",
            "email_sent_to" => $recipientEmail,
            "confirmation_sent" => $confirmationSent,
            "reference" => substr(md5($timestamp . $email), 0, 8)
        ]);
    } else {
        logContact(compact('name', 'email', 'department', 'subject'), 'email_failed');
        echo json_encode([
            "success" => false,
            "message" => "Message saved but email failed. Please call us at 064 653 4658.",
            "alternative_contact" => "064 653 4658"
        ]);
    }
    
} else {
    logContact([], 'invalid_method');
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method. Please use the contact form."
    ]);
}
?>