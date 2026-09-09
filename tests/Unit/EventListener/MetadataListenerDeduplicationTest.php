<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\EventListener;

use PHPUnit\Framework\TestCase;

class MetadataListenerDeduplicationTest extends TestCase
{
    private function processBuffer(
        string $buffer,
        string $title,
        ?string $desc = null,
        ?string $canonical = null,
        ?string $jsonLd = null,
        ?string $social = null
    ): string {
        $finalEscapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $finalEscapedDesc = ($desc !== null && $desc !== '') ? htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') : null;
        $finalEscapedCanonical = ($canonical !== null && $canonical !== '') ? htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') : null;

        $jsonLdBlock = null;
        if (preg_match('#\s*(<script\s+type=["\']application/ld\+json["\']>.*?</script>)\s*#si', $buffer, $jsonMatches)) {
            $jsonLdBlock = trim($jsonMatches[1]);
            $buffer = str_replace($jsonMatches[0], "\n", $buffer);
        }

        $socialBlock = null;
        if (preg_match('#\s*(<!-- Social SEO by phpBB SEO Pro -->.*?<!-- /Social SEO by phpBB SEO Pro -->)\s*#si', $buffer, $socialMatches)) {
            $socialBlock = trim($socialMatches[1]);
            $buffer = str_replace($socialMatches[0], "\n", $buffer);
        }

        // Strip any existing/template-rendered canonical tags to prevent duplicate <link rel="canonical"> tags
        if ($finalEscapedCanonical !== null && $finalEscapedCanonical !== '') {
            $buffer = preg_replace('#\s*<link\b(?=[^>]*\brel=["\']canonical["\'])[^>]*>\s*#si', "\n", $buffer);
        }

        // Strip any existing/template-rendered description tags to prevent duplicate <meta name="description"> tags
        if ($finalEscapedDesc !== null && $finalEscapedDesc !== '') {
            $buffer = preg_replace('#\s*<meta\b(?=[^>]*\bname=["\']description["\'])[^>]*>\s*#si', "\n", $buffer);
        }

        return preg_replace_callback('#<title>(.*?)</title>#si', function ($matches) use ($finalEscapedTitle, $finalEscapedDesc, $finalEscapedCanonical, $jsonLdBlock, $socialBlock) {
            $prefix = '';
            if (preg_match('#^(\(\d+\)\s*)#u', trim($matches[1]), $pMatch)) {
                $prefix = $pMatch[1];
            }

            $lines = [];
            $lines[] = '<!-- Search Engine Optimization by phpBB SEO Framework - https://www.phpbbseo.com/ -->';
            $lines[] = '';
            $lines[] = '<title>' . $prefix . $finalEscapedTitle . '</title>';
            if ($finalEscapedDesc !== null && $finalEscapedDesc !== '') {
                $lines[] = '<meta name="description" content="' . $finalEscapedDesc . '" />';
            }
            if ($finalEscapedCanonical !== null && $finalEscapedCanonical !== '') {
                $lines[] = '<link rel="canonical" href="' . $finalEscapedCanonical . '" />';
            }
            if ($socialBlock !== null && $socialBlock !== '') {
                $lines[] = '';
                $lines[] = $socialBlock;
            }
            if ($jsonLdBlock !== null && $jsonLdBlock !== '') {
                $lines[] = '';
                $lines[] = $jsonLdBlock;
            }
            $lines[] = '';
            $lines[] = '<!-- /phpBB SEO Framework -->';

            return implode("\n", $lines);
        }, $buffer, 1);
    }

    public function testDuplicateTemplateCanonicalTagIsDeduplicated(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Original Title</title>
    <link rel="alternate" type="application/atom+xml" href="/feed" />
    <link rel="canonical" href="https://example.com/topic/legacy-canonical-1/">
</head>
<body><p>Content</p></body>
</html>
HTML;

        $processed = $this->processBuffer(
            $html,
            'New SEO Title',
            'SEO Description',
            'https://example.com/topic/clean-slug-1/'
        );

        preg_match_all('#<link\b(?=[^>]*\brel=["\']canonical["\'])[^>]*>#si', $processed, $matches);
        $this->assertCount(1, $matches[0]);
        $this->assertContains('https://example.com/topic/clean-slug-1/', $matches[0][0]);
        $this->assertFalse(str_contains($processed, 'legacy-canonical-1'));
    }

    public function testMultiplePreexistingCanonicalFormatsDeduplicated(): void
    {
        $html = <<<HTML
<head>
    <title>Sample Title</title>
    <link href='https://example.com/topic/old-1' rel='canonical' />
    <link rel="canonical" href="https://example.com/topic/old-2">
</head>
HTML;

        $processed = $this->processBuffer(
            $html,
            'Clean Title',
            null,
            'https://example.com/topic/authoritative/'
        );

        preg_match_all('#<link\b(?=[^>]*\brel=["\']canonical["\'])[^>]*>#si', $processed, $matches);
        $this->assertCount(1, $matches[0]);
        $this->assertSame('<link rel="canonical" href="https://example.com/topic/authoritative/" />', $matches[0][0]);
    }

    public function testDuplicateMetaDescriptionIsDeduplicated(): void
    {
        $html = <<<HTML
<head>
    <title>Title</title>
    <meta name="description" content="Old template description" />
</head>
HTML;

        $processed = $this->processBuffer(
            $html,
            'Title',
            'New clean description',
            'https://example.com/canonical'
        );

        preg_match_all('#<meta\b(?=[^>]*\bname=["\']description["\'])[^>]*>#si', $processed, $matches);
        $this->assertCount(1, $matches[0]);
        $this->assertContains('New clean description', $matches[0][0]);
        $this->assertFalse(str_contains($processed, 'Old template description'));
    }

    public function testNotificationCountPrefixPreserved(): void
    {
        $html = <<<HTML
<head>
    <title>(3) Board Index</title>
</head>
HTML;

        $processed = $this->processBuffer(
            $html,
            'Community - MySite',
            'Desc',
            'https://example.com/'
        );

        $this->assertContains('<title>(3) Community - MySite</title>', $processed);
    }
}