<?php
/**
 * Created by PhpStorm.
 * User: Altea IT
 * Date: 01/06/2018
 * Time: 12:10
 */

namespace EmployerBundle\Controller;

use AppBundle\Entity\ActiveLog;
use AppBundle\Entity\Employer;
use AppBundle\Entity\Offer;
use AppBundle\Entity\PostulatedOffers;
use AppBundle\Form\EmployerType;
use AppBundle\Form\OfferType;
use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Ivory\GoogleMap\Map;
use Ivory\GoogleMap\Service\Geocoder\GeocoderService;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use Ivory\GoogleMap\Service\Geocoder\Request\GeocoderAddressRequest;
use Ivory\GoogleMap\Overlay\InfoWindow;
use Ivory\GoogleMap\Base\Coordinate;
use Ivory\GoogleMap\Overlay\Marker;
use Ivory\GoogleMap\Event\Event;
use Ivory\GoogleMap\Place\Autocomplete;
use Ivory\GoogleMap\Place\AutocompleteType;
use Ivory\GoogleMap\Helper\Builder\PlaceAutocompleteHelperBuilder;
use Ivory\GoogleMap\Helper\Builder\ApiHelperBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Trt\SwiftCssInlinerBundle\Plugin\CssInlinerPlugin;

class OfferController extends Controller
{

