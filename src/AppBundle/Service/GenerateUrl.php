<?php
/**
 * Created by PhpStorm.
 * User: Altea IT
 * Date: 03/07/2018
 * Time: 09:42
 */

namespace AppBundle\Service;

use Symfony\Component\Translation\TranslatorInterface;


class GenerateUrl
{

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator    = $translator;
    }

    /**
     * This method registers an user in the database manually.
     *
     * @return User
     **/
    public function generateOfferUrl($offer){
        $url = '';
        $tags = $offer->getTag();
        $unwanted_array = array(    'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
            'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', '\'' => '','(' => '', ')' => '');




        $offerOwner = strtr( $offer->getOwner()->getName(), $unwanted_array );

        $url .= str_replace([' ', '/'], '-', $offerOwner ) .'/';
        if(isset($tags) && count($tags)>0){
            foreach ($tags as $tag){
                $translated = $this->translator->trans($tag->getName());
                $translated = strtr( $translated, $unwanted_array );
                $translated = str_replace([' ', '/'], '-', $translated);
                $url .= strtolower($translated) . '-';
            }
            $url = rtrim($url,'-') . '/';
        }

        $offerTitle = strtr( $offer->getTitle(), $unwanted_array );
        $offerLocation = strtr($offer->getLocation(), $unwanted_array);


        $url .=  str_replace([' ', '/'], '-', $offerTitle ) . '/';
        $url .=  str_replace([' ', '/', ','], '-', $offerLocation);
        return strtolower($url);
    }
}