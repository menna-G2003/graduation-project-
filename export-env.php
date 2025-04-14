<?php
/**
 * Export environment variables from .env to a downloadable file
 */

// Read the .env file
$envFile = file_get_contents(__DIR__ . '/.env');

// Add some instructions
$instructions = <<<EOT
# Real Estate Listings API - Environment Variables
# Copy these variables to your .env file

EOT;

// Create the downloadable content
$content = $instructions . $envFile;

// Set headers for download
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename=".env.example"');
header('Content-Length: ' . strlen($content));

// Output the content
echo $content;
exit; 