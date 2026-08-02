<?php

use PHPUnit\Framework\TestCase;

final class CookieDKGDPRComplianceTest extends TestCase
{
    public function test_sanitize_consent_data_forces_necessary_true(): void
    {
        $gdpr = new CookieDK_GDPR_Compliance();
        $method = new ReflectionMethod(CookieDK_GDPR_Compliance::class, 'sanitize_consent_data');
        $method->setAccessible(true);

        $data = $method->invoke($gdpr, array( 'necessary' => false, 'analytics' => true ));

        $this->assertTrue($data['necessary']);
        $this->assertTrue($data['analytics']);
        $this->assertFalse($data['marketing']);
    }

    public function test_export_format_contains_group_data(): void
    {
        $exporter = new CookieDK_Consent_Export();
        $log = array(
            (object) array(
                'id' => 1,
                'consent_data' => json_encode(array( 'necessary' => true, 'analytics' => true )),
                'consent_timestamp' => '2026-01-01 12:00:00',
                'ip_anonymized' => 1,
                'ip_address' => null,
                'user_agent' => 'UA',
            ),
        );

        $data = $exporter->to_wordpress_export_format($log);

        $this->assertSame('cookiedk_consent', $data[0]['group_id']);
        $this->assertSame('consent-1', $data[0]['item_id']);
    }
}
