<?php

namespace EmployerBundle\Controller;

use AppBundle\Entity\Employer;
use AppBundle\Form\EmployerType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;


class EmployerController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('', array('name' => $name));
    }

    public function createAction(Request $request)
    {

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
                $employer->setCredit(0);
                $employer->setWhyUs($data->getWhyUs());
                $employer->setLocation($data->getLocation());
                $employer->setLatLong($data->getLatlong());
                $employer->setPhone($data->getPhone());
                $employer->addUser($user);
                $employer->setLogo($data->getLogo());
                $employer->setCoverImage($data->getCoverImage());

                $em->persist($user);
                $em->flush();

                $translated = $this->get('translator')->trans('form.registration.successEmployer');
                $session->getFlashBag()->add('info', $translated);

                return $this->redirectToRoute('jobnow_home');

            }else{

                $translated = $this->get('translator')->trans('form.registration.error');
                $session->getFlashBag()->add('danger', $translated);

                return $this->redirectToRoute('jobnow_home');
            }
        }
        return $this->render('EmployerBundle:form:createEmployer.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    public function editAction(Request $request ){
        $user = $this->getUser();
        if(!isset($user) || !in_array('ROLE_EMPLOYER', $user->getRoles())){
            return $this->redirectToRoute('create_employer');
        }

        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Employer')
        ;
        $employer = $repository->findOneBy(array('id' => $user->getEmployer()));

        $session = $request->getSession();

        $employer->setFirstName($user->getFirstName());
        $employer->setLastName($user->getLastName());
        $employer->setEmail($user->getEmail());

        $form = $this->get('form.factory')->create(EmployerType::class, $employer);

        $form->remove('password');

        // Si la requête est en POST
        if ($request->isMethod('POST')) {

            $form->handleRequest($request);
            if ($form->isValid()) {

                $data = $form->getData();

                $userManager = $this->get('fos_user.user_manager');

                $user->setEmail($data->getEmail());
                $user->setEmailCanonical($data->getEmail());
                $user->setFirstName($data->getFirstName());
                $user->SetLastName($data->getLastName());
                $userManager->updateUser($user);

                $employer->setName($data->getEmail());
                $employer->setDescription($data->getDescription());
                $employer->setCredit(0);
                $employer->setWhyUs($data->getWhyUs());
                $employer->setLocation($data->getLocation());
                $employer->setLatLong($data->getLatlong());
                $employer->setPhone($data->getPhone());
                $employer->addUser($user);
                $employer->setLogo($data->getLogo());
                $employer->setCoverImage($data->getCoverImage());

                $em = $this->getDoctrine()->getManager();
                $em->merge($employer);
                $em->flush();

                $session->getFlashBag()->add('info', 'Candidat modifié !');

                return $this->redirectToRoute('jobnow_home');
            }
        }

        return $this->render('EmployerBundle:form:editEmployer.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    public function deleteAction(Request $request, $id)
    {

        $session = $request->getSession();

        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Employer');

        $employer = $repository->find($id);

        $em = $this->getDoctrine()->getManager();

        $em->remove($employer);
        $em->flush();

        $session->getFlashBag()->add('info', 'Employer supprimé !');

        return $this->redirectToRoute('jobnow_home');

    }

    public function showAction(Request $request, $id)
    {

        $session = $request->getSession();

        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Employer');

        $employer = $repository->find($id);

        $em = $this->getDoctrine()->getManager();

        $em->remove($employer);
        $em->flush();

        $session->getFlashBag()->add('info', 'Employer supprimé !');

        return $this->redirectToRoute('jobnow_home');

    }

    public function dashboardAction(){
        $user = $this->getUser();

        $OfferRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('AppBundle:Offer')
        ;
        $offers = $OfferRepository->findBy(array('employerId' => $user->getEmployer()));
        $creditInfo = $this->container->get('app.credit_info');

        return $this->render('EmployerBundle::dashboard.html.twig', array(
            'offers' => $offers,
            'publishedOffer' => $creditInfo->getPublishOffer()
        ));
    }

}