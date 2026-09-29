<?php

declare(strict_types=1);

namespace MauticPlugin\SmtpingBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class ConfigAuthType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('apiKey', TextType::class, [
            'label'       => 'smtping.config.api_key',
            'label_attr'  => ['class' => 'control-label'],
            'required'    => true,
            'attr'        => ['class' => 'form-control', 'placeholder' => 'sk_live_...', 'tooltip' => 'smtping.config.api_key.tooltip'],
            'constraints' => [new NotBlank(['message' => 'smtping.config.api_key.required'])],
        ]);
    }
}
