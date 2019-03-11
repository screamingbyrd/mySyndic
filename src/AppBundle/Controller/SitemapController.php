<?php
/**
 * Created by PhpStorm.
 * User: Altea IT
 * Date: 04/07/2018
 * Time: 08:32
 */

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class SitemapController extends Controller
{

    public function sitemapAction(Request $request)
    {
        $locale = $request->get('_locale');

        // We define an array of urls
        $urls = [];
        // We store the hostname of our website
        $hostname = $request->getHost();

        //AppBundle routing

        $urls[] = ['loc' =>
            'https://mysyndic.lu' . $this->get('router')->generate('mysyndic_home',array('_locale' => $locale)), 'changefreq' => 'daily', 'priority' => '1.0'
        ];

        $urls[] = ['loc' =>
            'https://mysyndic.lu' . $this->get('router')->generate('fos_user_security_login',array('_locale' => $locale)), 'changefreq' => 'weekly', 'priority' => '1.0'
        ];
        $urls[] = ['loc' =>
            'https://mysyndic.lu' . $this->get('router')->generate('mysyndic_credit',array('locale' => $locale, '_locale' => $locale)), 'changefreq' => 'weekly', 'priority' => '1.0'
        ];
        $urls[] = ['loc' =>
            'https://mysyndic.lu' . $this->get('router')->generate('contact_us_page',array('locale' => $locale, '_locale' => $locale)), 'changefreq' => 'weekly', 'priority' => '1.0'
        ];
//        $urls[] = ['loc' =>
//            'https://mysyndic.lu' . $this->get('router')->generate('about_us',array('locale' => $locale, '_locale' => $locale)), 'changefreq' => 'weekly', 'priority' => '1.0'
//        ];
        $urls[] = ['loc' =>
            'https://mysyndic.lu' . $this->get('router')->generate('faq_page',array('locale' => $locale, '_locale' => $locale)), 'changefreq' => 'weekly', 'priority' => '1.0'
        ];

//        $urls[] = ['loc' =>
//            'https://mysyndic.lu' . $this->get('router')->generate('privacy_page',array('locale' => $locale, '_locale' => $locale)), 'changefreq' => 'weekly', 'priority' => '1.0'
//        ];
//
//        $urls[] = ['loc' =>
//            'https://mysyndic.lu' . $this->get('router')->generate('legal_page',array('locale' => $locale, '_locale' => $locale)), 'changefreq' => 'weekly', 'priority' => '1.0'
//        ];
        
        //CandidateBundle routing

        $urls[] = ['loc' =>
            'https://mysyndic.lu' . $this->get('router')->generate('create_candidate',array('locale' => $locale, '_locale' => $locale)), 'changefreq' => 'weekly', 'priority' => '1.0'
        ];

        //OwnerBundle routing
        $urls[] = ['loc' =>
            'https://mysyndic.lu' . $this->get('router')->generate('create_owner',array('locale' => $locale, '_locale' => $locale)), 'changefreq' => 'weekly', 'priority' => '1.0'
        ];

        $urls[] = ['loc' =>
            'https://mysyndic.lu' . $this->get('router')->generate('list_owner',array('locale' => $locale, '_locale' => $locale)), 'changefreq' => 'weekly', 'priority' => '1.0'
        ];

        $urls[] = ['loc' =>
            'https://mysyndic.lu' . $this->get('router')->generate('search_page_offer',array('locale' => $locale, '_locale' => $locale)), 'changefreq' => 'weekly', 'priority' => '1.0'
        ];

        // Then, we will find all our articles stored in the database
        $offerRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer');
        $offers = $offerRepository->getActiveOffers();

        $generateUrlService = $this->get('app.offer_generate_url');

        // We loop on them
        foreach ($offers as $offer) {
            $urls[] = ['loc' =>
                'https://mysyndic.lu' . $this->get('router')->generate('show_offer',array('locale' => $locale, '_locale' => $locale, 'id' => $offer->getId(), 'url' => $generateUrlService->generateOfferUrl($offer))), 'changefreq' => 'weekly', 'priority' => '1.0'
            ];
        }


        // Once our array is filled, we define the controller response
        $response = new Response($this->renderView('AppBundle:Default:sitemap.xml.twig', [
            'urls' => $urls,
            'hostname' => $hostname
        ]));
        $response->headers->set('Content-Type', 'xml; charset=utf-8');

        return $response;
    }


}