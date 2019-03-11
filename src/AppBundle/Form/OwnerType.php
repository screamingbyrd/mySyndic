<?php

namespace AppBundle\Form;


use AppBundle\Entity\Tag;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Ivory\GoogleMapBundle\Form\Type\PlaceAutocompleteType;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Validator\Constraints\NotNull;


class OwnerType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', EmailType::class, array(
                'required' => true,
                'label' => 'form.registration.email',
                'constraints' => array(
                    new EMail(),
                )
            ))
            ->add('password', RepeatedType::class, array(
                'type' => PasswordType::class,
                'options' => array(
                    'translation_domain' => 'FOSUserBundle',
                    'attr' => array(
                        'autocomplete' => 'new-password',
                    ),
                ),
                'constraints' => array(
                    new Length(array('min' => 6)),
                    new Regex('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*).*$/'),
                ),
                'first_options' => array('label' => 'form.password'),
                'second_options' => array('label' => 'form.password_confirmation'),
                'invalid_message' => 'fos_user.password.mismatch',
            ))
//            ->add('terms',CheckboxType::class, array('mapped' => false,
//                'label'    => 'form.registration.accept',
//                'constraints' => array(new NotNull())))
            ->add('firstName',TextType::class, array(
                'required' => true,
                'label' => 'form.registration.firstname'
            ))
            ->add('lastName', TextType::class, array(
                'required' => true,
                'label' => 'form.registration.lastname'
            ))

            ->add('name', TextType::class, array(
                'required' => true,
                'label' => 'form.registration.companyName'
            ))

            ->add('vatNumber', TextType::class, array(
                'required' => true,
                'label' => 'form.registration.vatNumber',
                'attr' => array(
                'placeholder' => 'LU49647567'
                )
            ))

            ->add('description', TextareaType::class, array(
                'required' => false,
                'label' => 'form.registration.description',
            ))

            ->add('tag', EntityType::class, array(
                'choice_translation_domain' => true,
                'required' => false,
                'class' => Tag::class,
                'choice_label' =>  'name',
                'placeholder' => 'Category',
                'multiple' => true,
                'attr' => array('class' => 'select2'),

            ))

            ->add('location', PlaceAutocompleteType::class,array(
                'attr' => array('class' => 'form-control'),
                'required' => false,
                ))

            ->add('phone', TelType::class, array(
                'required' => true,
                'label' => 'form.registration.phone'
            ))

            ->add('logo', ImageType::class)

            ->add('coverImage', ImageType::class)

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
            'data_class' => 'AppBundle\Entity\Owner'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_owner';
    }


}
