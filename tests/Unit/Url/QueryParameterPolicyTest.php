<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Url;

use PHPUnit\Framework\TestCase;
use phpbbseo\framework\Url\QueryParameterPolicy;
use phpbbseo\framework\Url\QueryParamClassification;

class QueryParameterPolicyTest extends TestCase
{
    private QueryParameterPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new QueryParameterPolicy();
    }

    public function testTopicIdIsRequired(): void
    {
        $this->assertSame(
            QueryParamClassification::REQUIRED,
            $this->policy->classify('viewtopic.php', 't')
        );
    }

    public function testStartIsPagination(): void
    {
        $this->assertSame(
            QueryParamClassification::PAGINATION,
            $this->policy->classify('viewtopic.php', 'start')
        );
    }

    public function testSessionIdIsIgnored(): void
    {
        $this->assertSame(
            QueryParamClassification::IGNORED,
            $this->policy->classify('viewtopic.php', 'sid')
        );
    }

    public function testUtmSourceIsTracking(): void
    {
        $this->assertSame(
            QueryParamClassification::TRACKING,
            $this->policy->classify('viewtopic.php', 'utm_source')
        );
    }

    public function testArbitraryParamIsUnknown(): void
    {
        $this->assertSame(
            QueryParamClassification::UNKNOWN,
            $this->policy->classify('viewtopic.php', 'arbitrary_param')
        );
    }

    public function testExtractRequired(): void
    {
        $required = $this->policy->extractRequired('viewtopic.php', [
            't' => '582',
            'start' => '20',
            'sid' => 'abc',
            'utm_source' => 'google',
            'arbitrary' => 'stuff',
        ]);
        $this->assertSame(['t' => '582'], $required);
    }

    public function testUnknownParamsNeverPropagated(): void
    {
        $required = $this->policy->extractRequired('viewtopic.php', [
            'custom_injected' => 'evil',
        ]);
        $this->assertEmpty($required, 'Unknown params must never propagate into canonical URLs');
    }

    public function testExtractPaginationOffset(): void
    {
        $offset = $this->policy->extractPaginationOffset('viewtopic.php', ['start' => '40', 't' => '582']);
        $this->assertSame(40, $offset);
    }
}
