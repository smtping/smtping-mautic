<?php

declare(strict_types=1);

namespace MauticPlugin\SmtpingBundle;

final class SmtpingEvents
{
    public const ON_FORM_VALIDATE = 'smtping.on_form_validate';
    public const ON_CAMPAIGN_ACTION = 'smtping.on_campaign_action';
    public const ON_CAMPAIGN_CONDITION = 'smtping.on_campaign_condition';
}
