<?php
namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeValue extends Model
{
    protected $guarded = ['id'];

    // القيمة تعود لخاصية رئيسية
    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }

    // المتغيرات التي تحمل هذه القيمة
    public function variants()
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'variant_attribute_values',
            'attribute_value_id',   
            'product_variant_id'
        );
    }
}