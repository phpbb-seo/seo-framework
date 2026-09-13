<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Url;

use PHPUnit\Framework\TestCase;
use phpbbseo\framework\Url\DefaultSlugGenerator;
use phpbbseo\framework\Url\SlugOptions;

class DefaultSlugGeneratorTest extends TestCase
{
    public function testGeneratesUtf8Slugs(): void
    {
        $generator = new DefaultSlugGenerator(new SlugOptions());
        
        // Persian/Arabic
        $this->assertSame('سلام-دنیا', $generator->generate('سلام دنیا!'));
        // Russian
        $this->assertSame('привет-мир', $generator->generate('Привет Мир'));
        // Accented
        $this->assertSame('café-au-lait', $generator->generate('Café au Lait'));
    }

    public function testTruncationWithoutSplittingCodePoints(): void
    {
        $options = new SlugOptions(maxLength: 8);
        $generator = new DefaultSlugGenerator($options);
        
        // "سلام دنیا" is 9 chars. "سلام-دنی" is 8 chars
        $this->assertSame('سلام-دنی', $generator->generate('سلام دنیا'));
    }

    public function testEmptyInputFallback(): void
    {
        $generator = new DefaultSlugGenerator(new SlugOptions(fallback: 'default'));
        
        $this->assertSame('default', $generator->generate('!!!???'));
        $this->assertSame('default', $generator->generate('   '));
    }

    public function testTransliterationDisabledByDefault(): void
    {
        $generator = new DefaultSlugGenerator(new SlugOptions(transliterate: false));

        $this->assertSame('všeobecně', $generator->generate('Všeobecně'));
        $this->assertSame('mädchen-übergrößen', $generator->generate('Mädchen Übergrößen'));
        $this->assertSame('zażółć-gęślą-jaźń', $generator->generate('Zażółć gęślą jaźń'));
        $this->assertSame('آموزش-نصب-phpbb', $generator->generate('آموزش نصب phpBB'));
        $this->assertSame('привет-мир', $generator->generate('Привет Мир'));
        $this->assertSame('你好世界', $generator->generate('你好世界'));
    }

    public function testTransliterationEnabledTransformsLatinAccents(): void
    {
        $generator = new DefaultSlugGenerator(new SlugOptions(transliterate: true));

        // Czech diacritics
        $this->assertSame('vseobecne', $generator->generate('Všeobecně'));
        // German umlauts
        $this->assertSame('madchen-ubergrossen', $generator->generate('Mädchen Übergrößen'));
        // Turkish
        $this->assertSame('istanbul-turkce-ag', $generator->generate('İstanbul Türkçe Ağ'));
        // Polish diacritics
        $this->assertSame('zazolc-gesla-jazn', $generator->generate('Zażółć gęślą jaźń'));
        // French
        $this->assertSame('cafe-au-lait', $generator->generate('Café au Lait'));
        // Spanish
        $this->assertSame('el-nino-en-el-ano-2026', $generator->generate('El Niño en el año 2026'));

        // Non-Latin scripts remain safe and non-empty
        $this->assertSame('آموزش-نصب-phpbb', $generator->generate('آموزش نصب phpBB'));
        $this->assertSame('привет-мир', $generator->generate('Привет Мир'));
        $this->assertSame('你好世界', $generator->generate('你好世界'));


    }

    public function testTransliterationViaConfigurationProvider(): void
    {
        $configEnabled = new \phpbbseo\framework\Configuration\ConfigurationProvider(
            new \phpbb\config\config(['seo_transliterate_slugs' => '1'])
        );
        $generatorOn = new DefaultSlugGenerator(null, $configEnabled);
        $this->assertSame('vseobecne', $generatorOn->generate('Všeobecně'));

        $configDisabled = new \phpbbseo\framework\Configuration\ConfigurationProvider(
            new \phpbb\config\config(['seo_transliterate_slugs' => '0'])
        );
        $generatorOff = new DefaultSlugGenerator(null, $configDisabled);
        $this->assertSame('všeobecně', $generatorOff->generate('Všeobecně'));
    }

    public function testMixedScriptPersianLatinNumerals(): void
    {
        $generatorOff = new DefaultSlugGenerator(new SlugOptions(transliterate: false));
        $generatorOn  = new DefaultSlugGenerator(new SlugOptions(transliterate: true));

        // 1. Persian + Latin product name + numeral
        $title1 = 'آموزش نصب WordPress در 2026';
        $this->assertSame('آموزش-نصب-wordpress-در-2026', $generatorOff->generate($title1));
        $this->assertSame('آموزش-نصب-wordpress-در-2026', $generatorOn->generate($title1));

        // 2. Persian + English brand/model + numeral
        $title2 = 'بررسی فنی گوشی iPhone 16 Pro Max با گارانتی';
        $this->assertSame('بررسی-فنی-گوشی-iphone-16-pro-max-با-گارانتی', $generatorOff->generate($title2));
        $this->assertSame('بررسی-فنی-گوشی-iphone-16-pro-max-با-گارانتی', $generatorOn->generate($title2));

        // 3. Persian + error code + Latin server acronyms
        $title3 = 'حل مشکل خطای 500 Internal Server Error در Nginx و PHP-FPM';
        $this->assertSame('حل-مشکل-خطای-500-internal-server-error-در-nginx-و-php-fpm', $generatorOff->generate($title3));
        $this->assertSame('حل-مشکل-خطای-500-internal-server-error-در-nginx-و-php-fpm', $generatorOn->generate($title3));

        // 4. Persian with ZWNJ (نیم‌فاصله) + Latin brand
        $title4 = 'راهنمای جامع سئو و بهینه‌سازی سایت برای Google Search Console';
        $this->assertSame('راهنمای-جامع-سئو-و-بهینه-سازی-سایت-برای-google-search-console', $generatorOff->generate($title4));
        $this->assertSame('راهنمای-جامع-سئو-و-بهینه-سازی-سایت-برای-google-search-console', $generatorOn->generate($title4));

        // 5. Persian with Latin accented word alongside Persian words
        $title5 = 'منوی کافه شامل Café au Lait ویژه سال 2026';
        $this->assertSame('منوی-کافه-شامل-café-au-lait-ویژه-سال-2026', $generatorOff->generate($title5));
        $this->assertSame('منوی-کافه-شامل-cafe-au-lait-ویژه-سال-2026', $generatorOn->generate($title5));
    }
}



