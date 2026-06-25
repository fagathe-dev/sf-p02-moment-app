<?php

namespace App\Controller;

use App\Service\AppViewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('', name: 'app_default_')]
final class DefaultController extends AbstractController
{

    public function __construct(private readonly AppViewService $service){}

    #[Route(path:'', name:'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('app/view/feed.html.twig', $this->service->feedView());
    }
}