<?php

declare(strict_types=1);

return [
    'name'        => 'SMTPing Email Verifier',
    'description' => 'Verify contacts with SMTPing, block risky addresses on forms and route campaigns by verification result.',
    'version'     => '1.0.0',
    'author'      => 'SMTPing',
    'services'    => [
        'integrations' => [
            'mautic.integration.smtping' => [
                'class' => MauticPlugin\SmtpingBundle\Integration\SmtpingIntegration::class,
                'tags'  => ['mautic.integration', 'mautic.basic_integration'],
            ],
            'smtping.integration.configuration' => [
                'class' => MauticPlugin\SmtpingBundle\Integration\Support\ConfigSupport::class,
                'tags'  => ['mautic.config_integration'],
            ],
        ],
    ],
];
