<?php
namespace App\Controller\App;

use App\Service\AppViewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app', name: 'app_view_')]
final class ViewController extends AbstractController
{

    public function __construct
    (
        private readonly AppViewService $service
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_view_feed');
    }

    #[Route('/feed', name: 'feed', methods: ['GET'])]
    public function feed(): Response
    {
        return $this->render('app/view/feed.html.twig', $this->service->feedView());
    }

    #[Route('/insights', name: 'insights', methods: ['GET'])]
    public function insights(): Response
    {
        return $this->render('app/view/insights.html.twig', $this->service->insights());
    }
}