<?php
/**
 * Created by PhpStorm.
 * User: Altea IT
 * Date: 30/05/2018
 * Time: 16:21
 */

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Form\ContactType;
use Trt\SwiftCssInlinerBundle\Plugin\CssInlinerPlugin;

class ContactController extends Controller
{

    public function contactAction(Request $request)
    {
        $session = $request->getSession();
        $name = $request->get('name');
        $emailSender = $request->get('email');
        $emailTo = $request->get('emailTo');
        $emailTo = isset($emailTo)?$emailTo:'contact@mysyndic.lu';
        $message = $request->get('message');
        $type = $request->get('type');
        $type = isset($type)?$type:'contactUs';
        $data = array('name' => $name, 'emailSender' => $emailSender, 'message' => $message);

        if ($request->isMethod('POST')) {
                // Send mail
                if($this->sendEmail($emailTo,$data, $type)){

                    $translated = $this->get('translator')->trans('email.sent');
                    $session->getFlashBag()->add('info', $translated);

                    return $this->redirectToRoute('contact_us_page');
                }else{
                    // An error ocurred, handle
                    var_dump("Errooooor :(");
                }

        }

        return $this->render('AppBundle:Contact:contact.html.twig',array('type' => $type, 'emailTo' => $emailTo));
    }

    private function sendEmail($email, $data, $template){
        $mailer = $this->container->get('swiftmailer.mailer');

        $translated = $this->get('translator')->trans('email.contacted');
        $message = (new \Swift_Message($translated))
            ->setFrom($data['emailSender'])
            ->setTo($email)
            ->setBody(
                $this->renderView(
                    'AppBundle:Emails:'.$template.'.html.twig',
                    array('data' => $data)
                ),
                'text/html'
            )
        ;

        $message->getHeaders()->addTextHeader(
            CssInlinerPlugin::CSS_HEADER_KEY_AUTODETECT
        );

//        if(isset($data['emailSender']) && $data['emailSender']){
//
//        }

        return $this->get('mailer')->send($message);
    }


    public function contactUsPageAction(Request $request)
    {

        return $this->render('AppBundle:Contact:contactUs.html.twig');

    }

    public function shareByMailAction(Request $request)
    {
        $session = $request->getSession();
        $emailSender = $request->get('email');
        $emailTo = $request->get('emailTo');
        $idOffer = $request->get('id');

        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;
        $offer = $offerRepository->findOneBy(array('id'=>$idOffer));

        $mailer = $this->container->get('swiftmailer.mailer');

        $translated = $this->get('translator')->trans('email.share.title');
        $message = (new \Swift_Message($translated))
            ->setFrom('mysyndic@noreply.lu')
            ->setTo($emailTo)
            ->setBody(
                $this->renderView(
                    'AppBundle:Emails:shareByMail.html.twig',
                    array(
                        'offer' => $offer,
                        'sendFrom' => $emailSender
                    )
                ),
                'text/html'
            )
        ;

        $message->getHeaders()->addTextHeader(
            CssInlinerPlugin::CSS_HEADER_KEY_AUTODETECT
        );

        $mailer->send($message);

        $generateUrlService = $this->get('app.offer_generate_url');

        $translated = $this->get('translator')->trans('offer.shared');
        $session->getFlashBag()->add('info', $translated);

        return $this->redirectToRoute('show_offer', array('id' => $offer->getId(),'url' => $generateUrlService->generateOfferUrl($offer)));

    }
}