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
use Symfony\Component\HttpFoundation\Response;
use Ivory\GoogleMap\Place\Autocomplete;
use Ivory\GoogleMap\Place\AutocompleteType;
use Ivory\GoogleMap\Helper\Builder\PlaceAutocompleteHelperBuilder;
use Ivory\GoogleMap\Helper\Builder\ApiHelperBuilder;
use Trt\SwiftCssInlinerBundle\Plugin\CssInlinerPlugin;

class AppController extends Controller
{


    public function indexAction(Request $request)
    {


        $adRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Ad')
        ;
        $ads = $adRepository->getCurrentAds();
        shuffle($ads);

        return $this->render('AppBundle:Default:index.html.twig', array(
            'ads' => $ads,
        ));

    }

    public function AboutPageAction(Request $request)
    {

        return $this->render('AppBundle:Footer:about.html.twig');

    }

    public function faqPageAction(Request $request)
    {

        return $this->render('AppBundle:Footer:faq.html.twig');

    }

    public function privacyPageAction(Request $request)
    {

        return $this->render('AppBundle:Footer:privacy.html.twig');

    }

    public function legalPageAction(Request $request)
    {

        return $this->render('AppBundle:Footer:legal.html.twig');

    }

    public function howitworkPageAction(Request $request)
    {

        return $this->render('AppBundle:Default:howitwork.html.twig');

    }

    public function checkUserAlreadyExistAction(Request $request)
    {
        $mail = $request->get('mail');
        $userRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:User')
        ;
        $user = $userRepository->findOneBy(array('email' => $mail));

        return new Response($user);

    }

    public function checkVatNumberAction(Request $request)
    {
        $vat = $request->get('vat');

        $countryCode = substr($vat, 0, 2);
        $vatNumber = substr($vat, 2);


        if(!ctype_alpha($countryCode) || !ctype_digit($vatNumber)){
            $response = null;
        }else{
            $client = new \SoapClient("http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl");
            $response = $client->checkVat(array(
                'countryCode' => $countryCode,
                'vatNumber' => $vatNumber
            ));
            $response = $response->valid;
            $response = $response ? 'true':'false';
        }

        return new Response($response);

    }

    public function sendStartMailAction(Request $request){

        $arrayNewUser = array();

//        $arrayNewUser = $request->get('list');

        $mailer = $this->container->get('swiftmailer.mailer');

        if(empty($arrayNewUser)){
            var_dump('Array is empty dude!');exit;
        }

        foreach ($arrayNewUser as $mail){
            $message = (new \Swift_Message('Jownow is live !'))
                ->setFrom('mysyndiclu@noreply.lu')
                ->setTo($mail)
                ->setBody(
                    $this->renderView(
                        'AppBundle:Emails:startMysyndic.html.twig',
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

        var_dump('send');

        return new Response();
    }
}