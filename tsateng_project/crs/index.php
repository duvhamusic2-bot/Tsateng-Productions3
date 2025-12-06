<!DOCTYPE html>
<html>
<head>
    <title>Tsateng Productions - PHP Server</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1 { color: #333; }
        .menu { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .menu a { margin-right: 15px; text-decoration: none; color: #0066cc; }
        .menu a:hover { text-decoration: underline; }
        .status { background: #e8f5e8; padding: 10px; border-radius: 3px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Tsateng Productions PHP Server</h1>
    
    <div class="status">
        <strong>✅ PHP is working!</strong><br>
        PHP Version: <?php echo phpversion(); ?><br>
        Server: <?php echo $_SERVER['SERVER_SOFTWARE']; ?>
    </div>
    
    <div class="menu">
        <h3>Available PHP Files:</h3>
        <?php
        $files = glob("*.php");
        foreach ($files as $file) {
            if ($file != "index.php") {
                echo '<a href="' . $file . '">' . $file . '</a><br>';
            }
        }
        ?>
    </div>
    
    <p><a href="https://www.tsatengproductions.org">← Back to Main Website</a></p>
</body>
</html>