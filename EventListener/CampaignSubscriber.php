<?php

declare(strict_types=1);

namespace MauticPlugin\SmtpingBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\ConditionEvent;
use Mautic\CampaignBundle\Event\PendingEvent;
use MauticPlugin\SmtpingBundle\Api\SmtpingException;
use MauticPlugin\SmtpingBundle\Form\Type\BandConditionType;
use MauticPlugin\SmtpingBundle\Service\ContactFields;
use MauticPlugin\SmtpingBundle\Service\Settings;
use MauticPlugin\SmtpingBundle\SmtpingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CampaignSubscriber implements EventSubscriberInterface
{
    private const ACTION = 'smtping.verify';
    private const CONDITION = 'smtping.band';

    public function __construct(private Settings $settings, private ContactFields $fields)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD    => ['onCampaignBuild', 0],
            SmtpingEvents::ON_CAMPAIGN_ACTION    => ['onAction', 0],
            SmtpingEvents::ON_CAMPAIGN_CONDITION => ['onCondition', 0],
        ];
    }

    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        $event->addAction(self::ACTION, [
            'label'          => 'smtping.campaign.action.verify',
            'description'    => 'smtping.campaign.action.verify.descr',
            'batchEventName' => SmtpingEvents::ON_CAMPAIGN_ACTION,
        ]);

        $event->addCondition(self::CONDITION, [
            'label'       => 'smtping.campaign.condition.band',
            'description' => 'smtping.campaign.condition.band.descr',
            'formType'    => BandConditionType::class,
            'eventName'   => SmtpingEvents::ON_CAMPAIGN_CONDITION,
        ]);
    }

    public function onAction(PendingEvent $event): void
    {
        if (!$event->checkContext(self::ACTION)) {
            return;
        }
        if (!$this->settings->isEnabled()) {
            $event->failAll('SMTPing Email Verifier is not configured or not published.');

            return;
        }
        $this->fields->ensure();
        $client = $this->settings->client();

        foreach ($event->getPending() as $log) {
            $contact = $log->getLead();
            $email   = trim((string) $contact->getEmail());
            if ('' === $email) {
                $event->fail($log, 'Contact has no email address.');
                continue;
            }
            try {
                $r = $client->verify($email);
                $this->fields->write((int) $contact->getId(), (string) ($r['status'] ?? 'unknown'), $r['band']);
                $event->pass($log);
            } catch (SmtpingException $e) {
                if ($e->isAuth() || $e->isNoCredits()) {
                    $event->failRemaining($e->getMessage());

                    return;
                }
                $event->fail($log, $e->getMessage());
            }
        }
    }

    public function onCondition(ConditionEvent $event): void
    {
        if (!$event->checkContext(self::CONDITION)) {
            return;
        }
        $wanted = (array) ($event->getEventConfig()->getProperties()['bands'] ?? []);
        $band   = $this->fields->band((int) $event->getLog()->getLead()->getId()) ?? 'unverified';

        in_array($band, $wanted, true) ? $event->pass() : $event->fail();
    }
}
