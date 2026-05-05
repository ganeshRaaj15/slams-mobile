<?php

namespace Tests\Unit;

use App\Libraries\WebPushConfiguration;
use CodeIgniter\Test\CIUnitTestCase;

class WebPushConfigurationTest extends CIUnitTestCase
{
    public function testReportsConfiguredWhenAllKeysExist(): void
    {
        $config = new WebPushConfiguration([
            'subject' => 'mailto:ops@example.com',
            'publicKey' => 'public-key',
            'privateKey' => 'private-key',
            'ttl' => 600,
        ]);

        $this->assertTrue($config->isConfigured());
        $this->assertSame(600, $config->defaultTtl());
        $this->assertSame('public-key', $config->clientConfig()['publicKey']);
    }

    public function testReportsNotConfiguredWhenKeysAreMissing(): void
    {
        $config = new WebPushConfiguration([
            'subject' => 'mailto:ops@example.com',
            'publicKey' => '',
            'privateKey' => '',
        ]);

        $this->assertFalse($config->isConfigured());
        $this->assertSame([], $config->authOptions());
    }
}
