<?php
/**
 * Created by PhpStorm.
 * User: Altea IT
 * Date: 11/03/2019
 * Time: 14:53
 */

namespace AppBundle\Controller;

use AppBundle\Entity\Building;
use AppBundle\Entity\Flat;
use AppBundle\Entity\Owner;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Trt\SwiftCssInlinerBundle\Plugin\CssInlinerPlugin;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Spipu\Html2Pdf\Html2Pdf;

class BuildingController extends Controller
{
    public function listBuildingAction(Request $request){

        $currentPage = $request->get('row');
        $sort = $request->get('sort');
        $currentPage = isset($currentPage)?$currentPage:1;
        $sort = isset($sort)?$sort:'DESC';

        $numberOfItem = 20;

        $user = $this->getUser();

        if(!(isset($user) and in_array('ROLE_ADMIN', $user->getRoles()))){
            return $this->redirectToRoute('mysyndic_home');
        }

        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Building')
        ;
        $owners = $repository->findAll();


        $countResult = count($owners);

        $finalArray = array_slice($owners, ($currentPage - 1 ) * $numberOfItem, $numberOfItem);

        $totalPage = ceil ($countResult / $numberOfItem);

        return $this->render('AppBundle:Building:listBuilding.html.twig', array(
            'buildings' => $finalArray,
            'page' => $currentPage,
            'total' => $totalPage,
            'sort' => $sort,
        ));
    }

    public function addBuildingAction(Request $request)
    {
        $session = $request->getSession();
        $user = $this->getUser();
        $name = $request->get('name');
        $zipcode = $request->get('zipcode');
        $location = $request->get('location');
        $city = $request->get('city');

        if(!(isset($user) and (in_array('ROLE_ADMIN', $user->getRoles()) or in_array('ROLE_SUPER_ADMIN', $user->getRoles())))){
            $translated = $this->get('translator')->trans('redirect.candidate');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('mysyndic_home');
        }

        $building = new Building();

        $building->setName($name);
        $building->setLocation($location);
        $building->setZipcode($zipcode);
        $building->setCity($city);

        $em = $this->getDoctrine()->getManager();
        $em->persist($building);
        $em->flush();


        return $this->redirectToRoute('list_building');
    }

    public function editBuildingAction(Request $request)
    {
        $session = $request->getSession();
        $user = $this->getUser();
        $id = $request->get('id');

        if(!(isset($user) and (in_array('ROLE_ADMIN', $user->getRoles()) or in_array('ROLE_SUPER_ADMIN', $user->getRoles())))){
            $translated = $this->get('translator')->trans('redirect.candidate');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('mysyndic_home');
        }

        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Building')
        ;
        $building = $repository->findOneBy(array('id' => $id));

        if ($request->isMethod('POST')){
            $name = $request->get('name');
            $zipcode = $request->get('zipcode');
            $location = $request->get('location');
            $city = $request->get('city');

            $building->setName($name);
            $building->setLocation($location);
            $building->setZipcode($zipcode);
            $building->setCity($city);

            $em = $this->getDoctrine()->getManager();
            $em->persist($building);
            $em->flush();

            return $this->redirectToRoute('list_building');
        }

        return $this->render('AppBundle:Building:editBuilding.html.twig', array(
            'building' => $building
        ));
    }

    public function deleteBuildingAction(Request $request)
    {
        $session = $request->getSession();
        $user = $this->getUser();
        $id = $request->get('id');

        if(!(isset($user) and (in_array('ROLE_ADMIN', $user->getRoles()) or in_array('ROLE_SUPER_ADMIN', $user->getRoles())))){
            $translated = $this->get('translator')->trans('redirect.candidate');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('mysyndic_home');
        }

        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Building')
        ;
        $building = $repository->findOneBy(array('id' => $id));

        $em = $this->getDoctrine()->getManager();
        $em->remove($building);
        $em->flush();

        return $this->redirectToRoute('list_building');
    }

    public function buildingPageAction(Request $request){
        $id = $request->get('id');

        $user = $this->getUser();

        if(!(isset($user) and in_array('ROLE_ADMIN', $user->getRoles()))){
            return $this->redirectToRoute('mysyndic_home');
        }

        $buildingRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Building')
        ;
        $building = $buildingRepository->findOneBy(array('id' => $id));

        $flatRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Flat')
        ;
        $flatArray = $flatRepository->findBy(array('building' => $building));

        return $this->render('AppBundle:Building:buildingPage.html.twig', array(
            'flatArray' => $flatArray,
            'building' => $building
        ));
    }

    public function addFlatAction(Request $request)
    {
        $session = $request->getSession();
        $user = $this->getUser();
        $name = $request->get('name');
        $surface = $request->get('surface');
        $floor = $request->get('floor');
        $idBuilding = $request->get('idBuilding');

        if(!(isset($user) and (in_array('ROLE_ADMIN', $user->getRoles()) or in_array('ROLE_SUPER_ADMIN', $user->getRoles())))){
            $translated = $this->get('translator')->trans('redirect.candidate');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('mysyndic_home');
        }

        $buildingRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Building')
        ;
        $building = $buildingRepository->findOneBy(array('id' => $idBuilding));

        $flat = new Flat();

        $flat->setBuilding($building);
        $flat->setName($name);
        $flat->setFloor($floor);
        $flat->setSurface($surface);

        $em = $this->getDoctrine()->getManager();
        $em->persist($flat);
        $em->flush();


        return $this->redirectToRoute('building_page', array('id' => $idBuilding));
    }

    public function editFlatAction(Request $request)
    {
        $session = $request->getSession();
        $user = $this->getUser();
        $id = $request->get('id');
        $idBuilding = $request->get('idBuilding');

        if(!(isset($user) and (in_array('ROLE_ADMIN', $user->getRoles()) or in_array('ROLE_SUPER_ADMIN', $user->getRoles())))){
            $translated = $this->get('translator')->trans('redirect.candidate');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('mysyndic_home');
        }

        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Flat')
        ;
        $flat = $repository->findOneBy(array('id' => $id));

        if ($request->isMethod('POST')){
            $name = $request->get('name');
            $surface = $request->get('surface');
            $floor = $request->get('floor');
            $flat->setName($name);
            $flat->setFloor($floor);
            $flat->setSurface($surface);
            $em = $this->getDoctrine()->getManager();
            $em->persist($flat);
            $em->flush();

            return $this->redirectToRoute('building_page', array('id' => $idBuilding));
        }

        return $this->render('AppBundle:Building:editFlat.html.twig', array(
            'flat' => $flat,
            'idBuilding' => $idBuilding
        ));
    }

    public function deleteFlatAction(Request $request)
    {
        $session = $request->getSession();
        $user = $this->getUser();
        $id = $request->get('id');
        $idBuilding = $request->get('idBuilding');

        if(!(isset($user) and (in_array('ROLE_ADMIN', $user->getRoles()) or in_array('ROLE_SUPER_ADMIN', $user->getRoles())))){
            $translated = $this->get('translator')->trans('redirect.candidate');
            $session->getFlashBag()->add('danger', $translated);
            return $this->redirectToRoute('mysyndic_home');
        }

        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Flat')
        ;
        $flat = $repository->findOneBy(array('id' => $id));

        $em = $this->getDoctrine()->getManager();
        $em->remove($flat);
        $em->flush();

        return $this->redirectToRoute('building_page', array('id' => $idBuilding));
    }
}