<?php
/**
 * Created by PhpStorm.
 * User: Altea IT
 * Date: 29/05/2018
 * Time: 10:53
 */

namespace AppBundle\Service;

use AppBundle\Entity\User;

class RegisterUser
{

    private $mailer;
    private $locale;
    private $minLength;

    public function __construct($userManager, $tokenStorage, $session)
    {
        $this->userManager    = $userManager;
        $this->tokenStorage    = $tokenStorage;
        $this->session = $session;
    }

    /**
     * This method registers an user in the database manually.
     *
     * @return User
     **/
    public function register($email,$username,$password,$role){

        $email_exist = $this->userManager->findUserByEmail($email);

        if($email_exist){
            return false;
        }

        $user = $this->userManager->createUser();
        $user->setEnabled(true);
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setEmailCanonical($email);
        $user->setPlainPassword($password);
        $user->addRole($role);

        $this->userManager->updateUser($user);


        return $user;
    }

    /**
     * This method registers an user in the database manually.
     *
     * @return User
     **/
    public function edit($oldEmail, $email,$username,$password,$role){

        $user = $this->userManager->findUserByEmail($oldEmail);

        if(!isset($user)){
            return false;
        }

        $user->setUsername($username);
        $user->setEmail($email);
        $user->setEmailCanonical($email);

        if($password != ''){
            $user->setPlainPassword($password);
        }

        $roleArray = $user->getRoles();

        foreach ($roleArray as $oldRole){
            if($oldRole != 'ROLE_USER'){
                $user->removeRole($oldRole);
            }
        }

        $user->addRole($role);

        $this->userManager->updateUser($user);

        return $user;
    }


    /**
     * This method registers an user in the database manually.
     *
     * @return User
     **/
    public function getUser($email){

        $user = $this->userManager->findUserByEmail($email);

        return $user;
    }
}