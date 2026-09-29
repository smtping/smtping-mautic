<?php

declare(strict_types=1);

namespace MauticPlugin\SmtpingBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class BandConditionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('bands', ChoiceType::class, [
            'label'      => 'smtping.campaign.condition.bands',
            'label_attr' => ['class' => 'control-label'],
            'choices'    => [
                'smtping.band.safe'       => 'safe',
                'smtping.band.judgement'  => 'judgement',
                'smtping.band.avoid'      => 'avoid',
                'smtping.band.unverified' => 'unverified',
            ],
            'multiple'   => true,
            'expanded'   => true,
            'required'   => true,
        ]);
    }
}
