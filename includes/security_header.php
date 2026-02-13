<?php
/**
 * Security Headers
 * Tested for Security Headers Grade A+
 */

// Prevent clickjacking
header('X-Frame-Options: SAMEORIGIN');

// Enable XSS protection (legacy browsers)
header('X-XSS-Protection: 1; mode=block');

// Prevent MIME type sniffing
header('X-Content-Type-Options: nosniff');

// Referrer policy - privacy focused
header('Referrer-Policy: strict-origin-when-cross-origin');

// Permissions Policy (new format with spaces, not commas)
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// Content Security Policy (single line, properly escaped)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; img-src 'self' data: https: blob:; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com data:; connect-src 'self'; media-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; upgrade-insecure-requests");

// HSTS (HTTPS Strict Transport Security)
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Remove server information
header_remove('X-Powered-By');
