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
        // Récupération des 3 sweat-shirts mis en avant (isFeatured = true)
        $featuredProducts = $this->productRepository->findBy(
            ['isFeatured' => true],
            null,
            3
        );

        return $this->render('home/index.html.twig', [
            'featured_products' => $featuredProducts,
        ]);
    }

    // --- PLACEHOLDERS POUR LES AUTRES ROUTES DU PROJET ---
    // Ces routes sont déclarées de manière minimale pour éviter que Twig ne plante avec une erreur 
    // "RouteNotFoundException" lorsque le menu de navigation ou le formulaire de connexion y font référence.
    // Elles seront remplacées au fur et à mesure de l'avancement du projet.

    #[Route('/cart', name: 'app_cart')]
    public function cartPlaceholder(): Response
    {
        return new Response('<html><body>Page Panier (En cours de développement)</body></html>');
    }
}
