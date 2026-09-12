<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use phpbbseo\framework\Compatibility\RecentTopicsCompatibility;
use phpbbseo\framework\Configuration\ConfigurationProvider;

class RecentTopicsCompatibilityTest extends TestCase
{
    private ConfigurationProvider $configProvider;
    private RecentTopicsCompatibility $subscriber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configProvider = $this->createMock(ConfigurationProvider::class);
        $this->configProvider->method('isRewriteEnabled')->willReturn(true);
        $this->subscriber = new RecentTopicsCompatibility($this->configProvider);
    }

    public function testSubscribedEvents(): void
    {
        $events = RecentTopicsCompatibility::getSubscribedEvents();
        $this->assertArrayHasKey('avathar.recenttopics.modify_tpl_ary', $events);
        $this->assertArrayHasKey('paybas.recenttopics.modify_tpl_ary', $events);
        $this->assertSame('onRecentTopicsModifyTplAry', $events['avathar.recenttopics.modify_tpl_ary']);
        $this->assertSame('onRecentTopicsModifyTplAry', $events['paybas.recenttopics.modify_tpl_ary']);
    }

    public function testRecentTopicsUrlHealing(): void
    {
        $event = new \ArrayObject([
            'tpl_ary' => [
                'U_NEWEST_POST' => '/topic/slug-100/&view=unread#unread',
                'U_LAST_POST'   => '/topic/slug-100/&amp;p=500#p500',
                'U_VIEW_TOPIC'  => '/topic/slug-100/',
            ],
        ]);

        $this->subscriber->onRecentTopicsModifyTplAry($event);

        $tpl = $event['tpl_ary'];
        $this->assertSame('/topic/slug-100/?view=unread#unread', $tpl['U_NEWEST_POST']);
        $this->assertSame('/topic/slug-100/?p=500#p500', $tpl['U_LAST_POST']);
        $this->assertSame('/topic/slug-100/', $tpl['U_VIEW_TOPIC']);
    }

    public function testDoesNotModifyWhenRewritingDisabled(): void
    {
        $disabledConfig = $this->createMock(ConfigurationProvider::class);
        $disabledConfig->method('isRewriteEnabled')->willReturn(false);
        $subscriber = new RecentTopicsCompatibility($disabledConfig);

        $event = new \ArrayObject([
            'tpl_ary' => [
                'U_NEWEST_POST' => '/topic/slug-100/&view=unread#unread',
            ],
        ]);

        $subscriber->onRecentTopicsModifyTplAry($event);

        $this->assertSame('/topic/slug-100/&view=unread#unread', $event['tpl_ary']['U_NEWEST_POST']);
    }

    public function testNoOpWhenTplAryMissingOrNonArray(): void
    {
        $event = new \ArrayObject(['tpl_ary' => 'invalid_string']);
        $this->subscriber->onRecentTopicsModifyTplAry($event);
        $this->assertSame('invalid_string', $event['tpl_ary']);

        $eventEmpty = new \ArrayObject([]);
        $this->subscriber->onRecentTopicsModifyTplAry($eventEmpty);
        $this->assertFalse(isset($eventEmpty['tpl_ary']));
    }

    public function testCleanUrlsRemainUnchanged(): void
    {
        $event = new \ArrayObject([
            'tpl_ary' => [
                'U_NEWEST_POST' => '/topic/slug-100/?view=unread#unread',
                'U_LAST_POST'   => '/topic/slug-100/?p=500#p500',
                'U_VIEW_TOPIC'  => '/topic/slug-100/',
            ],
        ]);

        $this->subscriber->onRecentTopicsModifyTplAry($event);

        $tpl = $event['tpl_ary'];
        $this->assertSame('/topic/slug-100/?view=unread#unread', $tpl['U_NEWEST_POST']);
        $this->assertSame('/topic/slug-100/?p=500#p500', $tpl['U_LAST_POST']);
        $this->assertSame('/topic/slug-100/', $tpl['U_VIEW_TOPIC']);
    }
}