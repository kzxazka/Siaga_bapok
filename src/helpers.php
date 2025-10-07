<?php
/**
 * Helper functions for Siaga Bapok
 */

if (!function_exists('formatRupiah')) {
    function formatRupiah($amount) {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'd M Y') {
        return date($format, strtotime($date));
    }
}

if (!function_exists('formatDateTime')) {
    function formatDateTime($datetime, $format = 'd M Y H:i') {
        return date($format, strtotime($datetime));
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }
}

if (!function_exists('validatePrice')) {
    function validatePrice($price) {
        if (!is_numeric($price)) {
            return false;
        }
        $price = (float) $price;
        return $price >= 0 && $price <= 1000000000; // Max 1 billion
    }
}

if (!function_exists('generateSessionToken')) {
    function generateSessionToken() {
        return bin2hex(random_bytes(32));
    }
}
