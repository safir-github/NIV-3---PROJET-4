<?php

namespace App\Entity;

// Importation des classes nécessaires de Doctrine (ORM)
use App\Repository\ProductStockRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité ProductStock (Stock par Taille)
 * 
 * Cette classe représente la table 'product_stock' en base de données.
 * Elle permet d'associer un stock précis à un sweat-shirt pour une taille donnée (XS, S, M, L, XL).
 */
#[ORM\Entity(repositoryClass: ProductStockRepository::class)]
class ProductStock
{
    // Identifiant unique généré par MySQL (Clé Primaire)
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Relation Many-To-One avec l'entité Product.
     * Plusieurs lignes de stock peuvent être associées à un seul et même sweat-shirt.
     * 
     * - inversedBy: spécifie la propriété de l'entité Product qui contient la collection des stocks.
     * - nullable: false (chaque ligne de stock doit impérativement être reliée à un produit).
     */
    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'stocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    // La taille du sweat-shirt (ex: XS, S, M, L, XL)
    #[ORM\Column(length: 10)]
    private ?string $size = null;

    // La quantité d'articles disponibles en stock pour cette taille
    #[ORM\Column]
    private ?int $quantity = null;

    // --- GETTERS ET SETTERS ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getSize(): ?string
    {
        return $this->size;
    }

    public function setSize(string $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }
}
