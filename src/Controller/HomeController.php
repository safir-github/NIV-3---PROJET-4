<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    private ProductRepository $productRepository;

    // Injection de dépendance du ProductRepository pour pouvoir interroger la base de données
    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $featuredProducts = $this->productRepository->findBy(
            ['isFeatured' => true]
        );

        return $this->render('home/index.html.twig', [
            'featured_products' => $featuredProducts,
        ]);
    }

}
