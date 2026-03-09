<?php

declare(strict_types=1);

namespace NanoPub\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;

/**
 * Test cases for text helper functions.
 */
final class TextHelperTest extends TestCase
{
    /**
     * Test truncate function with short text.
     */
    public function testTruncateShortText(): void
    {
        $text = 'Hello world';
        $result = truncate($text, 100);
        
        self::assertSame('Hello world', $result);
    }

    /**
     * Test truncate function with long text.
     */
    public function testTruncateLongText(): void
    {
        $text = 'This is a very long text that should be truncated when it exceeds the maximum length specified';
        $result = truncate($text, 20);
        
        self::assertStringEndsWith('...', $result);
        self::assertLessThanOrEqual(23, mb_strlen($result, 'UTF-8')); // 20 + 3 for ellipsis
    }

    /**
     * Test truncate respects word boundaries.
     */
    public function testTruncateRespectsWordBoundaries(): void
    {
        $text = 'Hello beautiful world today';
        $result = truncate($text, 15);
        
        // Should break at word boundary, not mid-word
        self::assertStringEndsWith('...', $result);
        self::assertLessThanOrEqual(18, mb_strlen($result, 'UTF-8')); // 15 + 3
    }

    /**
     * Test truncate with custom ellipsis.
     */
    public function testTruncateCustomEllipsis(): void
    {
        $text = 'This is a very long text';
        $result = truncate($text, 10, '…');
        
        self::assertStringEndsWith('…', $result);
    }

    /**
     * Test strip_html removes HTML tags.
     */
    public function testStripHtml(): void
    {
        $html = '<p>Hello <strong>world</strong></p>';
        $result = strip_html($html);
        
        self::assertSame('Hello world', $result);
    }

    /**
     * Test strip_html preserves line breaks.
     */
    public function testStripHtmlPreservesLineBreaks(): void
    {
        $html = '<p>Line one</p><p>Line two</p>';
        $result = strip_html($html);
        
        self::assertStringContainsString("\n", $result);
    }

    /**
     * Test text_to_html escapes HTML.
     */
    public function testTextToHtmlEscapesHtml(): void
    {
        $text = '<script>alert("xss")</script>';
        $result = text_to_html($text);
        
        // HTML should be escaped
        self::assertStringContainsString('&lt;script&gt;', $result);
        self::assertStringNotContainsString('<script>', $result);
    }

    /**
     * Test text_to_html linkifies URLs.
     */
    public function testTextToHtmlLinkifiesUrls(): void
    {
        $text = 'Check out https://example.com for more info';
        $result = text_to_html($text);
        
        self::assertStringContainsString('<a href="https://example.com"', $result);
    }

    /**
     * Test text_to_html linkifies mentions.
     */
    public function testTextToHtmlLinkifiesMentions(): void
    {
        $text = 'Hello @username how are you?';
        $result = text_to_html($text);
        
        self::assertStringContainsString('@username', $result);
        self::assertStringContainsString('/@/', $result);
    }

    /**
     * Test text_to_html linkifies hashtags.
     */
    public function testTextToHtmlLinkifiesHashtags(): void
    {
        $text = 'Testing #hashtag functionality';
        $result = text_to_html($text);
        
        self::assertStringContainsString('#hashtag', $result);
        self::assertStringContainsString('/tag/', $result);
    }

    /**
     * Test extract_mentions finds mentions.
     */
    public function testExtractMentions(): void
    {
        $text = 'Hello @user1 and @user2 and @user1 again';
        $result = extract_mentions($text);
        
        self::assertCount(2, $result);
        self::assertContains('user1', $result);
        self::assertContains('user2', $result);
    }

    /**
     * Test extract_mentions handles no mentions.
     */
    public function testExtractMentionsNoMentions(): void
    {
        $text = 'Hello world no mentions here';
        $result = extract_mentions($text);
        
        self::assertEmpty($result);
    }

    /**
     * Test extract_hashtags finds hashtags.
     */
    public function testExtractHashtags(): void
    {
        $text = 'Testing #Tag1 and #tag2 and #TAG1';
        $result = extract_hashtags($text);
        
        self::assertCount(2, $result);
        self::assertContains('tag1', $result);
        self::assertContains('tag2', $result);
    }

    /**
     * Test slugify creates URL-safe slugs.
     */
    public function testSlugify(): void
    {
        $text = 'Hello World Test Case';
        $result = slugify($text);
        
        self::assertSame('hello-world-test-case', $result);
    }

    /**
     * Test slugify handles special characters.
     */
    public function testSlugifySpecialCharacters(): void
    {
        $text = 'Test @#$% Case';
        $result = slugify($text);
        
        self::assertStringNotContainsString('@', $result);
        self::assertStringNotContainsString('#', $result);
        self::assertStringNotContainsString('$', $result);
    }

    /**
     * Test normalize_username removes @ symbol.
     */
    public function testNormalizeUsername(): void
    {
        $username = '@TestUser';
        $result = normalize_username($username);
        
        self::assertSame('testuser', $result);
    }

    /**
     * Test normalize_username lowercases.
     */
    public function testNormalizeUsernameLowercases(): void
    {
        $username = 'UPPERCASE';
        $result = normalize_username($username);
        
        self::assertSame('uppercase', $result);
    }

    /**
     * Test normalize_username removes special chars.
     */
    public function testNormalizeUsernameRemovesSpecialChars(): void
    {
        $username = 'user@name!123';
        $result = normalize_username($username);
        
        // @ is removed, but 'name' is kept and lowercase is applied
        self::assertSame('username123', $result);
    }

    /**
     * Test char_count with ASCII.
     */
    public function testCharCountAscii(): void
    {
        $text = 'Hello world';
        $result = char_count($text);
        
        self::assertSame(11, $result);
    }

    /**
     * Test char_count with multibyte.
     */
    public function testCharCountMultibyte(): void
    {
        $text = 'Hello 世界';
        $result = char_count($text);
        
        self::assertSame(8, $result); // 5 ASCII + 2 Chinese + 1 space
    }

    /**
     * Test truncate with multibyte characters.
     */
    public function testTruncateMultibyte(): void
    {
        $text = 'Hello 世界 this is a long text with multibyte characters';
        $result = truncate($text, 15);
        
        // Should handle multibyte correctly
        self::assertLessThanOrEqual(18, mb_strlen($result, 'UTF-8')); // 15 + 3
    }
}
