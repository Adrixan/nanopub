<?php

declare(strict_types=1);

namespace NanoPub\Core;

use RuntimeException;

/**
 * Template rendering without output buffering.
 * 
 * Uses plain PHP templates with layouts and partials support.
 */
final class View
{
    /**
     * Views directory path.
     */
    private static string $viewsPath = '';

    /**
     * Default layout name.
     */
    private static string $defaultLayout = 'layouts/main';

    /**
     * Shared data available to all views.
     * 
     * @var array<string, mixed>
     */
    private static array $shared = [];

    /**
     * Section content storage.
     * 
     * @var array<string, string>
     */
    private static array $sections = [];

    /**
     * Set the views directory path.
     * 
     * @param string $path Views directory path
     */
    public static function setViewsPath(string $path): void
    {
        self::$viewsPath = rtrim($path, '/');
    }

    /**
     * Set the default layout.
     * 
     * @param string $layout Layout name (without .php extension)
     */
    public static function setDefaultLayout(string $layout): void
    {
        self::$defaultLayout = $layout;
    }

    /**
     * Share data with all views.
     * 
     * @param string $key Data key
     * @param mixed $value Data value
     */
    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /**
     * Share multiple data with all views.
     * 
     * @param array<string, mixed> $data Data to share
     */
    public static function shareMultiple(array $data): void
    {
        self::$shared = array_merge(self::$shared, $data);
    }

    /**
     * Render a view template.
     * 
     * @param string $template Template name (without .php extension)
     * @param array<string, mixed> $data Template data
     * @return string Rendered content
     * @throws RuntimeException If template not found
     */
    public static function render(string $template, array $data = []): string
    {
        $templatePath = self::resolveTemplatePath($template);

        if (!file_exists($templatePath)) {
            throw new RuntimeException("View template not found: {$template}");
        }

        // Merge shared data with template data
        $data = array_merge(self::$shared, $data);

        // Extract variables for template access
        $escape = [self::class, 'escape'];
        $include = [self::class, 'include'];
        $section = [self::class, 'section'];
        $yield = [self::class, 'yield'];

        // Make data variables available in template scope
        extract($data);

        // Include template and capture output
        // Note: We use output buffering here for template rendering
        // but avoid it in controllers/responses
        ob_start();
        
        try {
            require $templatePath;
            return ob_get_clean() ?: '';
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new RuntimeException(
                "Error rendering view {$template}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Render a view with a layout.
     * 
     * @param string $template Template name
     * @param array<string, mixed> $data Template data
     * @param string|null $layout Layout name (null for default)
     * @return string Rendered content
     */
    public static function renderWithLayout(
        string $template,
        array $data = [],
        ?string $layout = null
    ): string {
        $layout = $layout ?? self::$defaultLayout;
        
        // Store content for layout
        $content = self::render($template, $data);
        
        // Add content to data for layout
        $data['content'] = $content;
        
        return self::render($layout, $data);
    }

    /**
     * Include a partial template.
     * 
     * @param string $template Partial template name
     * @param array<string, mixed> $data Partial data
     * @return string Rendered partial
     */
    public static function include(string $template, array $data = []): string
    {
        return self::render($template, $data);
    }

    /**
     * Escape HTML entities.
     * 
     * @param string $value Value to escape
     * @return string Escaped value
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Escape for JavaScript string.
     * 
     * @param string $value Value to escape
     * @return string Escaped value
     */
    public static function escapeJs(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) 
            ?: '""';
    }

    /**
     * Escape for HTML attribute.
     * 
     * @param string $value Value to escape
     * @return string Escaped value
     */
    public static function escapeAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Start a section.
     * 
     * @param string $name Section name
     */
    public static function section(string $name): void
    {
        self::$sections[$name] = '';
        ob_start();
    }

    /**
     * End a section and store content.
     * 
     * @param string $name Section name
     */
    public static function endSection(string $name): void
    {
        self::$sections[$name] = ob_get_clean() ?: '';
    }

    /**
     * Yield a section's content.
     * 
     * @param string $name Section name
     * @param string $default Default content if section not defined
     * @return string Section content
     */
    public static function yield(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    /**
     * Check if a section exists.
     * 
     * @param string $name Section name
     * @return bool
     */
    public static function hasSection(string $name): bool
    {
        return isset(self::$sections[$name]);
    }

    /**
     * Resolve a template path.
     * 
     * @param string $template Template name
     * @return string Full template path
     */
    private static function resolveTemplatePath(string $template): string
    {
        if (self::$viewsPath === '') {
            self::$viewsPath = dirname(__DIR__) . '/Views';
        }

        // Normalize template name
        $template = str_replace('.', '/', $template);
        
        return self::$viewsPath . "/{$template}.php";
    }

    /**
     * Check if a view exists.
     * 
     * @param string $template Template name
     * @return bool
     */
    public static function exists(string $template): bool
    {
        $templatePath = self::resolveTemplatePath($template);
        return file_exists($templatePath);
    }

    /**
     * Generate a CSRF hidden input field.
     * 
     * @return string HTML input field
     */
    public static function csrfField(): string
    {
        $token = Session::csrfToken();
        return sprintf(
            '<input type="hidden" name="_token" value="%s">',
            self::escape($token)
        );
    }

    /**
     * Generate a method spoofing hidden input field.
     * 
     * @param string $method HTTP method (PUT, DELETE, PATCH)
     * @return string HTML input field
     */
    public static function methodField(string $method): string
    {
        return sprintf(
            '<input type="hidden" name="_method" value="%s">',
            self::escape(strtoupper($method))
        );
    }

    /**
     * Get old input value from flash data.
     * 
     * @param string $key Input key
     * @param mixed $default Default value
     * @return mixed
     */
    public static function old(string $key, mixed $default = ''): mixed
    {
        $old = Session::getFlash('old', []);
        return $old[$key] ?? $default;
    }

    /**
     * Get error message from flash data.
     * 
     * @param string $key Error key
     * @return string|null
     */
    public static function error(string $key): ?string
    {
        $errors = Session::getFlash('errors', []);
        return $errors[$key] ?? null;
    }

    /**
     * Get all errors from flash data.
     * 
     * @return array<string, string>
     */
    public static function errors(): array
    {
        return Session::getFlash('errors', []);
    }

    /**
     * Clear all sections.
     */
    public static function clearSections(): void
    {
        self::$sections = [];
    }
}