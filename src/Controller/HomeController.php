<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class HomeController extends AbstractController
{
    /**
    * App index
    */
    public function index(): Response
    {
        //return $this->render('Home/index.html.twig');
        return $this->render('Front/home.html.twig');
    }
}
