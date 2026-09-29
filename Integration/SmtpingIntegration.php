<?php

declare(strict_types=1);

namespace MauticPlugin\SmtpingBundle\Integration;

use Mautic\IntegrationsBundle\Integration\BasicIntegration;
use Mautic\IntegrationsBundle\Integration\ConfigurationTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\BasicInterface;

class SmtpingIntegration extends BasicIntegration implements BasicInterface
{
    use ConfigurationTrait;

    public const NAME = 'Smtping';
    public const DISPLAY_NAME = 'SMTPing Email Verifier';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDisplayName(): string
    {
        return self::DISPLAY_NAME;
    }

    public function getIcon(): string
    {
        return 'plugins/SmtpingBundle/Assets/img/icon.png';
    }
}
