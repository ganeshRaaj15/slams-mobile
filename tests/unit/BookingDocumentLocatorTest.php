<?php

namespace Tests\Unit;

use App\Libraries\BookingDocumentLocator;
use CodeIgniter\Test\CIUnitTestCase;

class BookingDocumentLocatorTest extends CIUnitTestCase
{
    private string $writablePdfDir;
    private string $publicPdfDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writablePdfDir = WRITEPATH . 'uploads/pdfs';
        $this->publicPdfDir = FCPATH . 'uploads/pdfs';

        if (! is_dir($this->writablePdfDir)) {
            mkdir($this->writablePdfDir, 0755, true);
        }

        if (! is_dir($this->publicPdfDir)) {
            mkdir($this->publicPdfDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        $this->deleteIfExists($this->writablePdfDir . '/locator-current.pdf');
        $this->deleteIfExists($this->publicPdfDir . '/locator-legacy.pdf');

        parent::tearDown();
    }

    public function testResolvesWritablePdfFirst(): void
    {
        $filePath = $this->writablePdfDir . '/locator-current.pdf';
        file_put_contents($filePath, 'current-pdf');

        $locator = new BookingDocumentLocator();

        $resolved = $locator->resolvePdfPath('locator-current.pdf');

        $this->assertSame(realpath($filePath), $resolved);
    }

    public function testFallsBackToLegacyPublicPdf(): void
    {
        $filePath = $this->publicPdfDir . '/locator-legacy.pdf';
        file_put_contents($filePath, 'legacy-pdf');

        $locator = new BookingDocumentLocator();

        $resolved = $locator->resolvePdfPath('locator-legacy.pdf');

        $this->assertSame(realpath($filePath), $resolved);
    }

    public function testRejectsInvalidFilename(): void
    {
        $locator = new BookingDocumentLocator();

        $this->assertNull($locator->resolvePdfPath('../secret.pdf'));
    }

    private function deleteIfExists(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
