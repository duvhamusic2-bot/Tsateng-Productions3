<?php
// generate_ticket.php - Dynamic Ticket Image Generator with Mobile Support
header('Content-Type: application/json');

// Allow CORS for mobile access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Handle GET requests for testing
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['test']) && $_GET['test'] == '1') {
        echo json_encode([
            "status" => "online",
            "service" => "Ticket Generator",
            "gd_installed" => extension_loaded('gd'),
            "version" => "1.0",
            "timestamp" => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    // Show form for testing
    if (isset($_GET['form'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Test Ticket Generator</title>
            <style>
                body { font-family: Arial; padding: 20px; background: #f5f5f5; }
                .container { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
                input, textarea { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; }
                button { background: #7b0c0c; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; }
                @media (max-width: 600px) { .container { padding: 15px; } }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>Test Ticket Generator</h2>
                <form id="ticketForm">
                    <input type="text" name="ticketNumber" placeholder="Ticket Number" value="FCW202501010001" required>
                    <input type="text" name="name" placeholder="Name" value="John Doe" required>
                    <input type="email" name="email" placeholder="Email" value="test@example.com" required>
                    <textarea name="extra" placeholder="Extra info (optional)"></textarea>
                    <button type="submit">Generate Ticket</button>
                </form>
                <div id="result" style="margin-top: 20px;"></div>
            </div>
            <script>
                document.getElementById('ticketForm').addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const formData = new FormData(e.target);
                    
                    const response = await fetch('generate_ticket.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    const resultDiv = document.getElementById('result');
                    
                    if (result.success && result.imageUrl) {
                        resultDiv.innerHTML = `
                            <h3>Ticket Generated Successfully!</h3>
                            <img src="${result.imageUrl}" alt="Generated Ticket" style="max-width:100%; border:1px solid #ccc; border-radius:5px;">
                            <p><a href="${result.imageUrl}" download>Download Ticket</a></p>
                            <pre>${JSON.stringify(result, null, 2)}</pre>
                        `;
                    } else {
                        resultDiv.innerHTML = `<div style="color:red;">Error: ${result.message}</div>`;
                    }
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get data
    $ticketNumber = filter_var(trim($_POST['ticketNumber'] ?? ''), FILTER_SANITIZE_STRING);
    $name = filter_var(trim($_POST['name'] ?? ''), FILTER_SANITIZE_STRING);
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $extra = filter_var(trim($_POST['extra'] ?? ''), FILTER_SANITIZE_STRING);
    $type = $_POST['type'] ?? 'general';
    
    // Validate
    if (empty($ticketNumber) || empty($name)) {
        echo json_encode(["success" => false, "message" => "Ticket number and name are required."]);
        exit;
    }
    
    // Check if GD is installed
    if (!extension_loaded('gd')) {
        echo json_encode(["success" => false, "message" => "GD library not installed. Cannot generate images."]);
        exit;
    }
    
    // Generate the ticket
    $ticketImage = generateDynamicTicket($ticketNumber, $name, $email, $extra, $type);
    
    if ($ticketImage) {
        // Return the image URL
        $imageUrl = 'generated_tickets/' . basename($ticketImage);
        echo json_encode([
            "success" => true,
            "message" => "Ticket generated successfully.",
            "imageUrl" => $imageUrl,
            "downloadUrl" => $imageUrl,
            "ticketData" => [
                "ticketNumber" => $ticketNumber,
                "name" => $name,
                "email" => $email,
                "generated" => date('Y-m-d H:i:s'),
                "type" => $type
            ]
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to generate ticket image."]);
    }
}

function generateDynamicTicket($ticketNumber, $name, $email, $extra, $type) {
    // IMPORTANT: The user MUST replace [YOUR_BUCKET_NAME] with their actual bucket name
    $baseImageUrl = 'https://www.tsatengproductions.org/images/ticket-image.jpg'; 
    
    // Fetch the image data from the URL
    // We use stream_context_create to handle potential SSL issues, similar to your QR code logic
    $context = stream_context_create(['ssl' => ['verify_peer' => false]]);
    $imageData = @file_get_contents($baseImageUrl, false, $context);

    if ($imageData === false) {
        // Fallback if fetching the image fails
        return generateFallbackTicketImage($ticketNumber, $name, $email, $type);
    }

    // Create image resource from the fetched data
    $image = imagecreatefromstring($imageData);

    if (!$image) {
        return generateFallbackTicketImage($ticketNumber, $name, $email, $type);
    }
    
    // Get image info
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Colors
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $red = imagecolorallocate($image, 123, 12, 12);
    $green = imagecolorallocate($image, 40, 167, 69);
    $blue = imagecolorallocate($image, 0, 123, 255);
    
    // Choose color based on type
    $color = $red; // Default
    if ($type == 'cash') $color = $green;
    
    // Font sizes (responsive)
    $fontSizeLarge = $height * 0.035;  // 3.5%
    $fontSizeMedium = $height * 0.025; // 2.5%
    $fontSizeSmall = $height * 0.018;  // 1.8%
    
    // Check for TTF font
    $fontPath = 'fonts/arial.ttf';
    $useTTF = file_exists($fontPath);
    
    // POSITIONING
    // 1. TICKET NUMBER (70%, 90%)
    $ticketX = $width * 0.70;
    $ticketY = $height * 0.90;
    
    if ($useTTF) {
        imagettftext($image, $fontSizeLarge, 0, $ticketX, $ticketY, $color, $fontPath, $ticketNumber);
    } else {
        imagestring($image, 5, $ticketX, $ticketY, $ticketNumber, $color);
    }
    
    // 2. NAME (70%, 94%)
    $nameX = $width * 0.70;
    $nameY = $height * 0.94;
    $nameText = "Name: " . substr($name, 0, 30);
    
    if ($useTTF) {
        imagettftext($image, $fontSizeMedium, 0, $nameX, $nameY, $black, $fontPath, $nameText);
    } else {
        imagestring($image, 4, $nameX, $nameY, $nameText, $black);
    }
    
    // 3. Extra info if provided (small, top right)
    if (!empty($extra)) {
        $extraX = $width * 0.80;
        $extraY = $height * 0.10;
        $extraText = substr($extra, 0, 20);
        
        if ($useTTF) {
            imagettftext($image, $fontSizeSmall, 0, $extraX, $extraY, $blue, $fontPath, $extraText);
        } else {
            imagestring($image, 2, $extraX, $extraY, $extraText, $blue);
        }
    }
    
    // 4. Type indicator (top left)
    $typeX = $width * 0.05;
    $typeY = $height * 0.10;
    $typeText = strtoupper($type);
    
    if ($useTTF) {
        imagettftext($image, $fontSizeMedium, 0, $typeX, $typeY, $color, $fontPath, $typeText);
    } else {
        imagestring($image, 4, $typeX, $typeY, $typeText, $color);
    }
    
    // 5. Generate and add QR Code
    $qrCode = generateQRForTicket($ticketNumber, $name, $email, $type);
    if ($qrCode && file_exists($qrCode)) {
        $qrImage = imagecreatefrompng($qrCode);
        if ($qrImage) {
            $qrWidth = imagesx($qrImage);
            $qrHeight = imagesy($qrImage);
            
            // QR CODE POSITION (82%, 88%)
            $qrX = $width * 0.82;
            $qrY = $height * 0.88;
            $qrSize = $height * 0.12;
            
            imagecopyresampled($image, $qrImage, $qrX, $qrY, 0, 0, $qrSize, $qrSize, $qrWidth, $qrHeight);
            imagedestroy($qrImage);
        }
    }
    
    // Save the image
    $outputDir = 'generated_tickets/';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $filename = $ticketNumber . '_' . time() . '.jpg';
    $outputPath = $outputDir . $filename;
    
    // Save with quality
    imagejpeg($image, $outputPath, 92);
    imagedestroy($image);
    
    return $outputPath;
}

function generateQRForTicket($ticketNumber, $name, $email, $type) {
    if (!is_dir('qrcodes')) {
        mkdir('qrcodes', 0755, true);
    }
    
    $qrData = json_encode([
        'ticket' => $ticketNumber,
        'name' => $name,
        'email' => $email,
        'type' => $type,
        'event' => 'For Christ Worship - Songs of Redemption',
        'date' => '2026-03-28',
        'generated' => time(),
        'verification' => md5($ticketNumber . time())
    ]);
    
    $qrPath = "qrcodes/{$ticketNumber}_{$type}.png";
    $url = "https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=" . urlencode($qrData);
    
    $context = stream_context_create(['ssl' => ['verify_peer' => false]]);
    $qrImage = @file_get_contents($url, false, $context);
    
    if ($qrImage !== false) {
        file_put_contents($qrPath, $qrImage);
        return $qrPath;
    }
    
    return false;
}

function generateFallbackTicketImage($ticketNumber, $name, $email, $type) {
    // Create a simple ticket image
    $width = 600;
    $height = 300;
    
    $image = imagecreate($width, $height);
    $white = imagecolorallocate($image, 255, 255, 255);
    $red = imagecolorallocate($image, 123, 12, 12);
    $green = imagecolorallocate($image, 40, 167, 69);
    $blue = imagecolorallocate($image, 0, 123, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    
    imagefill($image, 0, 0, $white);
    
    // Choose border color
    $borderColor = $red;
    if ($type == 'cash') $borderColor = $green;
    
    // Border
    imagerectangle($image, 10, 10, $width-10, $height-10, $borderColor);
    imagerectangle($image, 8, 8, $width-8, $height-8, $borderColor);
    
    // Title
    imagestring($image, 5, 50, 30, "FOR CHRIST WORSHIP", $borderColor);
    imagestring($image, 3, 50, 60, "Songs of Redemption Album Launch", $black);
    
    // Ticket info
    imagestring($image, 4, 50, 100, "Ticket: " . $ticketNumber, $borderColor);
    imagestring($image, 3, 50, 130, "Name: " . substr($name, 0, 30), $black);
    imagestring($image, 3, 50, 160, "Type: " . strtoupper($type), $borderColor);
    
    // Event details
    imagestring($image, 3, 50, 190, "Date: March 28, 2026", $black);
    imagestring($image, 3, 50, 210, "Venue: Oukerk Klerksdorp", $black);
    
    // Scan info
    imagestring($image, 2, 50, 250, "SCAN QR AT ENTRANCE", $borderColor);
    
    // Save
    $outputDir = 'generated_tickets/';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $filename = $ticketNumber . '_fallback.jpg';
    $outputPath = $outputDir . $filename;
    imagejpeg($image, $outputPath, 90);
    imagedestroy($image);
    
    return $outputPath;
}
?>
