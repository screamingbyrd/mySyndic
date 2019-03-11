<?php
/**
 * Created by PhpStorm.
 * User: Altea IT
 * Date: 17/07/2018
 * Time: 08:54
 */

namespace AdminBundle\Controller;

use AppBundle\Entity\Ad;
use AppBundle\Entity\Owner;
use AppBundle\Entity\FeaturedOwner;
use AppBundle\Entity\FeaturedOffer;
use AppBundle\Entity\Slot;
use AppBundle\Form\AdType;
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

class AdController extends Controller
{

    public function createAction(Request $request)
    {

        $session = $request->getSession();

        $ad = new Ad();
        $translator = $this->get('translator');
        $form = $this->get('form.factory')->create(AdType::class, $ad);

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            $data = $form->getData();

            $endDate = $data->getEndDate();

            $ad->setEndDate($endDate->modify( '+ 1 day' ));
            $em = $this->getDoctrine()->getManager();

            $em->persist($ad);
            $em->flush();

            $translated = $this->get('translator')->trans('form.registration.successOwner');
            $session->getFlashBag()->add('info', $translated);

            return $this->redirectToRoute('list_ad_admin', array('archived' => $_SESSION['archived']));

        }
        return $this->render('AdminBundle:form:createAd.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    public function editAction(Request $request)
    {

        $translator = $this->get('translator');

        $session = $request->getSession();

        $id = $request->get('id');

        $adRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Ad')
        ;
        $ad = $adRepository->findOneBy(array('id' => $id));

        $form = $this->get('form.factory')->create(AdType::class, $ad);

        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {

            $em = $this->getDoctrine()->getManager();
            $data = $form->getData();

            $endDate = $data->getEndDate();

            $ad->setEndDate($endDate->modify( '+ 1 day' ));
            $em->merge($ad);
            $em->flush();

            $translated = $this->get('translator')->trans('form.offer.edition.success');
            $session->getFlashBag()->add('info', $translated);

            return $this->redirectToRoute('list_ad_admin', array('archived' => $_SESSION['archived']));

        }
        return $this->render('AdminBundle:form:editAd.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    public function archiveAction(Request $request){

        $id = $request->get('id');
        $user = $this->getUser();

        if(!(isset($user) and in_array('ROLE_ADMIN', $user->getRoles()))){
            return $this->redirectToRoute('jobnow_home');
        }

        $session = $request->getSession();

        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Ad')
        ;
        $ad = $repository->findOneBy(array('id' => $id));

        $em = $this->getDoctrine()->getManager();
        $bool = boolval($ad->isArchived());
        $ad->setArchived(!$bool);
        $em->merge($ad);
        $em->flush();

        $translated = $this->get('translator')->trans('form.offer.edition.success');
        $session->getFlashBag()->add('info', $translated);
        return $this->redirectToRoute('list_ad_admin', array('archived' => $_SESSION['archived']));
    }

    public function listAdAction(Request $request, $archived = 0){

        $user = $this->getUser();

        if(!(isset($user) and in_array('ROLE_ADMIN', $user->getRoles()))){
            return $this->redirectToRoute('jobnow_home');
        }

        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Ad')
        ;

        if($archived == 0){
            $searchArray = array('archived' => 0);
        }else{
            $searchArray = array('archived' => 1);
        }
        $ads = $repository->findBy($searchArray);

        $_SESSION['archived'] = $archived;


        return $this->render('AdminBundle::listAds.html.twig', array(
            'ads' => $ads,
        ));
    }

}