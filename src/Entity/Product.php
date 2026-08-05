<?php

namespace App\Entity;

// Importation des classes nécessaires de Doctrine (ORM)
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité Product (Sweat-shirt)
 * 
 * Cette classe représente la table 'product' dans notre base de données.
 * Chaque sweat-shirt possède un nom, un prix, une image et un marqueur "isFeatured" 
 * pour savoir s'il doit être mis en avant sur la page d'accueil.
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    // Identifiant unique généré par MySQL (Clé Primaire)
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Nom du sweat-shirt (ex: Blackbelt, Pokeball)
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    // Prix du produit (ex: 29.90, 45.00)
    #[ORM\Column]
    private ?float $price = null;

    // Nom ou chemin du fichier image associé (ex: "blackbelt.jpg")
    #[ORM\Column(length: 255)]
    private ?string $image = null;

    // Indique si le produit est mis en avant sur la page d'accueil (2 astérisques dans le brief)
    #[ORM\Column]
    private ?bool $isFeatured = false;

    /**
     * Relation One-To-Many avec l'entité ProductStock.
     * Un produit peut avoir plusieurs entrées de stock (une par taille : XS, S, M, L, XL).
     * 
     * - mappedBy: spécifie la propriété dans ProductStock qui pointe vers ce produit.
     * - targetEntity: définit la classe cible (ProductStock).
     * - cascade: ["persist", "remove"] permet de sauvegarder ou supprimer automatiquement 
     *            les stocks liés lorsqu'on enregistre ou supprime le produit.
     * - orphanRemoval: si on supprime un stock de la collection, Doctrine le supprime de la base.
     */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductStock::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $stocks;

    public function __construct()
    {
        // Initialisation de la collection des stocks sous forme d'ArrayCollection
        $this->stocks = new ArrayCollection();
    }

    // --- GETTERS ET SETTERS ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function isFeatured(): ?bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;

        return $this;
    }

    /**
     * @return Collection<int, ProductStock>
     */
    public function getStocks(): Collection
    {
        return $this->stocks;
    }

    /**
     * Ajoute une ligne de stock pour ce produit.
     */
    public function addStock(ProductStock $stock): static
    {
        if (!$this->stocks->contains($stock)) {
            $this->stocks->add($stock);
            $stock->setProduct($this);
        }

        return $this;
    }

    /**
     * Retire une ligne de stock pour ce produit.
     */
    public function removeStock(ProductStock $stock): static
    {
        if ($this->stocks->removeElement($stock)) {
            // Configure le côté inverse de la relation à null pour desynchroniser
            if ($stock->getProduct() === $this) {
                $stock->setProduct(null);
            }
        }

        return $this;
    }
}
