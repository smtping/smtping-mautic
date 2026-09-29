<?php

declare(strict_types=1);

namespace MauticPlugin\SmtpingBundle\EventListener;

use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\Event\ValidationEvent;
use Mautic\FormBundle\FormEvents;
use MauticPlugin\SmtpingBundle\Api\SmtpingException;
use MauticPlugin\SmtpingBundle\Service\Settings;
use MauticPlugin\SmtpingBundle\SmtpingEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Checks every email field of every Mautic form on submit. Fails open: if the API
 * cannot be reached or the account is out of credits, the submission goes through.
 */
class FormSubscriber implements EventSubscriberInterface
{
    public function __construct(private Settings $settings, private LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_ON_BUILD     => ['onFormBuild', 0],
            SmtpingEvents::ON_FORM_VALIDATE => ['onFormValidate', 0],
        ];
    }

    public function onFormBuild(FormBuilderEvent $event): void
    {
        $event->addValidator('plugin.smtping.email', [
            'eventName' => SmtpingEvents::ON_FORM_VALIDATE,
            'fieldType' => 'email',
        ]);
    }

    public function onFormValidate(ValidationEvent $event): void
    {
        if (!$this->settings->isEnabled() || !$this->settings->blockForms()) {
            return;
        }
        $email = trim((string) $event->getValue());
        if ('' === $email) {
            return;
        }

        try {
            $result = $this->settings->client(8.0)->verify($email);
        } catch (SmtpingException $e) {
            $this->logger->warning('SMTPing form check skipped: '.$e->getMessage());

            return;
        }

        if (in_array($result['band'], $this->settings->blockedBands(), true)) {
            $event->failedValidation($this->settings->formMessage());
        }
    }
}
