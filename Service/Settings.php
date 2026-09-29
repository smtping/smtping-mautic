<?php

declare(strict_types=1);

namespace MauticPlugin\SmtpingBundle\Service;

use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use MauticPlugin\SmtpingBundle\Api\SmtpingClient;
use MauticPlugin\SmtpingBundle\Integration\SmtpingIntegration;

/**
 * Reads the plugin configuration saved in Settings > Plugins > SMTPing Email Verifier.
 */
class Settings
{
    public function __construct(private IntegrationsHelper $integrationsHelper)
    {
    }

    public function isEnabled(): bool
    {
        $config = $this->config();

        return null !== $config && $config->getIsPublished() && '' !== $this->apiKey();
    }

    public function apiKey(): string
    {
        $config = $this->config();
        $keys   = $config ? $config->getApiKeys() : [];

        return trim((string) ($keys['apiKey'] ?? ''));
    }

    /** @return array<string, mixed> */
    public function features(): array
    {
        $config   = $this->config();
        $settings = $config ? $config->getFeatureSettings() : [];

        return is_array($settings['integration'] ?? null) ? $settings['integration'] : [];
    }

    public function blockForms(): bool
    {
        return (bool) ($this->features()['block_forms'] ?? true);
    }

    /** @return string[] */
    public function blockedBands(): array
    {
        return 'avoid_judgement' === ($this->features()['block_bands'] ?? 'avoid') ? ['avoid', 'judgement'] : ['avoid'];
    }

    public function formMessage(): string
    {
        $m = trim((string) ($this->features()['form_message'] ?? ''));

        return '' !== $m ? $m : 'This email address cannot be used. Please enter another one.';
    }

    public function client(float $timeout = 60.0): SmtpingClient
    {
        return new SmtpingClient($this->apiKey(), $timeout);
    }

    private function config(): ?\Mautic\PluginBundle\Entity\Integration
    {
        try {
            return $this->integrationsHelper->getIntegration(SmtpingIntegration::NAME)->getIntegrationConfiguration();
        } catch (IntegrationNotFoundException) {
            return null;
        }
    }
}