    public function postAction(Request $request)
    {

        $session = $request->getSession();

        $translator = $this->get('translator');

        $user = $this->getUser();

        if(isset($_SESSION['request'])){
            $request = $_SESSION['request'];
            unset($_SESSION['request']);
        }

        $offer = new Offer();


        $form = $this->get('form.factory')->create(OfferType::class, $offer, array(
            'translator' => $translator,
        ));

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {


            if(!isset($user)){
                $_SESSION['request'] = $request;
                return $this->redirectToRoute('create_employer', array('postOffer' => true));
            }

            $em = $this->getDoctrine()->getManager();

            $offer->setEmployer($user->getEmployer());

            $offer->setCountView(0);
            $offer->setCountContact(0);
            $past = new \DateTime('01-01-1900');
            $offer->setStartDate($past);
            $offer->setEndDate($past);
            $offer->setUpdateDate($past);

            $em->persist($offer);
            $em->flush();

            $translated = $this->get('translator')->trans('form.offer.creation.success');
            $session->getFlashBag()->add('info', $translated);

            return $this->redirectToRoute('employer_offers');

        }
        return $this->render('EmployerBundle:Form:postOffer.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    public function editAction(Request $request)
    {

        $translator = $this->get('translator');

        $session = $request->getSession();

        $id = $request->get('id');

        $user = $this->getUser();

        $employer = $user->getEmployer();

        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;
        $offer = $offerRepository->findOneBy(array('id' => $id));

        if(!((isset($user) and in_array('ROLE_EMPLOYER', $user->getRoles()) and $offer->getEmployer()->getId() == $employer->getId()) || in_array('ROLE_ADMIN', $user->getRoles()))){
            $translated = $this->get('translator')->trans('redirect.employer');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('create_employer');
        }

        $form = $this->get('form.factory')->create(OfferType::class, $offer, array(
            'translator' => $translator,
        ));

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {

            $em = $this->getDoctrine()->getManager();

            $offer->setCountView($offer->getCountView());
            $offer->setCountContact($offer->getCountContact());


            $em->merge($offer);
            $em->flush();

            $validated = $offer->isValidated();

            if(isset($validated) && !$validated){
                $mailer = $this->container->get('swiftmailer.mailer');
                $message = (new \Swift_Message('Invalid offer modified: ' . $offer->getTitle(). ' id:'. $offer->getId()))
                    ->setFrom('jobnowlu@noreply.lu')
                    ->setTo('contact@jobnow.lu')
                    ->setBody(
                        $this->renderView(
                            'AppBundle:Emails:invalidOfferModified.html.twig',
                            array('offer' => $offer,
                            )
                        ),
                        'text/html'
                    )
                ;

                $message->getHeaders()->addTextHeader(
                    CssInlinerPlugin::CSS_HEADER_KEY_AUTODETECT
                );
                $mailer->send($message);
            }

            $translated = $this->get('translator')->trans('form.offer.edition.success');
            $session->getFlashBag()->add('info', $translated);

            return $this->redirectToRoute('dashboard_employer', array('archived' => $_SESSION['archived']));

        }
        return $this->render('EmployerBundle:Form:editOffer.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    public function deleteAction(Request $request){

        $session = $request->getSession();

        $id = $request->get('id');

        $ajax = $request->get('ajax');
        $user = $this->getUser();

        $employerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Employer')
        ;
        $employer = $employerRepository->findOneBy(array('id' => $user->getEmployer()));

        $ids = is_array($id)?$id:array($id);

        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;

        foreach ($ids as $id){
            $offer = $offerRepository->findOneBy(array('id' => $id));

            if(!((isset($user) and in_array('ROLE_EMPLOYER', $user->getRoles()) and $offer->getEmployer()->getId() == $employer->getId()) || in_array('ROLE_ADMIN', $user->getRoles()))){
                $translated = $this->get('translator')->trans('form.offer.edition.error');
                $session->getFlashBag()->add('danger', $translated);
                return $this->redirectToRoute('dashboard_employer', array('archived' => $_SESSION['archived']));
            }

            $bool = boolval($offer->isArchived());
            $offer->setArchived(!$bool);

            $em = $this->getDoctrine()->getManager();
            $em->merge($offer);
        }

        $em->flush();

        $translated = $this->get('translator')->trans(!$bool?'form.offer.archived.success':'form.offer.unarchived.success');
        $session->getFlashBag()->add('info', $translated);

        $route = 'employer_offers';

        if(in_array('ROLE_ADMIN', $user->getRoles())){
            $route = 'list_offer_admin';
        }

        if(isset($ajax) && $ajax){
            return new JsonResponse($this->generateUrl($route, array('archived' => $_SESSION['archived'])));
        }
        return $this->redirectToRoute($route, array('archived' => $_SESSION['archived']));
    }

    public function eraseAction(Request $request){

        $session = $request->getSession();

        $id = $request->get('id');

        $user = $this->getUser();

        $employerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Employer')
        ;
        $employer = $employerRepository->findOneBy(array('id' => $user->getEmployer()));

        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;

        $offer = $offerRepository->findOneBy(array('id' => $id));

        $title = $offer->getTitle();

        if(!((isset($user) and in_array('ROLE_EMPLOYER', $user->getRoles()) and $offer->getEmployer()->getId() == $employer->getId()) || in_array('ROLE_ADMIN', $user->getRoles()))){
            $translated = $this->get('translator')->trans('form.offer.edition.error');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('dashboard_employer', array('archived' => $_SESSION['archived']));
        }

        $userRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:User')
        ;
        $users = $userRepository->findBy(array('employer' => $offer->getEmployer()));
        $arrayEmail = array();

        foreach ($users as $employerUser){
            $arrayEmail[] = $employerUser->getEmail();
        }

        if(is_array($arrayEmail)){
            $firstUser = $arrayEmail[0];

            $em = $this->getDoctrine()->getManager();
            $em->remove($offer);

            $em->flush();

            $mailer = $this->container->get('swiftmailer.mailer');
            $translated = $this->get('translator')->trans('form.offer.deleted.subject');
            $message = (new \Swift_Message($translated . ' ' . $title))
                ->setFrom('jobnowlu@noreply.lu')
                ->setTo($firstUser)
                ->setCc(array_shift($arrayEmail))
                ->setBody(
                    $this->renderView(
                        'AppBundle:Emails:offerDeleted.html.twig',
                        array('title' => $title,
                        )
                    ),
                    'text/html'
                )
            ;

            $message->getHeaders()->addTextHeader(
                CssInlinerPlugin::CSS_HEADER_KEY_AUTODETECT
            );
            $mailer->send($message);
        }

        return $this->redirectToRoute('list_offer_admin');
    }

    public function showAction(Request $requestPrime, $id){
        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;
        $offer = $offerRepository->findOneBy(array('id' => $id));

        $user = $this->getUser();

        if((!isset($user) || !in_array('ROLE_EMPLOYER', $user->getRoles())) && ($offer->getArchived() == 1 || $offer->isValidated() === false)){
            return $this->redirectToRoute('offer_archived', array('id' => $id));
        }

        $titleCV = null;
        $titleCoverLetter = null;
        if(isset($user)){
            $candidateRepository = $this
                ->getDoctrine()
                ->getManager()
                ->getRepository('AppBundle:Candidate')
            ;

            $candidate = $candidateRepository->findOneBy(array('user' => $user->getId()));

            if(isset($candidate)){
                $originalCvPath = $candidate->getCv();
                if (isset($originalCvPath)){
                    $titleCV = substr($originalCvPath, strrpos($originalCvPath, '-job42-') + 7);
                    $titleCV = str_replace('-space-', ' ', $titleCV);

                }
                $originalCoverLetterPath = $candidate->getCoverLetter();
                if(isset($originalCoverLetterPath)){
                    $titleCoverLetter = substr($originalCoverLetterPath, strrpos($originalCoverLetterPath, '-job42-') + 7);
                    $titleCoverLetter = str_replace('-space-', ' ', $titleCoverLetter);

                }
            }
        }


        $similarOfferArray = $this->getSimilarOffers($offer);

        $offer->setCountView($offer->getCountView() +1);

        $em = $this->getDoctrine()->getManager();
        $em->merge($offer);
        $em->flush();

        $location = $this->get('app.find_latlong')->geocode($offer->getLocation());

        $seoPage = $this->container->get('sonata.seo.page');

        $seoPage
            ->addMeta('property', 'og:title', $offer->getTitle())
            ->addMeta('property', 'og:type', 'website')
            ->addMeta('property', 'og:image', 'https://jobnow.lu/uploads/images/employer/'.$offer->getEmployer()->getLogo()->getImageName())
            ->addMeta('property', 'og:description', $offer->getDescription())
            ->addMeta('property', 'og:url', $requestPrime->getUri());

        return $this->render('EmployerBundle:Offer:show.html.twig', array(
            'offer' => $offer,
            'similarOfferArray' => $similarOfferArray['offers'],
            'tags' => $similarOfferArray['tags'],
            'cvTitle' => $titleCV,
            'coverLetterTitle' => $titleCoverLetter,
            'location' => $location
        ));
    }

    public function activateAction(Request $request){
        $session = $request->getSession();

        $id = $request->get('id');
        $ajax = $request->get('ajax');

        $user = $this->getUser();

        $employerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Employer')
        ;
        $employer = $employerRepository->findOneBy(array('id' => $user->getEmployer()));
        $creditEmployer = $employer->getCredit();
        $creditInfo = $this->container->get('app.credit_info');
        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;
        $ids = is_array($id)?$id:array($id);

        $creditOffer = 0;

        $offerArray = array();

        foreach ($ids as $id){
            $offer = $offerRepository->findOneBy(array('id' => $id));
            if(!isset($user) || !in_array('ROLE_EMPLOYER', $user->getRoles()) || $offer->getEmployer()->getId() != $employer->getId()){
                $translated = $this->get('translator')->trans('redirect.employer');
                $session->getFlashBag()->add('danger', $translated);
                return $this->redirectToRoute('create_employer');
            }
            $slot = $offer->getSlot();
            if(!isset($slot)){
                $creditOffer += $creditInfo->getPublishOffer();
                $offerArray[] = $offer;
            }

        }


        if($creditEmployer < $creditOffer){
            $translated = $this->get('translator')->trans('form.offer.activate.error');
            $session->getFlashBag()->add('danger', $translated);

            if(isset($ajax) && $ajax){
                return new JsonResponse($this->generateUrl('employer_offers', array('archived' => $_SESSION['archived'])));
            }
            return $this->redirectToRoute('jobnow_credit');
        }

        $employer->setCredit($creditEmployer - $creditOffer);

        $em = $this->getDoctrine()->getManager();

        foreach ($offerArray as $offer){
            $now =  new \DateTime("midnight");
            $next = new \DateTime("midnight");
            $offer->setUpdateDate($now);
            $endDate = $offer->getEndDate();
            if($endDate >= $now){
                $more = $endDate->modify( '+ 2 month' );
                $more = new \DateTime($more->format('Y-m-d H:i:s'));
                $offer->setEndDate($more);

                $activeLogRepository = $this
                    ->getDoctrine()
                    ->getManager()
                    ->getRepository('AppBundle:ActiveLog')
                ;
                $activeLog = $activeLogRepository->selectCurrentLog($offer->getId());

                if(isset($activeLog) && !empty($activeLog)){
                    $activeLog[0]->setEndDate($more);
                    $em->merge($activeLog[0]);
                }

            }else{
                $offer->setStartDate($now);
                $offer->setEndDate($next->modify( '+ 2 month' ));

                $activeLog = new ActiveLog();
                $activeLog->setOfferId($offer->getId());
                $activeLog->setStartDate($now);
                $new = new \DateTime("midnight");
                $activeLog->setEndDate($new->modify( '+ 2 month' ));
                $em->persist($activeLog);

            }

            $em->merge($offer);
        }

        $em->merge($employer);
        $em->flush();

        $translated = $this->get('translator')->trans('form.offer.activate.success');
        $session->getFlashBag()->add('info', $translated);

        if(isset($ajax) && $ajax){
            return new JsonResponse($this->generateUrl('employer_offers', array('archived' => $_SESSION['archived'])));
        }

        return $this->redirectToRoute('employer_offers', array('archived' => $_SESSION['archived']));
    }

    public function searchAction(Request $request){
        $keywords = $request->get('keyword');
        $location = $request->get('location');
        $employer = $request->get('employer');
        $tags = $request->get('tags');
        $type =  $request->get('type');
        $currentPage = $request->get('row');
        $numberOfItem =  $request->get('number');

        $searchParam = array(
            'keywords' => $keywords,
            'location' => $location,
            'employer' => $employer,
            'tags' => $tags,
            'type' => $type
        );
        $searchParam = json_encode($searchParam);

        $searchService = $this->get('app.search.offer');

        if(preg_match("/[0-9]/",$keywords)){
            $offerRepository = $this
                ->getDoctrine()
                ->getManager()
                ->getRepository('AppBundle:Offer')
            ;
            $data = $offerRepository->findBy(array('id'=>$keywords));
        }else{
            $data = $searchService->searchOffer($searchParam);
        }


        $generateUrlService = $this->get('app.offer_generate_url');

        $offerArray = array();

        foreach ($data as &$offer){
            $offer->setOfferUrl($generateUrlService->generateOfferUrl($offer));
            $validated = $offer->isValidated();
            if($offer->isActive() && (!isset($validated) || $validated)){
                $offerArray[] = $offer;
            }
        }

        $countResult = count($offerArray);

//        $locationArray =array();
//        foreach ($data as $offer){
//            $locationArray[$offer->getLocation()][] = $offer;
//        }
//
//        $map = new Map();
//
//        //workarround to ssl certificat pb curl error 60
//
//        $config = [
//            'verify' => false,
//        ];
//
//        $adapter = GuzzleAdapter::createWithConfig($config);
//
//        // GeoCoder API
//        $geocoder = new GeocoderService($adapter, new GuzzleMessageFactory());
//        $markers = array();
//        $i = 1;
//
//        foreach ($locationArray as $location => $offers){
//
//            //try to match string location to get Object with lat long info
//            if($location){
//                $request = new GeocoderAddressRequest($location);
//            }else{
//                $request = new GeocoderAddressRequest('228 Route d\'Esch, Luxembourg');
//            }
//
//            $response = $geocoder->geocode($request);
//
//            $status = $response->getStatus();
//
//            foreach ($response->getResults() as $result) {
//
//                $coord = $result->getGeometry()->getLocation();
//                continue;
//
//            }
//
//            if(isset($coord)) {
//                $marker = new Marker($coord);
//
//                $marker->setVariable('marker' . $i);
//                $content = '<p class="map-offer-container">';
//                foreach ($offers as $offer){
//                    $content .=  '<a class="map-offer" href="'.$this->generateUrl('show_offer', array('id' => $offer->getId(),'url' => $this->generateOfferUrl($offer))).'">'.$offer->getTitle().'</a>';
//                }
//                $content .= '</p>';
//                $infoWindow = new InfoWindow($content);
//                $infoWindow->setAutoOpen(true);
//                $infoWindow->setAutoClose(true);
//                $infoWindow->setOption('maxWidth', 400);
//                $marker->setInfoWindow($infoWindow);
//
//                $markers[] = $marker;
//            }
//            $i++;
//        }
//        $map->getOverlayManager()->addMarkers($markers);
//
//        $event = new Event(
//            $map->getVariable(),
//            'zoom_changed',
//            'function(){'.
//            $marker->getVariable().'.setMap(null)'
//            .'}'
//        );
//        $map->getEventManager()->addEvent($event);
//        $map->setCenter($coord);
//        $map->setStylesheetOption('width', 1000);
//        $map->setStylesheetOption('min-height', 1100);
//        $map->setMapOption('zoom', 2);

        $adRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Ad')
        ;
        $ads = $adRepository->getCurrentAds();
        shuffle($ads);

        $finalArray = array_slice($offerArray, ($currentPage - 1 ) * $numberOfItem, $numberOfItem);

        $totalPage = ceil ($countResult / $numberOfItem);

        return $this->render('EmployerBundle:Offer:search-data.html.twig',
            array(
                'data' => $finalArray,
                'page' => $currentPage,
                'total' => $totalPage,
                'numberOfItem' =>($numberOfItem > $countResult? $countResult:$numberOfItem),
                'countResult' => $countResult,
                'searchParam' => $searchParam,
                'ads' => $ads
            )
        );
    }

    public function searchPageAction(Request $request){
        $keywords = $request->get('keyword');
        $location = $request->get('location');
        $chosenEmployer = $request->get('employer');
        $chosenTags = $request->get('tags');
        $chosenContract  = $request->get('contracts');

        $contractTypeRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:ContractType')
        ;
        $contractType = $contractTypeRepository->findAll();

        $employerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Employer')
        ;
        $employers = $employerRepository->findAll();


        $tagRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Tag')
        ;
        $tags = $tagRepository->findAll();


        $featuredOfferRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:FeaturedOffer')
        ;

        $featuredOffers = $featuredOfferRepository->getCurrentFeaturedOffer();

        $generateUrlService = $this->get('app.offer_generate_url');

        foreach ($featuredOffers as $featuredOffer){
            $featuredOffer->getOffer()->setOfferUrl($generateUrlService->generateOfferUrl($featuredOffer->getOffer()));
        }

        if(isset($chosenTags)){
            foreach ($chosenTags as &$tag){
                $newTag = array();
                $newTag['text'] = $this->get('translator')->trans($tag);
                $newTag['value'] = $tag;
                $tag = $newTag;
            }
        }

        $autoComplete = new Autocomplete();
        $autoComplete->setInputId('place_input');

        $autoComplete->setInputAttributes(array(
            'class' => 'form-control',
            'name' => 'location',
            'placeholder' =>  $this->get('translator')->trans('form.offer.search.location')
        ));

        if(isset($location) && $location != ''){
            $autoComplete->setInputAttributes(array(
                'class' => 'form-control',
                'name' => 'location',
                'placeholder' =>  $this->get('translator')->trans('form.offer.search.location'),
                'value' => $location
            ));
        }

        $autoComplete->setTypes(array(AutocompleteType::CITIES));
        $autoCompleteHelperBuilder = new PlaceAutocompleteHelperBuilder();

        $autoCompleteHelper = $autoCompleteHelperBuilder->build();
        $apiHelperBuilder = ApiHelperBuilder::create();
        $apiHelperBuilder->setKey('AIzaSyBY8KoA6XgncXKSfDq7Ue7R2a1QWFSFxjc');
        $apiHelperBuilder->setLanguage($request->getLocale());

        $apiHelper = $apiHelperBuilder->build();

        return $this->render('EmployerBundle:Offer:searchPage.html.twig', array(
            'contractType' => $contractType,
            'keyword' => $keywords,
            'employers' => $employers,
            'chosenEmployer'=>$chosenEmployer,
            'chosenContracts' => $chosenContract,
            'tags' => $tags,
            'chosenTags' => $chosenTags,
            'featuredOffer' => $featuredOffers,
            'autoComplete' => $autoCompleteHelper->render($autoComplete),
            'autoCompleteScript' => $apiHelper->render([$autoComplete])
        ));
    }

    public function boostAction(Request $request){
        $session = $request->getSession();

        $user = $this->getUser();

        $employerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Employer')
        ;
        $employer = $employerRepository->findOneBy(array('id' => $user->getEmployer()));

        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;

        if(!isset($user) || !in_array('ROLE_EMPLOYER', $user->getRoles())){
            $translated = $this->get('translator')->trans('redirect.employer');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('create_employer');
        }

        $creditInfo = $this->container->get('app.credit_info');

        $creditEmployer = $employer->getCredit();
        $creditBoost = $creditInfo->getBoostOffers();

        if($creditEmployer < $creditBoost){
            $translated = $this->get('translator')->trans('form.offer.boost.error');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('jobnow_credit');
        }

        $employer->setCredit($creditEmployer - $creditBoost);

        $offers = $offerRepository->findBy(array('employer' => $employer, 'archived' => false));
        $em = $this->getDoctrine()->getManager();
        if(count($offers) > 0){
            $now =  new \DateTime();
            foreach ($offers as $offer){
                $offer->setUpdateDate($now);
                $em->merge($offer);
            }
        }


        $em->merge($employer);
        $em->flush();

        $translated = $this->get('translator')->trans('form.offer.boost.success');
        $session->getFlashBag()->add('info', $translated);

        return $this->redirectToRoute('employer_offers', array('archived' => $_SESSION['archived']));
    }

    public function applyAction(Request $request){
        $session = $request->getSession();

        $user = $this->getUser();

        $id = $request->get('id');

        if(!isset($user) || in_array('ROLE_EMPLOYER', $user->getRoles())){
            return $this->redirectToRoute('create_candidate', array('offerId' => $id));
        }

        $candidateRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Candidate')
        ;
        $candidate = $candidateRepository->findOneBy(array('user' => $user->getId()));

        $candidateMail = $user->getEmail();
        $comment = $request->get('comment');
        $target_dir = "uploads/images/candidate/";

        $cv = $candidate->getCv();
        if($_FILES["cv"]["size"] != 0){
            $target_file = $target_dir . md5(uniqid()) . '-job42-' . $this->cleanAccent(str_replace(' ', '-space-', basename($_FILES["cv"]["name"])));
            move_uploaded_file($_FILES["cv"]["tmp_name"], $target_file);
            if(isset($cv) && $cv != ''){
                unlink($cv);
            }
            $candidate->setCv($target_file);
        }else{
            $target_file = $cv;
        }

        $coverLetter = $candidate->getCoverLetter();
        if($_FILES["cover-file"]["size"] != 0){
            $target_file_cover = null;
            $target_dir_cover = "uploads/images/candidate/";
            $target_file_cover = $target_dir_cover . md5(uniqid()) . '-job42-' . $this->cleanAccent(str_replace(' ', '-space-', basename($_FILES["cover-file"]["name"])));
            move_uploaded_file($_FILES["cover-file"]["tmp_name"], $target_file_cover);
            if(isset($coverLetter) && $coverLetter != ''){
                unlink($coverLetter);
            }
            $candidate->setCoverLetter($target_file_cover);
        }else{
            $target_file_cover = $coverLetter;
        }

        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;
        $offer = $offerRepository->findOneBy(array('id' => $id));

        $generateUrlService = $this->get('app.offer_generate_url');

        $offer->setOfferUrl($generateUrlService->generateOfferUrl($offer));

        $postulatedOfferRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:PostulatedOffers')
        ;
        $postulatedOffer = $postulatedOfferRepository->findBy(array('candidate' => $candidate, 'offer' => $offer));

        if(isset($postulatedOffer) && count($postulatedOffer) > 0){
            $translated = $this->get('translator')->trans('offer.apply.already');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('dashboard_candidate');
        }

        $userRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:User')
        ;
        $users = $userRepository->findBy(array('employer' => $offer->getEmployer()));

        $arrayEmail = array();

        foreach ($users as $emplyerUser){
            $arrayEmail[] = $emplyerUser->getEmail();
        }
        $firstUser = $arrayEmail[0];

        $mailer = $this->container->get('swiftmailer.mailer');

        $translatedEmployer = $this->get('translator')->trans('offer.applied.employer');

        $messageEmmployer = (new \Swift_Message($translatedEmployer . ' ' . $offer->getTitle()))
            ->setFrom('jobnowlu@noreply.lu')
            ->setTo($firstUser)
            ->setCc(array_shift($arrayEmail))
            ->setBody(
                $this->renderView(
                    'AppBundle:Emails:apply.html.twig',
                    array('comment' => $comment, 'offer' => $offer, 'link' => $target_file, 'linkCover' => $target_file_cover)
                ),
                'text/html'
            );
        ;


        $translatedCandidate = $this->get('translator')->trans('offer.applied.candidate');
        $messageCandidate = (new \Swift_Message($translatedCandidate . ' ' . $offer->getTitle()))
            ->setFrom('jobnowlu@noreply.lu')
            ->setTo($candidateMail)
            ->setBody(
                $this->renderView(
                    'AppBundle:Emails:applied.html.twig',
                    array('offer' => $offer)
                ),
                'text/html'
            );
        ;
        $messageEmmployer->getHeaders()->addTextHeader(
            CssInlinerPlugin::CSS_HEADER_KEY_AUTODETECT
        );
        $messageCandidate->getHeaders()->addTextHeader(
            CssInlinerPlugin::CSS_HEADER_KEY_AUTODETECT
        );

        $mailer->send($messageCandidate);
        $mailer->send($messageEmmployer);

        $em = $this->getDoctrine()->getManager();

        $postulatedOffer = new PostulatedOffers();
        $postulatedOffer->setCandidate($candidate);
        $postulatedOffer->setOffer($offer);
        $now =  new \DateTime();
        $postulatedOffer->setDate($now);
        $postulatedOffer->setCoverLetter($comment);


        $offer->setCountContact($offer->getCountContact() +1);

        $em->merge($offer);
        $em->persist($postulatedOffer);
        $em->merge($candidate);
        $em->flush();

        $translated = $this->get('translator')->trans('offer.applied.success', array('%title%' => $offer->getTitle()));
        $session->getFlashBag()->add('info', $translated);

        return $this->redirectToRoute('dashboard_candidate');
    }

    public function offerNotFoundAction($id)
    {
        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;
        $offer = $offerRepository->findOneBy(array('id' => $id));
        $similarOfferArray = $this->getSimilarOffers($offer);

        return $this->render('EmployerBundle:Offer:offerNotFound.html.twig', array(
            'similarOfferArray' => $similarOfferArray['offers'],
            'tags' => $similarOfferArray['tags'],
        ));
    }

    private function getSimilarOffers($offer){
        $tags = $offer->getTag();
        $similarOfferArray = array();
        $tagsArray = array();
        $finalArray = array();
        if(isset($tags)){
            $finder = $this->container->get('fos_elastica.finder.app.offer');
            $boolQuery = new \Elastica\Query\BoolQuery();

            $newBool = new \Elastica\Query\BoolQuery();


            foreach($tags as $tag){
                $tagsArray[] = $tag->getName();
                $tagQuery = new \Elastica\Query\Match();
                $tagQuery->setFieldQuery('tag.name', $tag->getName());
                $newBool->addShould($tagQuery);
            }

            $boolQuery->addMust($newBool);

            $fieldQuery = new \Elastica\Query\Match();
            $fieldQuery->setFieldQuery('archived', false);
            $boolQuery->addMust($fieldQuery);

            $fieldQuery = new \Elastica\Query\Match();
            $fieldQuery->setFieldQuery('id', $offer->getId());
            $boolQuery->addMustNot($fieldQuery);

            $query = new \Elastica\Query($boolQuery);

            $query->setSort(array('updateDate' => 'desc'));
            $similarOfferArray = $finder->find($query);
        }
        $generateUrlService = $this->get('app.offer_generate_url');
        foreach ($similarOfferArray as &$offer){
            $offer->setOfferUrl($generateUrlService->generateOfferUrl($offer));
            if($offer->isActive()){
                $finalArray[] = $offer;
            }
        }

        return array('offers' => $similarOfferArray, 'tags' => $tagsArray);
    }

    //@TODO put in CRON with 1 and 7 days
    public function sendEndActivationAction(Request $request, $days){

        $now  =  new \DateTime("midnight");

        $next = $now->modify( '+ '.$days.' day' );

        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;
        $offers = $offerRepository->findBy(array('endDate' => $next));

        $userRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:User');

        if(!empty($offers)){
            $mailer = $this->container->get('swiftmailer.mailer');
            foreach ($offers as $offer){

                $userArray = $userRepository->findBy(array('employer' => $offer->getEmployer()));

                $translated = $this->get('translator')->trans('email.endOfActivation.title', array('%days%' => $days));
                foreach ($userArray as $user){
                    $message = (new \Swift_Message($translated))
                        ->setFrom('jobnowlu@noreply.lu')
                        ->setTo($user->getEmail())
                        ->setBody(
                            $this->renderView(
                                'AppBundle:Emails:endOfActivation.html.twig',
                                array('offer' => $offer,
                                    'days' => $days
                                )
                            ),
                            'text/html'
                        )
                    ;

                    $message->getHeaders()->addTextHeader(
                        CssInlinerPlugin::CSS_HEADER_KEY_AUTODETECT
                    );
                    $mailer->send($message);
                }
            }
        }

        return new Response();
    }

    public function incrementAction(Request $request)
    {
        $elementId = $request->get('id');
        $type = $request->get('type');

        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;
        $offer = $offerRepository->findOneBy(array('id' => $elementId));

        if($type == 'countView'){
            $offer->setCountView($offer->getCountView() +1);
        }elseif ($type == 'countContact'){
            $offer->setCountContact($offer->getCountContact() +1);
        }

        $em = $this->getDoctrine()->getManager();

        $em->merge($offer);
        $em->flush();


        return new Response();
    }

    private function cleanAccent($text){
        $unwanted_array = array(    'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
            'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );
        $text = strtr( $text, $unwanted_array );

        return $text;
    }

}