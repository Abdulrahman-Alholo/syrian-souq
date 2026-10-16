<?
namespace App\Modules\Catalog\DTOs;

class ProductData
{
    public function __construct(
        public int $vendor_id,
        public int $category_id,
        public string $name_ar,
        public ?string $name_en,
        public string $slug,
        public ?string $description,
        public bool $has_variants,
        public array $variants
    ) {}
}