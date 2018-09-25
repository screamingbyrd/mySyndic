<?php

namespace EmployerBundle\Controller;

use AppBundle\Entity\ActiveLog;
use AppBundle\Entity\Employer;
use AppBundle\Entity\FeaturedEmployer;
use AppBundle\Entity\FeaturedOffer;
use AppBundle\Entity\Slot;
use AppBundle\Form\EmployerType;
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



class EmployerController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('', array('name' => $name));
    }

    public function createAction(Request $request)
    {

        $postAnOffer = $request->get('postOffer');
        $session = $request->getSession();

        $employer = new Employer();

        $form = $this->get('form.factory')->create(EmployerType::class);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {

            $data = $form->getData();


            $userRegister = $this->get('app.user_register');
            $user = $userRegister->register($data->getEmail(),$data->getEmail(),$data->getPassword(),$data->getFirstName(),$data->getLastName(), 'ROLE_EMPLOYER');

            if($user != false){
                // the user is now registered !

                $em = $this->getDoctrine()->getManager();

                $employer->setName($data->getName());
                $employer->setDescription($data->getDescription());
                $employer->setCredit(2);
                $employer->setLocation($data->getLocation());
                $employer->setPhone($data->getPhone());
                $employer->setVatNumber($data->getVatNumber());
                $employer->addUser($user);
                $employer->setLogo($data->getLogo());
                $employer->setCoverImage($data->getCoverImage());

                $em->persist($user);
                $em->flush();

                $translated = $this->get('translator')->trans('form.registration.successEmployer');
                $session->getFlashBag()->add('info', $translated);

                if(isset($postAnOffer) and $postAnOffer){
                    return $this->redirectToRoute('post_offer');
                }else{
                    return $this->redirectToRoute('edit_employer');
                }



            }else{

                $translated = $this->get('translator')->trans('form.registration.error');
                $session->getFlashBag()->add('danger', $translated);

                return $this->redirectToRoute('jobnow_home');
            }
        }
        return $this->render('EmployerBundle:Form:createEmployer.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    public function editAction(Request $request ){
        $user = $this->getUser();

        $session = $request->getSession();
        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Employer')
        ;

        $idEmployer = $request->get('id');
        $userRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:User')
        ;

        $employer = $repository->findOneBy(array('id' => isset($idEmployer)?$idEmployer:$user->getEmployer()));

        if(!((isset($user) and $user->getEmployer() == $employer) ||  (isset($user) and in_array('ROLE_ADMIN', $user->getRoles())))){
            $translated = $this->get('translator')->trans('redirect.employer');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('create_employer');
        }

        if(in_array('ROLE_ADMIN', $user->getRoles())){
            $user = $userRepository->findOneBy(array('employer' => $employer));
        }

        $session = $request->getSession();

        $employer->setFirstName($user->getFirstName());
        $employer->setLastName($user->getLastName());
        $employer->setEmail($user->getEmail());

        $form = $this->get('form.factory')->create(EmployerType::class, $employer);

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

                $employer->setName($data->getName());
                $employer->setDescription($data->getDescription());
                $employer->setLocation($data->getLocation());
                $employer->setPhone($data->getPhone());
                $employer->setVatNumber($data->getVatNumber());
                $employer->addUser($user);
                $employer->setLogo($data->getLogo());
                $employer->setCoverImage($data->getCoverImage());

                $em = $this->getDoctrine()->getManager();
                $em->merge($employer);
                $em->flush();

                $translated = $this->get('translator')->trans('form.registration.edited');
                $session->getFlashBag()->add('info', $translated);


                return $this->redirectToRoute('dashboard_employer');

            }
        }

        $completion = 6;

        if(isset($employer->getTag()[0])){
            $completion += 1;
        }
        $location = $employer->getLocation();
        if(isset($location)){
            $completion += 1;
        }
        $description = $employer->getDescription();
        if(isset($description)){
            $completion += 1;
        }
        $logo = $employer->getLogo()->getImageName();
        if(isset($logo)){
            $completion += 1;
        }
        $cover = $employer->getCoverImage()->getImageName();
        if(isset($cover)){
            $completion += 1;
        }

        $completion = $completion/11 * 100;

        return $this->render('EmployerBundle:Form:editEmployer.html.twig', array(
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
            ->getRepository('AppBundle:Employer');

        $userRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:User');

        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer');

        $featuredEmployerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:FeaturedEmployer');

        $employer = $repository->findOneBy(array('id' => isset($id)?$id:$user->getEmployer()));

        if(!((isset($user) and $user->getEmployer() == $employer) ||  (isset($user) and in_array('ROLE_ADMIN', $user->getRoles())))){
            $translated = $this->get('translator')->trans('redirect.employer');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('create_employer');
        }

        $userArray = $userRepository->findBy(array('employer' => $employer));

        $featuredEmployerArray = $featuredEmployerRepository->findBy(array('employer' => $employer));

        $offers = $offerRepository->findBy(array('employer' => $employer, 'archived' => false));

        $em = $this->getDoctrine()->getManager();

        $employer->setPhone(null);
        $em->merge($employer);

        foreach ($offers as $offer) {
            $offer->setArchived(true);
            $em->merge($offer);
        }

        foreach ($featuredEmployerArray as $featuredEmployer) {
            $featuredEmployer->setArchived(true);
            $em->merge($featuredEmployer);
        }
        $mailer = $this->container->get('swiftmailer.mailer');
        foreach ($userArray as $user){
            $mail = $user->getEmail();
            $em->remove($user);

            $translated = $this->get('translator')->trans('candidate.delete.deleted');
            $message = (new \Swift_Message($translated))
                ->setFrom('jobnowlu@noreply.lu')
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

        $message = (new \Swift_Message($employer->getName().' has archived his account'))
            ->setFrom('jobnowlu@noreply.lu')
            ->setTo('contact@jobnow.lu')
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

        return $this->redirectToRoute('jobnow_home');

    }

    public function showAction($id)
    {
        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Employer');

        $employer = $repository->find($id);

        $phone = $employer->getPhone();
        if(!isset($phone)){
            return $this->redirectToRoute('jobnow_home');
        }

        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer');


        $offers = $offerRepository->findBy(
            array('employer' => $employer, 'archived' => false),
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

        $tagArray  = $employer->getTag();

        if(count($tagArray) == 0){
            $tagArray = $offerRepository->getOfferTags($employer->getId());
        }

        $location = $this->get('app.find_latlong')->geocode($employer->getLocation());

        return $this->render('EmployerBundle:Employer:show.html.twig', array(
            'employer' => $employer,
            'offers' => $arrayOffer,
            'tags' => $tagArray,
            'location' => $location
        ));

    }

    public function listAction(){
        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Employer');

        $employers = $repository->findAll();

        return $this->render('EmployerBundle:Employer:list.html.twig', array(
            'employers' => $employers
        ));
    }

    public function dashboardAction(Request $request){
        $user = $this->getUser();
        $session = $request->getSession();

        if(!isset($user) || !in_array('ROLE_EMPLOYER', $user->getRoles())){
            $translated = $this->get('translator')->trans('redirect.employer');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('create_employer');
        }

        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Employer')
        ;
        $idEmployer = $request->get('id');
        $userRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:User')
        ;

        $employer = $repository->findOneBy(array('id' => isset($idEmployer)?$idEmployer:$user->getEmployer()));
        $user = $userRepository->findOneBy(array('employer' => $employer));


        return $this->render('EmployerBundle::dashboard.html.twig', array(
            'employer' => $employer,
        ));
    }

    public function myOfferPageAction(Request $request, $archived = 0){
        $user = $this->getUser();
        $session = $request->getSession();

        if(!isset($user) || !in_array('ROLE_EMPLOYER', $user->getRoles())){
            $translated = $this->get('translator')->trans('redirect.employer');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('create_employer');
        }

        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Employer')
        ;
        $idEmployer = $request->get('id');
        $userRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:User')
        ;

        $employer = $repository->findOneBy(array('id' => isset($idEmployer)?$idEmployer:$user->getEmployer()));
        $user = $userRepository->findOneBy(array('employer' => $employer));

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
        $currentSlot = $slotRepository->getCurrentSlotEmployer($user->getEmployer()->getId());
        $searchArray = array('employer' => $user->getEmployer());

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

        $countOfferInSlot = $OfferRepository->countOffersInSlot($employer);

        $countActiveOffer = $OfferRepository->countActiveOffer($employer);

        $creditInfo = $this->container->get('app.credit_info');

        return $this->render('EmployerBundle::myOffers.html.twig', array(
            'offers' => $offers,
            'publishedOffer' => $creditInfo->getPublishOffer(),
            'boostOffers' => $creditInfo->getBoostOffers(),
            'buySlot' => $creditInfo->getBuySlot(),
            'slots' => $currentSlot,
            'employer' => $employer,
            'countOfferInSlot' => $countOfferInSlot,
            'countActiveOffer' => $countActiveOffer
        ));
    }

    public function featuredEmployerPageAction(Request $request){
        $user = $this->getUser();

        $now = new \DateTime();
        $year = $request->get('year');
        $year = isset($year)?$year:$now->format('Y');

        $featuredEmployerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:FeaturedEmployer')
        ;

        $featuredEmployer = $featuredEmployerRepository->findBy(array('archived' => 0));
        $featuredArray = array();

        foreach ($featuredEmployer as $item) {

            $featuredArray[$item->getStartDate()->format('d/m/Y')]['id'][] = $item->getEmployer()->getId();
            $featuredArray[$item->getStartDate()->format('d/m/Y')]['featured'][] = $item;
        }

        $creditInfo = $this->container->get('app.credit_info');
        $now = new \DateTime();
        $now->modify( '- 1 week' );

        return $this->render('EmployerBundle::featuredEmployer.html.twig', array(
            'featuredEmployerArray' => $featuredArray,
            'user' => $user,
            'featuredEmployerCredit' => $creditInfo->getFeaturedEmployer(),
            'now' => $now,
            'year' => $year
        ));
    }

    public function reserveFeaturedEmployerAction(Request $request){
        $date = $request->get('date');
        $userId = $request->get('user');
        $session = $request->getSession();

        $user = $this->getUser();
        if(!isset($user) || !in_array('ROLE_EMPLOYER', $user->getRoles())|| $user->getId() != (int)$userId){
            return $this->redirectToRoute('create_employer');
        }

        $creditInfo = $this->container->get('app.credit_info');

        $employer = $user->getEmployer();

        $creditEmployer = $employer->getCredit();
        $creditFeaturedEmployer = $creditInfo->getFeaturedEmployer();

        if($creditEmployer < $creditFeaturedEmployer){
            $translated = $this->get('translator')->trans('form.offer.activate.error');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('jobnow_credit');
        }

        $employer->setCredit($creditEmployer - $creditFeaturedEmployer);

        $featuredEmployer = new FeaturedEmployer();
        $featuredEmployer->setEmployer($employer);
        $startDate = new \DateTime($date['date']);
        $endDate = new \DateTime($date['date']);

        $featuredEmployer->setStartDate($startDate);
        $featuredEmployer->setEndDate($endDate->modify( '+ 1 week' ));
        $featuredEmployer->setArchived(0);

        $em = $this->getDoctrine()->getManager();
        $em->persist($featuredEmployer);
        $em->flush();

        return $this->redirectToRoute('featured_employer_page', array('year' => substr($date['date'], 0, strpos($date['date'], '-'))));
    }

    public function deleteFeaturedEmployerAction(Request $request){

        $session = $request->getSession();
        $featuredId = $request->get('id');
        $user = $this->getUser();

        if(!isset($user) || !in_array('ROLE_ADMIN', $user->getRoles())){
            return $this->redirectToRoute('create_candidate');
        }

        $featuredEmployerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:FeaturedEmployer')
        ;
        $featuredEmployer = $featuredEmployerRepository->findOneBy(array('id' => $featuredId));

        $featuredEmployer->setArchived(true);

        $em = $this->getDoctrine()->getManager();
        $em->merge($featuredEmployer);
        $em->flush();

        $session->getFlashBag()->add('info', 'featured employer archived');

        return $this->redirectToRoute('featured_employer_page');
    }

    public function featuredOfferPageAction(Request $request){
        $now = new \DateTime();
        $year = $request->get('year');
        $year = isset($year)?$year:$now->format('Y');
        $user = $this->getUser();
        $employer = $user->getEmployer();
        $featuredOfferRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:FeaturedOffer')
        ;
        $featuredOffer = $featuredOfferRepository->findBy(array('archived' => 0));
        $featuredArray = array();

        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;

        $offers = $offerRepository->findBy(array('employer' => $user->getEmployer(), 'archived' => false));

        foreach ($featuredOffer as $item) {
            $featuredArray[$item->getStartDate()->format('d/m/Y')]['ids'][] = $item->getOffer()->getId();
            if($item->getOffer()->getEmployer() == $employer){
                $featuredArray[$item->getStartDate()->format('d/m/Y')]['features'][] = $item;
            }
        }

        $creditInfo = $this->container->get('app.credit_info');

        $now = new \DateTime();
        $now->modify( '- 1 week' );

        return $this->render('EmployerBundle::featuredOffer.html.twig', array(
            'featuredOfferArray' => $featuredArray,
            'user' => $user,
            'featuredOfferCredit' => $creditInfo->getFeaturedOffer(),
            'offers' => $offers,
            'now' => $now,
            'year' => $year
        ));
    }

    public function reserveFeaturedOfferAction(Request $request){
        $date = $request->get('date');
        $userId = $request->get('user');
        $offerId = $request->get('offerId');
        $session = $request->getSession();

        $user = $this->getUser();
        if(!isset($user) || !in_array('ROLE_EMPLOYER', $user->getRoles())|| $user->getId() != (int)$userId){
            return $this->redirectToRoute('create_employer');
        }

        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;

        $offer = $offerRepository->findById($offerId);

        $creditInfo = $this->container->get('app.credit_info');

        $employer = $user->getEmployer();

        $creditEmployer = $employer->getCredit();
        $creditFeaturedOffer = $creditInfo->getFeaturedOffer();

        if($creditEmployer < $creditFeaturedOffer){
            $translated = $this->get('translator')->trans('form.offer.activate.error');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('jobnow_credit');
        }

        $employer->setCredit($creditEmployer - $creditFeaturedOffer);

        $featuredOffer = new FeaturedOffer();
        $featuredOffer->setOffer($offer[0]);
        $startDate = new \DateTime($date['date']);
        $endDate = new \DateTime($date['date']);

        $featuredOffer->setStartDate($startDate);
        $featuredOffer->setEndDate($endDate->modify( '+ 1 week' ));
        $featuredOffer->setArchived(0);

        $em = $this->getDoctrine()->getManager();
        $em->persist($featuredOffer);
        $em->flush();

        return $this->redirectToRoute('featured_offer_page', array('year' => substr($date['date'], 0, strpos($date['date'], '-'))));
    }

    public function deleteFeaturedOfferAction(Request $request){

        $session = $request->getSession();
        $featuredId = $request->get('id');
        $user = $this->getUser();

        if(!isset($user) || !in_array('ROLE_ADMIN', $user->getRoles())){
            return $this->redirectToRoute('create_candidate');
        }

        $featuredOfferRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:FeaturedOffer')
        ;
        $featuredOffer = $featuredOfferRepository->findOneBy(array('id' => $featuredId));

        $featuredOffer->setArchived(true);

        $em = $this->getDoctrine()->getManager();
        $em->merge($featuredOffer);
        $em->flush();

        $session->getFlashBag()->add('info', 'featured offer deleted');

        return $this->redirectToRoute('featured_offer_page');
    }

    public function buySlotAction(Request $request){
        $session = $request->getSession();
        $user = $this->getUser();

        if(!isset($user) || !in_array('ROLE_EMPLOYER', $user->getRoles())){
            return $this->redirectToRoute('create_employer');
        }

        $employer = $user->getEmployer();

        $creditInfo = $this->container->get('app.credit_info');

        $creditEmployer = $employer->getCredit();
        $buySlot = $creditInfo->getBuySlot();

        if($creditEmployer < $buySlot){
            $translated = $this->get('translator')->trans('form.offer.activate.error');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('jobnow_credit');
        }

        $employer->setCredit($creditEmployer - $buySlot);

        $em = $this->getDoctrine()->getManager();
        $em->merge($employer);
        $em->flush();

        $slot = new Slot();

        $slot->setEmployer($employer);
        $now =  new \DateTime();
        $next = new \DateTime();
        $slot->setStartDate($now);
        $slot->setEndDate($next->modify( '+ 1 year' ));

        $em->persist($slot);
        $em->flush();


        $translated = $this->get('translator')->trans('slot.buy.success');
        $session->getFlashBag()->add('info', $translated);

        return $this->redirectToRoute('employer_offers', array('archived' => $_SESSION['archived']));
    }

    public function addToSlotAction(Request $request){
        $session = $request->getSession();
        $id = $request->get('id');
        $ajax = $request->get('ajax');
        $user = $this->getUser();

        if(!isset($user) || !in_array('ROLE_EMPLOYER', $user->getRoles())){
            return $this->redirectToRoute('create_employer');
        }

        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;
        $currentOffer = $offerRepository->findOneBy(array('id' => $id));

        $slotRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Slot')
        ;
        $currentSlot = $slotRepository->getCurrentSlotEmployer($user->getEmployer()->getId());

        $em = $this->getDoctrine()->getManager();

        foreach ($currentSlot as $slot){
            $offer = $slot->getOffer();
            if(!isset($offer)){
                $now =  new \DateTime();
                $slot->setOffer($currentOffer);
                $currentOffer->setSlot($slot);
                $currentOffer->setUpdateDate($now);

                $activeLog = new ActiveLog();
                $activeLog->setOfferId($currentOffer->getId());
                $activeLog->setSlotId($slot->getId());
                $activeLog->setStartDate($now);

                $em->merge($slot);
                $em->merge($currentOffer);
                $em->merge($activeLog);
                $em->flush();

                $translated = $this->get('translator')->trans('slot.add.success');
                $session->getFlashBag()->add('info', $translated);

                return $this->redirectToRoute('employer_offers', array('archived' => $_SESSION['archived']));
            }
        }

        $translated = $this->get('translator')->trans('slot.add.error');
        $session->getFlashBag()->add('danger', $translated);

        return $this->redirectToRoute('employer_offers', array('archived' => $_SESSION['archived']));
    }

    public function removeFromSlotAction(Request $request){
        $session = $request->getSession();
        $id = $request->get('id');
        $user = $this->getUser();

        if(!isset($user) || !in_array('ROLE_EMPLOYER', $user->getRoles())){
            $translated = $this->get('translator')->trans('redirect.employer');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('create_employer');
        }

        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;
        $offer = $offerRepository->findOneBy(array('id' => $id));

        $slotRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Slot')
        ;
        $slot = $slotRepository->findOneBy(array('offer' => $offer));

        $em = $this->getDoctrine()->getManager();

        $slot->setOffer(null);
        $offer->setSlot(null);

        $activeLogRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:ActiveLog')
        ;
        $activeLog = $activeLogRepository->selectCurrentLog($offer->getId(), $slot->getId(), true);

        if(isset($activeLog) && !empty($activeLog)){
            $now = new \DateTime("midnight");
            $activeLog[0]->setEndDate($now);
            $em->merge($activeLog[0]);
        }

        $em->merge($slot);
        $em->merge($offer);
        $em->flush();

        $translated = $this->get('translator')->trans('slot.remove.success');
        $session->getFlashBag()->add('info', $translated);

        return $this->redirectToRoute('employer_offers', array('archived' => $_SESSION['archived']));
    }

    public function EmptySlotAction(Request $request){
        $session = $request->getSession();
        $id = $request->get('id');
        $user = $this->getUser();

        if(!isset($user) || !in_array('ROLE_EMPLOYER', $user->getRoles())){
            return $this->redirectToRoute('create_employer');
        }

        $slotRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Slot')
        ;
        $slot = $slotRepository->findOneBy(array('id' => $id));

        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;
        $offer = $offerRepository->findOneBy(array('slot' => $slot));

        $em = $this->getDoctrine()->getManager();

        $slot->setOffer(null);
        $offer->setSlot(null);

        $activeLogRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:ActiveLog')
        ;
        $activeLog = $activeLogRepository->selectCurrentLog($offer->getId(), $slot->getId(), true);

        if(isset($activeLog) && !empty($activeLog)){
            $now = new \DateTime("midnight");
            $activeLog[0]->setEndDate($now);
            $em->merge($activeLog[0]);
        }

        $em->merge($slot);
        $em->merge($offer);
        $em->flush();

        $translated = $this->get('translator')->trans('slot.empty.success');
        $session->getFlashBag()->add('info', $translated);

        return $this->redirectToRoute('employer_offers', array('archived' => $_SESSION['archived']));
    }

    public function listAppliedCandidatePageAction(Request $request){
        $id = $request->get('id');

        $session = $request->getSession();

        $user = $this->getUser();

        $employer = $user->getEmployer();

        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;
        $offer = $offerRepository->findOneBy(array('id' => $id));

        $generateUrlService = $this->get('app.offer_generate_url');
        $offer->setOfferUrl($generateUrlService->generateOfferUrl($offer));

        if(!((isset($user) and in_array('ROLE_EMPLOYER', $user->getRoles()) and $offer->getEmployer()->getId() == $employer->getId()) || in_array('ROLE_ADMIN', $user->getRoles()))){
            $translated = $this->get('translator')->trans('redirect.employer');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('create_employer');
        }

        $postulatedOfferRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:PostulatedOffers')
        ;

        $postulatedOffers = $postulatedOfferRepository->findBy(array('offer' => $offer, 'archived' => 0));

        return $this->render('EmployerBundle::appliedCandidateList.html.twig', array(
            'postulatedOffers' => $postulatedOffers,
            'offer' => $offer
        ));
    }

    public function spontaneousApplyAction(Request $request, $id){
        $session = $request->getSession();
        $emloyerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Employer')
        ;
        $employer = $emloyerRepository->findOneBy(array('id' => $id));
        $userRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:User')
        ;
        $users = $userRepository->findBy(array('employer' => $employer));

        $arrayEmail = array();

        foreach ($users as $user){
            $arrayEmail[] = $user->getEmail();
        }
        $firstUser = $arrayEmail[0];

        $comment = $request->get('comment');
        $target_dir = "uploads/images/candidate/";
        $target_file = $target_dir . md5(uniqid()) . basename($_FILES["cv"]["name"]);
        move_uploaded_file($_FILES["cv"]["tmp_name"], $target_file);

        $mailer = $this->container->get('swiftmailer.mailer');

        $message = (new \Swift_Message($this->get('translator')->trans('employer.show.spontaenous.send')))
            ->setFrom('jobnowlu@noreply.lu')
            ->setTo($firstUser)
            ->setCc(array_shift($arrayEmail))
            ->setBody(
                $this->renderView(
                    'AppBundle:Emails:apply.html.twig',
                    array('comment' => $comment)
                ),
                'text/html'
            )
            ->attach(\Swift_Attachment::fromPath($target_file));
        ;

        $message->getHeaders()->addTextHeader(
            CssInlinerPlugin::CSS_HEADER_KEY_AUTODETECT
        );

        $mailer->send($message);
        unlink($target_file);

        $translated = $this->get('translator')->trans('employer.show.spontaenous.sent');
        $session->getFlashBag()->add('info', $translated);
        return $this->redirectToRoute('show_employer', array('id' => $id));
    }

    public function addCollaboratorAction(Request $request){

        $user = $this->getUser();

        $session = $request->getSession();

        if(!(isset($user) and  in_array('ROLE_EMPLOYER', $user->getRoles()))){
            $translated = $this->get('translator')->trans('redirect.employer');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('create_employer');
        }

        $form = $this->get('form.factory')
            ->createNamedBuilder('collaborator-form')
            ->add('email',      EmailType::class, array(
                'required' => true,
                'label' => 'form.registration.email'
            ))
            ->add('submit', SubmitType::class)
            ->getForm();
        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            if ($form->isValid()) {

                $data = $form->getData();

                $email = $data['email'];

                $employer = $user->getEmployer();

                $userRegister = $this->get('app.user_register');
                $collaborator = $userRegister->addCollaborator($email, $employer);

                if(is_bool($collaborator) && !$collaborator){
                    $translated = $this->get('translator')->trans('form.registration.mailAlreadyExist');
                    $session->getFlashBag()->add('danger', $translated);
                    return $this->redirectToRoute('add_collaborator');
                }

                $em = $this->getDoctrine()->getManager();

                $em->persist($collaborator);
                $em->flush();

                $mailer = $this->container->get('swiftmailer.mailer');

                $translated = $this->get('translator')->trans('employer.addCollaborator.youHave');

                $message = (new \Swift_Message($translated . ' ' . $employer->getName()))
                    ->setFrom('jobnowlu@noreply.lu')
                    ->setTo($email)
                    ->setBody(
                        $this->renderView(
                            'AppBundle:Emails:addedAsCollaborator.html.twig',
                            array(
                                'employer' => $employer,
                            )
                        ),
                        'text/html'
                    )
                ;

                $message->getHeaders()->addTextHeader(
                    CssInlinerPlugin::CSS_HEADER_KEY_AUTODETECT
                );
                $mailer->send($message);

                $translated = $this->get('translator')->trans('employer.addCollaborator.added');
                $session->getFlashBag()->add('info', $translated);

                return $this->redirectToRoute('edit_employer', array('id' => $employer->getId()));

            }
        }
        return $this->render('EmployerBundle:Employer:addCollaborator.html.twig', [
            'form' => $form->createView(),
        ]);

    }

    public function listCollaboratorAction(Request $request, $id){

        $session = $request->getSession();

        $currentUser = $this->getUser();

        if(!(isset($currentUser) and  in_array('ROLE_EMPLOYER', $currentUser->getRoles()) and $currentUser->isMain())){
            $translated = $this->get('translator')->trans('redirect.employer');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('create_employer');
        }

        $employerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Employer');

        $employer = $employerRepository->findOneBy(array('id' => $id));

        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:User');

        $users = $repository->findBy(array('employer' => $employer, 'enabled' => 1));

        for ($i = 0; $i<= count($users); $i++){
            if($users[$i] == $currentUser){
                unset($users[$i]);
            }
        }

        return $this->render('EmployerBundle:Employer:collaboratorList.html.twig', array(
            'collaborators' => $users
        ));
    }

    public function archiveCollaboratorAction(Request $request, $id){

        $session = $request->getSession();

        $user = $this->getUser();

        if(!(isset($user) and  in_array('ROLE_EMPLOYER', $user->getRoles()) and $user->isMain())){
            $translated = $this->get('translator')->trans('redirect.employer');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('create_employer');
        }

        $employer = $user->getEmployer();

        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:User');

        $collaborator = $repository->findOneBy(array('id' => $id));

        $userManager = $this->container->get('fos_user.user_manager');
        $collaborator->setEnabled(false);
        $userManager->updateUser($collaborator);

        $translated = $this->get('translator')->trans('employer.addCollaborator.deleted');
        $session->getFlashBag()->add('info', $translated);

        return $this->redirectToRoute('list_collaborator', array('id' => $employer->getId()));
    }

    public function changeAccessCollaboratorAction(Request $request, $id){

        $session = $request->getSession();

        $user = $this->getUser();

        if(!(isset($user) and  in_array('ROLE_EMPLOYER', $user->getRoles()) and $user->isMain())){
            $translated = $this->get('translator')->trans('redirect.employer');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('create_employer');
        }

        $employer = $user->getEmployer();

        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:User');

        $collaborator = $repository->findOneBy(array('id' => $id));

        $em = $this->getDoctrine()->getManager();

        $collaborator->setMain(!$collaborator->isMain());

        $em->merge($collaborator);
        $em->flush();

        $translated = $this->get('translator')->trans('employer.addCollaborator.access.changed');
        $session->getFlashBag()->add('info', $translated);

        return $this->redirectToRoute('list_collaborator', array('id' => $employer->getId()));
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
//        $employerRepository = $this
//            ->getDoctrine()
//            ->getManager()
//            ->getRepository('AppBundle:Employer')
//        ;
//        $employer = $employerRepository->findOneBy(array('id' => $image->getOffer()->getId()));
//
//        if(is_object($offer)){
//            $employer->removeImage($image);
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
