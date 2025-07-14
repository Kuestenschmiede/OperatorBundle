<?php

namespace gutesio\OperatorBundle\Form;

use gutesio\OperatorBundle\Classes\Services\ShowcaseExportService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ShowcaseExportType extends AbstractType
{

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {


        $builder
            ->add(
                'types',
                ChoiceType::class,
                [
                    'label' => "Kategorie-Auswahl",
                    "multiple" => true,
                    "choices" => $options['type_options']
                ]
            )
            ->add('REQUEST_TOKEN', HiddenType::class)
            ->add(
                'submit',
                SubmitType::class,
                ['label' => "Schaufenster exportieren"]
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'type_options' => [],
        ]);
    }
}