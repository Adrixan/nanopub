<?php

declare(strict_types=1);

/**
 * NanoPub Text Processing Utilities
 * 
 * Text processing utilities for content handling.
 */

/**
 * Truncate text to length with ellipsis.
 * Respects word boundaries and multibyte characters.
 * 
 * @param string $text Text to truncate
 * @param int $length Maximum length (default 100)
 * @param string $ellipsis Ellipsis string (default '...')
 * @return string Truncated text
 */
function truncate(string $text, int $length = 100, string $ellipsis = '...'): string
{
    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }
    
    // Reserve space for ellipsis
    $maxLength = $length - mb_strlen($ellipsis, 'UTF-8');
    
    if ($maxLength <= 0) {
        return $ellipsis;
    }
    
    // Truncate at word boundary if possible
    $truncated = mb_substr($text, 0, $maxLength, 'UTF-8');
    $lastSpace = mb_strrpos($truncated, ' ', 0, 'UTF-8');
    
    // If we found a space in the last 20% of the string, break there
    $breakPoint = (int) ($maxLength * 0.8);
    if ($lastSpace !== false && $lastSpace > $breakPoint) {
        $truncated = mb_substr($truncated, 0, $lastSpace, 'UTF-8');
    }
    
    return rtrim($truncated) . $ellipsis;
}

/**
 * Strip HTML tags but preserve line breaks.
 * 
 * @param string $html HTML content
 * @return string Plain text with line breaks
 */
function strip_html(string $html): string
{
    // Replace block-level elements with newlines
    $blockElements = [
        '/<br\s*\/?>/i' => "\n",
        '/<\/p>/i' => "\n\n",
        '/<\/div>/i' => "\n",
        '/<\/li>/i' => "\n",
        '/<\/tr>/i' => "\n",
        '/<\/h[1-6]>/i' => "\n\n",
    ];
    
    $text = preg_replace(array_keys($blockElements), array_values($blockElements), $html);
    
    // Strip remaining tags
    $text = strip_tags($text);
    
    // Decode HTML entities
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Normalize whitespace
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    
    return trim($text);
}

/**
 * Convert text to HTML with linkified URLs.
 * 
 * @param string $text Plain text
 * @return string HTML with linked URLs
 */
function text_to_html(string $text): string
{
    // Escape HTML first
    $html = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Linkify URLs
    $urlPattern = '/(https?:\/\/[^\s<]+)/i';
    $html = preg_replace($urlPattern, '<a href="$1" rel="noopener noreferrer" target="_blank">$1</a>', $html);
    
    // Linkify mentions (@username)
    $mentionPattern = '/@([a-zA-Z0-9_]+)/';
    $html = preg_replace($mentionPattern, '<a href="/@/$1">@$1</a>', $html);
    
    // Linkify hashtags (#tag)
    $hashtagPattern = '/#([a-zA-Z0-9_]+)/';
    $html = preg_replace($hashtagPattern, '<a href="/tag/$1">#$1</a>', $html);
    
    // Convert newlines to <br>
    $html = nl2br($html);
    
    return $html;
}

/**
 * Extract mentions (@username) from text.
 * 
 * @param string $text Text to search
 * @return array<int, string> Array of mentioned usernames (without @)
 */
function extract_mentions(string $text): array
{
    $pattern = '/@([a-zA-Z0-9_]+)/';
    
    if (!preg_match_all($pattern, $text, $matches)) {
        return [];
    }
    
    // Return unique usernames
    return array_values(array_unique($matches[1]));
}

/**
 * Extract hashtags from text.
 * 
 * @param string $text Text to search
 * @return array<int, string> Array of hashtags (without #)
 */
function extract_hashtags(string $text): array
{
    $pattern = '/#([a-zA-Z0-9_]+)/';
    
    if (!preg_match_all($pattern, $text, $matches)) {
        return [];
    }
    
    // Return unique hashtags, lowercase
    $hashtags = array_map('strtolower', $matches[1]);
    return array_values(array_unique($hashtags));
}

/**
 * Generate a slug from text.
 * 
 * @param string $text Text to slugify
 * @return string URL-safe slug
 */
function slugify(string $text): string
{
    // Convert to lowercase
    $slug = mb_strtolower($text, 'UTF-8');
    
    // Transliterate non-ASCII characters
    $slug = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $slug);
    
    if ($slug === false) {
        // Fallback if transliterator not available
        $slug = $text;
    }
    
    // Replace non-alphanumeric characters with hyphens
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    
    if ($slug === null) {
        return '';
    }
    
    // Remove leading/trailing hyphens
    $slug = trim($slug, '-');
    
    // Limit length
    if (mb_strlen($slug, 'UTF-8') > 100) {
        $slug = mb_substr($slug, 0, 100, 'UTF-8');
        // Don't end mid-word
        $lastHyphen = mb_strrpos($slug, '-', 0, 'UTF-8');
        if ($lastHyphen !== false && $lastHyphen > 50) {
            $slug = mb_substr($slug, 0, $lastHyphen, 'UTF-8');
        }
    }
    
    return $slug;
}

/**
 * Normalize username (lowercase, alphanumeric only).
 * 
 * @param string $username Username to normalize
 * @return string Normalized username
 */
function normalize_username(string $username): string
{
    // Remove leading @ if present
    $username = ltrim($username, '@');
    
    // Lowercase
    $username = mb_strtolower($username, 'UTF-8');
    
    // Keep only alphanumeric and underscores
    $username = preg_replace('/[^a-z0-9_]/', '', $username);
    
    return $username ?? '';
}

/**
 * Count characters properly (multibyte).
 * 
 * @param string $text Text to count
 * @return int Character count
 */
function char_count(string $text): int
{
    return mb_strlen($text, 'UTF-8');
}
