<?php
declare(strict_types=1);

namespace phpbbseo\framework\Rewrite;

/**
 * Represents a safely compiled URL pattern for both generation and matching.
 */
class CompiledUrlPattern
{
    /**
     * @param string $rawPattern The original string pattern
     * @param string $regex The compiled regular expression for inbound matching
     * @param string $generationTemplate The sprintf-compatible template for outbound generation
     * @param array<string, int> $tokenPositions Maps token names to their indexed positions (1-based)
     */
    public function __construct(
        public readonly string $rawPattern,
        public readonly string $regex,
        public readonly string $generationTemplate,
        public readonly array $tokenPositions
    ) {}

    public function generate(array $context): string
    {
        $args = [];
        // The generationTemplate expects arguments in the exact order of their occurrence in the raw string.
        // But wait, it's easier to use named format or position format in sprintf if we know the positions.
        // If we compile `{slug}-{id}` to `%1$s-%2$d`, then args must be mapped 1=>slug, 2=>id.
        $orderedArgs = [];
        foreach ($this->tokenPositions as $token => $position) {
            $orderedArgs[$position] = $context[$token] ?? '';
        }
        
        ksort($orderedArgs);
        
        return sprintf($this->generationTemplate, ...array_values($orderedArgs));
    }

    public function match(string $path): ?array
    {
        if (preg_match($this->regex, $path, $matches)) {
            $result = [];
            foreach ($this->tokenPositions as $token => $position) {
                if (isset($matches[$position])) {
                    $result[$token] = $matches[$position];
                }
            }
            return $result;
        }

        return null;
    }
}
