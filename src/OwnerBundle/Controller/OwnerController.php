<?php

namespace OwnerBundle\Controller;

use AppBundle\Entity\ActiveLog;
use AppBundle\Entity\Owner;
use AppBundle\Entity\FeaturedOwner;
use AppBundle\Entity\FeaturedOffer;
use AppBundle\Entity\Slot;
use AppBundle\Form\OwnerType;
use AppBundle\Form\OfferType;
use Ivory\GoogleMap\Base\Coordinate;
use Ivory\GoogleMap\Overlay\Marker;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Ivory\GoogleMap\Map;
use Ivory\GoogleMap\Service\Geocoder\GeocoderService;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use Ivory\GoogleMap\Service\Geocoder\Request\GeocoderAddressRequest;
use Ivory\GoogleMap\Overlay\InfoWindow;
use Symfony\Component\HttpFoundation\JsonResponse;
use Trt\SwiftCssInlinerBundle\Plugin\CssInlinerPlugin;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;



class OwnerController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('', array('name' => $name));
    }

    public function createAction(Request $request)
    {

        $postAnOffer = $request->get('postOffer');
        $session = $request->getSession();

        $owner = new Owner();

        $form = $this->get('form.factory')->create(OwnerType::class);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {

            $data = $form->getData();


            $userRegister = $this->get('app.user_register');
            $user = $userRegister->register($data->getEmail(),$data->getEmail(),$data->getPassword(),$data->getFirstName(),$data->getLastName(), 'ROLE_EMPLOYER');

            if($user != false){
                // the user is now registered !

                $em = $this->getDoctrine()->getManager();

                $owner->setName($data->getName());
                $owner->setDescription($data->getDescription());
                $owner->setCredit(2);
                $owner->setLocation($data->getLocation());
                $owner->setPhone($data->getPhone());
                $owner->setVatNumber($data->getVatNumber());
                $owner->addUser($user);
                $owner->setLogo($data->getLogo());
                $owner->setCoverImage($data->getCoverImage());

                $em->persist($user);
                $em->flush();

                $translated = $this->get('translator')->trans('form.registration.successOwner');
                $session->getFlashBag()->add('info', $translated);

                if(isset($postAnOffer) and $postAnOffer){
                    return $this->redirectToRoute('post_offer');
                }else{
                    return $this->redirectToRoute('edit_owner');
                }



            }else{

                $translated = $this->get('translator')->trans('form.registration.error');
                $session->getFlashBag()->add('danger', $translated);

                return $this->redirectToRoute('mysyndic_home');
            }
        }
        return $this->render('OwnerBundle:Form:createOwner.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    public function editAction(Request $request ){
        $user = $this->getUser();

        $session = $request->getSession();
        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Owner')
        ;

        $idOwner = $request->get('id');
        $userRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:User')
        ;

        $owner = $repository->findOneBy(array('id' => isset($idOwner)?$idOwner:$user->getOwner()));

        if(!((isset($user) and $user->getOwner() == $owner) ||  (isset($user) and in_array('ROLE_ADMIN', $user->getRoles())))){
            $translated = $this->get('translator')->trans('redirect.owner');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('create_owner');
        }

        if(in_array('ROLE_ADMIN', $user->getRoles())){
            $user = $userRepository->findOneBy(array('owner' => $owner));
        }

        $session = $request->getSession();

        $owner->setFirstName($user->getFirstName());
        $owner->setLastName($user->getLastName());
        $owner->setEmail($user->getEmail());

        $form = $this->get('form.factory')->create(OwnerType::class, $owner);

        $form->remove('password');
        $form->remove('terms');

        // Si la requête est en POST
        if ($request->isMethod('POST')) {

            $form->handleRequest($request);
            if ($form->isValid()) {

                $data = $form->getData();

                $userManager = $this->get('fos_user.user_manager');

                $user->setUsername($data->getEmail());
                $user->setUsernameCanonical($data->getEmail());
                $user->setEmail($data->getEmail());
                $user->setEmailCanonical($data->getEmail());
                $user->setFirstName($data->getFirstName());
                $user->SetLastName($data->getLastName());
                $userManager->updateUser($user);

                $owner->setName($data->getName());
                $owner->setDescription($data->getDescription());
                $owner->setLocation($data->getLocation());
                $owner->setPhone($data->getPhone());
                $owner->setVatNumber($data->getVatNumber());
                $owner->addUser($user);
                $owner->setLogo($data->getLogo());
                $owner->setCoverImage($data->getCoverImage());

                $em = $this->getDoctrine()->getManager();
                $em->merge($owner);
                $em->flush();

                $translated = $this->get('translator')->trans('form.registration.edited');
                $session->getFlashBag()->add('info', $translated);


                return $this->redirectToRoute('dashboard_owner');

            }
        }

        $completion = 6;

        if(isset($owner->getTag()[0])){
            $completion += 1;
        }
        $location = $owner->getLocation();
        if(isset($location)){
            $completion += 1;
        }
        $description = $owner->getDescription();
        if(isset($description)){
            $completion += 1;
        }
        $logo = $owner->getLogo()->getImageName();
        if(isset($logo)){
            $completion += 1;
        }
        $cover = $owner->getCoverImage()->getImageName();
        if(isset($cover)){
            $completion += 1;
        }

        $completion = $completion/11 * 100;

        return $this->render('OwnerBundle:Form:editOwner.html.twig', array(
            'form' => $form->createView(),
            'user' => $user,
            'completion' => $completion,
            'logo' => $form->getData()->getLogo(),
            'coverImage' => $form->getData()->getCoverImage(),
        ));
    }

    public function deleteAction(Request $request, $id)
    {
        $user = $this->getUser();
        $session = $request->getSession();

        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Owner');

        $userRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:User');

        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer');

        $featuredOwnerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:FeaturedOwner');

        $owner = $repository->findOneBy(array('id' => isset($id)?$id:$user->getOwner()));

        if(!((isset($user) and $user->getOwner() == $owner) ||  (isset($user) and in_array('ROLE_ADMIN', $user->getRoles())))){
            $translated = $this->get('translator')->trans('redirect.owner');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('create_owner');
        }

        $userArray = $userRepository->findBy(array('owner' => $owner));

        $featuredOwnerArray = $featuredOwnerRepository->findBy(array('owner' => $owner));

        $offers = $offerRepository->findBy(array('owner' => $owner, 'archived' => false));

        $em = $this->getDoctrine()->getManager();

        $owner->setPhone(null);
        $em->merge($owner);

        foreach ($offers as $offer) {
            $offer->setArchived(true);
            $em->merge($offer);
        }

        foreach ($featuredOwnerArray as $featuredOwner) {
            $featuredOwner->setArchived(true);
            $em->merge($featuredOwner);
        }
        $mailer = $this->container->get('swiftmailer.mailer');
        foreach ($userArray as $user){
            $mail = $user->getEmail();
            $em->remove($user);

            $translated = $this->get('translator')->trans('candidate.delete.deleted');
            $message = (new \Swift_Message($translated))
                ->setFrom('mysyndiclu@noreply.lu')
                ->setTo($mail)
                ->setBody(
                    $this->renderView(
                        'AppBundle:Emails:userDeleted.html.twig',
                        array()
                    ),
                    'text/html'
                )
            ;


            $message->getHeaders()->addTextHeader(
                CssInlinerPlugin::CSS_HEADER_KEY_AUTODETECT
            );
            $mailer->send($message);
        }

        $message = (new \Swift_Message($owner->getName().' has archived his account'))
            ->setFrom('mysyndiclu@noreply.lu')
            ->setTo('contact@mysyndic.lu')
            ->setBody(
                $this->renderView(
                    'AppBundle:Emails:userDeleted.html.twig',
                    array()
                ),
                'text/html'
            )
        ;

        $message->getHeaders()->addTextHeader(
            CssInlinerPlugin::CSS_HEADER_KEY_AUTODETECT
        );

        $mailer->send($message);

        $em->flush();
        $translated = $this->get('translator')->trans('candidate.delete.deleted');
        $session->getFlashBag()->add('info', $translated);

        return $this->redirectToRoute('mysyndic_home');

    }

    public function showAction($id)
    {
        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Owner');

        $owner = $repository->find($id);

        $phone = $owner->getPhone();
        if(!isset($phone)){
            return $this->redirectToRoute('mysyndic_home');
        }

        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer');


        $offers = $offerRepository->findBy(
            array('owner' => $owner, 'archived' => false),
            array('startDate' => 'DESC')
        );

        $arrayOffer = array();
        $generateUrlService = $this->get('app.offer_generate_url');

        foreach ($offers as $offer){
            if($offer->isActive()){

                $offer->setOfferUrl($generateUrlService->generateOfferUrl($offer));

                $arrayOffer[] = $offer;
            }
        }

        $tagArray  = $owner->getTag();

        if(count($tagArray) == 0){
            $tagArray = $offerRepository->getOfferTags($owner->getId());
        }

        $location = $this->get('app.find_latlong')->geocode($owner->getLocation());

        return $this->render('OwnerBundle:Owner:show.html.twig', array(
            'owner' => $owner,
            'offers' => $arrayOffer,
            'tags' => $tagArray,
            'location' => $location
        ));

    }

    public function listAction(){
        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Owner');

        $owners = $repository->findAll();

        return $this->render('OwnerBundle:Owner:list.html.twig', array(
            'owners' => $owners
        ));
    }

    public function dashboardAction(Request $request){
        $user = $this->getUser();
        $session = $request->getSession();

        if(!isset($user) || !in_array('ROLE_EMPLOYER', $user->getRoles())){
            $translated = $this->get('translator')->trans('redirect.owner');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('create_owner');
        }

        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Owner')
        ;
        $idOwner = $request->get('id');
        $userRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:User')
        ;

        $owner = $repository->findOneBy(array('id' => isset($idOwner)?$idOwner:$user->getOwner()));
        $user = $userRepository->findOneBy(array('owner' => $owner));


        return $this->render('OwnerBundle::dashboard.html.twig', array(
            'owner' => $owner,
        ));
    }

    public function myOfferPageAction(Request $request, $archived = 0){
        $user = $this->getUser();
        $session = $request->getSession();

        if(!isset($user) || !in_array('ROLE_EMPLOYER', $user->getRoles())){
            $translated = $this->get('translator')->trans('redirect.owner');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('create_owner');
        }

        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Owner')
        ;
        $idOwner = $request->get('id');
        $userRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:User')
        ;

        $owner = $repository->findOneBy(array('id' => isset($idOwner)?$idOwner:$user->getOwner()));
        $user = $userRepository->findOneBy(array('owner' => $owner));

        $OfferRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;
        $slotRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Slot')
        ;
        $currentSlot = $slotRepository->getCurrentSlotOwner($user->getOwner()->getId());
        $searchArray = array('owner' => $user->getOwner());

        if($archived == 0){
            $searchArray['archived'] = 0;
        }

        $_SESSION['archived'] = $archived;

        $offers = $OfferRepository->findBy($searchArray);
        $generateUrlService = $this->get('app.offer_generate_url');
        foreach ($offers as &$offer){
            $offer->setOfferUrl($generateUrlService->generateOfferUrl($offer));
            $finalArray[$offer->getId()]['offer'] = $offer;
        }

        $countOfferInSlot = $OfferRepository->countOffersInSlot($owner);

        $countActiveOffer = $OfferRepository->countActiveOffer($owner);

        $creditInfo = $this->container->get('app.credit_info');

        return $this->render('OwnerBundle::myOffers.html.twig', array(
            'offers' => $offers,
            'publishedOffer' => $creditInfo->getPublishOffer(),
            'boostOffers' => $creditInfo->getBoostOffers(),
            'buySlot' => $creditInfo->getBuySlot(),
            'slots' => $currentSlot,
            'owner' => $owner,
            'countOfferInSlot' => $countOfferInSlot,
            'countActiveOffer' => $countActiveOffer
        ));
    }


    public function deleteImageAction(Request $request)
    {
//        $imageId = $request->get('id');
//
//        $imageRepository = $this
//            ->getDoctrine()
//            ->getManager()
//            ->getRepository('AppBundle:Image')
//        ;
//        $image = $imageRepository->findOneBy(array('id' => $imageId));
//
//        $ownerRepository = $this
//            ->getDoctrine()
//            ->getManager()
//            ->getRepository('AppBundle:Owner')
//        ;
//        $owner = $ownerRepository->findOneBy(array('id' => $image->getOffer()->getId()));
//
//        if(is_object($offer)){
//            $owner->removeImage($image);
//        }
//
//        $em = $this->getDoctrine()->getManager();
//
//        $em->merge($offer);
//        $em->remove($image);
//        $em->flush();


        return new Response();
    }
}
