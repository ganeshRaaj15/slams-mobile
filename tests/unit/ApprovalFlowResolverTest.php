<?php

use App\Libraries\ApprovalFlowResolver;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ApprovalFlowResolverTest extends CIUnitTestCase
{
    public function testDetermineFlowUsesFlaggedFacultyForDirectApproval(): void
    {
        $flow = ApprovalFlowResolver::determineFlow([
            'id' => 12,
            'is_fkmp' => 1,
        ]);

        $this->assertSame(ApprovalFlowResolver::DIRECT_APPROVAL, $flow);
    }

    public function testDetermineFlowUsesConfiguredFacultyIdForDirectApproval(): void
    {
        $flow = ApprovalFlowResolver::determineFlow([
            'id' => 12,
            'is_fkmp' => 0,
        ], 12);

        $this->assertSame(ApprovalFlowResolver::DIRECT_APPROVAL, $flow);
    }

    public function testDetermineFlowFallsBackToTwoStageApproval(): void
    {
        $flow = ApprovalFlowResolver::determineFlow([
            'id' => 12,
            'is_fkmp' => 0,
        ], 3);

        $this->assertSame(ApprovalFlowResolver::TWO_STAGE_APPROVAL, $flow);
    }

    public function testDetermineFlowRejectsFacultyWithoutAnId(): void
    {
        $flow = ApprovalFlowResolver::determineFlow([
            'is_fkmp' => 1,
        ]);

        $this->assertNull($flow);
    }
}
