<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('', name: 'app_default_')]
final class DefaultController extends AbstractController
{
    #[Route(path:'', name:'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('default/index.html.twig');
    }
}