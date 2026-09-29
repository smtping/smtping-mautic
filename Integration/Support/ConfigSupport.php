<?php

declare(strict_types=1);

namespace MauticPlugin\SmtpingBundle\Integration\Support;

use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormAuthInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormFeatureSettingsInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use MauticPlugin\SmtpingBundle\Form\Type\ConfigAuthType;
use MauticPlugin\SmtpingBundle\Form\Type\FeatureSettingsType;
use MauticPlugin\SmtpingBundle\Integration\SmtpingIntegration;

class ConfigSupport extends SmtpingIntegration implements ConfigFormInterface, ConfigFormAuthInterface, ConfigFormFeatureSettingsInterface
{
    use DefaultConfigFormTrait;

    public function getAuthConfigFormName(): string
    {
        return ConfigAuthType::class;
    }

    public function getFeatureSettingsConfigFormName(): string
    {
        return FeatureSettingsType::class;
    }
}
