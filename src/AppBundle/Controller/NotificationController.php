<?php
/**
 * Created by PhpStorm.
 * User: Altea IT
 * Date: 19/06/2018
 * Time: 08:33
 */

namespace AppBundle\Controller;


use AppBundle\Entity\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Trt\SwiftCssInlinerBundle\Plugin\CssInlinerPlugin;

class NotificationController extends Controller
{

    public function createAction(Request $request)
    {
        $session = $request->getSession();
        $type = $request->get('type');
        $elementId = $request->get('id');

        $user = $this->getUser();
        if(!isset($user) || !in_array('ROLE_CANDIDATE', $user->getRoles())){
            return $this->redirectToRoute('create_candidate');
        }

        $candidateRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Candidate')
        ;
        $candidate = $candidateRepository->findOneBy(array('user' => $user->getId()));

        $notificationRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Notification')
        ;
        $notification = $notificationRepository->findOneBy(array(
            'candidate' => $candidate,
            'typeNotification' => $type,
            'elementId' => $elementId
        ));

        if(isset($notification) && !empty($notification)){
            $translated = $this->get('translator')->trans('notification.already');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('dashboard_candidate');
        }

        $session = $request->getSession();

        $notification = new Notification();

        $em = $this->getDoctrine()->getManager();

        $notification->setCandidate($candidate);

        $now =  new \DateTime();
        $notification->setDate($now);
        $notification->setTypeNotification($type);
        $notification->setElementId($elementId);
        $notification->setUid(md5((string)$elementId));

        $em->persist($notification);
        $em->flush();

        $translated = $this->get('translator')->trans('notification.created');
        $session->getFlashBag()->add('info', $translated);

        $url = $this->generateUrl('dashboard_candidate');

        return $this->redirect($url.'#alerts');

    }

    public function createSearchAction(Request $request)
    {
        $session = $request->getSession();
        $type = $request->get('type');
        $elementId = $request->get('id');
        $mail = $request->get('mail');

        $session = $request->getSession();

        $notification = new Notification();

        $em = $this->getDoctrine()->getManager();

        $notification->setCandidate(null);

        $now =  new \DateTime();
        $notification->setDate($now);
        $notification->setTypeNotification($type);
        $notification->setElementId($elementId);
        $notification->setMail($mail);
        $notification->setUid(md5((string)$elementId));

        $em->persist($notification);
        $em->flush();

        $mailer = $this->container->get('swiftmailer.mailer');

        $translated = $this->get('translator')->trans('email.notification.new');
        $message = (new \Swift_Message($translated))

            ->setFrom('mysyndiclu@noreply.lu')
            ->setTo($mail)
            ->setBody(
                $this->renderView(
                    'AppBundle:Emails:notificationCreated.html.twig',
                    array()
                ),
                'text/html'
            )
        ;
//        return $this->render('AppBundle:Emails:notificationCreated.html.twig', array(
//        ));
        $message->getHeaders()->addTextHeader(
            CssInlinerPlugin::CSS_HEADER_KEY_AUTODETECT
        );

        $mailer->send($message);

        $translated = $this->get('translator')->trans('notification.created');
        $session->getFlashBag()->add('info', $translated);

        $url = $this->generateUrl('search_page_offer');

        return $this->redirect($url.'#alerts');

    }

    public function deleteAction(Request $request, $id){
        $user = $this->getUser();
        $session = $request->getSession();

        $notificationRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Notification')
        ;
        $notification = $notificationRepository->findOneBy(array('uid' => $id));

        $em = $this->getDoctrine()->getManager();
        $em->remove($notification);
        $em->flush();

        if(isset($user)){
            $translated = $this->get('translator')->trans('notification.deleted');
            $session->getFlashBag()->add('info', $translated);
            return $this->redirectToRoute('dashboard_candidate');
        }else{
            $translated = $this->get('translator')->trans('notification.deleted');
            $session->getFlashBag()->add('info', $translated);
            return $this->redirectToRoute('mysyndic_home');
        }
    }

    //@TODO put in CRON
    public function sendAction(Request $request){

        $session = $request->getSession();

        $now =  new \DateTime();

        $notificationRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Notification')
        ;
        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;
        $candidateRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Candidate')
        ;
        $ownerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Owner')
        ;
        $tagRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Tag')
        ;
        $notifications = $notificationRepository->findAll();
        $em = $this->getDoctrine()->getManager();

        foreach ($notifications as $notification){
            if($notification->getTypeNotification() != 'search'){
                $offers = $offerRepository->getNotificationOffers($notification);

                if(!empty($offers)){
                    $candidate = $candidateRepository->findOneBy(array('id' => $notification->getCandidate()));

                    if($notification->getTypeNotification() == 'notification.owner'){
                        $owner = $ownerRepository->findOneBy(array('id' => $notification->getElementId()));
                        $subject = $owner->getName();
                    }elseif ($notification->getTypeNotification() == 'notification.tag'){
                        $tag = $tagRepository->findOneBy(array('id' => $notification->getElementId()));
                        $subject = $tag->getName();
                    }
                    $mail = $candidate->getUser()->getEmail();
                    $mailer = $this->container->get('swiftmailer.mailer');

                    $message = (new \Swift_Message($this->get('translator')->trans('email.notification.send.title.search')))
                        ->setFrom('mysyndiclu@noreply.lu')
                        ->setTo($mail)
                        ->setBody(
                            $this->renderView(
                                'AppBundle:Emails:notification.html.twig',
                                array('offers' => $offers,
                                    'subject' => $translated = $this->get('translator')->trans($subject),
                                    'id' =>$notification->getUid(),
                                    'search' => false
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

                $notification->setDate($now);
                $em->merge($notification);
            }
        }

        $em->flush();

        return new Response();
    }

    //@TODO put in CRON
    public function sendSearchAction(Request $request){

        $session = $request->getSession();

        $now =  new \DateTime();

        $notificationRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Notification')
        ;
        $notifications = $notificationRepository->findBy(array('typeNotification' => 'notification.search'));
        $em = $this->getDoctrine()->getManager();

        $searchService = $this->get('app.search.offer');

        foreach ($notifications as $notification){
            $notifiDate = $notification->getDate();
            $finalArray = array();
            $offers = $searchService->searchOffer($notification->getElementId());
            foreach ($offers as $offer){
                $slot = $offer->getSlot();
                if($offer->getStartDate() > $notifiDate || ($offer->getCreationDate() > $notifiDate && isset($slot))){
                    $finalArray[] = $offer;
                }
            }

            if(!empty($finalArray)){
                $mail = $notification->getMail();
                $mailer = $this->container->get('swiftmailer.mailer');

                $translated = $this->get('translator')->trans('email.notification.send.title.search');
                $message = (new \Swift_Message($translated))
                    ->setFrom('mysyndiclu@noreply.lu')
                    ->setTo($mail)
                    ->setBody(
                        $this->renderView(
                            'AppBundle:Emails:notification.html.twig',
                            array('offers' => $finalArray,
                                'subject' => $translated,
                                'id' =>$notification->getUid(),
                                    'search' => true
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

            $notification->setDate($now);
            $em->merge($notification);
        }

        $em->flush();

        return new Response();
    }

    public function checkNotificationExistAction(Request $request)
    {
        $type = $request->get('type');
        $elementId = $request->get('id');
        $mail = $request->get('mail');

        $userRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Notification')
        ;
        $user = $userRepository->findOneBy(array('mail' => $mail,'typeNotification' =>$type,'elementId' => $elementId));

        if(is_object($user)){
            $response = 'true';
        }else{
            $response = 'false';
        }

        return new Response($response);
    }

}