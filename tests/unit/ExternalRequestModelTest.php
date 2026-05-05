<?php

use App\Models\ExternalRequestModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ExternalRequestModelTest extends CIUnitTestCase
{
    public function testCanUserEditSubmittedRequest(): void
    {
        $model = new ExternalRequestModel();

        $this->assertTrue($model->canUserEdit(['status' => 'submitted']));
    }

    public function testCanUserEditNeedsInformationRequest(): void
    {
        $model = new ExternalRequestModel();

        $this->assertTrue($model->canUserEdit(['status' => 'needs_information']));
    }

    public function testCannotUserEditApprovedRequest(): void
    {
        $model = new ExternalRequestModel();

        $this->assertFalse($model->canUserEdit(['status' => 'approved_for_scheduling']));
    }

    public function testStatusLabelIsHumanReadable(): void
    {
        $model = new ExternalRequestModel();

        $this->assertSame('Approved For Scheduling', $model->statusLabel('approved_for_scheduling'));
    }
}
