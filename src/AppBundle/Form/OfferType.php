<?php
/**
 * Created by PhpStorm.
 * User: Altea IT
 * Date: 31/05/2018
 * Time: 15:44
 */

namespace AppBundle\Form;

use AppBundle\Entity\ContractType;
use AppBundle\Entity\Tag;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Ivory\GoogleMapBundle\Form\Type\PlaceAutocompleteType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;



class OfferType extends AbstractType
{
    /**
     * {@inheritdoc}
     */


    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $translator = $options['translator'];

        $builder
            ->add('title',      TextType::class, array(
                'required' => true,
                'label' => 'offer.title',
                'attr' => array(
                    'placeholder' => 'offer.titlePH',
                )


            ))

            ->add('tag', EntityType::class, array(
                'choice_translation_domain' => true,
                'required' => true,
                'class' => Tag::class,
                'choice_label' =>  'name',
                'label' => 'offer.tag',

                'attr' => array(
                    'class' => 'select2',
                    'data-placeholder' =>  $translator->trans('offer.tagPH'),


                    ),

                'multiple' => true,


            ))

            ->add('location', PlaceAutocompleteType::class,array(
                'required' => true,
                'attr' => array(
                    'class' => 'form-control',
                    'placeholder' =>  $translator->trans('offer.locationPH')
                ),
                'label' => 'offer.location',
            ))

            ->add('description', CKEditorType::class, array(
                'required' => true,
                'label' => 'offer.description',

                'config' => array(
                    'toolbar' => 'basic',
                    'height' => '30vh'

                ),
            ))

            ->add('availableDate',      DateType::class, array('required' => false,'widget' => 'single_text',
                'format' => 'dd-MM-yyyy',
                'label' => 'offer.available',

                'attr' => [
                    'class' => 'form-control input-inline datepicker',
                    'data-provide' => 'datepicker',
                    'data-date-format' => 'dd-mm-yyyy',
                    'placeholder' => 'offer.availablePH']))

            ->add('contractType', EntityType::class, array(
                'required' => true,
                'class' => ContractType::class,
                'choice_label' =>  'name',
                'label' => 'offer.contract',
                'choice_translation_domain' => true,
                'placeholder' => '',
                'empty_data'  => null,
                'attr' => array(
                    'class' => 'select2',
                    'data-placeholder' =>  $translator->trans('offer.contractPH'))
            ))

            ->add('experience', ChoiceType::class, array(
                'choices' => array(

                'form.registration.exp1' => 'form.registration.exp1',
                'form.registration.exp2' => 'form.registration.exp2',
                'form.registration.exp3' => 'form.registration.exp3',
                ),



                'required' => false,
                'attr' => array(
                    'class' => 'select2',
                    'data-placeholder' =>  $translator->trans('offer.expPH')),
            ))

            ->add('diploma', ChoiceType::class, array(
                'choices' => array(
                'form.registration.dip1' => 'form.registration.dip1',
                'form.registration.dip2' => 'form.registration.dip2',
                'form.registration.dip3' => 'form.registration.dip3',
                'form.registration.dip4' => 'form.registration.dip4',
                'form.registration.dip5' => 'form.registration.dip5',
                 ),
                'required' => false,
                'label' => 'offer.diploma',
                'attr' => array(
                    'class' => 'select2',
                    'data-placeholder' =>  $translator->trans('offer.diplomaPH')),
            ))

            ->add('wage', IntegerType::class, array(
                'required' => false,
                'label' => 'offer.wage',
                'attr' => array(
                    'data-placeholder' =>  $translator->trans('offer.wagePH')
                    ),
            ))

            ->add('benefits', ChoiceType::class, array('choices' => array(
                'form.registration.ben1' => 'form.registration.ben1',
                'form.registration.ben2' => 'form.registration.ben2',
                'form.registration.ben3' => 'form.registration.ben3',
                'form.registration.ben4' => 'form.registration.ben4',

            ),

                'required' => false,
                'multiple' => true,
                'label' => 'offer.benefits',

                'attr' => array(
                    'class' => 'select2',
                    'data-placeholder' =>  $translator->trans('offer.benefitsPH')
                    ),
                


            ))

//            ->add('image', ImageType::class, array(
//                'required' => false,
//                'label' => 'offer.image',
//
//            ))

            ->add('language', ChoiceType::class, array('choices' => array(
                'language.fr' => 'language.fr',
                'language.en' => 'language.en',
                'language.de' => 'language.de',
                'language.lu' => 'language.lu',
            ),
                'multiple' => true,
                'required' => false,
                'attr' => array(
                    'class' => 'select2',
                    'data-placeholder' =>  $translator->trans('offer.languagesPH')


                ),
                'label' => 'offer.languages',


            ))

            ->add('link',      TextType::class, array(
                'required' => false,
                'label' => 'offer.link',

                'attr' => array('placeholder' => 'offer.linkPH'),

            ))

            ->add('submit',      SubmitType::class, array(
                'attr' => array(
                    'class' => 'mysyndic-button offer-submit login'
                )

            ))
            ->getForm()
        ;
    }/**
 * {@inheritdoc}
 */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\Offer'
        ));

        $resolver->setRequired('translator');
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_owner';
    }


}