<?php

declare(strict_types=1);

namespace MauticPlugin\SmtpingBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class FeatureSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('block_forms', YesNoButtonGroupType::class, [
            'label' => 'smtping.config.block_forms',
            'data'  => (bool) ($options['data']['block_forms'] ?? true),
            'attr'  => ['tooltip' => 'smtping.config.block_forms.tooltip'],
        ]);

        $builder->add('block_bands', ChoiceType::class, [
            'label'      => 'smtping.config.block_bands',
            'label_attr' => ['class' => 'control-label'],
            'choices'    => [
                'smtping.band.avoid_only'          => 'avoid',
                'smtping.band.avoid_and_judgement' => 'avoid_judgement',
            ],
            'data'       => $options['data']['block_bands'] ?? 'avoid',
            'attr'       => ['class' => 'form-control'],
            'required'   => true,
        ]);

        $builder->add('form_message', TextType::class, [
            'label'      => 'smtping.config.form_message',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'attr'       => ['class' => 'form-control', 'placeholder' => 'This email address cannot be used. Please enter another one.'],
        ]);
    }
}
