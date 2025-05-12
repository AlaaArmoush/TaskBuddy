/* Create uploads directories to store uploaded images */
<?php
$portfolio_dir = 'uploads/portfolio/';
$profiles_dir = 'uploads/profiles/';

if (!file_exists($portfolio_dir)) {
    if (!mkdir($portfolio_dir, 0777, true)) {
        die('Failed to create portfolio directory');
    }

    file_put_contents($portfolio_dir . 'index.php', '');

    echo "Created portfolio directory: $portfolio_dir<br>";
}

if (!file_exists($profiles_dir)) {
    if (!mkdir($profiles_dir, 0777, true)) {
        die('Failed to create profiles directory');
    }

    file_put_contents($profiles_dir . 'index.php', '');

    echo "Created profiles directory: $profiles_dir<br>";
}

echo "All directories are ready for file uploads.";
?>