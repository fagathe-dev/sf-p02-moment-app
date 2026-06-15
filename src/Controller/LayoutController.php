<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/layout', name: 'app_layout')]
class LayoutController extends AbstractController
{
    #[Route('/{page}', name: '_page', requirements: ['page' => 'app|dashboard|auth|error|base'])]
    public function index(string $page): Response
    {
        return $this->render('layouts/' . $page . '.html.twig', ['title' => ucfirst($page) . ' layout']);
    }
}

