<?php
declare(strict_types=1);

namespace phpbbseo\framework\Rewrite;

use InvalidArgumentException;

/**
 * Validates and compiles raw permalink patterns into CompiledUrlPattern.
 */
class UrlPatternCompiler
{
    private const ALLOWED_TOKENS = ['id', 'slug', 'page'];

    public function compile(string $pattern, array $requiredTokens = ['id']): CompiledUrlPattern
    {
        $this->validateSafePath($pattern);

        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $pattern, $matches);
        $foundTokens = $matches[1];

        $this->validateTokens($foundTokens, $requiredTokens);

        $regex = $pattern;
        $template = $pattern;
        $positions = [];
        
        // Escape regex special chars EXCEPT our token brackets
        $regex = preg_quote($regex, '~');
        // Unescape \{ and \}
        $regex = str_replace(['\\{', '\\}'], ['{', '}'], $regex);
        
        $position = 1;
        foreach ($foundTokens as $token) {
            $positions[$token] = $position;
            
            // Replace for regex matching
            $matchRegex = match($token) {
                'id', 'page' => '([0-9]+)',
                'slug' => '([^/]+)',
                default => '([^/]+)'
            };
            $regex = preg_replace('/\{' . $token . '\}/', $matchRegex, $regex, 1);
            
            // Replace for generation template
            $format = match($token) {
                'id', 'page' => '%' . $position . '$d',
                'slug' => '%' . $position . '$s',
                default => '%' . $position . '$s'
            };
            $template = preg_replace('/\{' . $token . '\}/', $format, $template, 1);
            
            $position++;
        }

        $regex = '~^' . $regex . '/?$~u';

        return new CompiledUrlPattern($pattern, $regex, $template, $positions);
    }

    private function validateSafePath(string $pattern): void
    {
        if (str_contains($pattern, '?') || str_contains($pattern, '&')) {
            throw new InvalidArgumentException("Pattern contains query string injection.");
        }
        if (str_contains($pattern, '#')) {
            throw new InvalidArgumentException("Pattern contains fragment injection.");
        }
        if (str_contains($pattern, '..')) {
            throw new InvalidArgumentException("Pattern contains path traversal.");
        }
        if (str_contains($pattern, '//')) {
            throw new InvalidArgumentException("Pattern contains ambiguous double slashes.");
        }
    }

    private function validateTokens(array $foundTokens, array $requiredTokens): void
    {
        foreach ($foundTokens as $token) {
            if (!in_array($token, self::ALLOWED_TOKENS, true)) {
                throw new InvalidArgumentException("Unknown token: {{$token}}");
            }
        }

        if (count(array_unique($foundTokens)) !== count($foundTokens)) {
            throw new InvalidArgumentException("Pattern contains duplicate tokens.");
        }

        foreach ($requiredTokens as $req) {
            if (!in_array($req, $foundTokens, true)) {
                throw new InvalidArgumentException("Pattern is missing required token: {{$req}}");
            }
        }
    }
}
