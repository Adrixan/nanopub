<?php

declare(strict_types=1);

/**
 * NanoPub Time Utilities
 * 
 * Time utilities for ActivityPub and display.
 */

/**
 * Get current time in ISO 8601 format (UTC).
 * 
 * @return string ISO 8601 formatted datetime
 */
function now_iso(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

/**
 * Convert timestamp to ISO 8601.
 * 
 * @param int|string|null $timestamp Unix timestamp or ISO string
 * @return string|null ISO 8601 formatted datetime or null
 */
function to_iso8601(int|string|null $timestamp): ?string
{
    if ($timestamp === null) {
        return null;
    }
    
    // If already a string, try to parse it
    if (is_string($timestamp)) {
        $parsed = strtotime($timestamp);
        if ($parsed === false) {
            return null;
        }
        $timestamp = $parsed;
    }
    
    return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
}

/**
 * Parse ISO 8601 string to Unix timestamp.
 * 
 * @param string $iso ISO 8601 datetime string
 * @return int Unix timestamp
 */
function from_iso8601(string $iso): int
{
    $timestamp = strtotime($iso);
    
    if ($timestamp === false) {
        throw new InvalidArgumentException("Invalid ISO 8601 format: $iso");
    }
    
    return $timestamp;
}

/**
 * Format time for display (relative).
 * Returns "just now", "5m ago", "2h ago", "3d ago", or formatted date.
 * 
 * @param string $iso ISO 8601 datetime string
 * @return string Human-readable time string
 */
function time_ago(string $iso): string
{
    $timestamp = from_iso8601($iso);
    $now = time();
    $diff = $now - $timestamp;
    
    // Just now (less than 60 seconds)
    if ($diff < 60) {
        return 'just now';
    }
    
    // Minutes ago (less than 1 hour)
    $minutes = (int) floor($diff / 60);
    if ($minutes < 60) {
        return $minutes . 'm ago';
    }
    
    // Hours ago (less than 24 hours)
    $hours = (int) floor($minutes / 60);
    if ($hours < 24) {
        return $hours . 'h ago';
    }
    
    // Days ago (less than 7 days)
    $days = (int) floor($hours / 24);
    if ($days < 7) {
        return $days . 'd ago';
    }
    
    // More than 7 days: show actual date
    // If same year, show month and day
    $year = gmdate('Y', $timestamp);
    $currentYear = gmdate('Y', $now);
    
    if ($year === $currentYear) {
        return gmdate('M j', $timestamp);
    }
    
    // Different year: show full date
    return gmdate('M j, Y', $timestamp);
}

/**
 * Format time for ActivityPub (ISO 8601 with microseconds).
 * 
 * @param string|null $iso Optional ISO 8601 datetime, defaults to now
 * @return string ISO 8601 formatted datetime with microseconds
 */
function activitypub_time(?string $iso = null): string
{
    if ($iso === null) {
        // Current time with microseconds
        $microtime = microtime(true);
        $seconds = (int) floor($microtime);
        $microseconds = (int) (($microtime - $seconds) * 1000000);
        
        return gmdate('Y-m-d\TH:i:s', $seconds) . '.' . sprintf('%06d', $microseconds) . 'Z';
    }
    
    // Parse and reformat
    $timestamp = from_iso8601($iso);
    return gmdate('Y-m-d\TH:i:s.000000\Z', $timestamp);
}

/**
 * Get current Unix timestamp.
 * 
 * @return int Unix timestamp
 */
function timestamp(): int
{
    return time();
}
