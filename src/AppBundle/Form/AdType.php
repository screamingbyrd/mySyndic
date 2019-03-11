<?php
/**
 * Created by PhpStorm.
 * User: Altea IT
 * Date: 17/07/2018
 * Time: 09:02
 */

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;

class AdType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', TextType::class, array(
                'required' => true,
                'label' => 'admin.ads.form.title'
            ))

            ->add('description', TextareaType::class, array(
                'required' => false,
                'label' => 'form.registration.description',
            ))
            ->add('link',      TextType::class, array(
                'required' => false,
                'label' => 'admin.ads.form.link',
            ))
            ->add('startDate',      DateType::class, array('required' => false,'widget' => 'single_text',
                'format' => 'dd-MM-yyyy',
                'label' => 'offer.startDate',

                'attr' => [
                    'class' => 'form-control input-inline datepicker',
                    'data-provide' => 'datepicker',
                    'data-date-format' => 'dd-mm-yyyy']))
            ->add('endDate',      DateType::class, array('required' => false,'widget' => 'single_text',
                'format' => 'dd-MM-yyyy',
                'label' => 'dashboard.owner.slot.endDate',

                'attr' => [
                    'class' => 'form-control input-inline datepicker',
                    'data-provide' => 'datepicker',
                    'data-date-format' => 'dd-mm-yyyy']))

            ->add('logo', ImageType::class, array(
                'label' => 'admin.ads.form.logo',
            ))

            ->add('coverImage', ImageType::class, array(
                'label' => 'admin.ads.form.image',
            ))

            ->add('submit',      SubmitType::class, array(
                'attr' => array(
                    'class' => 'mysyndic-button login',
                )
            ))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\Ad'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_ad';
    }


}
